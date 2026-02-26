<?php

namespace OpenCompany\Chatogrator\Tests\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\FileUpload;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;

class MockAdapter implements Adapter
{
    /** @var array<int, array{threadId: string, message: PostableMessage|string}> */
    public array $postedMessages = [];

    /** @var array<int, array{threadId: string, messageId: string, message: PostableMessage|string}> */
    public array $editedMessages = [];

    /** @var array<int, array{threadId: string, messageId: string}> */
    public array $deletedMessages = [];

    /** @var array<int, array{threadId: string, messageId: string, emoji: string}> */
    public array $addedReactions = [];

    /** @var array<int, array{threadId: string, messageId: string, emoji: string}> */
    public array $removedReactions = [];

    /** @var array<int, string> */
    public array $typingStarted = [];

    /** @var array<int, array{threadId: string, userId: string, message: PostableMessage|string}> */
    public array $ephemeralMessages = [];

    /** @var array<int, string> */
    public array $dmOpened = [];

    /** @var array<int, array{triggerId: string, modal: Modal}> */
    public array $modalsOpened = [];

    /** @var array<int, array{threadId: string, stream: iterable}> */
    public array $streamedMessages = [];

    /** @var array<int, array{channelId: string, message: PostableMessage|string}> */
    public array $channelMessages = [];

    /** @var array<int, array{threadId: string, messageId: string}> */
    public array $pinnedMessages = [];

    /** @var array<int, array{threadId: string, messageId: string}> */
    public array $unpinnedMessages = [];

    /** @var array<int, array{threadId: string, file: FileUpload}> */
    public array $sentFiles = [];

    public ?Message $nextParsedMessage = null;

    public bool $initialized = false;

    public ?string $streamSupport = 'native';

    private int $messageCounter = 0;

    public function __construct(
        protected string $adapterName = 'slack',
    ) {}

    public function name(): string
    {
        return $this->adapterName;
    }

    public function userName(): string
    {
        return $this->adapterName . '-bot';
    }

    public function botUserId(): ?string
    {
        return 'BOT_' . strtoupper($this->adapterName);
    }

    public function initialize(Chat $chat): void
    {
        $this->initialized = true;
    }

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        return new Response('ok');
    }

    public function parseMessage(mixed $raw): Message
    {
        if ($this->nextParsedMessage) {
            return $this->nextParsedMessage;
        }

        return TestMessageFactory::make('parsed-1', 'parsed message');
    }

    public function postMessage(string $threadId, PostableMessage $message): SentMessage
    {
        $this->messageCounter++;
        $id = 'msg-' . $this->messageCounter;

        $this->postedMessages[] = ['threadId' => $threadId, 'message' => $message];

        return new SentMessage(
            id: $id,
            threadId: $threadId,
            text: $message->getText() ?? '',
            formatted: null,
            raw: [],
            author: new Author(
                userId: $this->botUserId() ?? 'BOT',
                userName: $this->userName(),
                fullName: ucfirst($this->adapterName) . ' Bot',
                isBot: true,
                isMe: true,
            ),
            metadata: ['dateSent' => now()->toISOString()],
            attachments: [],
            isMention: false,
        );
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        $this->editedMessages[] = ['threadId' => $threadId, 'messageId' => $messageId, 'message' => $message];

        return new SentMessage(
            id: $messageId,
            threadId: $threadId,
            text: $message->getText() ?? '',
            formatted: null,
            raw: [],
            author: new Author(
                userId: $this->botUserId() ?? 'BOT',
                userName: $this->userName(),
                fullName: ucfirst($this->adapterName) . ' Bot',
                isBot: true,
                isMe: true,
            ),
            metadata: [],
            attachments: [],
            isMention: false,
        );
    }

    public function deleteMessage(string $threadId, string $messageId): void
    {
        $this->deletedMessages[] = ['threadId' => $threadId, 'messageId' => $messageId];
    }

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        return new FetchResult();
    }

    public function fetchMessage(string $threadId, string $messageId): ?Message
    {
        return null;
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        return new ThreadInfo(id: $threadId, channelId: 'C123');
    }

    public function encodeThreadId(array $data): string
    {
        return $this->adapterName . ':' . ($data['channel'] ?? '') . ':' . ($data['thread'] ?? '');
    }

    public function decodeThreadId(string $threadId): array
    {
        $parts = explode(':', $threadId);

        return [
            'adapter' => $parts[0] ?? $this->adapterName,
            'channel' => $parts[1] ?? '',
            'thread' => $parts[2] ?? '',
        ];
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void
    {
        $this->addedReactions[] = ['threadId' => $threadId, 'messageId' => $messageId, 'emoji' => $emoji];
    }

    public function removeReaction(string $threadId, string $messageId, string $emoji): void
    {
        $this->removedReactions[] = ['threadId' => $threadId, 'messageId' => $messageId, 'emoji' => $emoji];
    }

    public function startTyping(string $threadId, ?string $status = null): void
    {
        $this->typingStarted[] = $threadId;
    }

    public function renderFormatted(string $markdown): string
    {
        return $markdown;
    }

    public function openDM(string $userId): ?string
    {
        $this->dmOpened[] = $userId;

        return $this->adapterName . ':D' . $userId . ':';
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        $this->ephemeralMessages[] = ['threadId' => $threadId, 'userId' => $userId, 'message' => $message];

        return null;
    }

    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        $this->modalsOpened[] = ['triggerId' => $triggerId, 'modal' => $modal];

        return ['viewId' => 'V123'];
    }

    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        if ($this->streamSupport === null) {
            return null;
        }

        $this->streamedMessages[] = ['threadId' => $threadId, 'stream' => $textStream];

        $accumulated = '';
        foreach ($textStream as $chunk) {
            $accumulated .= $chunk;
        }

        return new SentMessage(
            id: 'msg-stream',
            threadId: $threadId,
            text: $accumulated,
            formatted: null,
            raw: [],
            author: new Author(
                userId: $this->botUserId() ?? 'BOT',
                userName: $this->userName(),
                fullName: ucfirst($this->adapterName) . ' Bot',
                isBot: true,
                isMe: true,
            ),
            metadata: [],
            attachments: [],
            isMention: false,
        );
    }

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage
    {
        $this->channelMessages[] = ['channelId' => $channelId, 'message' => $message];

        return null;
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        return new FetchResult();
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        return new ChannelInfo(id: $channelId, name: '#' . $channelId);
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        return new ListThreadsResult();
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $parts = explode(':', $threadId);

        return isset($parts[0], $parts[1]) ? $parts[0] . ':' . $parts[1] : null;
    }

    public function isDM(string $threadId): bool
    {
        return str_contains($threadId, ':D');
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }

    public function sendFile(string $threadId, FileUpload $file): ?SentMessage
    {
        $this->sentFiles[] = ['threadId' => $threadId, 'file' => $file];

        return null;
    }

    public function pinMessage(string $threadId, string $messageId): void
    {
        $this->pinnedMessages[] = ['threadId' => $threadId, 'messageId' => $messageId];
    }

    public function unpinMessage(string $threadId, string $messageId): void
    {
        $this->unpinnedMessages[] = ['threadId' => $threadId, 'messageId' => $messageId];
    }

    public function reset(): void
    {
        $this->postedMessages = [];
        $this->editedMessages = [];
        $this->deletedMessages = [];
        $this->addedReactions = [];
        $this->removedReactions = [];
        $this->typingStarted = [];
        $this->ephemeralMessages = [];
        $this->dmOpened = [];
        $this->modalsOpened = [];
        $this->streamedMessages = [];
        $this->channelMessages = [];
        $this->pinnedMessages = [];
        $this->unpinnedMessages = [];
        $this->sentFiles = [];
        $this->nextParsedMessage = null;
        $this->initialized = false;
        $this->messageCounter = 0;
    }
}
