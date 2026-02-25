<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Discord;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordFormatConverter;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the Discord adapter — webhook handling, message operations,
 * thread ID encoding/decoding, and format conversion.
 *
 * Ported from adapter-discord/src/index.test.ts (46 tests).
 *
 * @group discord
 */
class DiscordAdapterTest extends TestCase
{
    // ── Factory / Construction ───────────────────────────────────────

    public function test_create_discord_adapter_returns_instance(): void
    {
        $adapter = DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => str_repeat('ab', 32),
            'application_id' => 'test-app-id',
        ]);

        $this->assertInstanceOf(DiscordAdapter::class, $adapter);
    }

    public function test_adapter_name_is_discord(): void
    {
        $adapter = DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => str_repeat('ab', 32),
            'application_id' => 'test-app-id',
        ]);

        $this->assertSame('discord', $adapter->name());
    }

    public function test_default_user_name_is_bot(): void
    {
        $adapter = DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => str_repeat('ab', 32),
            'application_id' => 'test-app-id',
        ]);

        $this->assertSame('bot', $adapter->userName());
    }

    public function test_uses_provided_user_name(): void
    {
        $adapter = DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => str_repeat('ab', 32),
            'application_id' => 'test-app-id',
            'user_name' => 'custombot',
        ]);

        $this->assertSame('custombot', $adapter->userName());
    }

    // ── Thread ID Encoding ──────────────────────────────────────────

    public function test_encode_thread_id_with_guild_and_channel(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'guildId' => 'guild123',
            'channelId' => 'channel456',
        ]);

        $this->assertSame('discord:guild123:channel456', $threadId);
    }

    public function test_encode_thread_id_with_thread(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'guildId' => 'guild123',
            'channelId' => 'channel456',
            'threadId' => 'thread789',
        ]);

        $this->assertSame('discord:guild123:channel456:thread789', $threadId);
    }

    public function test_encode_thread_id_for_dm(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'guildId' => '@me',
            'channelId' => 'dm123',
        ]);

        $this->assertSame('discord:@me:dm123', $threadId);
    }

    // ── Thread ID Decoding ──────────────────────────────────────────

    public function test_decode_valid_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('discord:guild123:channel456');

        $this->assertSame('guild123', $result['guildId']);
        $this->assertSame('channel456', $result['channelId']);
        $this->assertArrayNotHasKey('threadId', $result);
    }

    public function test_decode_thread_id_with_thread(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('discord:guild123:channel456:thread789');

        $this->assertSame('guild123', $result['guildId']);
        $this->assertSame('channel456', $result['channelId']);
        $this->assertSame('thread789', $result['threadId']);
    }

    public function test_decode_dm_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('discord:@me:dm123');

        $this->assertSame('@me', $result['guildId']);
        $this->assertSame('dm123', $result['channelId']);
    }

    public function test_decode_throws_on_invalid_format(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('invalid');
    }

    public function test_decode_throws_on_missing_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('discord:channel');
    }

    public function test_decode_throws_on_wrong_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack:C12345:123');
    }

    // ── isDM ────────────────────────────────────────────────────────

    public function test_is_dm_returns_true_for_dm_channels(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('discord:@me:dm123'));
    }

    public function test_is_dm_returns_false_for_guild_channels(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('discord:guild123:channel456'));
    }

    public function test_is_dm_returns_false_for_threads_in_guilds(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('discord:guild123:channel456:thread789'));
    }

    // ── Webhook Signature Verification ──────────────────────────────

    public function test_rejects_requests_without_signature_header(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['type' => 1]));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_requests_without_timestamp_header(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE_ED25519' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['type' => 1]));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_requests_with_invalid_signature(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE_ED25519' => 'invalid',
            'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['type' => 1]));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_accepts_requests_with_valid_ed25519_signature(): void
    {
        // Generate test keypair
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keypair));
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        $adapter = DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => $publicKey,
            'application_id' => 'test-app-id',
        ]);

        $body = json_encode(['type' => 1]); // PING
        $timestamp = (string) time();
        $message = $timestamp . $body;
        $signature = bin2hex(sodium_crypto_sign_detached($message, $secretKey));

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE_ED25519' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── PING Interaction ────────────────────────────────────────────

    public function test_responds_to_ping_with_pong(): void
    {
        $adapter = $this->makeAdapterWithKeypair($keypair);

        $body = json_encode(['type' => 1]); // InteractionType.Ping
        $request = $this->makeSignedRequest($body, $keypair);

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame(1, $responseData['type']); // Pong
    }

    // ── MESSAGE_COMPONENT Interaction ───────────────────────────────

    public function test_handles_button_click_interaction(): void
    {
        $adapter = $this->makeAdapterWithKeypair($keypair);

        $body = json_encode([
            'type' => 3, // InteractionType.MessageComponent
            'id' => 'interaction123',
            'application_id' => 'test-app-id',
            'token' => 'interaction-token',
            'version' => 1,
            'guild_id' => 'guild123',
            'channel_id' => 'channel456',
            'member' => [
                'user' => [
                    'id' => 'user789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                    'global_name' => 'Test User',
                ],
                'nick' => null,
                'roles' => [],
                'joined_at' => '2021-01-01T00:00:00.000Z',
            ],
            'message' => [
                'id' => 'message123',
                'channel_id' => 'channel456',
                'author' => ['id' => 'bot', 'username' => 'bot', 'discriminator' => '0000'],
                'content' => 'Test message',
                'timestamp' => '2021-01-01T00:00:00.000Z',
                'tts' => false,
                'mention_everyone' => false,
                'mentions' => [],
                'mention_roles' => [],
                'attachments' => [],
                'embeds' => [],
                'pinned' => false,
                'type' => 0,
            ],
            'data' => [
                'custom_id' => 'approve_btn',
                'component_type' => 2,
            ],
        ]);

        $request = $this->makeSignedRequest($body, $keypair);
        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame(6, $responseData['type']); // DeferredUpdateMessage
    }

    // ── APPLICATION_COMMAND Interaction ──────────────────────────────

    public function test_handles_slash_command_interaction(): void
    {
        $adapter = $this->makeAdapterWithKeypair($keypair);

        $body = json_encode([
            'type' => 2, // InteractionType.ApplicationCommand
            'id' => 'interaction123',
            'application_id' => 'test-app-id',
            'token' => 'interaction-token',
            'version' => 1,
            'guild_id' => 'guild123',
            'channel_id' => 'channel456',
            'member' => [
                'user' => [
                    'id' => 'user789',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
                'roles' => [],
                'joined_at' => '2021-01-01T00:00:00.000Z',
            ],
            'data' => [
                'id' => 'cmd123',
                'name' => 'test',
                'type' => 1,
            ],
        ]);

        $request = $this->makeSignedRequest($body, $keypair);
        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame(5, $responseData['type']); // DeferredChannelMessageWithSource
    }

    // ── JSON Parsing ────────────────────────────────────────────────

    public function test_returns_400_for_invalid_json(): void
    {
        $adapter = $this->makeAdapterWithKeypair($keypair);

        $body = 'not valid json';
        $request = $this->makeSignedRequest($body, $keypair);

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_returns_400_for_unknown_interaction_type(): void
    {
        $adapter = $this->makeAdapterWithKeypair($keypair);

        $body = json_encode(['type' => 999]);
        $request = $this->makeSignedRequest($body, $keypair);

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    // ── parseMessage ────────────────────────────────────────────────

    public function test_parses_basic_message(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'guild_id' => 'guild789',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
                'global_name' => 'Test User',
            ],
            'content' => 'Hello world',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);

        $this->assertSame('message123', $message->id);
        $this->assertSame('Hello world', $message->text);
        $this->assertSame('user123', $message->author->userId);
        $this->assertSame('testuser', $message->author->userName);
        $this->assertSame('Test User', $message->author->fullName);
        $this->assertFalse($message->author->isBot);
        $this->assertSame('discord:guild789:channel456', $message->threadId);
    }

    public function test_parses_bot_message(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'bot123',
                'username' => 'somebot',
                'discriminator' => '0000',
                'bot' => true,
            ],
            'content' => 'Bot message',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);

        $this->assertSame('bot123', $message->author->userId);
        $this->assertTrue($message->author->isBot);
    }

    public function test_parses_dm_message_no_guild_id(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'dm456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'DM message',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);

        $this->assertSame('discord:@me:dm456', $message->threadId);
    }

    public function test_parses_edited_message(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'guild_id' => 'guild789',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Edited message',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => '2021-01-01T00:01:00.000Z',
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);

        $this->assertTrue($message->metadata['edited']);
        $this->assertSame('2021-01-01T00:01:00.000Z', $message->metadata['editedAt']);
    }

    public function test_parses_message_with_image_attachment(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'guild_id' => 'guild789',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Message with attachment',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [
                [
                    'id' => 'att123',
                    'filename' => 'image.png',
                    'size' => 12345,
                    'url' => 'https://cdn.discord.com/image.png',
                    'proxy_url' => 'https://media.discord.com/image.png',
                    'content_type' => 'image/png',
                    'width' => 800,
                    'height' => 600,
                ],
            ],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);

        $this->assertCount(1, $message->attachments);
        $this->assertSame('image', $message->attachments[0]['type']);
        $this->assertSame('image.png', $message->attachments[0]['name']);
        $this->assertSame('image/png', $message->attachments[0]['mimeType']);
        $this->assertSame(800, $message->attachments[0]['width']);
        $this->assertSame(600, $message->attachments[0]['height']);
    }

    public function test_handles_different_attachment_types(): void
    {
        $adapter = $this->makeAdapter();

        $makeMessage = function (string $contentType) {
            return [
                'id' => 'message123',
                'channel_id' => 'channel456',
                'author' => [
                    'id' => 'user123',
                    'username' => 'testuser',
                    'discriminator' => '0001',
                ],
                'content' => '',
                'timestamp' => '2021-01-01T00:00:00.000Z',
                'edited_timestamp' => null,
                'tts' => false,
                'mention_everyone' => false,
                'mentions' => [],
                'mention_roles' => [],
                'attachments' => [
                    [
                        'id' => 'att123',
                        'filename' => 'file',
                        'size' => 1000,
                        'url' => 'https://example.com',
                        'proxy_url' => 'https://example.com',
                        'content_type' => $contentType,
                    ],
                ],
                'embeds' => [],
                'pinned' => false,
                'type' => 0,
            ];
        };

        $imageMsg = $adapter->parseMessage($makeMessage('image/jpeg'));
        $this->assertSame('image', $imageMsg->attachments[0]['type']);

        $videoMsg = $adapter->parseMessage($makeMessage('video/mp4'));
        $this->assertSame('video', $videoMsg->attachments[0]['type']);

        $audioMsg = $adapter->parseMessage($makeMessage('audio/mpeg'));
        $this->assertSame('audio', $audioMsg->attachments[0]['type']);

        $fileMsg = $adapter->parseMessage($makeMessage('application/pdf'));
        $this->assertSame('file', $fileMsg->attachments[0]['type']);
    }

    public function test_uses_username_as_full_name_when_global_name_missing(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Hello',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertSame('testuser', $message->author->fullName);
    }

    // ── renderFormatted ─────────────────────────────────────────────

    public function test_renders_bold_markdown(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('**bold**');

        $this->assertStringContainsString('**bold**', $result);
    }

    public function test_converts_at_mentions_in_rendered_output(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('Hello @someone');

        $this->assertStringContainsString('<@someone>', $result);
    }

    // ── Edge Cases ──────────────────────────────────────────────────

    public function test_handles_empty_content_in_message(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => '',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertSame('', $message->text);
    }

    public function test_handles_null_width_height_in_attachments(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => '',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [
                [
                    'id' => 'att123',
                    'filename' => 'doc.pdf',
                    'size' => 1000,
                    'url' => 'https://example.com',
                    'proxy_url' => 'https://example.com',
                    'content_type' => 'application/pdf',
                    'width' => null,
                    'height' => null,
                ],
            ],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertArrayNotHasKey('width', $message->attachments[0]);
        $this->assertArrayNotHasKey('height', $message->attachments[0]);
    }

    public function test_handles_missing_attachment_content_type(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => '',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [
                [
                    'id' => 'att123',
                    'filename' => 'unknown',
                    'size' => 1000,
                    'url' => 'https://example.com',
                    'proxy_url' => 'https://example.com',
                ],
            ],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertSame('file', $message->attachments[0]['type']);
    }

    // ── Date Parsing ────────────────────────────────────────────────

    public function test_parses_iso_timestamp_to_date(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Hello',
            'timestamp' => '2021-01-01T12:30:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertSame('2021-01-01T12:30:00.000Z', $message->metadata['dateSent']);
    }

    // ── Formatted Text Extraction ───────────────────────────────────

    public function test_extracts_plain_text_from_discord_markdown(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => '**bold** and *italic*',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertSame('bold and italic', $message->text);
    }

    public function test_extracts_text_from_user_mentions(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Hey <@456789>!',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertStringContainsString('@456789', $message->text);
    }

    public function test_extracts_text_from_channel_mentions(): void
    {
        $adapter = $this->makeAdapter();

        $rawMessage = [
            'id' => 'message123',
            'channel_id' => 'channel456',
            'author' => [
                'id' => 'user123',
                'username' => 'testuser',
                'discriminator' => '0001',
            ],
            'content' => 'Check <#987654>',
            'timestamp' => '2021-01-01T00:00:00.000Z',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];

        $message = $adapter->parseMessage($rawMessage);
        $this->assertStringContainsString('#987654', $message->text);
    }

    // ── DiscordFormatConverter basics ────────────────────────────────

    public function test_converter_extracts_user_mentions(): void
    {
        $converter = new DiscordFormatConverter;

        $text = $converter->toMarkdown('Hello <@123456789>');
        $this->assertStringContainsString('@123456789', $text);
    }

    public function test_converter_extracts_channel_mentions(): void
    {
        $converter = new DiscordFormatConverter;

        $text = $converter->toMarkdown('Check <#987654321>');
        $this->assertStringContainsString('#987654321', $text);
    }

    public function test_converter_extracts_custom_emoji(): void
    {
        $converter = new DiscordFormatConverter;

        $text = $converter->toMarkdown('Nice <:thumbsup:123>');
        $this->assertStringContainsString(':thumbsup:', $text);
    }

    public function test_converter_handles_bold_text(): void
    {
        $converter = new DiscordFormatConverter;

        $markdown = $converter->toMarkdown('**bold text**');
        $this->assertStringContainsString('bold text', $markdown);
    }

    public function test_converter_handles_italic_text(): void
    {
        $converter = new DiscordFormatConverter;

        $markdown = $converter->toMarkdown('*italic text*');
        $this->assertStringContainsString('italic text', $markdown);
    }

    public function test_converter_from_markdown_with_mentions(): void
    {
        $converter = new DiscordFormatConverter;

        $result = $converter->fromMarkdown('Hello @someone');
        $this->assertStringContainsString('<@someone>', $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeAdapter(): DiscordAdapter
    {
        return DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => str_repeat('ab', 32),
            'application_id' => 'test-app-id',
        ]);
    }

    private function makeAdapterWithKeypair(?string &$keypair = null): DiscordAdapter
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keypair));

        return DiscordAdapter::fromConfig([
            'bot_token' => 'test-token',
            'public_key' => $publicKey,
            'application_id' => 'test-app-id',
        ]);
    }

    private function makeSignedRequest(string $body, string $keypair): Request
    {
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $timestamp = (string) time();
        $message = $timestamp . $body;
        $signature = bin2hex(sodium_crypto_sign_detached($message, $secretKey));

        return Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE_ED25519' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function makeChat(): \OpenCompany\Chatogrator\Chat
    {
        return $this->app->make(\OpenCompany\Chatogrator\Chat::class);
    }
}
