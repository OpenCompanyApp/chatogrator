<?php

namespace OpenCompany\Chatogrator\Adapters\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Adapters\Concerns\HandlesRateLimits;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Errors\AdapterError;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
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

/** @phpstan-consistent-constructor */
class TelegramAdapter implements Adapter
{
    use HandlesRateLimits;

    protected string $adapterName = 'telegram';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?TelegramFormatConverter $formatConverter = null;

    protected const MAX_MESSAGE_LENGTH = 4096;

    protected const MAX_CAPTION_LENGTH = 1024;

    /**
     * @param array<string, mixed> $config
     */
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
            'bot_token' => config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN')),
            'webhook_secret' => config('services.telegram.webhook_secret', env('TELEGRAM_WEBHOOK_SECRET')),
            'bot_user_id' => config('services.telegram.bot_user_id', env('TELEGRAM_BOT_USER_ID')),
            'bot_username' => config('services.telegram.bot_username', env('TELEGRAM_BOT_USERNAME')),
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

    protected function getFormatConverter(): TelegramFormatConverter
    {
        return $this->formatConverter ??= new TelegramFormatConverter;
    }

    // ── Thread ID Encoding/Decoding ─────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function encodeThreadId(array $data): string
    {
        $chatId = $data['chatId'] ?? '';
        $messageThreadId = $data['messageThreadId'] ?? null;

        if ($messageThreadId !== null && $messageThreadId !== '') {
            return "telegram:{$chatId}:{$messageThreadId}";
        }

        return "telegram:{$chatId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeThreadId(string $threadId): array
    {
        if (! str_starts_with($threadId, 'telegram:')) {
            throw new ValidationError('Invalid Telegram thread ID: must start with "telegram:"');
        }

        $rest = substr($threadId, 9); // Remove "telegram:" prefix

        if ($rest === '') {
            throw new ValidationError('Invalid Telegram thread ID: missing chat ID');
        }

        // Format: chatId[:messageThreadId]
        // chatId can be negative (groups), so we need to handle the leading "-"
        $parts = explode(':', $rest, 2);
        $chatId = $parts[0];
        $messageThreadId = $parts[1] ?? null;

        if ($chatId === '') {
            throw new ValidationError('Invalid Telegram thread ID: empty chat ID');
        }

        $result = ['chatId' => $chatId];

        if ($messageThreadId !== null && $messageThreadId !== '') {
            $result['messageThreadId'] = $messageThreadId;
        }

        return $result;
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['chatId'] ?? null;
    }

    public function isDM(string $threadId): bool
    {
        $decoded = $this->decodeThreadId($threadId);
        $chatId = $decoded['chatId'] ?? '0';

        // Private chats have positive chat IDs
        return is_numeric($chatId) && (int) $chatId > 0;
    }

    // ── Webhook Handling ─────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        $secret = $request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');
        $expectedSecret = $this->config['webhook_secret'] ?? '';

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            return new Response('Unauthorized', 401);
        }

        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return new Response('Invalid JSON', 400);
        }

        if (isset($data['message'])) {
            $this->handleMessageUpdate($data['message'], $chat);
        } elseif (isset($data['edited_message'])) {
            $this->handleEditedMessage($data['edited_message'], $chat);
        } elseif (isset($data['callback_query'])) {
            $this->handleCallbackQuery($data['callback_query'], $chat);
        } elseif (isset($data['message_reaction'])) {
            $this->handleMessageReaction($data['message_reaction'], $chat);
        } elseif (isset($data['channel_post'])) {
            $this->handleMessageUpdate($data['channel_post'], $chat);
        } elseif (isset($data['edited_channel_post'])) {
            $this->handleEditedMessage($data['edited_channel_post'], $chat);
        }

        return new Response('', 200);
    }

    /**
     * @param array<string, mixed> $messageData
     */
    protected function handleMessageUpdate(array $messageData, Chat $chat): void
    {
        $from = $messageData['from'] ?? [];
        $fromId = (string) ($from['id'] ?? 'unknown');

        // Skip messages from self
        if ($fromId === $this->botUserId()) {
            return;
        }

        // Check for bot commands
        $entities = $messageData['entities'] ?? [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'bot_command' && ($entity['offset'] ?? -1) === 0) {
                $this->handleBotCommand($messageData, $chat);

                return;
            }
        }

        $message = $this->parseMessage($messageData);
        $threadId = $this->buildThreadId($messageData);

        // All private chats and @mentions are treated as mentions
        $chatType = $messageData['chat']['type'] ?? 'private';
        $isMention = $chatType === 'private' || $this->hasBotMention($messageData);

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

    /**
     * @param array<string, mixed> $messageData
     */
    protected function handleBotCommand(array $messageData, Chat $chat): void
    {
        $text = $messageData['text'] ?? '';
        $parts = explode(' ', $text, 2);
        $command = $parts[0]; // e.g., "/start" or "/start@BotName"

        // Strip @botname suffix from command
        if (str_contains($command, '@')) {
            $command = substr($command, 0, strpos($command, '@'));
        }

        $commandText = $parts[1] ?? '';
        $from = $messageData['from'] ?? [];
        $threadId = $this->buildThreadId($messageData);

        $chat->dispatchSlashCommand($this, [
            'adapter' => $this,
            'command' => $command,
            'text' => $commandText,
            'userId' => (string) ($from['id'] ?? ''),
            'channelId' => (string) ($messageData['chat']['id'] ?? ''),
            'triggerId' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $messageData
     */
    protected function handleEditedMessage(array $messageData, Chat $chat): void
    {
        $threadId = $this->buildThreadId($messageData);

        $chat->dispatchMessageEdited($this, [
            'adapter' => $this,
            'threadId' => $threadId,
            'message' => $messageData,
            'previousMessage' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    protected function handleCallbackQuery(array $callbackQuery, Chat $chat): void
    {
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $data = $callbackQuery['callback_data'] ?? '';
        $from = $callbackQuery['from'] ?? [];
        $message = $callbackQuery['message'] ?? [];
        $chatId = (string) ($message['chat']['id'] ?? '');

        // Acknowledge the callback to dismiss the loading spinner
        try {
            $this->apiCall('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
            ]);
        } catch (\Throwable) {
            // Non-critical, continue processing
        }

        // Parse actionId and value from callback_data
        $actionId = $data;
        $value = null;
        if (str_contains($data, ':')) {
            [$actionId, $value] = explode(':', $data, 2);
        }

        $threadId = $this->buildThreadIdFromChat($chatId, $message['message_thread_id'] ?? null);

        $user = new Author(
            userId: (string) ($from['id'] ?? 'unknown'),
            userName: $from['username'] ?? '',
            fullName: trim(($from['first_name'] ?? '').(' '.($from['last_name'] ?? ''))),
            isBot: $from['is_bot'] ?? false,
            isMe: (string) ($from['id'] ?? '') === $this->botUserId(),
        );

        $chat->dispatchAction($this, [
            'adapter' => $this,
            'actionId' => $actionId,
            'value' => $value,
            'user' => $user,
            'threadId' => $threadId,
            'triggerId' => $callbackQueryId,
            'payload' => $callbackQuery,
        ]);
    }

    /**
     * @param array<string, mixed> $reactionData
     */
    protected function handleMessageReaction(array $reactionData, Chat $chat): void
    {
        $chatId = (string) ($reactionData['chat']['id'] ?? '');
        $messageId = (string) ($reactionData['message_id'] ?? '');
        $user = $reactionData['user'] ?? $reactionData['actor_chat'] ?? [];
        $userId = (string) ($user['id'] ?? 'unknown');

        $threadId = $this->buildThreadIdFromChat($chatId);

        $newReactions = $reactionData['new_reaction'] ?? [];
        $oldReactions = $reactionData['old_reaction'] ?? [];

        // Find added reactions
        foreach ($newReactions as $reaction) {
            $emoji = $reaction['emoji'] ?? '';
            if ($emoji === '') {
                continue;
            }

            $chat->dispatchReaction($this, [
                'adapter' => $this,
                'type' => 'reaction_added',
                'threadId' => $threadId,
                'messageId' => $messageId,
                'emoji' => $emoji,
                'rawEmoji' => $emoji,
                'user' => new Author(
                    userId: $userId,
                    userName: $user['username'] ?? '',
                    fullName: trim(($user['first_name'] ?? '').(' '.($user['last_name'] ?? ''))),
                    isBot: $user['is_bot'] ?? false,
                    isMe: $userId === $this->botUserId(),
                ),
            ]);
        }

        // Find removed reactions
        foreach ($oldReactions as $reaction) {
            $emoji = $reaction['emoji'] ?? '';
            if ($emoji === '') {
                continue;
            }

            // Check if this reaction was removed (not in newReactions)
            $stillPresent = false;
            foreach ($newReactions as $newReaction) {
                if (($newReaction['emoji'] ?? '') === $emoji) {
                    $stillPresent = true;
                    break;
                }
            }

            if (! $stillPresent) {
                $chat->dispatchReaction($this, [
                    'adapter' => $this,
                    'type' => 'reaction_removed',
                    'threadId' => $threadId,
                    'messageId' => $messageId,
                    'emoji' => $emoji,
                    'rawEmoji' => $emoji,
                    'user' => new Author(
                        userId: $userId,
                        userName: $user['username'] ?? '',
                        fullName: trim(($user['first_name'] ?? '').(' '.($user['last_name'] ?? ''))),
                        isBot: $user['is_bot'] ?? false,
                        isMe: $userId === $this->botUserId(),
                    ),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $messageData
     */
    protected function hasBotMention(array $messageData): bool
    {
        $entities = $messageData['entities'] ?? [];
        $text = $messageData['text'] ?? '';
        $botUsername = $this->config['bot_username'] ?? $this->userName();

        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'mention') {
                $offset = $entity['offset'] ?? 0;
                $length = $entity['length'] ?? 0;
                $mention = mb_substr($text, $offset, $length);

                if (strtolower($mention) === '@'.strtolower($botUsername)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $messageData
     */
    protected function buildThreadId(array $messageData): string
    {
        $chatId = (string) ($messageData['chat']['id'] ?? '');
        $messageThreadId = $messageData['message_thread_id'] ?? null;

        return $this->buildThreadIdFromChat($chatId, $messageThreadId);
    }

    protected function buildThreadIdFromChat(string $chatId, ?int $messageThreadId = null): string
    {
        $data = ['chatId' => $chatId];

        if ($messageThreadId !== null) {
            $data['messageThreadId'] = (string) $messageThreadId;
        }

        return $this->encodeThreadId($data);
    }

    // ── Message Parsing ──────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $data = is_array($raw) ? $raw : [];

        $from = $data['from'] ?? [];
        $userId = (string) ($from['id'] ?? 'unknown');
        $firstName = $from['first_name'] ?? '';
        $lastName = $from['last_name'] ?? '';
        $username = $from['username'] ?? '';
        $isBot = $from['is_bot'] ?? false;
        $isMe = $userId === $this->botUserId();

        $fullName = trim($firstName.' '.$lastName);

        $text = $data['text'] ?? $data['caption'] ?? '';
        $messageId = (string) ($data['message_id'] ?? '');
        $threadId = $this->buildThreadId($data);
        $date = $data['date'] ?? null;

        $metadata = [];
        if ($date !== null) {
            $metadata['dateSent'] = gmdate('Y-m-d\TH:i:s\Z', $date);
        }

        if (isset($data['edit_date'])) {
            $metadata['edited'] = true;
            $metadata['editedAt'] = gmdate('Y-m-d\TH:i:s\Z', $data['edit_date']);
        }

        $chatType = $data['chat']['type'] ?? 'private';
        $metadata['chatType'] = $chatType;

        if (isset($data['reply_to_message'])) {
            $metadata['replyToMessageId'] = (string) ($data['reply_to_message']['message_id'] ?? '');
        }

        // Parse attachments
        $attachments = $this->parseAttachments($data);

        return new Message(
            id: $messageId,
            threadId: $threadId,
            text: $text,
            formatted: $text,
            raw: $data,
            author: new Author(
                userId: $userId,
                userName: $username ?: $fullName,
                fullName: $fullName,
                isBot: $isBot,
                isMe: $isMe,
            ),
            metadata: $metadata,
            attachments: $attachments,
            isMention: $chatType === 'private',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    protected function parseAttachments(array $data): array
    {
        $attachments = [];

        // Photo (Telegram sends multiple sizes, take the largest)
        if (! empty($data['photo'])) {
            $photo = end($data['photo']);
            $attachments[] = [
                'type' => 'image',
                'fileId' => $photo['file_id'] ?? '',
                'name' => 'photo.jpg',
                'mimeType' => 'image/jpeg',
                'size' => $photo['file_size'] ?? null,
                'width' => $photo['width'] ?? null,
                'height' => $photo['height'] ?? null,
            ];
        }

        // Document
        if (! empty($data['document'])) {
            $doc = $data['document'];
            $mime = $doc['mime_type'] ?? 'application/octet-stream';
            $type = str_starts_with($mime, 'image/') ? 'image' : 'file';

            $attachments[] = [
                'type' => $type,
                'fileId' => $doc['file_id'] ?? '',
                'name' => $doc['file_name'] ?? 'document',
                'mimeType' => $mime,
                'size' => $doc['file_size'] ?? null,
            ];
        }

        // Video
        if (! empty($data['video'])) {
            $video = $data['video'];
            $attachments[] = [
                'type' => 'video',
                'fileId' => $video['file_id'] ?? '',
                'name' => $video['file_name'] ?? 'video.mp4',
                'mimeType' => $video['mime_type'] ?? 'video/mp4',
                'size' => $video['file_size'] ?? null,
                'width' => $video['width'] ?? null,
                'height' => $video['height'] ?? null,
            ];
        }

        // Audio
        if (! empty($data['audio'])) {
            $audio = $data['audio'];
            $attachments[] = [
                'type' => 'audio',
                'fileId' => $audio['file_id'] ?? '',
                'name' => $audio['file_name'] ?? 'audio',
                'mimeType' => $audio['mime_type'] ?? 'audio/mpeg',
                'size' => $audio['file_size'] ?? null,
            ];
        }

        // Voice
        if (! empty($data['voice'])) {
            $voice = $data['voice'];
            $attachments[] = [
                'type' => 'audio',
                'fileId' => $voice['file_id'] ?? '',
                'name' => 'voice.ogg',
                'mimeType' => $voice['mime_type'] ?? 'audio/ogg',
                'size' => $voice['file_size'] ?? null,
            ];
        }

        // Sticker
        if (! empty($data['sticker'])) {
            $sticker = $data['sticker'];
            $attachments[] = [
                'type' => 'image',
                'fileId' => $sticker['file_id'] ?? '',
                'name' => ($sticker['emoji'] ?? 'sticker').'.webp',
                'mimeType' => 'image/webp',
                'size' => $sticker['file_size'] ?? null,
            ];
        }

        return $attachments;
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
        $chatId = $decoded['chatId'];
        $messageThreadId = $decoded['messageThreadId'] ?? null;

        // Handle card messages
        $formatted = $this->getFormatConverter()->renderPostable($message);

        if (is_array($formatted) && isset($formatted['text'])) {
            // Card rendered to Telegram format
            $payload = [
                'chat_id' => $chatId,
                'text' => $formatted['text'],
                'parse_mode' => 'HTML',
            ];

            if (isset($formatted['reply_markup'])) {
                $payload['reply_markup'] = json_encode($formatted['reply_markup']);
            }
        } else {
            // Text or markdown content
            $text = is_string($formatted) ? $formatted : ($message->getText() ?? '');

            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];
        }

        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = (int) $messageThreadId;
        }

        // Handle long messages
        if (mb_strlen($payload['text']) > self::MAX_MESSAGE_LENGTH) {
            return $this->sendLongMessage($payload);
        }

        $response = $this->apiCall('sendMessage', $payload);

        // Send file attachments if any
        $files = $message->getFiles();
        if (! empty($files)) {
            $this->sendFiles($chatId, $files, $messageThreadId);
        }

        return $this->buildSentMessage($response, $threadId);
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);
        $chatId = $decoded['chatId'];

        $formatted = $this->getFormatConverter()->renderPostable($message);

        $payload = [
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
            'parse_mode' => 'HTML',
        ];

        if (is_array($formatted) && isset($formatted['text'])) {
            $payload['text'] = $formatted['text'];
            if (isset($formatted['reply_markup'])) {
                $payload['reply_markup'] = json_encode($formatted['reply_markup']);
            }
        } else {
            $payload['text'] = is_string($formatted) ? $formatted : ($message->getText() ?? '');
        }

        $response = $this->apiCall('editMessageText', $payload);

        return $this->buildSentMessage($response, $threadId);
    }

    public function deleteMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('deleteMessage', [
            'chat_id' => $decoded['chatId'],
            'message_id' => (int) $messageId,
        ]);
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('setMessageReaction', [
            'chat_id' => $decoded['chatId'],
            'message_id' => (int) $messageId,
            'reaction' => json_encode([
                ['type' => 'emoji', 'emoji' => $emoji],
            ]),
        ]);
    }

    public function removeReaction(string $threadId, string $messageId, string $emoji): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('setMessageReaction', [
            'chat_id' => $decoded['chatId'],
            'message_id' => (int) $messageId,
            'reaction' => json_encode([]),
        ]);
    }

    public function startTyping(string $threadId, ?string $status = null): void
    {
        $decoded = $this->decodeThreadId($threadId);

        try {
            $this->apiCall('sendChatAction', [
                'chat_id' => $decoded['chatId'],
                'action' => 'typing',
            ]);
        } catch (\Throwable) {
            // Non-critical, silently ignore
        }
    }

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        throw new NotImplementedError('Telegram Bot API does not support fetching message history');
    }

    public function fetchMessage(string $threadId, string $messageId): ?Message
    {
        throw new NotImplementedError('Telegram Bot API does not support fetching individual messages');
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        $decoded = $this->decodeThreadId($threadId);

        return new ThreadInfo(
            id: $threadId,
            channelId: $decoded['chatId'] ?? null,
            isDM: $this->isDM($threadId),
        );
    }

    public function openDM(string $userId): ?string
    {
        // In Telegram, the user's chat_id IS the user_id for private chats
        return $this->encodeThreadId(['chatId' => $userId]);
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        // Telegram has no native ephemeral messages
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        throw new NotImplementedError('Telegram does not support modal dialogs');
    }

    /**
     * @param iterable<string> $textStream
     * @param array<string, mixed> $options
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
                try {
                    $this->editMessage($threadId, $placeholder->id, PostableMessage::text($accumulated));
                    $lastEdited = $accumulated;
                    $lastUpdate = $now;
                } catch (\Throwable) {
                    // Edit may fail if content hasn't changed enough; continue
                }
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
        $threadId = $this->encodeThreadId(['chatId' => $channelId]);

        return $this->postMessage($threadId, $message);
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        throw new NotImplementedError('Telegram Bot API does not support fetching channel message history');
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        $response = $this->apiCall('getChat', [
            'chat_id' => $channelId,
        ]);

        $chatType = $response['type'] ?? 'private';
        $title = $response['title'] ?? null;
        $firstName = $response['first_name'] ?? '';
        $lastName = $response['last_name'] ?? '';
        $name = $title ?? trim($firstName.' '.$lastName);

        return new ChannelInfo(
            id: (string) ($response['id'] ?? $channelId),
            name: $name,
            topic: $response['description'] ?? null,
            memberCount: null,
            isDM: $chatType === 'private',
        );
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        throw new NotImplementedError('Telegram Bot API does not support listing threads');
    }

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }

    public function sendFile(string $threadId, FileUpload $file): ?SentMessage
    {
        $decoded = $this->decodeThreadId($threadId);
        $chatId = $decoded['chatId'];
        $messageThreadId = $decoded['messageThreadId'] ?? null;

        $isImage = str_starts_with($file->mimeType, 'image/') && ! $file->forceDocument;
        $method = $isImage ? 'sendPhoto' : 'sendDocument';
        $fileKey = $isImage ? 'photo' : 'document';

        $params = ['chat_id' => $chatId];
        if ($messageThreadId !== null) {
            $params['message_thread_id'] = (int) $messageThreadId;
        }
        if ($file->caption) {
            $params['caption'] = mb_substr($file->caption, 0, self::MAX_CAPTION_LENGTH);
        }

        $botToken = $this->config['bot_token'] ?? '';
        $url = "https://api.telegram.org/bot{$botToken}/{$method}";

        if ($file->url !== null) {
            // Telegram accepts URLs directly — no multipart needed
            $params[$fileKey] = $file->url;
            $response = Http::timeout(30)->post($url, $params);
        } else {
            // Upload from path or content buffer via multipart
            $contents = $file->path !== null && file_exists($file->path)
                ? file_get_contents($file->path)
                : ($file->content ?? '');

            $response = Http::timeout(30)
                ->attach($fileKey, $contents, $file->filename)
                ->post($url, $params);
        }

        $data = $response->json() ?? [];

        if (! ($data['ok'] ?? false)) {
            // Auto-retry as document if photo dimensions are rejected
            if ($isImage && str_contains($data['description'] ?? '', 'PHOTO_INVALID_DIMENSIONS')) {
                return $this->sendFile($threadId, new FileUpload(
                    content: $file->content,
                    filename: $file->filename,
                    mimeType: $file->mimeType,
                    path: $file->path,
                    url: $file->url,
                    caption: $file->caption,
                    forceDocument: true,
                ));
            }

            throw new AdapterError(
                'Telegram API error: '.($data['description'] ?? 'Unknown error')
            );
        }

        return $this->buildSentMessage($data['result'] ?? [], $threadId);
    }

    public function pinMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('pinChatMessage', [
            'chat_id' => $decoded['chatId'],
            'message_id' => (int) $messageId,
            'disable_notification' => true,
        ]);
    }

    public function unpinMessage(string $threadId, string $messageId): void
    {
        $decoded = $this->decodeThreadId($threadId);

        $this->apiCall('unpinChatMessage', [
            'chat_id' => $decoded['chatId'],
            'message_id' => (int) $messageId,
        ]);
    }

    // ── Internal Helpers ─────────────────────────────────────────────

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function apiCall(string $method, array $payload = []): array
    {
        $botToken = $this->config['bot_token'] ?? '';
        $url = "https://api.telegram.org/bot{$botToken}/{$method}";

        $response = Http::timeout(10)->post($url, $payload);

        $data = $response->json() ?? [];

        if (! ($data['ok'] ?? false)) {
            $errorCode = $data['error_code'] ?? 0;

            if ($errorCode === 429) {
                $retryAfter = $data['parameters']['retry_after'] ?? 30;
                $this->handleRateLimit($retryAfter);
            }

            throw new AdapterError(
                'Telegram API error: '.($data['description'] ?? 'Unknown error')
            );
        }

        return $data['result'] ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function sendLongMessage(array $payload): SentMessage
    {
        $text = $payload['text'];
        $chunks = $this->splitAtLineBoundaries($text, self::MAX_MESSAGE_LENGTH);
        $firstResponse = null;
        $threadId = $this->encodeThreadId(['chatId' => $payload['chat_id']]);

        foreach ($chunks as $i => $chunk) {
            $chunkPayload = $payload;
            $chunkPayload['text'] = $chunk;

            // Only include reply_markup on last chunk
            if ($i < count($chunks) - 1) {
                unset($chunkPayload['reply_markup']);
            }

            $response = $this->apiCall('sendMessage', $chunkPayload);

            if ($firstResponse === null) {
                $firstResponse = $response;
            }
        }

        return $this->buildSentMessage($firstResponse ?? [], $threadId);
    }

    /**
     * Split text at safe boundaries, never inside <pre> blocks.
     *
     * @return list<string>
     */
    protected function splitAtLineBoundaries(string $text, int $maxLength): array
    {
        if (mb_strlen($text) <= $maxLength) {
            return [$text];
        }

        // Split into segments: alternating text and <pre>...</pre> blocks
        $segments = preg_split('/(<pre>.*?<\/pre>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $current = '';

        foreach ($segments as $segment) {
            $isPre = str_starts_with($segment, '<pre>');

            if ($isPre) {
                // Never split a <pre> block — flush current and give it its own chunk if needed
                if (mb_strlen($current.$segment) <= $maxLength) {
                    $current .= $segment;
                } else {
                    if ($current !== '') {
                        $chunks[] = trim($current);
                        $current = '';
                    }
                    // Pre block alone (may exceed max, but we can't split it safely)
                    $chunks[] = trim($segment);
                }
            } else {
                // Regular text — split at line boundaries
                $lines = explode("\n", $segment);
                foreach ($lines as $line) {
                    $candidate = $current === '' ? $line : $current."\n".$line;

                    if (mb_strlen($candidate) > $maxLength) {
                        if ($current !== '') {
                            $chunks[] = trim($current);
                            $current = $line;
                        } else {
                            // Single line exceeds max; force-split
                            $chunks[] = mb_substr($line, 0, $maxLength);
                            $current = mb_substr($line, $maxLength);
                        }
                    } else {
                        $current = $candidate;
                    }
                }
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    /**
     * @param  array<int, FileUpload>  $files
     */
    protected function sendFiles(string $chatId, array $files, ?string $messageThreadId = null): void
    {
        $threadId = $this->encodeThreadId(array_filter([
            'chatId' => $chatId,
            'messageThreadId' => $messageThreadId,
        ]));

        foreach ($files as $file) {
            $this->sendFile($threadId, $file);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function buildSentMessage(array $data, string $threadId): SentMessage
    {
        $messageId = (string) ($data['message_id'] ?? '');
        $text = $data['text'] ?? $data['caption'] ?? '';

        $sentMessage = new SentMessage(
            id: $messageId,
            threadId: $threadId,
            text: $text,
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
