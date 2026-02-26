<?php

namespace OpenCompany\Chatogrator\Adapters\Linear;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\FileUpload;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;

/** @phpstan-consistent-constructor */
class LinearAdapter implements Adapter
{
    protected string $adapterName = 'linear';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?LinearFormatConverter $formatConverter = null;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config = []): static
    {
        $instance = new static;
        $instance->config = array_merge(static::envDefaults(), $config);

        return $instance;
    }

    /** @return array<string, mixed> */
    protected static function envDefaults(): array
    {
        return array_filter([
            'bot_name' => config('services.linear.bot_name', env('LINEAR_BOT_NAME')),
            'bot_user_id' => config('services.linear.bot_user_id', env('LINEAR_BOT_USER_ID')),
            'webhook_secret' => config('services.linear.webhook_secret', env('LINEAR_WEBHOOK_SECRET')),
        ], fn ($v) => $v !== null);
    }

    public function name(): string
    {
        return $this->adapterName;
    }

    public function userName(): string
    {
        return $this->config['bot_name'] ?? $this->adapterName.'-bot';
    }

    public function botUserId(): ?string
    {
        return $this->config['bot_user_id'] ?? null;
    }

    public function initialize(Chat $chat): void
    {
        //
    }

    protected function getFormatConverter(): LinearFormatConverter
    {
        return $this->formatConverter ??= new LinearFormatConverter;
    }

    // ── Thread ID Encoding ──────────────────────────────────────────

    public function encodeThreadId(array $data): string
    {
        $issueId = $data['issueId'] ?? '';

        $threadId = "linear:{$issueId}";

        if (isset($data['commentId'])) {
            $threadId .= ':c:'.$data['commentId'];
        }

        return $threadId;
    }

    public function decodeThreadId(string $threadId): array
    {
        if (! str_starts_with($threadId, 'linear:')) {
            throw new ValidationError("Invalid Linear thread ID: '{$threadId}'");
        }

        $withoutPrefix = substr($threadId, strlen('linear:'));

        if ($withoutPrefix === '') {
            throw new ValidationError("Invalid Linear thread ID format: '{$threadId}'");
        }

        // Check for comment: issueId:c:commentId
        if (str_contains($withoutPrefix, ':c:')) {
            $parts = explode(':c:', $withoutPrefix, 2);
            $issueId = $parts[0];
            $commentId = $parts[1];

            if ($issueId === '') {
                throw new ValidationError("Invalid Linear thread ID format: '{$threadId}'");
            }

            return [
                'issueId' => $issueId,
                'commentId' => $commentId,
            ];
        }

        return [
            'issueId' => $withoutPrefix,
        ];
    }

    // ── Webhook Handling ────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->headers->get('Linear-Signature', '');

        if (! $signature) {
            return new Response('Unauthorized', 401);
        }

        $secret = $this->config['webhook_secret'] ?? '';
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return new Response('Unauthorized', 401);
        }

        $data = json_decode($rawBody, true);
        if ($data === null) {
            return new Response('Invalid JSON', 400);
        }

        return $this->handleLinearEvent($data, $chat);
    }

    /** @param array<string, mixed> $data */
    protected function handleLinearEvent(array $data, Chat $chat): Response
    {
        $type = $data['type'] ?? '';
        $action = $data['action'] ?? '';

        if ($type === 'Comment' && in_array($action, ['create', 'update'])) {
            $this->handleCommentEvent($data, $chat);
        }

        return new Response('', 200);
    }

    /** @param array<string, mixed> $data */
    protected function handleCommentEvent(array $data, Chat $chat): void
    {
        $message = $this->parseMessage($data);

        $comment = $data['comment'] ?? $data['data'] ?? [];
        $issueId = $comment['issueId'] ?? $data['issueId'] ?? '';

        $threadId = $this->encodeThreadId([
            'issueId' => $issueId,
        ]);

        $chat->dispatchIncomingMessage($this, $threadId, $message);
    }

    // ── Message Parsing ─────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $data = is_array($raw) ? $raw : [];
        $comment = $data['comment'] ?? $data['data'] ?? [];

        $commentId = $comment['id'] ?? '';
        $body = $comment['body'] ?? '';
        $issueId = $comment['issueId'] ?? $data['issueId'] ?? '';
        $userId = $comment['userId'] ?? 'unknown';

        $createdAt = $comment['createdAt'] ?? '';
        $updatedAt = $comment['updatedAt'] ?? '';

        $metadata = [];
        if ($createdAt !== '') {
            $metadata['dateSent'] = $createdAt;
        }

        $edited = $createdAt !== '' && $updatedAt !== '' && $createdAt !== $updatedAt;
        if ($edited) {
            $metadata['edited'] = true;
            $metadata['editedAt'] = $updatedAt;
        }

        $threadId = $this->encodeThreadId([
            'issueId' => $issueId,
        ]);

        return new Message(
            id: $commentId,
            threadId: $threadId,
            text: $body,
            formatted: $body,
            raw: $data,
            author: new Author(
                userId: $userId,
                userName: '',
                fullName: '',
                isBot: false,
                isMe: false,
            ),
            metadata: $metadata,
            attachments: [],
            isMention: false,
        );
    }

    // ── Format Rendering ────────────────────────────────────────────

    public function renderFormatted(string $markdown): string
    {
        return $this->getFormatConverter()->fromMarkdown($markdown);
    }

    // ── API Methods (not yet implemented - require HTTP client) ──────

    public function postMessage(string $threadId, PostableMessage $message): SentMessage
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function deleteMessage(string $threadId, string $messageId): void
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function fetchMessage(string $threadId, string $messageId): ?Message
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function removeReaction(string $threadId, string $messageId, string $emoji): void
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function startTyping(string $threadId, ?string $status = null): void
    {
        // Linear doesn't support typing indicators
    }

    public function openDM(string $userId): ?string
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        return null;
    }

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['issueId'];
    }

    public function isDM(string $threadId): bool
    {
        return false;
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }

    public function sendFile(string $threadId, FileUpload $file): ?SentMessage
    {
        return null;
    }

    public function pinMessage(string $threadId, string $messageId): void
    {
        //
    }

    public function unpinMessage(string $threadId, string $messageId): void
    {
        //
    }
}
