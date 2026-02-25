<?php

namespace OpenCompany\Chatogrator\Tests\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatAdapter;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * End-to-end integration tests for Google Chat webhook handling.
 *
 * Creates a real Chat instance with the GoogleChat adapter + MockStateAdapter,
 * simulates full Google Chat event webhook lifecycle, and asserts on handler
 * invocation and response status codes.
 *
 * These tests WILL FAIL initially -- they define target behavior for the
 * Google Chat adapter implementation.
 *
 * @group integration
 * @group gchat
 */
class GoogleChatIntegrationTest extends TestCase
{
    protected GoogleChatAdapter $adapter;

    protected Chat $chat;

    protected MockStateAdapter $state;

    protected const BOT_NAME = 'TestBot';

    protected const BOT_USER_ID = 'users/bot-user-123';

    protected const TEST_SPACE_NAME = 'spaces/AAAA_BBBB';

    protected const TEST_THREAD_NAME = 'spaces/AAAA_BBBB/threads/CCCC_DDDD';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->state = new MockStateAdapter;

        $this->adapter = GoogleChatAdapter::fromConfig([
            'credentials' => [
                'type' => 'service_account',
                'project_id' => 'test-project',
                'private_key_id' => 'key-123',
                'private_key' => 'test-private-key',
                'client_email' => 'bot@test-project.iam.gserviceaccount.com',
                'client_id' => '123456789',
            ],
            'user_name' => self::BOT_NAME,
            'bot_user_id' => self::BOT_USER_ID,
        ]);

        $this->chat = Chat::make('test-gchat')
            ->adapter('gchat', $this->adapter)
            ->state($this->state);
    }

    // ========================================================================
    // MESSAGE Event -- @mentions
    // ========================================================================

    public function test_message_event_with_mention_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedThreadId = null;
        $capturedText = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$handlerCalled, &$capturedThreadId, &$capturedText) {
            $handlerCalled = true;
            $capturedThreadId = $thread->id;
            $capturedText = $message->text;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
                'displayName' => 'Test Room',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/msg-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => '@' . self::BOT_NAME . ' hello bot!',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'annotations' => [
                    [
                        'type' => 'USER_MENTION',
                        'userMention' => [
                            'user' => [
                                'name' => self::BOT_USER_ID,
                                'displayName' => self::BOT_NAME,
                                'type' => 'BOT',
                            ],
                            'type' => 'MENTION',
                        ],
                    ],
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onNewMention handler should be called');
        $this->assertStringContainsString('hello bot!', $capturedText);
    }

    public function test_message_from_self_bot_is_ignored(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMention(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/msg-bot-001',
                'sender' => [
                    'name' => self::BOT_USER_ID,
                    'displayName' => self::BOT_NAME,
                    'type' => 'BOT',
                ],
                'text' => 'Bot own message',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => self::BOT_USER_ID,
                'displayName' => self::BOT_NAME,
                'type' => 'BOT',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'Handler should not fire for bot self messages');
    }

    public function test_message_from_other_bot_is_processed(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMessage('/./', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/msg-other-bot',
                'sender' => [
                    'name' => 'users/other-bot-456',
                    'displayName' => 'Other Bot',
                    'type' => 'BOT',
                ],
                'text' => 'Message from another bot',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/other-bot-456',
                'displayName' => 'Other Bot',
                'type' => 'BOT',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'Messages from other bots should be processed');
    }

    // ========================================================================
    // ADDED_TO_SPACE Event
    // ========================================================================

    public function test_added_to_space_triggers_on_subscribe_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onSubscribe(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeEventRequest([
            'type' => 'ADDED_TO_SPACE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
                'displayName' => 'Test Room',
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onSubscribe handler should fire on ADDED_TO_SPACE');
    }

    // ========================================================================
    // REMOVED_FROM_SPACE Event
    // ========================================================================

    public function test_removed_from_space_triggers_on_unsubscribe_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onUnsubscribe(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeEventRequest([
            'type' => 'REMOVED_FROM_SPACE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
                'displayName' => 'Test Room',
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onUnsubscribe handler should fire on REMOVED_FROM_SPACE');
    }

    // ========================================================================
    // Card Click Action
    // ========================================================================

    public function test_card_click_action_triggers_on_action_handler(): void
    {
        $handlerCalled = false;
        $capturedActionId = null;

        $this->chat->onAction('approve_request', function ($event) use (&$handlerCalled, &$capturedActionId) {
            $handlerCalled = true;
            $capturedActionId = $event->actionId;
        });

        $request = $this->makeEventRequest([
            'type' => 'CARD_CLICKED',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/msg-card-001',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
            ],
            'action' => [
                'actionMethodName' => 'approve_request',
                'parameters' => [
                    [
                        'key' => 'requestId',
                        'value' => 'req-456',
                    ],
                ],
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onAction handler should fire on CARD_CLICKED');
        $this->assertSame('approve_request', $capturedActionId);
    }

    // ========================================================================
    // DM Message Handling
    // ========================================================================

    public function test_dm_message_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedText = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$handlerCalled, &$capturedText) {
            $handlerCalled = true;
            $capturedText = $message->text;
        });

        $dmSpaceName = 'spaces/DM_SPACE_999';
        $dmThreadName = 'spaces/DM_SPACE_999/threads/DM_THREAD_111';

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => $dmSpaceName,
                'type' => 'DM',
                'singleUserBotDm' => true,
            ],
            'message' => [
                'name' => $dmSpaceName . '/messages/dm-msg-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => 'Hello in DM',
                'thread' => [
                    'name' => $dmThreadName,
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'DM messages should trigger mention handler');
        $this->assertSame('Hello in DM', $capturedText);
    }

    // ========================================================================
    // Thread Reply Handling
    // ========================================================================

    public function test_thread_reply_includes_correct_thread_id(): void
    {
        $capturedThreadId = null;

        $threadName = 'spaces/AAAA_BBBB/threads/THREAD_REPLY_TEST';

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedThreadId) {
            $capturedThreadId = $thread->id ?? $message->threadId;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/reply-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => '@' . self::BOT_NAME . ' reply in thread',
                'thread' => [
                    'name' => $threadName,
                ],
                'annotations' => [
                    [
                        'type' => 'USER_MENTION',
                        'userMention' => [
                            'user' => [
                                'name' => self::BOT_USER_ID,
                                'displayName' => self::BOT_NAME,
                                'type' => 'BOT',
                            ],
                            'type' => 'MENTION',
                        ],
                    ],
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedThreadId);
        // Thread ID should encode the space and thread name
        $threadNameBase64 = base64_encode($threadName);
        $this->assertSame("gchat:" . self::TEST_SPACE_NAME . ":{$threadNameBase64}", $capturedThreadId);
    }

    // ========================================================================
    // Subscribed Thread Messages
    // ========================================================================

    public function test_subscribed_thread_message_triggers_handler(): void
    {
        $subscribedHandlerCalled = false;
        $capturedText = null;

        // Pre-subscribe the thread
        $threadNameBase64 = base64_encode(self::TEST_THREAD_NAME);
        $threadId = "gchat:" . self::TEST_SPACE_NAME . ":{$threadNameBase64}";
        $this->state->subscribe($threadId);

        $this->chat->onSubscribedMessage(function ($thread, Message $message) use (&$subscribedHandlerCalled, &$capturedText) {
            $subscribedHandlerCalled = true;
            $capturedText = $message->text;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/sub-msg-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => 'Follow-up in subscribed thread',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($subscribedHandlerCalled, 'Subscribed message handler should be called');
        $this->assertSame('Follow-up in subscribed thread', $capturedText);
    }

    // ========================================================================
    // Space Type Detection
    // ========================================================================

    public function test_detects_dm_space_type(): void
    {
        $capturedSpaceType = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedSpaceType) {
            // The adapter should expose space type information through
            // message metadata or the raw payload.
            $capturedSpaceType = $message->metadata['spaceType'] ?? $message->raw['space']['type'] ?? null;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => 'spaces/DM_DETECT',
                'type' => 'DM',
                'singleUserBotDm' => true,
            ],
            'message' => [
                'name' => 'spaces/DM_DETECT/messages/detect-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => '@' . self::BOT_NAME . ' dm detection test',
                'thread' => [
                    'name' => 'spaces/DM_DETECT/threads/detect-thread',
                ],
                'annotations' => [
                    [
                        'type' => 'USER_MENTION',
                        'userMention' => [
                            'user' => [
                                'name' => self::BOT_USER_ID,
                                'type' => 'BOT',
                            ],
                            'type' => 'MENTION',
                        ],
                    ],
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('DM', $capturedSpaceType);
    }

    public function test_detects_room_space_type(): void
    {
        $capturedSpaceType = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedSpaceType) {
            $capturedSpaceType = $message->metadata['spaceType'] ?? $message->raw['space']['type'] ?? null;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
                'displayName' => 'Test Room',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/room-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => '@' . self::BOT_NAME . ' room detection test',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'annotations' => [
                    [
                        'type' => 'USER_MENTION',
                        'userMention' => [
                            'user' => [
                                'name' => self::BOT_USER_ID,
                                'type' => 'BOT',
                            ],
                            'type' => 'MENTION',
                        ],
                    ],
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ROOM', $capturedSpaceType);
    }

    // ========================================================================
    // Message Author Info
    // ========================================================================

    public function test_message_includes_correct_author_info(): void
    {
        $capturedMessage = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $request = $this->makeEventRequest([
            'type' => 'MESSAGE',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'message' => [
                'name' => self::TEST_SPACE_NAME . '/messages/author-001',
                'sender' => [
                    'name' => 'users/user-123',
                    'displayName' => 'John Doe',
                    'type' => 'HUMAN',
                ],
                'text' => '@' . self::BOT_NAME . ' test author',
                'thread' => [
                    'name' => self::TEST_THREAD_NAME,
                ],
                'annotations' => [
                    [
                        'type' => 'USER_MENTION',
                        'userMention' => [
                            'user' => [
                                'name' => self::BOT_USER_ID,
                                'type' => 'BOT',
                            ],
                            'type' => 'MENTION',
                        ],
                    ],
                ],
                'createTime' => now()->toISOString(),
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedMessage);
        $this->assertSame('users/user-123', $capturedMessage->author->userId);
        $this->assertSame('John Doe', $capturedMessage->author->userName);
        $this->assertFalse($capturedMessage->author->isBot);
    }

    // ========================================================================
    // Non-message Events Handled Gracefully
    // ========================================================================

    public function test_non_message_event_returns_200(): void
    {
        $request = $this->makeEventRequest([
            'type' => 'WIDGET_UPDATED',
            'eventTime' => now()->toISOString(),
            'space' => [
                'name' => self::TEST_SPACE_NAME,
                'type' => 'ROOM',
            ],
            'user' => [
                'name' => 'users/user-123',
                'displayName' => 'John Doe',
                'type' => 'HUMAN',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Invalid JSON
    // ========================================================================

    public function test_returns_400_for_invalid_json(): void
    {
        $request = Request::create(
            uri: '/webhooks/chat/gchat',
            method: 'POST',
            content: 'not valid json',
        );

        $request->headers->set('Content-Type', 'application/json');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeEventRequest(array $event): Request
    {
        $body = json_encode($event);

        $request = Request::create(
            uri: '/webhooks/chat/gchat',
            method: 'POST',
            content: $body,
        );

        $request->headers->set('Content-Type', 'application/json');

        // Google Chat webhooks can optionally carry a bearer token that the
        // adapter validates. For testing, we provide a marker token.
        $request->headers->set('Authorization', 'Bearer test-gchat-token');

        return $request;
    }
}
