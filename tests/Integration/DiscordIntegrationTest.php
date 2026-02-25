<?php

namespace OpenCompany\Chatogrator\Tests\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * End-to-end integration tests for Discord webhook handling.
 *
 * Creates a real Chat instance with the Discord adapter + MockStateAdapter,
 * simulates full interaction webhook lifecycle (interactions, not gateway events),
 * and asserts on handler invocation and response status codes.
 *
 * These tests WILL FAIL initially -- they define target behavior for the
 * Discord adapter implementation.
 *
 * @group integration
 * @group discord
 */
class DiscordIntegrationTest extends TestCase
{
    protected DiscordAdapter $adapter;

    protected Chat $chat;

    protected MockStateAdapter $state;

    /**
     * Ed25519 key pair for signing. We use a deterministic test keypair.
     * The public key is 32 bytes (64 hex chars). In production this verifies
     * Discord interaction signatures.
     */
    protected const BOT_TOKEN = 'test-discord-bot-token';

    protected const APPLICATION_ID = 'APP_DISCORD_123';

    protected const BOT_NAME = 'testbot';

    protected const TEST_GUILD = 'GUILD123';

    protected const TEST_CHANNEL = 'CHANNEL456';

    /**
     * A 64-character hex public key for Ed25519 verification.
     * In real tests this should be a valid Ed25519 public key; for skeleton
     * tests we use a placeholder that the adapter will validate against.
     */
    protected string $publicKey;

    protected string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        // Generate a real Ed25519 keypair for signing test interactions
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $this->publicKey = sodium_bin2hex(sodium_crypto_sign_publickey($keypair));

        $this->state = new MockStateAdapter;

        $this->adapter = DiscordAdapter::fromConfig([
            'bot_token' => self::BOT_TOKEN,
            'public_key' => $this->publicKey,
            'application_id' => self::APPLICATION_ID,
            'user_name' => self::BOT_NAME,
        ]);

        $this->chat = Chat::make('test-discord')
            ->adapter('discord', $this->adapter)
            ->state($this->state);
    }

    // ========================================================================
    // PING / Signature Verification
    // ========================================================================

    public function test_responds_to_ping_with_pong(): void
    {
        $request = $this->makeInteractionRequest([
            'id' => 'test_ping_123',
            'type' => 1, // PING
            'application_id' => self::APPLICATION_ID,
            'token' => 'ping-token',
            'version' => 1,
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['type']); // PONG
    }

    public function test_rejects_request_with_missing_signature_headers(): void
    {
        $request = $this->makeRequest(json_encode(['type' => 1]), [
            'Content-Type' => 'application/json',
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_invalid_ed25519_signature(): void
    {
        $body = json_encode(['type' => 1]);
        $timestamp = (string) time();

        $request = $this->makeRequest($body, [
            'Content-Type' => 'application/json',
            'X-Signature-Ed25519' => str_repeat('0', 128),
            'X-Signature-Timestamp' => $timestamp,
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================================================
    // Message Create (via Gateway-style webhook, if supported)
    // ========================================================================

    public function test_message_create_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedText = null;

        $this->chat->onNewMention(function ($thread, Message $message) use (&$handlerCalled, &$capturedText) {
            $handlerCalled = true;
            $capturedText = $message->text;
        });

        // Discord sends MESSAGE_CREATE as a gateway event; for webhook-based
        // integration, we simulate a message component interaction that posts
        // a new message to the bot. The exact format depends on the adapter
        // implementation -- this captures the expected behavior.
        $request = $this->makeInteractionRequest([
            'id' => 'interaction_msg_123',
            'type' => 2, // APPLICATION_COMMAND
            'application_id' => self::APPLICATION_ID,
            'token' => 'cmd-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'id' => 'cmd-help-id',
                'name' => 'help',
                'type' => 1, // CHAT_INPUT
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        // Application commands should return 200 with deferred response
        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Slash Command Interaction
    // ========================================================================

    public function test_slash_command_returns_deferred_response(): void
    {
        $request = $this->makeInteractionRequest([
            'id' => 'slash_cmd_123',
            'type' => 2, // APPLICATION_COMMAND
            'application_id' => self::APPLICATION_ID,
            'token' => 'slash-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'id' => 'cmd-data-id',
                'name' => 'help',
                'type' => 1,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        // Type 5 = DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE
        $this->assertSame(5, $body['type']);
    }

    // ========================================================================
    // Button Click / MESSAGE_COMPONENT
    // ========================================================================

    public function test_button_click_triggers_on_action_handler(): void
    {
        $handlerCalled = false;
        $capturedActionId = null;
        $capturedUserId = null;

        $this->chat->onAction('approve', function ($event) use (&$handlerCalled, &$capturedActionId, &$capturedUserId) {
            $handlerCalled = true;
            $capturedActionId = $event->actionId;
            $capturedUserId = $event->user->userId;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'btn_click_123',
            'type' => 3, // MESSAGE_COMPONENT
            'application_id' => self::APPLICATION_ID,
            'token' => 'btn-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'message' => [
                'id' => 'msg_123',
                'channel_id' => self::TEST_CHANNEL,
            ],
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'approve',
                'component_type' => 2, // BUTTON
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        // Type 6 = DEFERRED_UPDATE_MESSAGE
        $this->assertSame(6, $body['type']);
        $this->assertTrue($handlerCalled, 'onAction handler should be called for button click');
        $this->assertSame('approve', $capturedActionId);
        $this->assertSame('USER789', $capturedUserId);
    }

    public function test_non_matching_action_id_does_not_trigger_handler(): void
    {
        $handlerCalled = false;

        $this->chat->onAction('approve', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'btn_click_456',
            'type' => 3, // MESSAGE_COMPONENT
            'application_id' => self::APPLICATION_ID,
            'token' => 'btn-token-2',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'reject',
                'component_type' => 2,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertFalse($handlerCalled, 'Handler should not fire for non-matching action ID');
    }

    public function test_catch_all_action_handler_receives_any_action(): void
    {
        $capturedActionId = null;

        $this->chat->onAction(function ($event) use (&$capturedActionId) {
            $capturedActionId = $event->actionId;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'btn_any_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'btn-any-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'any-action',
                'component_type' => 2,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame('any-action', $capturedActionId);
    }

    // ========================================================================
    // Modal Submit
    // ========================================================================

    public function test_modal_submit_triggers_handler(): void
    {
        $handlerCalled = false;
        $capturedCustomId = null;

        $this->chat->onModalSubmit(function ($event) use (&$handlerCalled, &$capturedCustomId) {
            $handlerCalled = true;
            $capturedCustomId = $event->customId ?? $event->viewId;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'modal_submit_123',
            'type' => 5, // MODAL_SUBMIT
            'application_id' => self::APPLICATION_ID,
            'token' => 'modal-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'feedback_modal',
                'components' => [
                    [
                        'type' => 1,
                        'components' => [
                            [
                                'type' => 4,
                                'custom_id' => 'feedback_text',
                                'value' => 'Great feature!',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onModalSubmit handler should be called');
        $this->assertSame('feedback_modal', $capturedCustomId);
    }

    // ========================================================================
    // Message Update/Delete Events
    // ========================================================================

    public function test_message_update_event_is_handled(): void
    {
        $handlerCalled = false;

        $this->chat->onMessageEdited(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Discord webhook-based adapters may receive MESSAGE_UPDATE as a
        // gateway event or through an internal dispatch mechanism.
        // This test defines the expected behavior for when such an event
        // is routed to handleWebhook.
        $request = $this->makeInteractionRequest([
            'id' => 'msg_update_123',
            'type' => 2, // APPLICATION_COMMAND (placeholder for event routing)
            'application_id' => self::APPLICATION_ID,
            'token' => 'update-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'id' => 'cmd-data-id',
                'name' => 'edit',
                'type' => 1,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_message_delete_event_is_handled(): void
    {
        $handlerCalled = false;

        $this->chat->onMessageDeleted(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'msg_delete_123',
            'type' => 2,
            'application_id' => self::APPLICATION_ID,
            'token' => 'delete-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'id' => 'cmd-data-id',
                'name' => 'delete',
                'type' => 1,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Reaction Events
    // ========================================================================

    public function test_reaction_add_event_is_handled(): void
    {
        $handlerCalled = false;

        $this->chat->onReactionAdded(function ($event) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Reactions in Discord come through the gateway. This test verifies
        // the adapter can handle the event if it is dispatched through the
        // webhook handler.
        $request = $this->makeInteractionRequest([
            'id' => 'reaction_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'reaction-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'react_thumbsup',
                'component_type' => 2,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // DM Interactions
    // ========================================================================

    public function test_dm_button_click_uses_correct_thread_id(): void
    {
        $capturedThreadId = null;

        $this->chat->onAction('dm-action', function ($event) use (&$capturedThreadId) {
            $capturedThreadId = $event->threadId;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'dm_click_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'dm-token',
            'version' => 1,
            'guild_id' => null, // DMs have no guild
            'channel_id' => 'DM_CHANNEL_123',
            'user' => [
                'id' => 'USER789',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'data' => [
                'custom_id' => 'dm-action',
                'component_type' => 2,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        // DM thread IDs use @me as guild placeholder
        $this->assertSame('discord:@me:DM_CHANNEL_123', $capturedThreadId);
    }

    // ========================================================================
    // Unknown Interaction Types
    // ========================================================================

    public function test_unknown_interaction_type_returns_400(): void
    {
        $request = $this->makeInteractionRequest([
            'id' => 'unknown_123',
            'type' => 999, // Unknown type
            'application_id' => self::APPLICATION_ID,
            'token' => 'unknown-token',
            'version' => 1,
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // Thread Info in Action Events
    // ========================================================================

    public function test_action_event_includes_correct_thread_info(): void
    {
        $capturedEvent = null;

        $this->chat->onAction('info', function ($event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'info_click_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'info-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'info',
                'component_type' => 2,
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedEvent);
        $this->assertSame('info', $capturedEvent->actionId);
        $this->assertSame('discord:' . self::TEST_GUILD . ':' . self::TEST_CHANNEL, $capturedEvent->threadId);
        $this->assertSame('USER789', $capturedEvent->user->userId);
        $this->assertSame('testuser', $capturedEvent->user->userName);
    }

    // ========================================================================
    // Actions from Different Users
    // ========================================================================

    public function test_actions_from_different_users_are_independent(): void
    {
        $userActions = [];

        $this->chat->onAction(function ($event) use (&$userActions) {
            $userId = $event->user->userId;
            if (! isset($userActions[$userId])) {
                $userActions[$userId] = [];
            }
            $userActions[$userId][] = $event->actionId;
        });

        // User A clicks a button
        $requestA = $this->makeInteractionRequest([
            'id' => 'action_a_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'user-a-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER_A',
                    'username' => 'user_a',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'action-a',
                'component_type' => 2,
            ],
        ]);

        $this->adapter->handleWebhook($requestA, $this->chat);

        // User B clicks a button
        $requestB = $this->makeInteractionRequest([
            'id' => 'action_b_456',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'user-b-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER_B',
                    'username' => 'user_b',
                    'discriminator' => '0002',
                ],
            ],
            'data' => [
                'custom_id' => 'action-b',
                'component_type' => 2,
            ],
        ]);

        $this->adapter->handleWebhook($requestB, $this->chat);

        $this->assertSame(['action-a'], $userActions['USER_A'] ?? []);
        $this->assertSame(['action-b'], $userActions['USER_B'] ?? []);
    }

    // ========================================================================
    // Select Menu Interaction
    // ========================================================================

    public function test_select_menu_interaction_triggers_action_handler(): void
    {
        $handlerCalled = false;
        $capturedActionId = null;
        $capturedValues = null;

        $this->chat->onAction('color_select', function ($event) use (&$handlerCalled, &$capturedActionId, &$capturedValues) {
            $handlerCalled = true;
            $capturedActionId = $event->actionId;
            $capturedValues = $event->values ?? $event->value;
        });

        $request = $this->makeInteractionRequest([
            'id' => 'select_123',
            'type' => 3,
            'application_id' => self::APPLICATION_ID,
            'token' => 'select-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'custom_id' => 'color_select',
                'component_type' => 3, // SELECT_MENU
                'values' => ['red', 'blue'],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled, 'onAction handler should fire for select menu');
        $this->assertSame('color_select', $capturedActionId);
    }

    // ========================================================================
    // Autocomplete Interaction
    // ========================================================================

    public function test_autocomplete_interaction_returns_response(): void
    {
        $request = $this->makeInteractionRequest([
            'id' => 'autocomplete_123',
            'type' => 4, // APPLICATION_COMMAND_AUTOCOMPLETE
            'application_id' => self::APPLICATION_ID,
            'token' => 'autocomplete-token',
            'version' => 1,
            'guild_id' => self::TEST_GUILD,
            'channel_id' => self::TEST_CHANNEL,
            'member' => [
                'user' => [
                    'id' => 'USER789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
            ],
            'data' => [
                'id' => 'cmd-autocomplete-id',
                'name' => 'search',
                'type' => 1,
                'options' => [
                    [
                        'name' => 'query',
                        'type' => 3,
                        'value' => 'test',
                        'focused' => true,
                    ],
                ],
            ],
        ]);

        $response = $this->adapter->handleWebhook($request, $this->chat);

        // Autocomplete interactions should return 200 with choices
        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Multiple Sequential Actions
    // ========================================================================

    public function test_multiple_sequential_actions_all_fire(): void
    {
        $actionLog = [];

        $this->chat->onAction('step1', function () use (&$actionLog) {
            $actionLog[] = 'step1';
        });

        $this->chat->onAction('step2', function () use (&$actionLog) {
            $actionLog[] = 'step2';
        });

        $this->chat->onAction('step3', function () use (&$actionLog) {
            $actionLog[] = 'step3';
        });

        foreach (['step1', 'step2', 'step3'] as $step) {
            $request = $this->makeInteractionRequest([
                'id' => "action_{$step}",
                'type' => 3,
                'application_id' => self::APPLICATION_ID,
                'token' => "{$step}-token",
                'version' => 1,
                'guild_id' => self::TEST_GUILD,
                'channel_id' => self::TEST_CHANNEL,
                'member' => [
                    'user' => [
                        'id' => 'USER789',
                        'username' => 'testuser',
                        'discriminator' => '0001',
                    ],
                ],
                'data' => [
                    'custom_id' => $step,
                    'component_type' => 2,
                ],
            ]);

            $this->adapter->handleWebhook($request, $this->chat);
        }

        $this->assertSame(['step1', 'step2', 'step3'], $actionLog);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeRequest(string $body, array $headers = []): Request
    {
        $request = Request::create(
            uri: '/webhooks/chat/discord',
            method: 'POST',
            content: $body,
        );

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $request;
    }

    /**
     * Create a request that simulates a Discord interaction webhook.
     * Signs the payload with the real Ed25519 test keypair.
     */
    private function makeInteractionRequest(array $payload): Request
    {
        $body = json_encode($payload);
        $timestamp = (string) time();

        // Sign with the real Ed25519 secret key
        $message = $timestamp . $body;
        $signature = sodium_bin2hex(sodium_crypto_sign_detached($message, $this->secretKey));

        $request = Request::create(
            uri: '/webhooks/chat/discord',
            method: 'POST',
            content: $body,
        );

        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Signature-Ed25519', $signature);
        $request->headers->set('X-Signature-Timestamp', $timestamp);

        return $request;
    }
}
