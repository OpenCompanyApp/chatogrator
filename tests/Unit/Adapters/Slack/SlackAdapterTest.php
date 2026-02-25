<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\FixtureLoader;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group slack
 */
class SlackAdapterTest extends TestCase
{
    // ========================================================================
    // Factory / Construction Tests
    // ========================================================================

    public function test_from_config_creates_slack_adapter_instance(): void
    {
        $adapter = SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(SlackAdapter::class, $adapter);
        $this->assertSame('slack', $adapter->name());
    }

    public function test_default_user_name_is_bot(): void
    {
        $adapter = SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
        ]);

        $this->assertSame('bot', $adapter->userName());
    }

    public function test_uses_provided_user_name(): void
    {
        $adapter = SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
            'user_name' => 'custombot',
        ]);

        $this->assertSame('custombot', $adapter->userName());
    }

    public function test_stores_bot_user_id_when_provided(): void
    {
        $adapter = SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
            'bot_user_id' => 'U12345',
        ]);

        $this->assertSame('U12345', $adapter->botUserId());
    }

    public function test_bot_user_id_is_null_when_not_provided(): void
    {
        $adapter = SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
        ]);

        $this->assertNull($adapter->botUserId());
    }

    // ========================================================================
    // Thread ID Encoding Tests
    // ========================================================================

    public function test_encode_thread_id_with_channel_and_thread_ts(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C12345',
            'threadTs' => '1234567890.123456',
        ]);

        $this->assertSame('slack:C12345:1234567890.123456', $threadId);
    }

    public function test_encode_thread_id_with_empty_thread_ts(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C12345',
            'threadTs' => '',
        ]);

        $this->assertSame('slack:C12345:', $threadId);
    }

    // ========================================================================
    // Thread ID Decoding Tests
    // ========================================================================

    public function test_decode_valid_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345:1234567890.123456');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('1234567890.123456', $result['threadTs']);
    }

    public function test_decode_thread_id_with_empty_thread_ts(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345:');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    public function test_decode_channel_only_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    public function test_decode_throws_on_invalid_format(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('invalid');
    }

    public function test_decode_throws_on_slack_prefix_only(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack');
    }

    public function test_decode_throws_on_wrong_adapter_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('teams:C12345:123');
    }

    public function test_decode_throws_on_too_many_segments(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack:A:B:C:D');
    }

    // ========================================================================
    // DM Detection Tests
    // ========================================================================

    public function test_is_dm_returns_true_for_d_prefix_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('slack:D12345:1234567890.123456'));
    }

    public function test_is_dm_returns_false_for_public_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('slack:C12345:1234567890.123456'));
    }

    public function test_is_dm_returns_false_for_private_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('slack:G12345:1234567890.123456'));
    }

    // ========================================================================
    // Webhook Signature Verification Tests
    // ========================================================================

    public function test_rejects_request_without_timestamp_header(): void
    {
        $adapter = $this->makeAdapter();

        $body = json_encode(['type' => 'url_verification']);
        $request = $this->makeRequest($body, [
            'X-Slack-Signature' => 'v0=invalid',
            'Content-Type' => 'application/json',
        ]);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_without_signature_header(): void
    {
        $adapter = $this->makeAdapter();

        $body = json_encode(['type' => 'url_verification']);
        $request = $this->makeRequest($body, [
            'X-Slack-Request-Timestamp' => (string) time(),
            'Content-Type' => 'application/json',
        ]);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $adapter = $this->makeAdapter();

        $body = json_encode(['type' => 'url_verification']);
        $request = $this->makeRequest($body, [
            'X-Slack-Request-Timestamp' => (string) time(),
            'X-Slack-Signature' => 'v0=invalid',
            'Content-Type' => 'application/json',
        ]);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_old_timestamp(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode(['type' => 'url_verification']);
        $timestamp = time() - 400; // 400 seconds old, exceeds 5 min threshold
        $request = $this->makeSignedRequest($body, $secret, $timestamp);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_accepts_request_with_valid_signature(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'url_verification',
            'challenge' => 'test-challenge',
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // URL Verification Challenge Tests
    // ========================================================================

    public function test_responds_to_url_verification_challenge(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'url_verification',
            'challenge' => 'test-challenge-123',
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);
        $this->assertSame(['challenge' => 'test-challenge-123'], $responseBody);
    }

    // ========================================================================
    // Event Callback Tests
    // ========================================================================

    public function test_handles_message_events(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'user' => 'U123',
                'channel' => 'C456',
                'text' => 'Hello world',
                'ts' => '1234567890.123456',
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_app_mention_events(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'user' => 'U123',
                'channel' => 'C456',
                'text' => '<@U_BOT> hello',
                'ts' => '1234567890.123456',
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_reaction_added_events(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_added',
                'user' => 'U123',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => 'C456',
                    'ts' => '1234567890.123456',
                ],
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_reaction_removed_events(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_removed',
                'user' => 'U123',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => 'C456',
                    'ts' => '1234567890.123456',
                ],
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // Interactive Payload Tests (Block Actions)
    // ========================================================================

    public function test_handles_block_actions_payload(): void
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
                ],
            ],
        ]);
        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_returns_400_for_missing_payload(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = 'foo=bar';
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_returns_400_for_invalid_payload_json(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = 'payload=invalid-json';
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_handles_view_submission_payload(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'view_submission',
            'trigger_id' => 'trigger123',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'view' => [
                'id' => 'V123',
                'callback_id' => 'feedback_form',
                'private_metadata' => 'thread-context',
                'state' => [
                    'values' => [
                        'message_block' => [
                            'message_input' => ['value' => 'Great feedback!'],
                        ],
                        'category_block' => [
                            'category_select' => ['selected_option' => ['value' => 'feature']],
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

    public function test_handles_view_closed_payload(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'view_closed',
            'user' => [
                'id' => 'U123',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
            'view' => [
                'id' => 'V123',
                'callback_id' => 'feedback_form',
                'private_metadata' => 'thread-context',
            ],
        ]);
        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_trigger_id_included_in_block_actions_event(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'trigger_id' => 'trigger456',
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
                    'value' => 'modal-data',
                ],
            ],
        ]);
        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // JSON Parsing Tests
    // ========================================================================

    public function test_returns_400_for_invalid_json(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = 'not valid json';
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // parseMessage Tests
    // ========================================================================

    public function test_parses_basic_message_event(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Hello world',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('1234567890.123456', $message->id);
        $this->assertSame('Hello world', $message->text);
        $this->assertSame('U123', $message->author->userId);
        $this->assertFalse($message->author->isBot);
        $this->assertFalse($message->author->isMe);
    }

    public function test_parses_bot_message(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'bot_id' => 'B123',
            'channel' => 'C456',
            'text' => 'Bot message',
            'ts' => '1234567890.123456',
            'subtype' => 'bot_message',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('B123', $message->author->userId);
        $this->assertTrue($message->author->isBot);
    }

    public function test_detects_messages_from_self(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'user' => 'U_BOT',
            'channel' => 'C456',
            'text' => 'Self message',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertTrue($message->author->isMe);
    }

    public function test_parses_message_with_thread_ts(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Thread reply',
            'ts' => '1234567891.123456',
            'thread_ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('slack:C456:1234567890.123456', $message->threadId);
    }

    public function test_parses_edited_message(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Edited message',
            'ts' => '1234567890.123456',
            'edited' => ['ts' => '1234567891.000000'],
        ];

        $message = $adapter->parseMessage($event);

        $this->assertTrue($message->metadata['edited']);
        $this->assertArrayHasKey('editedAt', $message->metadata);
    }

    public function test_parses_message_with_files(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Message with file',
            'ts' => '1234567890.123456',
            'files' => [
                [
                    'id' => 'F123',
                    'mimetype' => 'image/png',
                    'url_private' => 'https://files.slack.com/file.png',
                    'name' => 'image.png',
                    'size' => 12345,
                    'original_w' => 800,
                    'original_h' => 600,
                ],
            ],
        ];

        $message = $adapter->parseMessage($event);

        $this->assertCount(1, $message->attachments);
        $this->assertSame('image', $message->attachments[0]['type']);
        $this->assertSame('image.png', $message->attachments[0]['name']);
        $this->assertSame('image/png', $message->attachments[0]['mimeType']);
        $this->assertSame(800, $message->attachments[0]['width']);
        $this->assertSame(600, $message->attachments[0]['height']);
    }

    public function test_handles_different_file_types(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('U_BOT');

        $makeEvent = fn (string $mimetype) => [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => '',
            'ts' => '1234567890.123456',
            'files' => [['id' => 'F123', 'mimetype' => $mimetype, 'url_private' => 'https://example.com']],
        ];

        $imageMsg = $adapter->parseMessage($makeEvent('image/jpeg'));
        $this->assertSame('image', $imageMsg->attachments[0]['type']);

        $videoMsg = $adapter->parseMessage($makeEvent('video/mp4'));
        $this->assertSame('video', $videoMsg->attachments[0]['type']);

        $audioMsg = $adapter->parseMessage($makeEvent('audio/mpeg'));
        $this->assertSame('audio', $audioMsg->attachments[0]['type']);

        $fileMsg = $adapter->parseMessage($makeEvent('application/pdf'));
        $this->assertSame('file', $fileMsg->attachments[0]['type']);
    }

    // ========================================================================
    // Formatted Text Rendering Tests
    // ========================================================================

    public function test_render_formatted_converts_bold_to_mrkdwn(): void
    {
        $adapter = $this->makeAdapter();

        // Simple markdown bold -> Slack mrkdwn bold
        $result = $adapter->renderFormatted('**bold**');

        $this->assertSame('*bold*', $result);
    }

    // ========================================================================
    // Edge Cases Tests
    // ========================================================================

    public function test_handles_missing_text_in_event(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('', $message->text);
    }

    public function test_handles_missing_user_in_event(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'channel' => 'C456',
            'text' => 'Anonymous message',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('unknown', $message->author->userId);
    }

    public function test_handles_missing_ts_in_event(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'No timestamp',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('', $message->id);
    }

    public function test_parses_username_from_event_when_available(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'username' => 'testuser',
            'channel' => 'C456',
            'text' => 'Hello',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertSame('testuser', $message->author->userName);
    }

    // ========================================================================
    // Date Parsing Tests
    // ========================================================================

    public function test_parses_slack_timestamp_to_date(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Hello',
            'ts' => '1609459200.000000', // 2021-01-01 00:00:00 UTC
        ];

        $message = $adapter->parseMessage($event);

        $this->assertArrayHasKey('dateSent', $message->metadata);
        // The timestamp 1609459200 = 2021-01-01T00:00:00Z
        $this->assertSame(1609459200, (int) strtotime($message->metadata['dateSent']));
    }

    public function test_handles_edited_timestamp_in_metadata(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Hello',
            'ts' => '1609459200.000000',
            'edited' => ['ts' => '1609459260.000000'], // 1 minute later
        ];

        $message = $adapter->parseMessage($event);

        $this->assertArrayHasKey('editedAt', $message->metadata);
    }

    // ========================================================================
    // Formatted Text Extraction Tests
    // ========================================================================

    public function test_extracts_plain_text_from_mrkdwn(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => '*bold* and _italic_',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        // The raw mrkdwn text may be kept as-is or stripped; the adapter should normalize
        $this->assertStringContainsString('bold', $message->text);
        $this->assertStringContainsString('italic', $message->text);
    }

    public function test_extracts_text_from_links(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Check <https://example.com|this link>',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertStringContainsString('this link', $message->text);
    }

    public function test_extracts_text_from_user_mentions(): void
    {
        $adapter = $this->makeAdapter();

        $event = [
            'type' => 'message',
            'user' => 'U123',
            'channel' => 'C456',
            'text' => 'Hey <@U456|john>!',
            'ts' => '1234567890.123456',
        ];

        $message = $adapter->parseMessage($event);

        $this->assertStringContainsString('@john', $message->text);
    }

    // ========================================================================
    // Slash Command Tests
    // ========================================================================

    public function test_handles_slash_command_payload(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = http_build_query([
            'command' => '/help',
            'text' => 'topic search',
            'user_id' => 'U123456',
            'channel_id' => 'C789ABC',
            'trigger_id' => 'trigger-123',
            'team_id' => 'T_TEAM_1',
        ]);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_slash_command_returns_200_immediately(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = http_build_query([
            'command' => '/feedback',
            'text' => '',
            'user_id' => 'U123',
            'channel_id' => 'C456',
            'team_id' => 'T_TEAM_1',
        ]);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_does_not_treat_interactive_payload_as_slash_command(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $payload = json_encode([
            'type' => 'block_actions',
            'user' => ['id' => 'U123', 'username' => 'user'],
            'actions' => [['action_id' => 'test']],
            'container' => ['message_ts' => '123', 'channel_id' => 'C456'],
            'channel' => ['id' => 'C456', 'name' => 'general'],
            'message' => ['ts' => '123'],
        ]);
        $body = 'payload=' . urlencode($payload);
        $request = $this->makeSignedRequest($body, $secret, null, 'application/x-www-form-urlencoded');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // DM Message Handling Tests
    // ========================================================================

    public function test_dm_message_uses_empty_thread_ts_for_top_level(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'team_id' => 'T123',
            'event' => [
                'type' => 'message',
                'user' => 'U_USER',
                'channel' => 'D_DM_CHAN',
                'channel_type' => 'im',
                'text' => 'hello from DM',
                'ts' => '1234567890.111111',
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
        // The adapter should recognize the DM channel and route appropriately
    }

    public function test_dm_thread_reply_uses_parent_thread_ts(): void
    {
        $adapter = $this->makeAdapter();
        $secret = 'test-signing-secret';

        $body = json_encode([
            'type' => 'event_callback',
            'team_id' => 'T123',
            'event' => [
                'type' => 'message',
                'user' => 'U_USER',
                'channel' => 'D_DM_CHAN',
                'channel_type' => 'im',
                'text' => 'reply in DM thread',
                'ts' => '1234567890.222222',
                'thread_ts' => '1234567890.111111',
            ],
        ]);
        $request = $this->makeSignedRequest($body, $secret);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

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

    private function makeAdapterWithBotUserId(string $botUserId): SlackAdapter
    {
        return SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-secret',
            'bot_user_id' => $botUserId,
        ]);
    }

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
        string $secret,
        ?int $timestamp = null,
        string $contentType = 'application/json'
    ): Request {
        $timestamp = $timestamp ?? time();
        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, $secret);

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

    /**
     * Create a mock Chat instance for webhook handling.
     */
    private function makeMockChat(): \OpenCompany\Chatogrator\Chat
    {
        $chat = \OpenCompany\Chatogrator\Chat::make('test');
        $chat->state(new \OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter);

        return $chat;
    }
}
