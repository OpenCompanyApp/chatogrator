<?php

namespace OpenCompany\Chatogrator\Adapters\Slack;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Errors\ValidationError;
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
use OpenCompany\Chatogrator\Types\ThreadSummary;

/** @phpstan-consistent-constructor */
class SlackAdapter implements Adapter
{
    protected string $adapterName = 'slack';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?SlackFormatConverter $formatConverter = null;

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
            'bot_token' => config('services.slack.bot_token', env('SLACK_BOT_TOKEN')),
            'signing_secret' => config('services.slack.signing_secret', env('SLACK_SIGNING_SECRET')),
            'bot_user_id' => config('services.slack.bot_user_id', env('SLACK_BOT_USER_ID')),
            'user_name' => config('services.slack.user_name', env('SLACK_BOT_NAME')),
        ], fn ($v) => $v !== null);
    }

    public function name(): string
    {
        return $this->adapterName;
    }

    public function userName(): string
    {
        return $this->config['user_name'] ?? 'bot';
    }

    public function botUserId(): ?string
    {
        return $this->config['bot_user_id'] ?? null;
    }

    public function initialize(Chat $chat): void
    {
        //
    }

    protected function getFormatConverter(): SlackFormatConverter
    {
        return $this->formatConverter ??= new SlackFormatConverter;
    }

    // ── Webhook Handling ─────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        $rawBody = $request->getContent();
        $timestamp = $request->headers->get('X-Slack-Request-Timestamp', '');
        $signature = $request->headers->get('X-Slack-Signature', '');

        if (! $timestamp || ! $signature) {
            return new Response('Unauthorized', 401);
        }

        if (! SlackCrypto::verifySignature(
            $rawBody,
            $signature,
            $timestamp,
            $this->config['signing_secret']
        )) {
            return new Response('Unauthorized', 401);
        }

        $contentType = $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $this->handleFormWebhook($rawBody, $chat);
        }

        $data = json_decode($rawBody, true);

        if ($data === null) {
            return new Response('Invalid JSON', 400);
        }

        return $this->handleJsonWebhook($data, $chat);
    }

    /** @param array<string, mixed> $data */
    protected function handleJsonWebhook(array $data, Chat $chat): Response
    {
        $type = $data['type'] ?? '';

        if ($type === 'url_verification') {
            return new Response(
                json_encode(['challenge' => $data['challenge'] ?? '']),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        if ($type === 'event_callback') {
            $event = $data['event'] ?? [];
            $this->handleEvent($event, $chat);
        }

        return new Response('', 200);
    }

    protected function handleFormWebhook(string $rawBody, Chat $chat): Response
    {
        parse_str($rawBody, $formData);

        if (isset($formData['payload'])) {
            $payload = json_decode($formData['payload'], true);

            if ($payload === null) {
                return new Response('Invalid payload', 400);
            }

            return $this->handleInteractivePayload($payload, $chat);
        }

        if (isset($formData['command'])) {
            return $this->handleSlashCommand($formData, $chat);
        }

        return new Response('Bad request', 400);
    }

    /** @param array<string, mixed> $event */
    protected function handleEvent(array $event, Chat $chat): void
    {
        $eventType = $event['type'] ?? '';

        switch ($eventType) {
            case 'message':
            case 'app_mention':
                $this->handleMessageEvent($event, $chat, $eventType === 'app_mention');
                break;
            case 'reaction_added':
            case 'reaction_removed':
                $this->handleReactionEvent($event, $chat, $eventType);
                break;
        }
    }

    /** @param array<string, mixed> $event */
    protected function handleMessageEvent(array $event, Chat $chat, bool $isAppMention = false): void
    {
        // Handle message subtypes
        $subtype = $event['subtype'] ?? '';

        if ($subtype === 'message_changed') {
            $this->handleMessageChanged($event, $chat);

            return;
        }

        if ($subtype === 'message_deleted') {
            $this->handleMessageDeleted($event, $chat);

            return;
        }

        $message = $this->parseMessage($event);

        $channel = $event['channel'] ?? '';
        $threadTs = $event['thread_ts'] ?? '';

        $threadId = $this->encodeThreadId([
            'channel' => $channel,
            'threadTs' => $threadTs,
        ]);

        // Detect DMs: channel_type 'im' or channel starting with 'D'
        $isDM = ($event['channel_type'] ?? '') === 'im' || str_starts_with($channel, 'D');
        $isMention = $isAppMention || $isDM;

        if ($isMention && ! $message->isMention) {
            $message = new Message(
                id: $message->id,
                threadId: $threadId,
                text: $message->text,
                formatted: $message->formatted,
                raw: $message->raw,
                author: $message->author,
                metadata: $message->metadata,
                attachments: $message->attachments,
                isMention: true,
            );
        }

        $chat->dispatchIncomingMessage($this, $threadId, $message);
    }

    /** @param array<string, mixed> $event */
    protected function handleMessageChanged(array $event, Chat $chat): void
    {
        $channel = $event['channel'] ?? '';
        $innerMessage = $event['message'] ?? [];
        $threadTs = $innerMessage['thread_ts'] ?? $innerMessage['ts'] ?? '';

        $threadId = $this->encodeThreadId([
            'channel' => $channel,
            'threadTs' => $threadTs,
        ]);

        $chat->dispatchMessageEdited($this, [
            'adapter' => $this,
            'threadId' => $threadId,
            'message' => $innerMessage,
            'previousMessage' => $event['previous_message'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $event */
    protected function handleMessageDeleted(array $event, Chat $chat): void
    {
        $channel = $event['channel'] ?? '';
        $deletedTs = $event['deleted_ts'] ?? '';

        $threadId = $this->encodeThreadId([
            'channel' => $channel,
            'threadTs' => $deletedTs,
        ]);

        $chat->dispatchMessageDeleted($this, [
            'adapter' => $this,
            'threadId' => $threadId,
            'deletedTs' => $deletedTs,
            'previousMessage' => $event['previous_message'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $event */
    protected function handleReactionEvent(array $event, Chat $chat, string $eventType): void
    {
        $userId = $event['user'] ?? 'unknown';
        $emoji = $event['reaction'] ?? '';
        $item = $event['item'] ?? [];
        $channel = $item['channel'] ?? '';
        $messageTs = $item['ts'] ?? '';

        $threadId = $this->encodeThreadId([
            'channel' => $channel,
            'threadTs' => $messageTs,
        ]);

        $chat->dispatchReaction($this, [
            'adapter' => $this,
            'type' => $eventType,
            'threadId' => $threadId,
            'messageId' => $messageTs,
            'emoji' => $emoji,
            'rawEmoji' => $emoji,
            'user' => new Author(
                userId: $userId,
                userName: '',
                fullName: '',
                isBot: false,
                isMe: $userId === $this->botUserId(),
            ),
        ]);
    }

    /** @param array<string, mixed> $payload */
    protected function handleInteractivePayload(array $payload, Chat $chat): Response
    {
        $type = $payload['type'] ?? '';

        switch ($type) {
            case 'block_actions':
                $this->handleBlockActions($payload, $chat);
                break;
            case 'view_submission':
                $result = $this->handleViewSubmission($payload, $chat);
                if ($result !== null) {
                    return new Response(
                        json_encode($result),
                        200,
                        ['Content-Type' => 'application/json']
                    );
                }
                break;
            case 'view_closed':
                $this->handleViewClosed($payload, $chat);
                break;
        }

        return new Response('', 200);
    }

    /** @param array<string, mixed> $payload */
    protected function handleBlockActions(array $payload, Chat $chat): void
    {
        $user = $this->parseAuthorFromPayload($payload);
        $channel = $payload['channel']['id'] ?? $payload['container']['channel_id'] ?? null;
        $messageTs = $payload['message']['ts'] ?? $payload['container']['message_ts'] ?? null;
        $threadTs = $payload['message']['thread_ts'] ?? null;
        $triggerId = $payload['trigger_id'] ?? null;

        $threadId = null;
        if ($channel) {
            $threadId = $this->encodeThreadId([
                'channel' => $channel,
                'threadTs' => $threadTs ?? $messageTs ?? '',
            ]);
        }

        foreach ($payload['actions'] ?? [] as $action) {
            $chat->dispatchAction($this, [
                'adapter' => $this,
                'actionId' => $action['action_id'] ?? '',
                'value' => $action['value'] ?? ($action['selected_option']['value'] ?? null),
                'user' => $user,
                'threadId' => $threadId,
                'triggerId' => $triggerId,
                'payload' => $payload,
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    protected function handleViewSubmission(array $payload, Chat $chat): mixed
    {
        $user = $this->parseAuthorFromPayload($payload);
        $view = $payload['view'] ?? [];

        return $chat->processModalSubmit([
            'adapter' => $this,
            'callbackId' => $view['callback_id'] ?? '',
            'user' => $user,
            'values' => $view['state']['values'] ?? [],
            'privateMetadata' => $view['private_metadata'] ?? null,
            'triggerId' => $payload['trigger_id'] ?? null,
            'viewId' => $view['id'] ?? '',
        ]);
    }

    /** @param array<string, mixed> $payload */
    protected function handleViewClosed(array $payload, Chat $chat): void
    {
        $user = $this->parseAuthorFromPayload($payload);
        $view = $payload['view'] ?? [];

        $chat->processModalClose([
            'adapter' => $this,
            'callbackId' => $view['callback_id'] ?? '',
            'user' => $user,
            'privateMetadata' => $view['private_metadata'] ?? null,
            'viewId' => $view['id'] ?? '',
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function handleSlashCommand(array $data, Chat $chat): Response
    {
        $chat->dispatchSlashCommand($this, [
            'adapter' => $this,
            'command' => $data['command'] ?? '',
            'text' => $data['text'] ?? '',
            'userId' => $data['user_id'] ?? '',
            'channelId' => $data['channel_id'] ?? '',
            'triggerId' => $data['trigger_id'] ?? null,
            'teamId' => $data['team_id'] ?? null,
        ]);

        return new Response('', 200);
    }

    /** @param array<string, mixed> $payload */
    protected function parseAuthorFromPayload(array $payload): Author
    {
        $userData = $payload['user'] ?? [];

        return new Author(
            userId: $userData['id'] ?? 'unknown',
            userName: $userData['username'] ?? '',
            fullName: $userData['name'] ?? '',
            isBot: false,
            isMe: ($userData['id'] ?? '') === $this->botUserId(),
        );
    }

    // ── Message Parsing ──────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $event = is_array($raw) ? $raw : [];

        $userId = $event['user'] ?? $event['bot_id'] ?? 'unknown';
        $isBot = isset($event['bot_id']) || ($event['subtype'] ?? '') === 'bot_message';
        $isMe = $userId === $this->botUserId();
        $userName = $event['username'] ?? '';
        $channel = $event['channel'] ?? '';
        $ts = $event['ts'] ?? '';
        $threadTs = $event['thread_ts'] ?? '';
        $text = $event['text'] ?? '';

        $normalizedText = $this->getFormatConverter()->toMarkdown($text);

        $threadId = $this->encodeThreadId([
            'channel' => $channel,
            'threadTs' => $threadTs,
        ]);

        $metadata = [];
        if ($ts !== '') {
            $unixTs = (int) floor((float) $ts);
            $metadata['dateSent'] = gmdate('Y-m-d\TH:i:s\Z', $unixTs);
        }

        if (isset($event['edited'])) {
            $metadata['edited'] = true;
            $editedTs = $event['edited']['ts'] ?? '';
            if ($editedTs !== '') {
                $unixTs = (int) floor((float) $editedTs);
                $metadata['editedAt'] = gmdate('Y-m-d\TH:i:s\Z', $unixTs);
            }
        }

        $attachments = [];
        foreach ($event['files'] ?? [] as $file) {
            $mimetype = $file['mimetype'] ?? '';
            $type = 'file';
            if (str_starts_with($mimetype, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($mimetype, 'video/')) {
                $type = 'video';
            } elseif (str_starts_with($mimetype, 'audio/')) {
                $type = 'audio';
            }

            $attachment = [
                'type' => $type,
                'url' => $file['url_private'] ?? '',
                'name' => $file['name'] ?? '',
                'mimeType' => $mimetype,
                'size' => $file['size'] ?? null,
            ];

            if (isset($file['original_w'])) {
                $attachment['width'] = $file['original_w'];
            }
            if (isset($file['original_h'])) {
                $attachment['height'] = $file['original_h'];
            }

            $attachments[] = $attachment;
        }

        $isMention = ($event['type'] ?? '') === 'app_mention';

        return new Message(
            id: $ts,
            threadId: $threadId,
            text: $normalizedText,
            formatted: $text,
            raw: $event,
            author: new Author(
                userId: $userId,
                userName: $userName,
                fullName: '',
                isBot: $isBot,
                isMe: $isMe,
            ),
            metadata: $metadata,
            attachments: $attachments,
            isMention: $isMention,
        );
    }

    // ── Thread ID ────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function encodeThreadId(array $data): string
    {
        $channel = $data['channel'] ?? '';
        $threadTs = $data['threadTs'] ?? '';

        return "slack:{$channel}:{$threadTs}";
    }

    /** @return array<string, mixed> */
    public function decodeThreadId(string $threadId): array
    {
        if ($threadId === '' || ! str_contains($threadId, ':')) {
            throw new ValidationError("Invalid Slack thread ID: '{$threadId}'");
        }

        $parts = explode(':', $threadId);

        if ($parts[0] !== 'slack') {
            throw new ValidationError("Invalid Slack thread ID prefix: '{$parts[0]}'");
        }

        if (count($parts) < 2 || count($parts) > 3) {
            throw new ValidationError("Invalid Slack thread ID format: '{$threadId}'");
        }

        if ($parts[1] === '') {
            throw new ValidationError("Invalid Slack thread ID: missing channel");
        }

        return [
            'channel' => $parts[1],
            'threadTs' => $parts[2] ?? '',
        ];
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['channel'];
    }

    public function isDM(string $threadId): bool
    {
        $decoded = $this->decodeThreadId($threadId);

        return str_starts_with($decoded['channel'], 'D');
    }

    // ── Format Rendering ─────────────────────────────────────────────

    public function renderFormatted(string $markdown): string
    {
        return $this->getFormatConverter()->fromMarkdown($markdown);
    }

    // ── API Methods ──────────────────────────────────────────────────

    public function postMessage(string $threadId, PostableMessage $message): SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);

        $payload = [
            'channel' => $decoded['channel'],
            'text' => $message->getText() ?? '',
        ];

        if ($decoded['threadTs'] !== '') {
            $payload['thread_ts'] = $decoded['threadTs'];
        }

        $formatted = $this->getFormatConverter()->renderPostable($message);
        if (is_string($formatted)) {
            $payload['text'] = $formatted;
        } elseif (is_array($formatted)) {
            $payload['blocks'] = $formatted;
        }

        $response = $this->apiCall('chat.postMessage', $payload);
        $sentMessage = $this->buildSentMessage($response, $threadId);

        // Upload files in batch if present
        $files = $message->getFiles();
        if (! empty($files)) {
            $this->uploadFiles($files, $decoded['channel'], $decoded['threadTs'] ?: ($response['ts'] ?? ''));
        }

        return $sentMessage;
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);

        $payload = [
            'channel' => $decoded['channel'],
            'ts' => $messageId,
            'text' => $message->getText() ?? '',
        ];

        $formatted = $this->getFormatConverter()->renderPostable($message);
        if (is_string($formatted)) {
            $payload['text'] = $formatted;
        } elseif (is_array($formatted)) {
            $payload['blocks'] = $formatted;
        }

        $response = $this->apiCall('chat.update', $payload);

        return $this->buildSentMessage($response, $threadId);
    }

    public function deleteMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('chat.delete', [
            'channel' => $decoded['channel'],
            'ts' => $messageId,
        ]);
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('reactions.add', [
            'channel' => $decoded['channel'],
            'timestamp' => $messageId,
            'name' => $emoji,
        ]);
    }

    public function removeReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('reactions.remove', [
            'channel' => $decoded['channel'],
            'timestamp' => $messageId,
            'name' => $emoji,
        ]);
    }

    public function startTyping(string $threadId, ?string $status = null): void
    {
        if ($status === null) {
            return;
        }

        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('assistant.threads.setStatus', [
            'channel_id' => $decoded['channel'],
            'thread_ts' => $decoded['threadTs'],
            'status' => $status,
        ]);
    }

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        $decoded = $this->decodeThreadId($threadId);

        $params = [
            'channel' => $decoded['channel'],
            'ts' => $decoded['threadTs'],
        ];

        if ($options?->limit !== null) {
            $params['limit'] = $options->limit;
        }

        $response = $this->apiCall('conversations.replies', $params);

        $messages = array_map(
            fn ($msg) => $this->parseMessage($msg + ['channel' => $decoded['channel']]),
            $response['messages'] ?? []
        );

        $nextCursor = $response['response_metadata']['next_cursor'] ?? null;

        return new FetchResult(
            messages: $messages,
            nextCursor: $nextCursor ?: null,
            hasMore: ! empty($nextCursor),
        );
    }

    public function fetchMessage(string $threadId, string $messageId): ?Message
    {
        $decoded = $this->decodeThreadId($threadId);

        $response = $this->apiCall('conversations.history', [
            'channel' => $decoded['channel'],
            'latest' => $messageId,
            'inclusive' => true,
            'limit' => 1,
        ]);

        $messages = $response['messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        return $this->parseMessage($messages[0] + ['channel' => $decoded['channel']]);
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        $decoded = $this->decodeThreadId($threadId);

        return new ThreadInfo(
            id: $threadId,
            channelId: $decoded['channel'] ?? null,
            isDM: $this->isDM($threadId),
        );
    }

    public function openDM(string $userId): ?string
    {
        $response = $this->apiCall('conversations.open', [
            'users' => $userId,
        ]);

        $channelId = $response['channel']['id'] ?? null;
        if (! $channelId) {
            return null;
        }

        return $this->encodeThreadId([
            'channel' => $channelId,
            'threadTs' => '',
        ]);
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);

        $payload = [
            'channel' => $decoded['channel'],
            'user' => $userId,
            'text' => $message->getText() ?? '',
        ];

        if ($decoded['threadTs'] !== '') {
            $payload['thread_ts'] = $decoded['threadTs'];
        }

        $formatted = $this->getFormatConverter()->renderPostable($message);
        if (is_string($formatted)) {
            $payload['text'] = $formatted;
        } elseif (is_array($formatted)) {
            $payload['blocks'] = $formatted;
        }

        $this->apiCall('chat.postEphemeral', $payload);

        return null;
    }

    /** @return array<string, mixed>|null */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        $view = SlackModalRenderer::toSlackView($modal, $contextId);

        $response = $this->apiCall('views.open', [
            'trigger_id' => $triggerId,
            'view' => $view,
        ]);

        return $response['view'] ?? null;
    }

    /**
     * @param  iterable<string>  $textStream
     * @param  array<string, mixed>  $options
     */
    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        $intervalMs = $options['streamingUpdateIntervalMs'] ?? 500;

        // Post initial placeholder
        $placeholder = $this->postMessage($threadId, PostableMessage::text('...'));

        $accumulated = '';
        $lastEdited = '';
        $lastUpdate = microtime(true) * 1000;

        foreach ($textStream as $chunk) {
            $accumulated .= $chunk;
            $now = microtime(true) * 1000;

            if (($now - $lastUpdate) >= $intervalMs && $accumulated !== $lastEdited) {
                $this->editMessage($threadId, $placeholder->id, PostableMessage::text($accumulated));
                $lastEdited = $accumulated;
                $lastUpdate = $now;
            }
        }

        // Final edit with complete content
        if ($accumulated !== $lastEdited) {
            $this->editMessage($threadId, $placeholder->id, PostableMessage::text($accumulated));
        }

        return $placeholder;
    }

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage
    {
        $threadId = $this->encodeThreadId([
            'channel' => $channelId,
            'threadTs' => '',
        ]);

        return $this->postMessage($threadId, $message);
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        $params = ['channel' => $channelId];

        if ($options?->limit !== null) {
            $params['limit'] = $options->limit;
        }

        $response = $this->apiCall('conversations.history', $params);

        $messages = array_map(
            fn ($msg) => $this->parseMessage($msg + ['channel' => $channelId]),
            $response['messages'] ?? []
        );

        $nextCursor = $response['response_metadata']['next_cursor'] ?? null;

        return new FetchResult(
            messages: $messages,
            nextCursor: $nextCursor ?: null,
            hasMore: ! empty($nextCursor),
        );
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        $response = $this->apiCall('conversations.info', [
            'channel' => $channelId,
        ]);

        $channel = $response['channel'] ?? null;
        if (! $channel) {
            return null;
        }

        return new ChannelInfo(
            id: $channel['id'] ?? $channelId,
            name: $channel['name'] ?? '',
            topic: $channel['topic']['value'] ?? null,
            memberCount: $channel['num_members'] ?? null,
            isDM: ($channel['is_im'] ?? false) || ($channel['is_mpim'] ?? false),
        );
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        $params = ['channel' => $channelId];

        if ($options?->limit !== null) {
            $params['limit'] = $options->limit;
        }

        $response = $this->apiCall('conversations.history', $params);

        $threadMessages = array_filter(
            $response['messages'] ?? [],
            fn ($msg) => isset($msg['reply_count']) && $msg['reply_count'] > 0
        );

        $threads = array_map(
            fn ($msg) => new ThreadSummary(
                id: $this->encodeThreadId(['channel' => $channelId, 'threadTs' => $msg['ts']]),
                title: mb_substr($msg['text'] ?? '', 0, 100) ?: null,
                lastActivity: $msg['latest_reply'] ?? $msg['ts'] ?? null,
                messageCount: $msg['reply_count'] ?? 0,
            ),
            array_values($threadMessages)
        );

        $nextCursor = $response['response_metadata']['next_cursor'] ?? null;

        return new ListThreadsResult(
            threads: $threads,
            nextCursor: $nextCursor ?: null,
            hasMore: ! empty($nextCursor),
        );
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }

    public function sendFile(string $threadId, FileUpload $file): ?SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);
        $channelId = $decoded['channel'];
        $threadTs = $decoded['threadTs'] ?? '';

        $result = $this->uploadFiles([$file], $channelId, $threadTs);

        if (empty($result)) {
            return null;
        }

        return $this->buildSentMessage(['ts' => ''], $threadId);
    }

    public function pinMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('pins.add', [
            'channel' => $decoded['channel'],
            'timestamp' => $messageId,
        ]);
    }

    public function unpinMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('pins.remove', [
            'channel' => $decoded['channel'],
            'timestamp' => $messageId,
        ]);
    }

    // ── File Uploads ─────────────────────────────────────────────────

    /**
     * Upload files using Slack's batch upload API.
     *
     * @param  list<\OpenCompany\Chatogrator\Messages\FileUpload>  $files
     * @return list<array<string, mixed>>
     */
    protected function uploadFiles(array $files, string $channelId, string $threadTs = ''): array
    {
        if (empty($files)) {
            return [];
        }

        // Step 1: Get upload URLs for each file
        $uploadEntries = [];
        foreach ($files as $file) {
            $response = $this->apiCall('files.getUploadURLExternal', [
                'filename' => $file->filename,
                'length' => strlen($file->getContents()),
            ]);

            if (! ($response['ok'] ?? false)) {
                continue;
            }

            $uploadEntries[] = [
                'upload_url' => $response['upload_url'],
                'file_id' => $response['file_id'],
                'file' => $file,
            ];
        }

        if (empty($uploadEntries)) {
            return [];
        }

        // Step 2: Upload file content to each URL in parallel
        Http::pool(fn (Pool $pool) => array_map(
            fn ($entry) => $pool->withBody($entry['file']->getContents(), $entry['file']->mimeType)
                ->post($entry['upload_url']),
            $uploadEntries
        ));

        // Step 3: Complete uploads — share files to channel/thread
        $fileUploads = array_map(
            fn ($entry) => ['id' => $entry['file_id'], 'title' => $entry['file']->filename],
            $uploadEntries
        );

        $completePayload = ['files' => $fileUploads];

        if ($channelId !== '') {
            $completePayload['channel_id'] = $channelId;
        }

        if ($threadTs !== '') {
            $completePayload['thread_ts'] = $threadTs;
        }

        $result = $this->apiCall('files.completeUploadExternal', $completePayload);

        return $result['files'] ?? [];
    }

    // ── Internal Helpers ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function apiCall(string $method, array $payload): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.($this->config['bot_token'] ?? ''),
        ])->post("https://slack.com/api/{$method}", $payload);

        return $response->json() ?? [];
    }

    /** @param array<string, mixed> $data */
    protected function buildSentMessage(array $data, string $threadId): SentMessage
    {
        $messageData = $data['message'] ?? $data;
        $ts = $messageData['ts'] ?? '';

        $sentMessage = new SentMessage(
            id: $ts,
            threadId: $threadId,
            text: $messageData['text'] ?? '',
            formatted: null,
            raw: $data,
            author: new Author(
                userId: $this->botUserId() ?? '',
                userName: $this->userName(),
                fullName: '',
                isBot: true,
                isMe: true,
            ),
            metadata: [],
            attachments: [],
            isMention: false,
        );

        $sentMessage->setAdapter($this);

        return $sentMessage;
    }
}
