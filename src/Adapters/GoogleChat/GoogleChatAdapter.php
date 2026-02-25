<?php

namespace OpenCompany\Chatogrator\Adapters\GoogleChat;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Contracts\StateAdapter;
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
class GoogleChatAdapter implements Adapter
{
    protected string $adapterName = 'gchat';

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?GoogleChatFormatConverter $formatConverter = null;

    protected ?GoogleChatUserInfo $userInfoCache = null;

    protected ?StateAdapter $stateAdapter = null;

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
        return $this->config['user_name'] ?? $this->config['bot_name'] ?? $this->adapterName . '-bot';
    }

    public function botUserId(): ?string
    {
        return $this->config['bot_user_id'] ?? null;
    }

    public function initialize(Chat $chat): void
    {
        //
    }

    /**
     * Set the state adapter for user info caching.
     */
    public function setState(StateAdapter $state): void
    {
        $this->stateAdapter = $state;
        // Re-create the user info cache with the new state
        $this->userInfoCache = new GoogleChatUserInfo($state);
    }

    protected function getFormatConverter(): GoogleChatFormatConverter
    {
        return $this->formatConverter ??= new GoogleChatFormatConverter;
    }

    protected function getUserInfoCache(): GoogleChatUserInfo
    {
        return $this->userInfoCache ??= new GoogleChatUserInfo($this->stateAdapter);
    }

    // ── Thread ID Encoding/Decoding ─────────────────────────────────

    /** @param array<string, mixed> $data */
    public function encodeThreadId(array $data): string
    {
        $spaceName = $data['spaceName'] ?? '';
        $threadName = $data['threadName'] ?? null;
        $isDM = $data['isDM'] ?? false;

        $parts = ['gchat', $spaceName];

        if ($threadName !== null) {
            $parts[] = base64_encode($threadName);
        }

        if ($isDM) {
            $parts[] = 'dm';
        }

        return implode(':', $parts);
    }

    /** @return array<string, mixed> */
    public function decodeThreadId(string $threadId): array
    {
        if (! str_starts_with($threadId, 'gchat:')) {
            throw new ValidationError('Invalid Google Chat thread ID: must start with "gchat:"');
        }

        $rest = substr($threadId, 6); // Remove "gchat:" prefix
        if ($rest === '') {
            throw new ValidationError('Invalid Google Chat thread ID: missing space name');
        }

        // Parse: spaceName[:base64thread][:dm]
        // spaceName itself contains "/" (e.g., "spaces/ABC123") so we need to be careful.
        // The format is: gchat:spaces/XXXXX[:encoded_thread][:dm]
        //
        // Split by ":" but space name is "spaces/XXX" which doesn't contain ":"
        $parts = explode(':', $rest);

        if ($parts[0] === '') {
            throw new ValidationError('Invalid Google Chat thread ID: missing space name');
        }

        $spaceName = $parts[0];
        $result = ['spaceName' => $spaceName];

        $isDM = false;
        $remainingParts = array_slice($parts, 1);

        // Check for DM suffix
        if (! empty($remainingParts) && end($remainingParts) === 'dm') {
            $isDM = true;
            array_pop($remainingParts);
        }

        // If there's a remaining part, it's the base64-encoded thread name
        if (! empty($remainingParts)) {
            $encoded = implode(':', $remainingParts);
            $decoded = base64_decode($encoded, true);
            if ($decoded !== false) {
                $result['threadName'] = $decoded;
            }
        }

        if ($isDM) {
            $result['isDM'] = true;
        }

        return $result;
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        $decoded = $this->decodeThreadId($threadId);

        return $decoded['spaceName'] ?? null;
    }

    public function isDM(string $threadId): bool
    {
        return str_ends_with($threadId, ':dm');
    }

    // ── Webhook Handling ─────────────────────────────────────────────

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return new Response('Invalid JSON', 400);
        }

        // Initialize state from chat if available
        $stateAdapter = $chat->getStateAdapter();
        if ($stateAdapter !== null && $this->stateAdapter === null) {
            $this->setState($stateAdapter);
        }

        $eventType = $data['type'] ?? '';

        switch ($eventType) {
            case 'MESSAGE':
                $this->handleMessageEvent($data, $chat);
                break;
            case 'ADDED_TO_SPACE':
                $this->handleAddedToSpace($data, $chat);
                break;
            case 'REMOVED_FROM_SPACE':
                $this->handleRemovedFromSpace($data, $chat);
                break;
            case 'CARD_CLICKED':
                $this->handleCardClicked($data, $chat);
                break;
        }

        return new Response('', 200);
    }

    /** @param array<string, mixed> $event */
    protected function handleMessageEvent(array $event, Chat $chat): void
    {
        $messageData = $event['message'] ?? [];
        $space = $event['space'] ?? [];
        $sender = $messageData['sender'] ?? $event['user'] ?? [];

        // Skip messages from self (our bot)
        $senderName = $sender['name'] ?? '';
        if ($senderName === $this->botUserId()) {
            return;
        }

        // Cache user info from webhook messages
        $this->cacheUserInfoFromSender($sender);

        $message = $this->parseMessage(['chat' => ['messagePayload' => ['space' => $space, 'message' => $messageData]]]);

        // Override threadId with proper encoding
        $spaceName = $space['name'] ?? '';
        $threadName = $messageData['thread']['name'] ?? null;
        $spaceType = $space['type'] ?? '';
        $isDM = $spaceType === 'DM' || ($space['singleUserBotDm'] ?? false);

        $threadIdData = ['spaceName' => $spaceName];
        if ($threadName !== null) {
            $threadIdData['threadName'] = $threadName;
        }
        if ($isDM) {
            $threadIdData['isDM'] = true;
        }

        $threadId = $this->encodeThreadId($threadIdData);

        // Detect mentions
        $isMention = $isDM || $this->hasBotMention($messageData);

        // Rebuild message with correct threadId and metadata
        $message = new Message(
            id: $message->id,
            threadId: $threadId,
            text: $message->text,
            formatted: $message->formatted,
            raw: array_merge($message->raw ?? [], ['space' => $space]),
            author: $message->author,
            metadata: array_merge($message->metadata, ['spaceType' => $spaceType]),
            attachments: $message->attachments,
            isMention: $isMention,
        );

        $chat->dispatchIncomingMessage($this, $threadId, $message);
    }

    /** @param array<string, mixed> $event */
    protected function handleAddedToSpace(array $event, Chat $chat): void
    {
        $space = $event['space'] ?? [];
        $user = $event['user'] ?? [];

        $eventObj = new \ArrayObject([
            'adapter' => $this,
            'type' => 'ADDED_TO_SPACE',
            'space' => $space,
            'user' => $user,
        ], \ArrayObject::ARRAY_AS_PROPS);

        $chat->processSubscribeEvent($eventObj);
    }

    /** @param array<string, mixed> $event */
    protected function handleRemovedFromSpace(array $event, Chat $chat): void
    {
        $space = $event['space'] ?? [];
        $user = $event['user'] ?? [];

        $eventObj = new \ArrayObject([
            'adapter' => $this,
            'type' => 'REMOVED_FROM_SPACE',
            'space' => $space,
            'user' => $user,
        ], \ArrayObject::ARRAY_AS_PROPS);

        $chat->processUnsubscribeEvent($eventObj);
    }

    /** @param array<string, mixed> $event */
    protected function handleCardClicked(array $event, Chat $chat): void
    {
        $action = $event['action'] ?? [];
        $space = $event['space'] ?? [];
        $message = $event['message'] ?? [];
        $user = $event['user'] ?? [];

        $actionMethodName = $action['actionMethodName'] ?? '';
        $parameters = $action['parameters'] ?? [];

        // Build params map
        $paramMap = [];
        foreach ($parameters as $param) {
            $paramMap[$param['key']] = $param['value'];
        }

        // Determine the actionId: check parameters first, then method name
        $actionId = $paramMap['actionId'] ?? $actionMethodName;

        // Build thread ID
        $spaceName = $space['name'] ?? '';
        $threadName = $message['thread']['name'] ?? null;
        $threadIdData = ['spaceName' => $spaceName];
        if ($threadName !== null) {
            $threadIdData['threadName'] = $threadName;
        }
        $threadId = $this->encodeThreadId($threadIdData);

        $userAuthor = new Author(
            userId: $user['name'] ?? 'unknown',
            userName: $user['displayName'] ?? '',
            fullName: $user['displayName'] ?? '',
            isBot: ($user['type'] ?? '') === 'BOT',
            isMe: ($user['name'] ?? '') === $this->botUserId(),
        );

        $chat->dispatchAction($this, [
            'adapter' => $this,
            'actionId' => $actionId,
            'value' => $paramMap['value'] ?? null,
            'user' => $userAuthor,
            'threadId' => $threadId,
            'triggerId' => null,
            'payload' => $event,
        ]);
    }

    /** @param array<string, mixed> $messageData */
    protected function hasBotMention(array $messageData): bool
    {
        $annotations = $messageData['annotations'] ?? [];
        $botUserId = $this->botUserId();

        foreach ($annotations as $annotation) {
            if (($annotation['type'] ?? '') === 'USER_MENTION') {
                $mentionedUser = $annotation['userMention']['user'] ?? [];
                if (($mentionedUser['name'] ?? '') === $botUserId) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $sender */
    protected function cacheUserInfoFromSender(array $sender): void
    {
        $userId = $sender['name'] ?? '';
        $displayName = $sender['displayName'] ?? '';
        $email = $sender['email'] ?? null;

        if ($userId !== '' && $displayName !== '' && strtolower($displayName) !== 'unknown') {
            $this->getUserInfoCache()->set($userId, $displayName, $email);
        }
    }

    // ── Message Parsing ──────────────────────────────────────────────

    public function parseMessage(mixed $raw): Message
    {
        $event = is_array($raw) ? $raw : [];

        // Google Chat direct webhook format wraps in 'chat' -> 'messagePayload'
        $payload = $event['chat']['messagePayload'] ?? $event;
        $messageData = $payload['message'] ?? [];
        $space = $payload['space'] ?? [];

        $sender = $messageData['sender'] ?? [];
        $userId = $sender['name'] ?? 'unknown';
        $displayName = $sender['displayName'] ?? '';
        $senderType = $sender['type'] ?? '';
        $email = $sender['email'] ?? null;
        $isBot = $senderType === 'BOT';
        $isMe = $userId === $this->botUserId();

        // Cache user info from webhook messages
        $this->cacheUserInfoFromSender($sender);

        $text = $messageData['text'] ?? '';
        $messageName = $messageData['name'] ?? '';
        $createTime = $messageData['createTime'] ?? null;

        $spaceName = $space['name'] ?? '';
        $threadName = $messageData['thread']['name'] ?? null;
        $spaceType = $space['type'] ?? '';
        $isDM = $spaceType === 'DM' || ($space['singleUserBotDm'] ?? false);

        $threadIdData = ['spaceName' => $spaceName];
        if ($threadName !== null) {
            $threadIdData['threadName'] = $threadName;
        }
        if ($isDM) {
            $threadIdData['isDM'] = true;
        }

        $threadId = $this->encodeThreadId($threadIdData);

        $normalizedText = $this->getFormatConverter()->toMarkdown($text);

        $metadata = [];
        if ($createTime) {
            $metadata['dateSent'] = $createTime;
        }
        $metadata['spaceType'] = $spaceType;

        return new Message(
            id: $messageName,
            threadId: $threadId,
            text: $normalizedText,
            formatted: $text,
            raw: array_merge($messageData, ['space' => $space]),
            author: new Author(
                userId: $userId,
                userName: $displayName ?: $userId,
                fullName: $displayName ?: $userId,
                isBot: $isBot,
                isMe: $isMe,
            ),
            metadata: $metadata,
            attachments: [],
            isMention: false,
        );
    }

    /**
     * Parse a Pub/Sub push notification into a Message.
     *
     * @param  array<string, mixed>  $notification
     */
    public function parsePubSubMessage(array $notification, string $fallbackThreadId): Message
    {
        $messageData = $notification['message'] ?? [];
        $sender = $messageData['sender'] ?? [];
        $userId = $sender['name'] ?? 'unknown';
        $providedName = $sender['displayName'] ?? null;
        $senderType = $sender['type'] ?? '';
        $isBot = $senderType === 'BOT';
        $isMe = $userId === $this->botUserId();

        $text = $messageData['text'] ?? '';
        $messageName = $messageData['name'] ?? '';
        $createTime = $messageData['createTime'] ?? null;

        // Resolve display name using cache
        $displayName = $this->getUserInfoCache()->resolveDisplayName(
            $userId,
            $providedName,
            $this->botUserId() ?? '',
            $this->userName(),
        );

        // Cache the display name if it was provided and valid
        if ($providedName !== null && $providedName !== '' && strtolower($providedName) !== 'unknown') {
            $this->getUserInfoCache()->set($userId, $providedName, $sender['email'] ?? null);
        }

        $normalizedText = $this->getFormatConverter()->toMarkdown($text);

        $metadata = [];
        if ($createTime) {
            $metadata['dateSent'] = $createTime;
        }

        return new Message(
            id: $messageName,
            threadId: $fallbackThreadId,
            text: $normalizedText,
            formatted: $text,
            raw: $messageData,
            author: new Author(
                userId: $userId,
                userName: $displayName,
                fullName: $displayName,
                isBot: $isBot,
                isMe: $isMe,
            ),
            metadata: $metadata,
            attachments: [],
            isMention: false,
        );
    }

    // ── Format Rendering ─────────────────────────────────────────────

    public function renderFormatted(string $markdown): string
    {
        return $this->getFormatConverter()->fromMarkdown($markdown);
    }

    // ── API Methods ──────────────────────────────────────────────────

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
        // Google Chat doesn't support typing indicators
    }

    public function openDM(string $userId): ?string
    {
        throw new NotImplementedError('Method not implemented');
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        throw new NotImplementedError('Method not implemented');
    }

    /** @return array<string, mixed>|null */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        throw new NotImplementedError('Method not implemented');
    }

    /**
     * @param  iterable<string>  $textStream
     * @param  array<string, mixed>  $options
     */
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

    public function onThreadSubscribe(string $threadId): void
    {
        //
    }
}
