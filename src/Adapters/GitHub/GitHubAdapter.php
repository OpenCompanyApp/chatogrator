<?php

namespace OpenCompany\Chatogrator\Adapters\GitHub;

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
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;

/** @phpstan-consistent-constructor */
class GitHubAdapter implements Adapter
{
    protected string $adapterName = 'github';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?GitHubFormatConverter $formatConverter = null;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        $instance = new static;
        $instance->config = $config;

        return $instance;
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

    protected function getFormatConverter(): GitHubFormatConverter
    {
        return $this->formatConverter ??= new GitHubFormatConverter;
    }

    // ── Thread ID Encoding ──────────────────────────────────────────

    public function encodeThreadId(array $data): string
    {
        $owner = $data['owner'] ?? '';
        $repo = $data['repo'] ?? '';
        $prNumber = $data['prNumber'] ?? $data['issueNumber'] ?? 0;

        $threadId = "github:{$owner}/{$repo}:{$prNumber}";

        if (isset($data['reviewCommentId'])) {
            $threadId .= ':rc:'.$data['reviewCommentId'];
        }

        return $threadId;
    }

    public function decodeThreadId(string $threadId): array
    {
        if (! str_starts_with($threadId, 'github:')) {
            throw new ValidationError("Invalid GitHub thread ID: '{$threadId}'");
        }

        $withoutPrefix = substr($threadId, strlen('github:'));

        // Check for review comment: owner/repo:number:rc:commentId
        if (str_contains($withoutPrefix, ':rc:')) {
            $rcParts = explode(':rc:', $withoutPrefix, 2);
            $mainPart = $rcParts[0];
            $reviewCommentId = (int) $rcParts[1];

            $result = $this->parseMainPart($mainPart, $threadId);
            $result['reviewCommentId'] = $reviewCommentId;

            return $result;
        }

        // Standard: owner/repo:number
        return $this->parseMainPart($withoutPrefix, $threadId);
    }

    /** @return array<string, mixed> */
    protected function parseMainPart(string $mainPart, string $originalThreadId): array
    {
        // Expected format: owner/repo:number
        $colonPos = strrpos($mainPart, ':');

        if ($colonPos === false || ! str_contains(substr($mainPart, 0, $colonPos), '/')) {
            throw new ValidationError("Invalid GitHub thread ID format: '{$originalThreadId}'");
        }

        $ownerRepo = substr($mainPart, 0, $colonPos);
        $number = (int) substr($mainPart, $colonPos + 1);

        $slashPos = strpos($ownerRepo, '/');
        $owner = substr($ownerRepo, 0, $slashPos);
        $repo = substr($ownerRepo, $slashPos + 1);

        return [
            'owner' => $owner,
            'repo' => $repo,
            'prNumber' => $number,
        ];
    }

    // ── Webhook Handling ────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->headers->get('X-Hub-Signature-256', '');

        if (! $signature) {
            return new Response('Unauthorized', 401);
        }

        $secret = $this->config['webhook_secret'] ?? '';
        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return new Response('Unauthorized', 401);
        }

        $data = json_decode($rawBody, true);
        if ($data === null) {
            return new Response('Invalid JSON', 400);
        }

        $event = $request->headers->get('X-GitHub-Event', '');

        return $this->handleGitHubEvent($event, $data, $chat);
    }

    /** @param array<string, mixed> $data */
    protected function handleGitHubEvent(string $eventType, array $data, Chat $chat): Response
    {
        $action = $data['action'] ?? '';

        // Handle issue_comment and pull_request_review_comment events
        if (in_array($eventType, ['issue_comment', 'pull_request_review_comment'])) {
            if ($action === 'created' || $action === 'edited') {
                $this->handleCommentEvent($data, $chat);
            }
        }

        return new Response('', 200);
    }

    /** @param array<string, mixed> $data */
    protected function handleCommentEvent(array $data, Chat $chat): void
    {
        $message = $this->parseMessage($data);

        $repo = $data['repository'] ?? [];
        $owner = $repo['owner']['login'] ?? '';
        $repoName = $repo['name'] ?? '';
        $number = $data['pull_request']['number']
            ?? $data['issue']['number']
            ?? 0;

        $threadId = $this->encodeThreadId([
            'owner' => $owner,
            'repo' => $repoName,
            'prNumber' => $number,
        ]);

        $chat->dispatchIncomingMessage($this, $threadId, $message);
    }

    // ── Message Parsing ─────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $data = is_array($raw) ? $raw : [];
        $comment = $data['comment'] ?? [];

        $commentId = (string) ($comment['id'] ?? '');
        $body = $comment['body'] ?? '';
        $user = $comment['user'] ?? [];
        $login = $user['login'] ?? 'unknown';
        $userId = (string) ($user['id'] ?? 'unknown');

        $createdAt = $comment['created_at'] ?? '';
        $updatedAt = $comment['updated_at'] ?? '';

        $metadata = [];
        if ($createdAt !== '') {
            $metadata['dateSent'] = $createdAt;
        }

        $edited = $createdAt !== '' && $updatedAt !== '' && $createdAt !== $updatedAt;
        if ($edited) {
            $metadata['edited'] = true;
            $metadata['editedAt'] = $updatedAt;
        }

        $repo = $data['repository'] ?? [];
        $owner = $repo['owner']['login'] ?? '';
        $repoName = $repo['name'] ?? '';
        $number = $data['pull_request']['number']
            ?? $data['issue']['number']
            ?? 0;

        $threadId = $this->encodeThreadId([
            'owner' => $owner,
            'repo' => $repoName,
            'prNumber' => $number,
        ]);

        return new Message(
            id: $commentId,
            threadId: $threadId,
            text: $body,
            formatted: $body,
            raw: $data,
            author: new Author(
                userId: $userId,
                userName: $login,
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

    public function startTyping(string $threadId): void
    {
        // GitHub doesn't support typing indicators
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

        return $decoded['owner'].'/'.$decoded['repo'];
    }

    public function isDM(string $threadId): bool
    {
        return false;
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }
}
