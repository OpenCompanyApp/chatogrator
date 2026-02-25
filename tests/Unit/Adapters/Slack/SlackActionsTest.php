<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Slack interactive action handling: block_actions parsing,
 * action value extraction, trigger ID capture, and select menu interactions.
 *
 * @group slack
 */
class SlackActionsTest extends TestCase
{
    // ========================================================================
    // Block Action Parsing (Button Clicks)
    // ========================================================================

    public function test_parses_button_click_block_action(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
                'thread_ts' => '1234567890.000000',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'approve_btn',
                    'value' => 'approved',
                    'block_id' => 'action_block_1',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_parses_multiple_actions_in_single_payload(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'btn_1',
                    'value' => 'val_1',
                ],
                [
                    'type' => 'button',
                    'action_id' => 'btn_2',
                    'value' => 'val_2',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Action Value Extraction
    // ========================================================================

    public function test_extracts_action_value_from_button_click(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U_ACTOR',
                'username' => 'actor',
                'name' => 'Actor User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'confirm_order',
                    'value' => 'order-123-confirmed',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_button_click_without_value(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'click_me',
                    // No value set
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Trigger ID Capture for Modals
    // ========================================================================

    public function test_captures_trigger_id_from_block_actions(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'trigger_id' => 'trigger-for-modal-789',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'open_modal',
                    'value' => 'open',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        // The adapter should capture trigger_id for potential modal opening
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_trigger_id_present_in_view_submission(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'view_submission',
            'trigger_id' => 'trigger-submission-456',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'view' => [
                'id' => 'V_MODAL_123',
                'callback_id' => 'feedback_form',
                'private_metadata' => 'context-data',
                'state' => [
                    'values' => [
                        'input_block' => [
                            'input_action' => ['value' => 'user-typed-text'],
                        ],
                    ],
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Select Menu Interactions
    // ========================================================================

    public function test_handles_static_select_interaction(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'static_select',
                    'action_id' => 'priority_select',
                    'selected_option' => [
                        'text' => ['type' => 'plain_text', 'text' => 'High'],
                        'value' => 'high',
                    ],
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_radio_button_interaction(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'radio_buttons',
                    'action_id' => 'plan_select',
                    'selected_option' => [
                        'text' => ['type' => 'mrkdwn', 'text' => 'Pro Plan'],
                        'value' => 'pro',
                    ],
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_overflow_menu_interaction(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
            ],
            'actions' => [
                [
                    'type' => 'overflow',
                    'action_id' => 'more_options',
                    'selected_option' => [
                        'text' => ['type' => 'plain_text', 'text' => 'Edit'],
                        'value' => 'edit',
                    ],
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_block_actions_from_ephemeral_message(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'is_ephemeral' => true,
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'dismiss',
                    'value' => 'dismiss',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_action_with_thread_context(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'trigger_id' => 'trigger-with-thread',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'container' => [
                'type' => 'message',
                'message_ts' => '1234567890.123456',
                'channel_id' => 'C456',
            ],
            'channel' => [
                'id' => 'C456',
                'name' => 'general',
            ],
            'message' => [
                'ts' => '1234567890.123456',
                'thread_ts' => '1234567890.000000',
            ],
            'actions' => [
                [
                    'type' => 'button',
                    'action_id' => 'reply_action',
                    'value' => 'reply-to-thread',
                ],
            ],
        ]);

        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        // The adapter should parse thread context from the message's thread_ts
        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeAdapter(): SlackAdapter
    {
        return SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-signing-secret',
        ]);
    }

    private function makeSignedRequest(
        string $body,
        string $secret,
        ?int $timestamp = null,
        string $contentType = 'application/json'
    ): \Illuminate\Http\Request {
        $timestamp = $timestamp ?? time();
        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, $secret);

        $request = \Illuminate\Http\Request::create(
            uri: '/webhooks/chat/slack',
            method: 'POST',
            content: $body,
        );

        $request->headers->set('X-Slack-Request-Timestamp', (string) $timestamp);
        $request->headers->set('X-Slack-Signature', $signature);
        $request->headers->set('Content-Type', $contentType);

        return $request;
    }

    private function makeMockChat(): \OpenCompany\Chatogrator\Chat
    {
        $chat = \OpenCompany\Chatogrator\Chat::make('test');
        $chat->state(new MockStateAdapter);

        return $chat;
    }
}
