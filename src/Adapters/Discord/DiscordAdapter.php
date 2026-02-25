<?php

namespace OpenCompany\Chatogrator\Adapters\Discord;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
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
use OpenCompany\Chatogrator\Types\ThreadSummary;

/** @phpstan-consistent-constructor */
class DiscordAdapter implements Adapter
{
    protected string $adapterName = 'discord';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?DiscordFormatConverter $formatConverter = null;

    /**
     * Discord interaction types.
     */
    private const INTERACTION_PING = 1;

    private const INTERACTION_APPLICATION_COMMAND = 2;

    private const INTERACTION_MESSAGE_COMPONENT = 3;

    private const INTERACTION_APPLICATION_COMMAND_AUTOCOMPLETE = 4;

    private const INTERACTION_MODAL_SUBMIT = 5;

    /**
     * Discord interaction response types.
     */
    private const RESPONSE_PONG = 1;

    private const RESPONSE_CHANNEL_MESSAGE_WITH_SOURCE = 4;

    private const RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE = 5;

    private const RESPONSE_DEFERRED_UPDATE_MESSAGE = 6;

    private const RESPONSE_UPDATE_MESSAGE = 7;

    private const RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT = 8;

    private const RESPONSE_MODAL = 9;

    /**
     * @param array<string, mixed> $config
     */
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
        return $this->config['user_name'] ?? 'bot';
    }

    public function botUserId(): ?string
    {
        return $this->config['application_id'] ?? null;
    }

    public function initialize(Chat $chat): void
    {
        //
    }

    protected function getFormatConverter(): DiscordFormatConverter
    {
        return $this->formatConverter ??= new DiscordFormatConverter;
    }

    // ── Webhook Handling ─────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        // Gateway-forwarded events (authenticated via shared secret, not Ed25519)
        if ($request->header('X-Gateway-Source') === 'discord-gateway') {
            $gatewaySecret = $this->config['gateway_secret'] ?? '';
            if (empty($gatewaySecret) || ! hash_equals($gatewaySecret, $request->header('X-Gateway-Secret', ''))) {
                return new Response(
                    json_encode(['error' => 'Unauthorized gateway request']),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }

            return $this->handleGatewayEvent($request->json()->all(), $chat);
        }

        $rawBody = $request->getContent();
        $signature = $request->headers->get('X-Signature-Ed25519', '');
        $timestamp = $request->headers->get('X-Signature-Timestamp', '');

        if (! $signature || ! $timestamp) {
            return new Response(
                json_encode(['error' => 'Missing signature headers']),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        // Reject signatures with invalid format (wrong length, non-hex)
        if (! DiscordCrypto::hasValidFormat($signature)) {
            return new Response(
                json_encode(['error' => 'Invalid signature format']),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        $publicKey = $this->config['public_key'] ?? '';

        // Verify Ed25519 signature cryptographically
        if (! DiscordCrypto::verifySignature($rawBody, $signature, $timestamp, $publicKey)) {
            return new Response(
                json_encode(['error' => 'Invalid signature']),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        $data = json_decode($rawBody, true);

        if ($data === null) {
            return new Response(
                json_encode(['error' => 'Invalid JSON']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        return $this->handleInteraction($data, $chat);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleInteraction(array $data, Chat $chat): Response
    {
        $type = $data['type'] ?? null;

        return match ($type) {
            self::INTERACTION_PING => $this->handlePing(),
            self::INTERACTION_APPLICATION_COMMAND => $this->handleApplicationCommand($data, $chat),
            self::INTERACTION_MESSAGE_COMPONENT => $this->handleMessageComponent($data, $chat),
            self::INTERACTION_APPLICATION_COMMAND_AUTOCOMPLETE => $this->handleAutocomplete($data, $chat),
            self::INTERACTION_MODAL_SUBMIT => $this->handleModalSubmit($data, $chat),
            default => new Response(
                json_encode(['error' => 'Unknown interaction type']),
                400,
                ['Content-Type' => 'application/json']
            ),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleGatewayEvent(array $data, Chat $chat): Response
    {
        $eventName = $data['event'] ?? '';
        $eventData = $data['data'] ?? [];

        match ($eventName) {
            'MESSAGE_CREATE' => $this->handleGatewayMessageCreate($eventData, $chat),
            'MESSAGE_UPDATE' => $this->handleGatewayMessageUpdate($eventData, $chat),
            'MESSAGE_DELETE' => $this->handleGatewayMessageDelete($eventData, $chat),
            'MESSAGE_REACTION_ADD' => $this->handleGatewayReaction($eventData, $chat, 'reaction_added'),
            'MESSAGE_REACTION_REMOVE' => $this->handleGatewayReaction($eventData, $chat, 'reaction_removed'),
            default => null,
        };

        return new Response('', 204);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleGatewayMessageCreate(array $data, Chat $chat): void
    {
        $guildId = $data['guild_id'] ?? '@me';
        $channelId = $data['channel_id'] ?? '';

        $threadId = $this->encodeThreadId([
            'guildId' => $guildId,
            'channelId' => $channelId,
        ]);

        $message = $this->parseMessage($data);

        $chat->dispatchIncomingMessage($this, $threadId, $message);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleGatewayMessageUpdate(array $data, Chat $chat): void
    {
        $guildId = $data['guild_id'] ?? '@me';
        $channelId = $data['channel_id'] ?? '';

        $threadId = $this->encodeThreadId([
            'guildId' => $guildId,
            'channelId' => $channelId,
        ]);

        $chat->dispatchMessageEdited($this, [
            'adapter' => $this,
            'threadId' => $threadId,
            'messageId' => $data['id'] ?? '',
            'message' => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleGatewayMessageDelete(array $data, Chat $chat): void
    {
        $guildId = $data['guild_id'] ?? '@me';
        $channelId = $data['channel_id'] ?? '';

        $threadId = $this->encodeThreadId([
            'guildId' => $guildId,
            'channelId' => $channelId,
        ]);

        $chat->dispatchMessageDeleted($this, [
            'adapter' => $this,
            'threadId' => $threadId,
            'messageId' => $data['id'] ?? '',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleGatewayReaction(array $data, Chat $chat, string $type): void
    {
        $guildId = $data['guild_id'] ?? '@me';
        $channelId = $data['channel_id'] ?? '';

        $threadId = $this->encodeThreadId([
            'guildId' => $guildId,
            'channelId' => $channelId,
        ]);

        $emoji = $data['emoji']['name'] ?? '';
        $userId = $data['user_id'] ?? '';

        $chat->dispatchReaction($this, [
            'adapter' => $this,
            'type' => $type,
            'threadId' => $threadId,
            'messageId' => $data['message_id'] ?? '',
            'emoji' => $emoji,
            'rawEmoji' => $emoji,
            'user' => new Author(
                userId: $userId,
                userName: $userId,
                fullName: $userId,
                isBot: false,
                isMe: false,
            ),
        ]);
    }

    protected function handlePing(): Response
    {
        return new Response(
            json_encode(['type' => self::RESPONSE_PONG]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleApplicationCommand(array $data, Chat $chat): Response
    {
        $user = $this->parseUserFromInteraction($data);
        $threadId = $this->buildThreadIdFromInteraction($data);

        $commandName = $data['data']['name'] ?? '';
        $commandOptions = $data['data']['options'] ?? [];

        // Build text from command name and options
        $text = '/' . $commandName;
        foreach ($commandOptions as $option) {
            if (isset($option['value'])) {
                $text .= ' ' . $option['value'];
            }
        }

        $chat->dispatchSlashCommand($this, [
            'adapter' => $this,
            'command' => '/' . $commandName,
            'text' => $text,
            'userId' => $user->userId,
            'channelId' => $data['channel_id'] ?? '',
            'triggerId' => $data['id'] ?? null,
            'interactionToken' => $data['token'] ?? null,
            'threadId' => $threadId,
        ]);

        return new Response(
            json_encode(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleMessageComponent(array $data, Chat $chat): Response
    {
        $user = $this->parseUserFromInteraction($data);
        $threadId = $this->buildThreadIdFromInteraction($data);

        $customId = $data['data']['custom_id'] ?? '';
        $componentType = $data['data']['component_type'] ?? null;
        $values = $data['data']['values'] ?? null;

        $chat->dispatchAction($this, [
            'adapter' => $this,
            'actionId' => $customId,
            'value' => $values[0] ?? null,
            'values' => $values,
            'user' => $user,
            'threadId' => $threadId,
            'triggerId' => $data['id'] ?? null,
            'interactionToken' => $data['token'] ?? null,
            'componentType' => $componentType,
            'payload' => $data,
        ]);

        return new Response(
            json_encode(['type' => self::RESPONSE_DEFERRED_UPDATE_MESSAGE]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleAutocomplete(array $data, Chat $chat): Response
    {
        // Return empty choices for autocomplete
        return new Response(
            json_encode([
                'type' => self::RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT,
                'data' => ['choices' => []],
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handleModalSubmit(array $data, Chat $chat): Response
    {
        $user = $this->parseUserFromInteraction($data);
        $threadId = $this->buildThreadIdFromInteraction($data);

        $customId = $data['data']['custom_id'] ?? '';
        $components = $data['data']['components'] ?? [];

        // Extract values from modal components
        $values = [];
        foreach ($components as $row) {
            foreach ($row['components'] ?? [] as $component) {
                $fieldId = $component['custom_id'] ?? '';
                $values[$fieldId] = $component['value'] ?? '';
            }
        }

        $chat->processModalSubmit([
            'adapter' => $this,
            'callbackId' => $customId,
            'customId' => $customId,
            'viewId' => $customId,
            'user' => $user,
            'values' => $values,
            'threadId' => $threadId,
            'triggerId' => $data['id'] ?? null,
            'interactionToken' => $data['token'] ?? null,
        ]);

        return new Response(
            json_encode(['type' => self::RESPONSE_DEFERRED_UPDATE_MESSAGE]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function parseUserFromInteraction(array $data): Author
    {
        // In guild interactions, user info is in member.user
        // In DM interactions, user info is in user directly
        $userData = $data['member']['user'] ?? $data['user'] ?? [];

        return new Author(
            userId: $userData['id'] ?? 'unknown',
            userName: $userData['username'] ?? '',
            fullName: $userData['global_name'] ?? $userData['username'] ?? '',
            isBot: $userData['bot'] ?? false,
            isMe: ($userData['id'] ?? '') === $this->botUserId(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function buildThreadIdFromInteraction(array $data): string
    {
        $guildId = $data['guild_id'] ?? null;
        $channelId = $data['channel_id'] ?? '';

        // If channel is from the channel object
        if (! $channelId && isset($data['channel']['id'])) {
            $channelId = $data['channel']['id'];
        }

        return $this->encodeThreadId([
            'guildId' => $guildId ?? '@me',
            'channelId' => $channelId,
        ]);
    }

    // ── Message Parsing ──────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $event = is_array($raw) ? $raw : [];

        $authorData = $event['author'] ?? [];
        $userId = $authorData['id'] ?? 'unknown';
        $userName = $authorData['username'] ?? '';
        $fullName = $authorData['global_name'] ?? $userName;
        $isBot = $authorData['bot'] ?? false;
        $isMe = $userId === $this->botUserId();

        $messageId = $event['id'] ?? '';
        $channelId = $event['channel_id'] ?? '';
        $guildId = $event['guild_id'] ?? null;
        $content = $event['content'] ?? '';
        $timestamp = $event['timestamp'] ?? '';
        $editedTimestamp = $event['edited_timestamp'] ?? null;

        // Build thread ID
        $threadId = $this->encodeThreadId([
            'guildId' => $guildId ?? '@me',
            'channelId' => $channelId,
        ]);

        // Convert Discord markdown to standard markdown, then strip to plain text
        $converter = $this->getFormatConverter();
        $normalizedText = $converter->toPlainText($content);

        // Build metadata
        $metadata = [];
        if ($timestamp !== '') {
            $metadata['dateSent'] = $timestamp;
        }

        if ($editedTimestamp !== null) {
            $metadata['edited'] = true;
            $metadata['editedAt'] = $editedTimestamp;
        }

        // Build attachments
        $attachments = [];
        foreach ($event['attachments'] ?? [] as $attachment) {
            $contentType = $attachment['content_type'] ?? null;
            $type = 'file';

            if ($contentType !== null) {
                if (str_starts_with($contentType, 'image/')) {
                    $type = 'image';
                } elseif (str_starts_with($contentType, 'video/')) {
                    $type = 'video';
                } elseif (str_starts_with($contentType, 'audio/')) {
                    $type = 'audio';
                }
            }

            $att = [
                'type' => $type,
                'url' => $attachment['url'] ?? '',
                'name' => $attachment['filename'] ?? '',
            ];

            if ($contentType !== null) {
                $att['mimeType'] = $contentType;
            }

            if (isset($attachment['width'])) {
                $att['width'] = $attachment['width'];
            }
            if (isset($attachment['height'])) {
                $att['height'] = $attachment['height'];
            }

            $attachments[] = $att;
        }

        return new Message(
            id: $messageId,
            threadId: $threadId,
            text: $normalizedText,
            formatted: $content,
            raw: $event,
            author: new Author(
                userId: $userId,
                userName: $userName,
                fullName: $fullName,
                isBot: $isBot,
                isMe: $isMe,
            ),
            metadata: $metadata,
            attachments: $attachments,
            isMention: false,
        );
    }

    // ── Thread ID ────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function encodeThreadId(array $data): string
    {
        $guildId = $data['guildId'] ?? '@me';
        $channelId = $data['channelId'] ?? '';
        $threadId = $data['threadId'] ?? null;

        $encoded = "discord:{$guildId}:{$channelId}";

        if ($threadId !== null && $threadId !== '') {
            $encoded .= ":{$threadId}";
        }

        return $encoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeThreadId(string $threadId): array
    {
        if ($threadId === '' || ! str_contains($threadId, ':')) {
            throw new ValidationError("Invalid Discord thread ID: '{$threadId}'");
        }

        $parts = explode(':', $threadId);

        if ($parts[0] !== 'discord') {
            throw new ValidationError("Invalid Discord thread ID prefix: '{$parts[0]}'");
        }

        if (count($parts) < 3) {
            throw new ValidationError("Invalid Discord thread ID format: '{$threadId}'");
        }

        $result = [
            'guildId' => $parts[1],
            'channelId' => $parts[2],
        ];

        if (isset($parts[3]) && $parts[3] !== '') {
            $result['threadId'] = $parts[3];
        }

        return $result;
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['channelId'];
    }

    public function isDM(string $threadId): bool
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['guildId'] === '@me';
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
            'content' => $message->getText() ?? '',
        ];

        $formatted = $this->getFormatConverter()->renderPostable($message);
        if (is_string($formatted)) {
            $payload['content'] = $formatted;
        } elseif (is_array($formatted)) {
            $payload = array_merge($payload, $formatted);
        }

        $response = $this->apiCall(
            'POST',
            "/channels/{$decoded['channelId']}/messages",
            $payload
        );

        return $this->buildSentMessage($response, $threadId);
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);

        $payload = [
            'content' => $message->getText() ?? '',
        ];

        $formatted = $this->getFormatConverter()->renderPostable($message);
        if (is_string($formatted)) {
            $payload['content'] = $formatted;
        } elseif (is_array($formatted)) {
            $payload = array_merge($payload, $formatted);
        }

        $response = $this->apiCall(
            'PATCH',
            "/channels/{$decoded['channelId']}/messages/{$messageId}",
            $payload
        );

        return $this->buildSentMessage($response, $threadId);
    }

    public function deleteMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall(
            'DELETE',
            "/channels/{$decoded['channelId']}/messages/{$messageId}"
        );
    }

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        $decoded = $this->decodeThreadId($threadId);

        $params = [];
        if ($options?->limit !== null) {
            $params['limit'] = $options->limit;
        }

        $response = $this->apiCall(
            'GET',
            "/channels/{$decoded['channelId']}/messages",
            $params
        );

        /** @var array<int, Message> $messages */
        $messages = array_map(
            fn ($msg) => $this->parseMessage($msg),
            $response
        );

        return new FetchResult(messages: $messages);
    }

    public function fetchMessage(string $threadId, string $messageId): ?Message
    {
        $decoded = $this->decodeThreadId($threadId);

        $response = $this->apiCall(
            'GET',
            "/channels/{$decoded['channelId']}/messages/{$messageId}"
        );

        if (empty($response)) {
            return null;
        }

        return $this->parseMessage($response);
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        $decoded = $this->decodeThreadId($threadId);

        return new ThreadInfo(
            id: $threadId,
            channelId: $decoded['channelId'] ?? null,
            isDM: $this->isDM($threadId),
        );
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);
        $encodedEmoji = urlencode($emoji);

        $this->apiCall(
            'PUT',
            "/channels/{$decoded['channelId']}/messages/{$messageId}/reactions/{$encodedEmoji}/@me"
        );
    }

    public function removeReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);
        $encodedEmoji = urlencode($emoji);

        $this->apiCall(
            'DELETE',
            "/channels/{$decoded['channelId']}/messages/{$messageId}/reactions/{$encodedEmoji}/@me"
        );
    }

    public function startTyping(string $threadId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall(
            'POST',
            "/channels/{$decoded['channelId']}/typing"
        );
    }

    public function openDM(string $userId): ?string
    {
        $response = $this->apiCall('POST', '/users/@me/channels', [
            'recipient_id' => $userId,
        ]);

        $channelId = $response['id'] ?? null;
        if (! $channelId) {
            return null;
        }

        return $this->encodeThreadId([
            'guildId' => '@me',
            'channelId' => $channelId,
        ]);
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        // Discord doesn't have a direct ephemeral message API outside of interactions
        // Ephemeral messages are sent as interaction responses
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        // Discord modals are opened through interaction responses
        return null;
    }

    /**
     * @param iterable<string> $textStream
     * @param array<string, mixed> $options
     */
    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        return null;
    }

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage
    {
        $threadId = $this->encodeThreadId([
            'guildId' => '@me',
            'channelId' => $channelId,
        ]);

        return $this->postMessage($threadId, $message);
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        $params = [];
        if ($options?->limit !== null) {
            $params['limit'] = $options->limit;
        }

        $response = $this->apiCall('GET', "/channels/{$channelId}/messages", $params);

        /** @var array<int, Message> $messages */
        $messages = array_map(
            fn ($msg) => $this->parseMessage($msg),
            $response
        );

        return new FetchResult(messages: $messages);
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        $response = $this->apiCall('GET', "/channels/{$channelId}");

        if (empty($response) || ! isset($response['id'])) {
            return null;
        }

        return new ChannelInfo(
            id: $response['id'],
            name: $response['name'] ?? '',
            topic: $response['topic'] ?? null,
            memberCount: $response['member_count'] ?? null,
            isDM: ($response['type'] ?? 0) === 1,
        );
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        $response = $this->apiCall('GET', "/channels/{$channelId}/threads/archived/public");

        $threads = array_map(
            fn ($thread) => new ThreadSummary(
                id: $this->encodeThreadId(['guildId' => '@me', 'channelId' => $thread['id'] ?? '']),
                title: $thread['name'] ?? null,
                lastActivity: $thread['last_message_id'] ?? null,
                messageCount: $thread['message_count'] ?? 0,
            ),
            $response['threads'] ?? []
        );

        return new ListThreadsResult(
            threads: $threads,
            hasMore: $response['has_more'] ?? false,
        );
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }

    // ── Internal Helpers ─────────────────────────────────────────────

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function apiCall(string $method, string $path, array $payload = []): array
    {
        $baseUrl = 'https://discord.com/api/v10';
        $url = $baseUrl . $path;

        $headers = [
            'Authorization' => 'Bot ' . ($this->config['bot_token'] ?? ''),
        ];

        $response = match (strtoupper($method)) {
            'GET' => Http::withHeaders($headers)->get($url, $payload),
            'POST' => Http::withHeaders($headers)->post($url, $payload),
            'PATCH' => Http::withHeaders($headers)->patch($url, $payload),
            'PUT' => Http::withHeaders($headers)->put($url, $payload),
            'DELETE' => Http::withHeaders($headers)->delete($url, $payload),
            default => Http::withHeaders($headers)->post($url, $payload),
        };

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function buildSentMessage(array $data, string $threadId): SentMessage
    {
        $messageId = $data['id'] ?? '';

        $sentMessage = new SentMessage(
            id: $messageId,
            threadId: $threadId,
            text: $data['content'] ?? '',
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
