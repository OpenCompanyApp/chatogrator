<?php

namespace OpenCompany\Chatogrator\Threads;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;

/** @phpstan-consistent-constructor */
class Channel
{
    /** @var array<string, mixed> */
    protected array $state = [];

    public function __construct(
        public readonly string $id,
        public readonly Adapter $adapter,
        protected Chat $chat,
        public readonly bool $isDM = false,
    ) {}

    public function post(string|PostableMessage $content): ?SentMessage
    {
        $message = is_string($content) ? PostableMessage::text($content) : $content;

        return $this->adapter->postChannelMessage($this->id, $message);
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        if (empty($this->state)) {
            $this->state = $this->chat->getStateAdapter()?->get("channel-state:{$this->id}") ?? [];
        }

        return $this->state;
    }

    /** @param array<string, mixed> $data */
    public function setState(array $data, bool $replace = false): void
    {
        $this->state = $replace ? $data : array_merge($this->state(), $data);
        $this->chat->getStateAdapter()?->set("channel-state:{$this->id}", $this->state, 30 * 86400);
    }

    public function threads(?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        return $this->adapter->listThreads($this->id, $options);
    }

    public function messages(?FetchOptions $options = null): ?FetchResult
    {
        return $this->adapter->fetchChannelMessages($this->id, $options);
    }

    public function fetchMetadata(): ?ChannelInfo
    {
        return $this->adapter->fetchChannelInfo($this->id);
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

    public function startTyping(): void
    {
        $this->adapter->startTyping($this->id);
    }

    /** @return array<string, mixed> */
    public function toJSON(): array
    {
        return [
            'id' => $this->id,
            'adapterName' => $this->adapter->name(),
            'isDM' => $this->isDM,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromJSON(array $data, Chat $chat): static
    {
        $adapter = $chat->getAdapter($data['adapterName']);

        return new static(
            id: $data['id'],
            adapter: $adapter,
            chat: $chat,
            isDM: $data['isDM'] ?? false,
        );
    }
}
