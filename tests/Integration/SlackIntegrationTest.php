<?php

namespace OpenCompany\Chatogrator\Tests\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * End-to-end integration tests for Slack webhook handling.
 *
 * Creates a real Chat instance with the Slack adapter + MockStateAdapter,
 * simulates full webhook request lifecycle, and asserts on handler invocation
 * and response status codes.
 *
 * These tests WILL FAIL initially -- they define target behavior for the
 * Slack adapter implementation.
 *
 * @group integration
 * @group slack
 */
class SlackIntegrationTest extends TestCase
{
    protected SlackAdapter $adapter;

    protected Chat $chat;

    protected MockStateAdapter $state;

    protected const BOT_TOKEN = 'xoxb-test-bot-token';

    protected const SIGNING_SECRET = 'test-signing-secret-slack';

    protected const BOT_USER_ID = 'U_BOT_SLACK';

    protected const BOT_NAME = 'testbot';

    protected const TEST_CHANNEL = 'C123456';

    protected const TEST_THREAD_TS = '1234567890.000001';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->state = new MockStateAdapter;

        $this->adapter = SlackAdapter::fromConfig([
            'bot_token' => self::BOT_TOKEN,
            'signing_secret' => self::SIGNING_SECRET,
            'bot_user_id' => self::BOT_USER_ID,
            'user_name' => self::BOT_NAME,
        ]);

        $this->chat = Chat::make('test-slack')
            ->adapter('slack', $this->adapter)
            ->state($this->state);
    }

    // ========================================================================
    // URL Verification / Challenge
    // ========================================================================

    public function test_responds_to_url_verification_challenge(): void
    {
        $request = $this->makeSignedRequest(json_encode([
            'type' => 'url_verification',
            'challenge' => 'test-challenge-token',
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('test-challenge-token', $body['challenge']);
    }

    public function test_challenge_response_echoes_arbitrary_token(): void
    {
        $token = 'abc-' . uniqid();

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'url_verification',
            'challenge' => $token,
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $body = json_decode($response->getContent(), true);
        $this->assertSame($token, $body['challenge']);
    }

    // ========================================================================
    // Signature Verification
    // ========================================================================

    public function test_rejects_request_with_invalid_signature(): void
    {
        $body = json_encode(['type' => 'event_callback', 'event' => []]);

        $request = $this->makeRequest($body, [
            'X-Slack-Request-Timestamp' => (string) time(),
            'X-Slack-Signature' => 'v0=invalid_signature',
            'Content-Type' => 'application/json',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_missing_signature_headers(): void
    {
        $body = json_encode(['type' => 'event_callback', 'event' => []]);

        $request = $this->makeRequest($body, [
            'Content-Type' => 'application/json',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_stale_timestamp(): void
    {
        $staleTimestamp = time() - 600; // 10 minutes old

        $request = $this->makeSignedRequest(
            json_encode(['type' => 'event_callback', 'event' => []]),
            $staleTimestamp,
        );

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================================================
    // Message Handling -- @mentions
    // ========================================================================

    public function test_mention_event_triggers_on_new_mention_handler(): void
    {
        $handlerCalled = false;
        $capturedThreadId = null;
        $capturedText = null;

        // Register handler -- NOTE: Chat::onNewMention is target API
        // that the Chat class should expose.
        $this->chat->onNewMention(function ($thread, Message $message) use (&$handlerCalled, &$capturedThreadId, &$capturedText) {
            $handlerCalled = true;
            $capturedThreadId = $thread->id;
            $capturedText = $message->text;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'text' => '<@' . self::BOT_USER_ID . '> hello bot!',
                'user' => 'U_USER_123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.111111',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onNewMention handler should have been called');
        $this->assertSame('slack:' . self::TEST_CHANNEL . ':' . self::TEST_THREAD_TS, $capturedThreadId);
        $this->assertStringContainsString('hello bot!', $capturedText);
    }

    public function test_message_from_bot_self_is_ignored(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMention(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Bot own message',
                'user' => self::BOT_USER_ID,
                'bot_id' => 'B123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.111111',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'Handler should not be called for bot self messages');
    }

    // ========================================================================
    // DM Handling
    // ========================================================================

    public function test_dm_message_triggers_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMention(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'channel_type' => 'im',
                'text' => 'Hello in DM',
                'user' => 'U_USER_456',
                'channel' => 'D999888',
                'ts' => '1234567890.222222',
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        // DMs should be treated as mentions since the bot is directly addressed
        $this->assertTrue($handlerCalled, 'DM messages should trigger mention handler');
    }

    // ========================================================================
    // Subscribed Thread Handling
    // ========================================================================

    public function test_subscribed_thread_message_triggers_on_subscribed_handler(): void
    {
        $subscribedHandlerCalled = false;
        $capturedText = null;

        // Pre-subscribe the thread in state
        $threadId = 'slack:' . self::TEST_CHANNEL . ':' . self::TEST_THREAD_TS;
        $this->state->subscribe($threadId);

        $this->chat->onSubscribedMessage(function ($thread, Message $message) use (&$subscribedHandlerCalled, &$capturedText) {
            $subscribedHandlerCalled = true;
            $capturedText = $message->text;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Follow-up message in subscribed thread',
                'user' => 'U_USER_123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.333333',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($subscribedHandlerCalled, 'onSubscribedMessage handler should be called for subscribed threads');
        $this->assertSame('Follow-up message in subscribed thread', $capturedText);
    }

    // ========================================================================
    // Pattern Matching
    // ========================================================================

    public function test_message_matching_pattern_triggers_on_new_message_handler(): void
    {
        $handlerCalled = false;
        $capturedText = null;

        $this->chat->onNewMessage('/help/i', function ($thread, Message $message) use (&$handlerCalled, &$capturedText) {
            $handlerCalled = true;
            $capturedText = $message->text;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'I need help with something',
                'user' => 'U_USER_123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.444444',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'Pattern-matched handler should be called');
        $this->assertSame('I need help with something', $capturedText);
    }

    public function test_non_matching_pattern_does_not_trigger_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMessage('/help/i', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Just a regular message',
                'user' => 'U_USER_123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.555555',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'Non-matching pattern should not trigger handler');
    }

    // ========================================================================
    // Slash Command Handling
    // ========================================================================

    public function test_slash_command_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedCommand = null;

        $this->chat->onSlashCommand('/help', function ($event) use (&$handlerCalled, &$capturedCommand) {
            $handlerCalled = true;
            $capturedCommand = $event->command;
        });

        $body = http_build_query([
            'command' => '/help',
            'text' => 'topic search',
            'user_id' => 'U_USER_123',
            'user_name' => 'testuser',
            'channel_id' => self::TEST_CHANNEL,
            'trigger_id' => 'trigger-123',
            'response_url' => 'https://hooks.slack.com/commands/response',
        ]);

        $request = $this->makeSignedRequest($body, null, 'application/x-www-form-urlencoded');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'Slash command handler should be called');
        $this->assertSame('/help', $capturedCommand);
    }

    // ========================================================================
    // Action / Block Actions Handling
    // ========================================================================

    public function test_block_action_button_click_triggers_on_action_handler(): void
    {
        $handlerCalled = false;
        $capturedActionId = null;
        $capturedValue = null;

        $this->chat->onAction('approve', function ($event) use (&$handlerCalled, &$capturedActionId, &$capturedValue) {
            $handlerCalled = true;
            $capturedActionId = $event->actionId;
            $capturedValue = $event->value;
        });

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER_123', 'username' => 'testuser'],
            'trigger_id' => 'trigger-456',
            'channel' => ['id' => self::TEST_CHANNEL],
            'message' => [
                'ts' => self::TEST_THREAD_TS,
                'thread_ts' => self::TEST_THREAD_TS,
            ],
            'actions' => [
                [
                    'action_id' => 'approve',
                    'value' => 'order-123',
                    'type' => 'button',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, null, 'application/x-www-form-urlencoded');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onAction handler should be called for block_actions');
        $this->assertSame('approve', $capturedActionId);
        $this->assertSame('order-123', $capturedValue);
    }

    public function test_non_matching_action_id_does_not_trigger_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onAction('approve', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER_123', 'username' => 'testuser'],
            'trigger_id' => 'trigger-789',
            'channel' => ['id' => self::TEST_CHANNEL],
            'message' => [
                'ts' => self::TEST_THREAD_TS,
                'thread_ts' => self::TEST_THREAD_TS,
            ],
            'actions' => [
                [
                    'action_id' => 'reject',
                    'type' => 'button',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, null, 'application/x-www-form-urlencoded');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'Handler should not fire for non-matching action ID');
    }

    public function test_catch_all_action_handler_receives_any_action(): void
    {
        $capturedActionId = null;

        $this->chat->onAction(function ($event) use (&$capturedActionId) {
            $capturedActionId = $event->actionId;
        });

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER_123', 'username' => 'testuser'],
            'trigger_id' => 'trigger-abc',
            'channel' => ['id' => self::TEST_CHANNEL],
            'message' => [
                'ts' => self::TEST_THREAD_TS,
                'thread_ts' => self::TEST_THREAD_TS,
            ],
            'actions' => [
                [
                    'action_id' => 'any-custom-action',
                    'type' => 'button',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, null, 'application/x-www-form-urlencoded');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('any-custom-action', $capturedActionId);
    }

    // ========================================================================
    // Modal Submit Handling
    // ========================================================================

    public function test_modal_submit_triggers_on_modal_submit_handler(): void
    {
        $handlerCalled = false;
        $capturedViewId = null;

        $this->chat->onModalSubmit(function ($event) use (&$handlerCalled, &$capturedViewId) {
            $handlerCalled = true;
            $capturedViewId = $event->viewId;
        });

        $payload = json_encode([
            'type' => 'view_submission',
            'trigger_id' => 'trigger-modal',
            'user' => ['id' => 'U_USER_123', 'username' => 'testuser'],
            'view' => [
                'id' => 'V_VIEW_123',
                'callback_id' => 'feedback_form',
                'type' => 'modal',
                'title' => ['text' => 'Feedback'],
                'state' => [
                    'values' => [
                        'input_block' => [
                            'feedback_input' => [
                                'type' => 'plain_text_input',
                                'value' => 'Great product!',
                            ],
                        ],
                    ],
                ],
                'private_metadata' => json_encode(['threadId' => 'slack:C123:1234.5678']),
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, null, 'application/x-www-form-urlencoded');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onModalSubmit handler should be called');
        $this->assertSame('V_VIEW_123', $capturedViewId);
    }

    // ========================================================================
    // Reaction Events
    // ========================================================================

    public function test_reaction_added_event_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedEmoji = null;

        $this->chat->onReactionAdded(function ($event) use (&$handlerCalled, &$capturedEmoji) {
            $handlerCalled = true;
            $capturedEmoji = $event->emoji;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_added',
                'user' => 'U_USER_123',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => self::TEST_CHANNEL,
                    'ts' => '1234567890.111111',
                ],
                'item_user' => 'U_OTHER_USER',
                'event_ts' => '1234567891.000000',
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onReactionAdded handler should be called');
        $this->assertSame('thumbsup', $capturedEmoji);
    }

    public function test_reaction_removed_event_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedEmoji = null;

        $this->chat->onReactionRemoved(function ($event) use (&$handlerCalled, &$capturedEmoji) {
            $handlerCalled = true;
            $capturedEmoji = $event->emoji;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_removed',
                'user' => 'U_USER_123',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => self::TEST_CHANNEL,
                    'ts' => '1234567890.111111',
                ],
                'item_user' => 'U_OTHER_USER',
                'event_ts' => '1234567891.000000',
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onReactionRemoved handler should be called');
        $this->assertSame('thumbsup', $capturedEmoji);
    }

    // ========================================================================
    // Thread Reply Handling
    // ========================================================================

    public function test_thread_reply_includes_correct_thread_id(): void
    {
        $capturedThreadId = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedThreadId) {
            $capturedThreadId = $thread->id ?? $message->threadId;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'text' => '<@' . self::BOT_USER_ID . '> reply in thread',
                'user' => 'U_USER_123',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.999999',
                'thread_ts' => '1234567890.000100',
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('slack:' . self::TEST_CHANNEL . ':1234567890.000100', $capturedThreadId);
    }

    // ========================================================================
    // Message Edit / Delete Events
    // ========================================================================

    public function test_message_changed_event_triggers_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onMessageEdited(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'subtype' => 'message_changed',
                'channel' => self::TEST_CHANNEL,
                'message' => [
                    'text' => 'Updated text',
                    'user' => 'U_USER_123',
                    'ts' => '1234567890.111111',
                    'thread_ts' => self::TEST_THREAD_TS,
                    'edited' => [
                        'user' => 'U_USER_123',
                        'ts' => '1234567891.000000',
                    ],
                ],
                'previous_message' => [
                    'text' => 'Original text',
                    'user' => 'U_USER_123',
                    'ts' => '1234567890.111111',
                ],
                'event_ts' => '1234567891.000001',
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onMessageEdited handler should be called');
    }

    public function test_message_deleted_event_triggers_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onMessageDeleted(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'subtype' => 'message_deleted',
                'channel' => self::TEST_CHANNEL,
                'deleted_ts' => '1234567890.111111',
                'event_ts' => '1234567891.000001',
                'previous_message' => [
                    'text' => 'Deleted message',
                    'user' => 'U_USER_123',
                    'ts' => '1234567890.111111',
                ],
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onMessageDeleted handler should be called');
    }

    // ========================================================================
    // Message Author Parsing
    // ========================================================================

    public function test_message_includes_correct_author_info(): void
    {
        $capturedMessage = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $request = $this->makeSignedRequest(json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'text' => '<@' . self::BOT_USER_ID . '> test',
                'user' => 'U_USER_789',
                'channel' => self::TEST_CHANNEL,
                'ts' => '1234567890.111111',
                'thread_ts' => self::TEST_THREAD_TS,
            ],
        ]));

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedMessage);
        $this->assertSame('U_USER_789', $capturedMessage->author->userId);
        $this->assertFalse($capturedMessage->author->isBot);
        $this->assertFalse($capturedMessage->author->isMe);
    }

    // ========================================================================
    // Invalid JSON
    // ========================================================================

    public function test_returns_400_for_invalid_json_body(): void
    {
        $body = 'not valid json';
        $request = $this->makeSignedRequest($body);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeRequest(string $body, array $headers = []): Request
    {
        $request = Request::create(
            uri: '/webhooks/chat/slack',
            method: 'POST',
            content: $body,
        );

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $request;
    }

    private function makeSignedRequest(
        string $body,
        ?int $timestamp = null,
        string $contentType = 'application/json',
    ): Request {
        $timestamp = $timestamp ?? time();
        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, self::SIGNING_SECRET);

        $request = Request::create(
            uri: '/webhooks/chat/slack',
            method: 'POST',
            content: $body,
        );

        $request->headers->set('X-Slack-Request-Timestamp', (string) $timestamp);
        $request->headers->set('X-Slack-Signature', $signature);
        $request->headers->set('Content-Type', $contentType);

        return $request;
    }
}
