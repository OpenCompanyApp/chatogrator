<?php

namespace OpenCompany\Chatogrator\Tests\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Adapters\Teams\TeamsAdapter;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * End-to-end integration tests for Microsoft Teams Bot Framework webhook handling.
 *
 * Creates a real Chat instance with the Teams adapter + MockStateAdapter,
 * simulates full Bot Framework activity webhook lifecycle, and asserts on
 * handler invocation and response status codes.
 *
 * These tests WILL FAIL initially -- they define target behavior for the
 * Teams adapter implementation.
 *
 * @group integration
 * @group teams
 */
class TeamsIntegrationTest extends TestCase
{
    protected TeamsAdapter $adapter;

    protected Chat $chat;

    protected MockStateAdapter $state;

    protected const APP_ID = 'teams-app-id-123';

    protected const APP_PASSWORD = 'teams-app-password-secret';

    protected const BOT_ID = '28:teams-bot-id';

    protected const BOT_NAME = 'TestBot';

    protected const TEST_CONVERSATION_ID = '19:meeting_abc123@thread.v2';

    protected const SERVICE_URL = 'https://smba.trafficmanager.net/teams/';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->state = new MockStateAdapter;

        $this->adapter = TeamsAdapter::fromConfig([
            'app_id' => self::APP_ID,
            'app_password' => self::APP_PASSWORD,
            'bot_id' => self::BOT_ID,
            'user_name' => self::BOT_NAME,
        ]);

        $this->chat = Chat::make('test-teams')
            ->adapter('teams', $this->adapter)
            ->state($this->state);
    }

    // ========================================================================
    // Message Activity Handling
    // ========================================================================

    public function test_message_activity_triggers_mention_handler(): void
    {
        $handlerCalled = false;
        $capturedThreadId = null;
        $capturedText = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$handlerCalled, &$capturedThreadId, &$capturedText) {
            $handlerCalled = true;
            $capturedThreadId = $thread->id;
            $capturedText = $message->text;
        });

        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
                'aadObjectId' => 'aad-user-123',
            ],
            'recipient' => [
                'id' => self::BOT_ID,
                'name' => self::BOT_NAME,
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
                'conversationType' => 'channel',
                'tenantId' => 'tenant-123',
            ],
            'text' => '<at>' . self::BOT_NAME . '</at> hello bot!',
            'entities' => [
                [
                    'type' => 'mention',
                    'mentioned' => [
                        'id' => self::BOT_ID,
                        'name' => self::BOT_NAME,
                    ],
                    'text' => '<at>' . self::BOT_NAME . '</at>',
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onNewMention handler should be called');
        $this->assertStringContainsString('hello bot!', $capturedText);
    }

    public function test_message_from_bot_self_is_ignored(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMention(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-bot-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => self::BOT_ID,
                'name' => self::BOT_NAME,
            ],
            'recipient' => [
                'id' => self::BOT_ID,
                'name' => self::BOT_NAME,
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
                'conversationType' => 'channel',
            ],
            'text' => 'Bot own message',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'Handler should not fire for bot self messages');
    }

    // ========================================================================
    // Token / Auth Validation
    // ========================================================================

    public function test_rejects_request_with_invalid_auth_token(): void
    {
        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-unauth',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'text' => 'Should be rejected',
        ], 'Bearer invalid-jwt-token-here');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================================================
    // Subscribed Thread Messages
    // ========================================================================

    public function test_subscribed_thread_message_triggers_handler(): void
    {
        $subscribedHandlerCalled = false;
        $capturedText = null;

        // Pre-subscribe the thread
        $conversationBase64 = base64_encode(self::TEST_CONVERSATION_ID);
        $serviceBase64 = base64_encode(self::SERVICE_URL);
        $threadId = "teams:{$conversationBase64}:{$serviceBase64}";
        $this->state->subscribe($threadId);

        $this->chat->onSubscribedMessage(function ($thread, Message $message) use (&$subscribedHandlerCalled, &$capturedText) {
            $subscribedHandlerCalled = true;
            $capturedText = $message->text;
        });

        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-sub-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
                'conversationType' => 'channel',
            ],
            'text' => 'Follow-up in subscribed thread',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($subscribedHandlerCalled, 'Subscribed message handler should be called');
        $this->assertSame('Follow-up in subscribed thread', $capturedText);
    }

    // ========================================================================
    // Conversation Update Events
    // ========================================================================

    public function test_conversation_update_does_not_trigger_message_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onNewMention(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeActivityRequest([
            'type' => 'conversationUpdate',
            'id' => 'conv-update-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'membersAdded' => [
                [
                    'id' => self::BOT_ID,
                    'name' => self::BOT_NAME,
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($handlerCalled, 'conversationUpdate should not trigger message handler');
    }

    public function test_conversation_update_members_added_triggers_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onSubscribe(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeActivityRequest([
            'type' => 'conversationUpdate',
            'id' => 'conv-update-002',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'membersAdded' => [
                [
                    'id' => self::BOT_ID,
                    'name' => self::BOT_NAME,
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onSubscribe handler should fire when bot is added');
    }

    // ========================================================================
    // Pattern Matching
    // ========================================================================

    public function test_message_matching_pattern_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedText = null;

        $this->chat->onNewMessage('/help/i', function ($thread, Message $message) use (&$handlerCalled, &$capturedText) {
            $handlerCalled = true;
            $capturedText = $message->text;
        });

        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-pattern-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'text' => 'I need help with something',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'Pattern handler should be called');
        $this->assertSame('I need help with something', $capturedText);
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

        $request = $this->makeActivityRequest([
            'type' => 'message',
            'id' => 'msg-author-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'recipient' => [
                'id' => self::BOT_ID,
                'name' => self::BOT_NAME,
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'text' => '<at>' . self::BOT_NAME . '</at> test',
            'entities' => [
                [
                    'type' => 'mention',
                    'mentioned' => [
                        'id' => self::BOT_ID,
                        'name' => self::BOT_NAME,
                    ],
                    'text' => '<at>' . self::BOT_NAME . '</at>',
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedMessage);
        $this->assertSame('user-123', $capturedMessage->author->userId);
        $this->assertSame('John Doe', $capturedMessage->author->userName);
        $this->assertFalse($capturedMessage->author->isBot);
    }

    // ========================================================================
    // Adaptive Card Action
    // ========================================================================

    public function test_adaptive_card_action_triggers_on_action_handler(): void
    {
        $handlerCalled = false;
        $capturedActionId = null;

        $this->chat->onAction('submit_feedback', function ($event) use (&$handlerCalled, &$capturedActionId) {
            $handlerCalled = true;
            $capturedActionId = $event->actionId;
        });

        $request = $this->makeActivityRequest([
            'type' => 'invoke',
            'name' => 'adaptiveCard/action',
            'id' => 'invoke-card-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'value' => [
                'action' => [
                    'type' => 'Action.Execute',
                    'verb' => 'submit_feedback',
                    'data' => [
                        'rating' => 5,
                        'comment' => 'Excellent!',
                    ],
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onAction handler should fire for adaptive card action');
        $this->assertSame('submit_feedback', $capturedActionId);
    }

    // ========================================================================
    // Message Reaction Events
    // ========================================================================

    public function test_message_reaction_event_triggers_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onReactionAdded(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeActivityRequest([
            'type' => 'messageReaction',
            'id' => 'reaction-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'reactionsAdded' => [
                [
                    'type' => 'like',
                ],
            ],
            'replyToId' => 'msg-001',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onReactionAdded handler should fire for Teams reaction');
    }

    // ========================================================================
    // Proactive Messaging (invoke activity)
    // ========================================================================

    public function test_invoke_activity_returns_200(): void
    {
        $request = $this->makeActivityRequest([
            'type' => 'invoke',
            'name' => 'composeExtension/query',
            'id' => 'invoke-compose-001',
            'timestamp' => now()->toISOString(),
            'serviceUrl' => self::SERVICE_URL,
            'channelId' => 'msteams',
            'from' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
            'conversation' => [
                'id' => self::TEST_CONVERSATION_ID,
            ],
            'value' => [
                'queryText' => 'search term',
                'commandId' => 'searchCmd',
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Invalid JSON Body
    // ========================================================================

    public function test_returns_400_for_invalid_json_body(): void
    {
        $request = Request::create(
            uri: '/webhooks/chat/teams',
            method: 'POST',
            content: 'not valid json',
        );

        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Authorization', 'Bearer test-valid-jwt-token');

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /**
     * Create a Bot Framework activity request.
     * In production, these would carry a JWT Bearer token in the Authorization header
     * that the adapter validates against Microsoft's OpenID metadata.
     */
    private function makeActivityRequest(array $activity, ?string $authHeader = null): Request
    {
        $body = json_encode($activity);

        $request = Request::create(
            uri: '/webhooks/chat/teams',
            method: 'POST',
            content: $body,
        );

        $request->headers->set('Content-Type', 'application/json');

        // In production the adapter validates the JWT in the Authorization header.
        // For integration tests, we pass a marker that the adapter can recognize
        // as a test token or we skip validation in test mode.
        if ($authHeader !== null) {
            $request->headers->set('Authorization', $authHeader);
        } else {
            $request->headers->set('Authorization', 'Bearer test-valid-jwt-token');
        }

        return $request;
    }
}
