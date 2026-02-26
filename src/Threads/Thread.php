<?php

namespace OpenCompany\Chatogrator\Threads;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;

/** @phpstan-consistent-constructor */
class Thread
{
    /** @var array<string, mixed> */
    protected array $state = [];

    public ?Message $currentMessage = null;

    public function __construct(
        public readonly string $id,
        public readonly Adapter $adapter,
        protected Chat $chat,
        public readonly ?string $channelId = null,
        public readonly bool $isDM = false,
    ) {}

    /** @param string|PostableMessage|iterable<string> $content */
    public function post(string|PostableMessage|iterable $content): SentMessage
    {
        if (is_string($content)) {
            return $this->adapter->postMessage($this->id, PostableMessage::text($content));
        }

        if ($content instanceof PostableMessage && ! $content->isStreaming()) {
            return $this->adapter->postMessage($this->id, $content);
        }

        // Streaming: iterable or PostableMessage with stream
        $stream = $content instanceof PostableMessage ? $content->getStream() : $content;

        // Try native streaming first
        $result = $this->adapter->stream($this->id, $stream);
        if ($result !== null) {
            return $result;
        }

        // Fallback: post placeholder then progressively edit with accumulated content
        $placeholder = $this->adapter->postMessage($this->id, PostableMessage::text('...'));
        $accumulated = '';
        $lastEdited = '';
        $lastUpdate = microtime(true) * 1000;
        $intervalMs = 500;

        foreach ($stream as $chunk) {
            $accumulated .= $chunk;
            $now = microtime(true) * 1000;

            if (($now - $lastUpdate) >= $intervalMs && $accumulated !== $lastEdited) {
                $this->adapter->editMessage($this->id, $placeholder->id, PostableMessage::text($accumulated));
                $lastEdited = $accumulated;
                $lastUpdate = $now;
            }
        }

        // Final edit if content changed since last edit
        if ($accumulated !== $lastEdited) {
            $this->adapter->editMessage($this->id, $placeholder->id, PostableMessage::text($accumulated));
        }

        return $placeholder;
    }

    public function subscribe(): void
    {
        $this->chat->getStateAdapter()?->subscribe($this->id);
        $this->adapter->onThreadSubscribe($this->id);
    }

    public function unsubscribe(): void
    {
        $this->chat->getStateAdapter()?->unsubscribe($this->id);
    }

    public function isSubscribed(): bool
    {
        return $this->chat->getStateAdapter()?->isSubscribed($this->id) ?? false;
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        if (empty($this->state)) {
            $this->state = $this->chat->getStateAdapter()?->get("thread-state:{$this->id}") ?? [];
        }

        return $this->state;
    }

    /** @param array<string, mixed> $data */
    public function setState(array $data, bool $replace = false): void
    {
        $this->state = $replace ? $data : array_merge($this->state(), $data);
        $this->chat->getStateAdapter()?->set("thread-state:{$this->id}", $this->state, 30 * 86400);
    }

    public function messages(?FetchOptions $options = null): FetchResult
    {
        return $this->adapter->fetchMessages($this->id, $options);
    }

    /**
     * Fetch all messages by paginating through all pages.
     *
     * @return array<int, \OpenCompany\Chatogrator\Messages\Message>
     */
    public function allMessages(): array
    {
        $all = [];
        $cursor = null;

        do {
            $options = $cursor !== null ? new FetchOptions(cursor: $cursor) : null;
            $result = $this->adapter->fetchMessages($this->id, $options);
            $all = array_merge($all, $result->messages);
            $cursor = $result->nextCursor;
        } while ($cursor !== null);

        return $all;
    }

    /**
     * Fetch the most recent messages.
     *
     * @return array<int, \OpenCompany\Chatogrator\Messages\Message>
     */
    public function recentMessages(int $limit = 10): array
    {
        return $this->adapter->fetchMessages($this->id, new FetchOptions(limit: $limit))->messages;
    }

    /**
     * Clear cached thread state so it reloads on next access.
     */
    public function refresh(): static
    {
        $this->state = [];

        return $this;
    }

    public function startTyping(?string $status = null): void
    {
        $this->adapter->startTyping($this->id, $status);
    }

    public function mentionUser(string $userId): string
    {
        return $this->adapter->renderFormatted("@{$userId}");
    }

    public function postEphemeral(string $userId, string|PostableMessage $content, bool $fallbackToDM = false): void
    {
        $message = is_string($content) ? PostableMessage::text($content) : $content;
        $result = $this->adapter->postEphemeral($this->id, $userId, $message);

        if ($result === null && $fallbackToDM) {
            $dmThreadId = $this->adapter->openDM($userId);
            if ($dmThreadId) {
                $this->adapter->postMessage($dmThreadId, $message);
            }
        }
    }

    /** @return array<string, mixed> */
    public function toJSON(): array
    {
        $data = [
            'id' => $this->id,
            'adapterName' => $this->adapter->name(),
            'channelId' => $this->channelId,
            'isDM' => $this->isDM,
        ];

        if ($this->currentMessage !== null) {
            $data['currentMessage'] = $this->currentMessage->toJSON();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public static function fromJSON(array $data, Chat $chat): static
    {
        $adapter = $chat->getAdapter($data['adapterName']);

        $thread = new static(
            id: $data['id'],
            adapter: $adapter,
            chat: $chat,
            channelId: $data['channelId'] ?? null,
            isDM: $data['isDM'] ?? false,
        );

        if (isset($data['currentMessage'])) {
            $thread->currentMessage = Message::fromJSON($data['currentMessage']);
        }

        return $thread;
    }
}
