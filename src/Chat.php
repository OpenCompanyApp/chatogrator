<?php

namespace OpenCompany\Chatogrator;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Contracts\StateAdapter;
use OpenCompany\Chatogrator\Errors\ResourceNotFoundError;
use OpenCompany\Chatogrator\Jobs\ProcessChatEvent;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Threads\Channel;
use OpenCompany\Chatogrator\Threads\Thread;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** @phpstan-consistent-constructor */
class Chat
{
    /** @var array<string, Adapter> */
    protected array $adapters = [];

    protected ?StateAdapter $stateAdapter = null;

    protected LoggerInterface $logger;

    /** @var list<callable> */
    protected array $mentionHandlers = [];

    /** @var list<callable> */
    protected array $subscribedHandlers = [];

    /** @var list<array{pattern: string, handler: callable}> */
    protected array $patternHandlers = [];

    /** @var list<array{filter: string|list<string>|null, handler: callable}> */
    protected array $actionHandlers = [];

    /** @var list<array{filter: string|list<string>|null, handler: callable}> */
    protected array $reactionHandlers = [];

    /** @var list<array{filter: string|list<string>|null, handler: callable}> */
    protected array $slashCommandHandlers = [];

    /** @var list<array{filter: string|null, handler: callable}> */
    protected array $modalSubmitHandlers = [];

    /** @var list<array{filter: string|null, handler: callable}> */
    protected array $modalCloseHandlers = [];

    /** @var list<callable> */
    protected array $reactionAddedHandlers = [];

    /** @var list<callable> */
    protected array $reactionRemovedHandlers = [];

    /** @var list<callable> */
    protected array $messageEditedHandlers = [];

    /** @var list<callable> */
    protected array $messageDeletedHandlers = [];

    /** @var list<callable> */
    protected array $subscribeHandlers = [];

    /** @var list<callable> */
    protected array $unsubscribeHandlers = [];

    protected int $dedupeTtlSeconds = 300;

    protected bool $queued = false;

    protected ?string $queueName = null;

    protected function __construct(
        protected string $name,
    ) {
        $this->logger = new NullLogger;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function adapter(string $name, Adapter $adapter): static
    {
        $this->adapters[$name] = $adapter;

        return $this;
    }

    public function state(StateAdapter $stateAdapter): static
    {
        $this->stateAdapter = $stateAdapter;

        return $this;
    }

    public function logger(string|LoggerInterface $logger): static
    {
        if (is_string($logger)) {
            $this->logger = app('log')->channel($logger);
        } else {
            $this->logger = $logger;
        }

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function dedupeTtl(int $seconds): static
    {
        $this->dedupeTtlSeconds = $seconds;

        return $this;
    }

    public function queued(bool $queued = true, ?string $queue = null): static
    {
        $this->queued = $queued;
        $this->queueName = $queue;

        return $this;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    public function getAdapter(string $name): ?Adapter
    {
        return $this->adapters[$name] ?? null;
    }

    public function getStateAdapter(): ?StateAdapter
    {
        return $this->stateAdapter;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function handleWebhook(string $adapterName, Request $request): Response
    {
        $adapter = $this->getAdapter($adapterName);

        if ($adapter === null) {
            throw new ResourceNotFoundError("Adapter '{$adapterName}' not registered.");
        }

        return $adapter->handleWebhook($request, $this);
    }

    // ── Handler registration ────────────────────────────────────────

    public function onNewMention(callable $handler): static
    {
        $this->mentionHandlers[] = $handler;

        return $this;
    }

    public function onSubscribedMessage(callable $handler): static
    {
        $this->subscribedHandlers[] = $handler;

        return $this;
    }

    public function onNewMessage(string $pattern, callable $handler): static
    {
        $this->patternHandlers[] = ['pattern' => $pattern, 'handler' => $handler];

        return $this;
    }

    /** @param string|list<string>|callable $filterOrHandler */
    public function onAction(string|array|callable $filterOrHandler, ?callable $handler = null): static
    {
        if ($handler === null) {
            // Catch-all: onAction(function)
            $this->actionHandlers[] = ['filter' => null, 'handler' => $filterOrHandler];
        } else {
            // Filtered: onAction('id', function) or onAction(['id1', 'id2'], function)
            $this->actionHandlers[] = ['filter' => $filterOrHandler, 'handler' => $handler];
        }

        return $this;
    }

    /** @param string|list<string>|callable $filterOrHandler */
    public function onReaction(string|array|callable $filterOrHandler, ?callable $handler = null): static
    {
        if ($handler === null) {
            $this->reactionHandlers[] = ['filter' => null, 'handler' => $filterOrHandler];
        } else {
            $this->reactionHandlers[] = ['filter' => $filterOrHandler, 'handler' => $handler];
        }

        return $this;
    }

    /** @param string|list<string>|callable $filterOrHandler */
    public function onSlashCommand(string|array|callable $filterOrHandler, ?callable $handler = null): static
    {
        if ($handler === null) {
            $this->slashCommandHandlers[] = ['filter' => null, 'handler' => $filterOrHandler];
        } else {
            $this->slashCommandHandlers[] = ['filter' => $filterOrHandler, 'handler' => $handler];
        }

        return $this;
    }

    public function onModalSubmit(string|callable $filterOrHandler, ?callable $handler = null): static
    {
        if ($handler === null) {
            $this->modalSubmitHandlers[] = ['filter' => null, 'handler' => $filterOrHandler];
        } else {
            $this->modalSubmitHandlers[] = ['filter' => $filterOrHandler, 'handler' => $handler];
        }

        return $this;
    }

    public function onModalClose(string|callable $filterOrHandler, ?callable $handler = null): static
    {
        if ($handler === null) {
            $this->modalCloseHandlers[] = ['filter' => null, 'handler' => $filterOrHandler];
        } else {
            $this->modalCloseHandlers[] = ['filter' => $filterOrHandler, 'handler' => $handler];
        }

        return $this;
    }

    public function onReactionAdded(callable $handler): static
    {
        $this->reactionAddedHandlers[] = $handler;

        return $this;
    }

    public function onReactionRemoved(callable $handler): static
    {
        $this->reactionRemovedHandlers[] = $handler;

        return $this;
    }

    public function onMessageEdited(callable $handler): static
    {
        $this->messageEditedHandlers[] = $handler;

        return $this;
    }

    public function onMessageDeleted(callable $handler): static
    {
        $this->messageDeletedHandlers[] = $handler;

        return $this;
    }

    public function onSubscribe(callable $handler): static
    {
        $this->subscribeHandlers[] = $handler;

        return $this;
    }

    public function onUnsubscribe(callable $handler): static
    {
        $this->unsubscribeHandlers[] = $handler;

        return $this;
    }

    // ── Message processing ──────────────────────────────────────────

    public function handleIncomingMessage(Adapter $adapter, string $threadId, Message $message): void
    {
        // Skip messages from self
        if ($message->author->isMe) {
            return;
        }

        // Dedup
        $dedupeKey = "dedupe:{$adapter->name()}:{$message->id}";
        if ($this->stateAdapter?->get($dedupeKey) !== null) {
            return;
        }
        $this->stateAdapter?->set($dedupeKey, true, $this->dedupeTtlSeconds);

        // Acquire lock
        $lock = $this->stateAdapter?->acquireLock($threadId);

        try {
            $thread = new Thread(
                id: $threadId,
                adapter: $adapter,
                chat: $this,
            );
            $thread->currentMessage = $message;

            $isSubscribed = $this->stateAdapter?->isSubscribed($threadId) ?? false;
            $isMention = $message->isMention || $this->detectMention($adapter, $message);

            if ($isSubscribed) {
                // Subscribed thread → onSubscribedMessage handlers
                foreach ($this->subscribedHandlers as $handler) {
                    $handler($thread, $message);
                }
            } elseif ($isMention) {
                // Mention in unsubscribed thread → onNewMention handlers
                foreach ($this->mentionHandlers as $handler) {
                    $handler($thread, $message);
                }
            }

            // Pattern handlers always run (regardless of subscription status)
            foreach ($this->patternHandlers as $entry) {
                if (preg_match($entry['pattern'], $message->text)) {
                    ($entry['handler'])($thread, $message);
                }
            }
        } finally {
            if ($lock !== null) {
                $this->stateAdapter?->releaseLock($lock);
            }
        }
    }

    protected function detectMention(Adapter $adapter, Message $message): bool
    {
        $text = $message->text;
        if (empty($text)) {
            return false;
        }

        // Check if message text contains @botUserName
        $botUserName = $adapter->userName();
        if ($botUserName && str_contains($text, '@'.$botUserName)) {
            return true;
        }

        // Check if message text contains @botUserId
        $botUserId = $adapter->botUserId();
        if ($botUserId && str_contains($text, '@'.$botUserId)) {
            return true;
        }

        return false;
    }

    // ── Action processing ───────────────────────────────────────────

    /** @param array<string, mixed> $event */
    public function processAction(array $event): void
    {
        // Skip self actions
        $user = $event['user'] ?? null;
        if ($user && $user->isMe) {
            return;
        }

        $adapter = $event['adapter'];
        $threadId = $event['threadId'] ?? null;

        // Inject thread into event
        if ($threadId) {
            $event['thread'] = new Thread(
                id: $threadId,
                adapter: $adapter,
                chat: $this,
            );
        }

        $actionId = $event['actionId'] ?? null;
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->actionHandlers as $entry) {
            $filter = $entry['filter'];

            if ($filter === null) {
                ($entry['handler'])($eventObj);
            } elseif (is_string($filter) && $filter === $actionId) {
                ($entry['handler'])($eventObj);
            } elseif (is_array($filter) && in_array($actionId, $filter, true)) {
                ($entry['handler'])($eventObj);
            }
        }
    }

    // ── Reaction processing ─────────────────────────────────────────

    /** @param array<string, mixed> $event */
    public function processReaction(array $event): void
    {
        $user = $event['user'] ?? null;
        if ($user && $user->isMe) {
            return;
        }

        $adapter = $event['adapter'];
        $threadId = $event['threadId'] ?? null;

        if ($threadId) {
            $event['thread'] = new Thread(
                id: $threadId,
                adapter: $adapter,
                chat: $this,
            );
        }

        $emoji = $event['emoji'] ?? null;
        $rawEmoji = $event['rawEmoji'] ?? null;
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->reactionHandlers as $entry) {
            $filter = $entry['filter'];

            if ($filter === null) {
                ($entry['handler'])($eventObj);
            } elseif (is_array($filter)) {
                if (in_array($emoji, $filter, true) || in_array($rawEmoji, $filter, true)) {
                    ($entry['handler'])($eventObj);
                }
            } elseif ($filter === $emoji || $filter === $rawEmoji) {
                ($entry['handler'])($eventObj);
            }
        }

        // Type-specific reaction handlers
        $type = $event['type'] ?? null;
        if ($type === 'reaction_added') {
            foreach ($this->reactionAddedHandlers as $handler) {
                $handler($eventObj);
            }
        } elseif ($type === 'reaction_removed') {
            foreach ($this->reactionRemovedHandlers as $handler) {
                $handler($eventObj);
            }
        }
    }

    // ── Slash command processing ─────────────────────────────────────

    /** @param array<string, mixed> $event */
    public function processSlashCommand(array $event): void
    {
        $user = $event['user'] ?? null;
        if ($user && $user->isMe) {
            return;
        }

        $command = $event['command'] ?? '';
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->slashCommandHandlers as $entry) {
            $filter = $entry['filter'];

            if ($filter === null) {
                ($entry['handler'])($eventObj);
            } elseif (is_string($filter)) {
                $normalizedFilter = str_starts_with($filter, '/') ? $filter : '/'.$filter;
                if ($normalizedFilter === $command) {
                    ($entry['handler'])($eventObj);
                }
            } elseif (is_array($filter)) {
                $normalizedFilters = array_map(
                    fn ($f) => str_starts_with($f, '/') ? $f : '/'.$f,
                    $filter
                );
                if (in_array($command, $normalizedFilters, true)) {
                    ($entry['handler'])($eventObj);
                }
            }
        }
    }

    // ── Modal submit processing ─────────────────────────────────────

    /** @param array<string, mixed> $event */
    public function processModalSubmit(array $event): mixed
    {
        $callbackId = $event['callbackId'] ?? null;
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->modalSubmitHandlers as $entry) {
            $filter = $entry['filter'];

            if ($filter === null || $filter === $callbackId) {
                $result = ($entry['handler'])($eventObj);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    // ── Modal close processing ──────────────────────────────────────

    /** @param array<string, mixed> $event */
    public function processModalClose(array $event): void
    {
        $callbackId = $event['callbackId'] ?? null;
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->modalCloseHandlers as $entry) {
            $filter = $entry['filter'];

            if ($filter === null || $filter === $callbackId) {
                ($entry['handler'])($eventObj);
            }
        }
    }

    // ── Message edit/delete processing ───────────────────────────────

    /** @param array<string, mixed> $event */
    public function processMessageEdited(array $event): void
    {
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->messageEditedHandlers as $handler) {
            $handler($eventObj);
        }
    }

    /** @param array<string, mixed> $event */
    public function processMessageDeleted(array $event): void
    {
        $eventObj = new \ArrayObject($event, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this->messageDeletedHandlers as $handler) {
            $handler($eventObj);
        }
    }

    // ── Subscribe/Unsubscribe processing ────────────────────────────

    public function processSubscribeEvent(mixed $event): void
    {
        foreach ($this->subscribeHandlers as $handler) {
            $handler($event);
        }
    }

    public function processUnsubscribeEvent(mixed $event): void
    {
        foreach ($this->unsubscribeHandlers as $handler) {
            $handler($event);
        }
    }

    // ── Queue dispatch wrappers ────────────────────────────────────

    public function dispatchIncomingMessage(Adapter $adapter, string $threadId, Message $message): void
    {
        if ($this->queued) {
            $job = new ProcessChatEvent('message', $adapter->name(), [
                'threadId' => $threadId,
                'message' => $message->toJSON(),
            ]);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->handleIncomingMessage($adapter, $threadId, $message);
    }

    /** @param array<string, mixed> $event */
    public function dispatchAction(Adapter $adapter, array $event): void
    {
        if ($this->queued) {
            $payload = $event;
            unset($payload['adapter']);
            $job = new ProcessChatEvent('action', $adapter->name(), $payload);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->processAction($event);
    }

    /** @param array<string, mixed> $event */
    public function dispatchReaction(Adapter $adapter, array $event): void
    {
        if ($this->queued) {
            $payload = $event;
            unset($payload['adapter']);
            $job = new ProcessChatEvent('reaction', $adapter->name(), $payload);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->processReaction($event);
    }

    /** @param array<string, mixed> $event */
    public function dispatchSlashCommand(Adapter $adapter, array $event): void
    {
        if ($this->queued) {
            $payload = $event;
            unset($payload['adapter']);
            $job = new ProcessChatEvent('slash_command', $adapter->name(), $payload);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->processSlashCommand($event);
    }

    /** @param array<string, mixed> $event */
    public function dispatchMessageEdited(Adapter $adapter, array $event): void
    {
        if ($this->queued) {
            $payload = $event;
            unset($payload['adapter']);
            $job = new ProcessChatEvent('message_edited', $adapter->name(), $payload);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->processMessageEdited($event);
    }

    /** @param array<string, mixed> $event */
    public function dispatchMessageDeleted(Adapter $adapter, array $event): void
    {
        if ($this->queued) {
            $payload = $event;
            unset($payload['adapter']);
            $job = new ProcessChatEvent('message_deleted', $adapter->name(), $payload);
            if ($this->queueName) {
                $job->onQueue($this->queueName);
            }
            dispatch($job);

            return;
        }

        $this->processMessageDeleted($event);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function channel(string $adapterName, string $channelId): Channel
    {
        $adapter = $this->getAdapter($adapterName);

        return new Channel(
            id: $channelId,
            adapter: $adapter,
            chat: $this,
        );
    }

    public function openDM(string $adapterName, string $userId): ?Thread
    {
        $adapter = $this->getAdapter($adapterName);

        if ($adapter === null) {
            throw new ResourceNotFoundError("Adapter '{$adapterName}' not registered.");
        }

        $threadId = $adapter->openDM($userId);

        if ($threadId === null) {
            return null;
        }

        return new Thread(
            id: $threadId,
            adapter: $adapter,
            chat: $this,
            isDM: true,
        );
    }
}
