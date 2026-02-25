<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Telegram;

use Illuminate\Http\Request;
use OpenCompany\Chatogrator\Adapters\Telegram\TelegramAdapter;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Types\ThreadInfo;

/**
 * @group telegram
 */
class TelegramAdapterTest extends TestCase
{
    // ========================================================================
    // Factory / Construction Tests
    // ========================================================================

    public function test_from_config_creates_telegram_adapter_instance(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(TelegramAdapter::class, $adapter);
    }

    public function test_name_returns_telegram(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertSame('telegram', $adapter->name());
    }

    public function test_default_user_name_is_bot(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertSame('bot', $adapter->userName());
    }

    public function test_uses_provided_user_name(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
            'user_name' => 'myCustomBot',
        ]);

        $this->assertSame('myCustomBot', $adapter->userName());
    }

    public function test_bot_user_id_from_config(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
            'bot_user_id' => '123456789',
        ]);

        $this->assertSame('123456789', $adapter->botUserId());
    }

    public function test_bot_user_id_is_null_when_not_set(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertNull($adapter->botUserId());
    }

    // ========================================================================
    // Thread ID Encoding Tests
    // ========================================================================

    public function test_encode_private_chat_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId(['chatId' => '123456']);

        $this->assertSame('telegram:123456', $threadId);
    }

    public function test_encode_group_chat_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId(['chatId' => '-987654']);

        $this->assertSame('telegram:-987654', $threadId);
    }

    public function test_encode_forum_topic_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'chatId' => '-100123',
            'messageThreadId' => '42',
        ]);

        $this->assertSame('telegram:-100123:42', $threadId);
    }

    public function test_encode_with_null_message_thread_id_omits_it(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'chatId' => '123456',
            'messageThreadId' => null,
        ]);

        $this->assertSame('telegram:123456', $threadId);
    }

    public function test_encode_with_empty_message_thread_id_omits_it(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'chatId' => '123456',
            'messageThreadId' => '',
        ]);

        $this->assertSame('telegram:123456', $threadId);
    }

    // ========================================================================
    // Thread ID Decoding Tests
    // ========================================================================

    public function test_decode_private_chat_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('telegram:123456');

        $this->assertSame('123456', $result['chatId']);
        $this->assertArrayNotHasKey('messageThreadId', $result);
    }

    public function test_decode_group_chat_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('telegram:-987654');

        $this->assertSame('-987654', $result['chatId']);
        $this->assertArrayNotHasKey('messageThreadId', $result);
    }

    public function test_decode_forum_topic_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('telegram:-100123:42');

        $this->assertSame('-100123', $result['chatId']);
        $this->assertSame('42', $result['messageThreadId']);
    }

    public function test_decode_roundtrip_private_chat(): void
    {
        $adapter = $this->makeAdapter();

        $original = ['chatId' => '123456'];
        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame('123456', $decoded['chatId']);
    }

    public function test_decode_roundtrip_forum_topic(): void
    {
        $adapter = $this->makeAdapter();

        $original = ['chatId' => '-100123', 'messageThreadId' => '42'];
        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame('-100123', $decoded['chatId']);
        $this->assertSame('42', $decoded['messageThreadId']);
    }

    public function test_decode_throws_on_invalid_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack:C12345');
    }

    public function test_decode_throws_on_empty_string(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('');
    }

    public function test_decode_throws_on_missing_chat_id(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('telegram:');
    }

    // ========================================================================
    // isDM Tests
    // ========================================================================

    public function test_is_dm_returns_true_for_positive_chat_id(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('telegram:123456'));
    }

    public function test_is_dm_returns_false_for_negative_chat_id(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('telegram:-987654'));
    }

    public function test_is_dm_returns_false_for_supergroup(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('telegram:-1001234567890'));
    }

    // ========================================================================
    // channelIdFromThreadId Tests
    // ========================================================================

    public function test_channel_id_from_simple_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $channelId = $adapter->channelIdFromThreadId('telegram:123456');

        $this->assertSame('123456', $channelId);
    }

    public function test_channel_id_from_forum_topic_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $channelId = $adapter->channelIdFromThreadId('telegram:-100123:42');

        $this->assertSame('-100123', $channelId);
    }

    // ========================================================================
    // Webhook Handling — Authentication Tests
    // ========================================================================

    public function test_webhook_rejects_missing_secret_header(): void
    {
        $adapter = $this->makeAdapter();

        $payload = json_encode(['message' => $this->makeBasicMessagePayload()]);
        $request = $this->makeTelegramRequest($payload);

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_webhook_rejects_wrong_secret(): void
    {
        $adapter = $this->makeAdapter();

        $payload = json_encode(['message' => $this->makeBasicMessagePayload()]);
        $request = $this->makeTelegramRequest($payload, 'wrong-secret');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_webhook_accepts_valid_secret(): void
    {
        $adapter = $this->makeAdapter();

        $payload = json_encode(['message' => $this->makeBasicMessagePayload()]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_webhook_rejects_invalid_json(): void
    {
        $adapter = $this->makeAdapter();

        $request = $this->makeTelegramRequest('not valid json', 'test-secret');

        $response = $adapter->handleWebhook($request, $this->makeMockChat());

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================================================
    // Webhook Handling — Message Dispatch Tests
    // ========================================================================

    public function test_webhook_message_dispatches_incoming_message(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $dispatchedMessage = null;
        $chat->onNewMention(function ($thread, $message) use (&$dispatched, &$dispatchedMessage) {
            $dispatched = true;
            $dispatchedMessage = $message;
        });

        $payload = json_encode([
            'message' => $this->makeBasicMessagePayload(),
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $response = $adapter->handleWebhook($request, $chat);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($dispatched, 'Message handler should have been dispatched');
        $this->assertSame('Hello world', $dispatchedMessage->text);
    }

    public function test_webhook_message_creates_correct_thread_id(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $capturedThreadId = null;
        $chat->onNewMention(function ($thread, $message) use (&$capturedThreadId) {
            $capturedThreadId = $message->threadId;
        });

        $payload = json_encode([
            'message' => $this->makeBasicMessagePayload(['chat' => ['id' => 99999, 'type' => 'private']]),
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertSame('telegram:99999', $capturedThreadId);
    }

    public function test_webhook_bot_command_dispatches_slash_command(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $capturedCommand = null;
        $chat->onSlashCommand(function ($event) use (&$dispatched, &$capturedCommand) {
            $dispatched = true;
            $capturedCommand = $event->command;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => 222, 'type' => 'private'],
                'date' => time(),
                'text' => '/start some argument',
                'entities' => [
                    ['type' => 'bot_command', 'offset' => 0, 'length' => 6],
                ],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Slash command handler should have been dispatched');
        $this->assertSame('/start', $capturedCommand);
    }

    public function test_webhook_bot_command_strips_bot_username_suffix(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $capturedCommand = null;
        $chat->onSlashCommand(function ($event) use (&$capturedCommand) {
            $capturedCommand = $event->command;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => 222, 'type' => 'group'],
                'date' => time(),
                'text' => '/help@MyBot',
                'entities' => [
                    ['type' => 'bot_command', 'offset' => 0, 'length' => 11],
                ],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertSame('/help', $capturedCommand);
    }

    public function test_webhook_callback_query_dispatches_action(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('999');
        $chat = $this->makeMockChat();

        $dispatched = false;
        $capturedActionId = null;
        $capturedValue = null;
        $chat->onAction(function ($event) use (&$dispatched, &$capturedActionId, &$capturedValue) {
            $dispatched = true;
            $capturedActionId = $event->actionId;
            $capturedValue = $event->value;
        });

        $payload = json_encode([
            'callback_query' => [
                'id' => 'cbq-123',
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'message' => [
                    'message_id' => 50,
                    'chat' => ['id' => 222, 'type' => 'private'],
                ],
                'callback_data' => 'approve:task-42',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        // Mock the HTTP call for answerCallbackQuery so it doesn't hit a real API
        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => true]),
        ]);

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Action handler should have been dispatched');
        $this->assertSame('approve', $capturedActionId);
        $this->assertSame('task-42', $capturedValue);
    }

    public function test_webhook_callback_query_without_colon_sends_full_data_as_action_id(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('999');
        $chat = $this->makeMockChat();

        $capturedActionId = null;
        $capturedValue = null;
        $chat->onAction(function ($event) use (&$capturedActionId, &$capturedValue) {
            $capturedActionId = $event->actionId;
            $capturedValue = $event->value;
        });

        $payload = json_encode([
            'callback_query' => [
                'id' => 'cbq-456',
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'message' => [
                    'message_id' => 50,
                    'chat' => ['id' => 222, 'type' => 'private'],
                ],
                'callback_data' => 'simple_action',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => true]),
        ]);

        $adapter->handleWebhook($request, $chat);

        $this->assertSame('simple_action', $capturedActionId);
        $this->assertNull($capturedValue);
    }

    public function test_webhook_edited_message_dispatches_edit(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $chat->onMessageEdited(function ($event) use (&$dispatched) {
            $dispatched = true;
        });

        $payload = json_encode([
            'edited_message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => 222, 'type' => 'private'],
                'date' => time(),
                'edit_date' => time(),
                'text' => 'Edited message text',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Message edited handler should have been dispatched');
    }

    public function test_webhook_reaction_added_dispatches_reaction(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $capturedType = null;
        $capturedEmoji = null;
        $chat->onReaction(function ($event) use (&$dispatched, &$capturedType, &$capturedEmoji) {
            $dispatched = true;
            $capturedType = $event->type;
            $capturedEmoji = $event->emoji;
        });

        $payload = json_encode([
            'message_reaction' => [
                'chat' => ['id' => 222, 'type' => 'private'],
                'message_id' => 10,
                'user' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'date' => time(),
                'new_reaction' => [
                    ['type' => 'emoji', 'emoji' => "\xF0\x9F\x91\x8D"], // thumbs up
                ],
                'old_reaction' => [],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Reaction handler should have been dispatched');
        $this->assertSame('reaction_added', $capturedType);
        $this->assertSame("\xF0\x9F\x91\x8D", $capturedEmoji);
    }

    public function test_webhook_reaction_removed_dispatches_reaction(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $capturedTypes = [];
        $chat->onReaction(function ($event) use (&$capturedTypes) {
            $capturedTypes[] = $event->type;
        });

        $payload = json_encode([
            'message_reaction' => [
                'chat' => ['id' => 222, 'type' => 'private'],
                'message_id' => 10,
                'user' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'date' => time(),
                'new_reaction' => [],
                'old_reaction' => [
                    ['type' => 'emoji', 'emoji' => "\xF0\x9F\x91\x8D"],
                ],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertContains('reaction_removed', $capturedTypes);
    }

    public function test_webhook_channel_post_handled_as_message(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $chat->onNewMention(function ($thread, $message) use (&$dispatched) {
            $dispatched = true;
        });

        $payload = json_encode([
            'channel_post' => [
                'message_id' => 1,
                'chat' => ['id' => 123456, 'type' => 'private'],
                'date' => time(),
                'text' => 'Channel post text',
                'from' => ['id' => 333, 'is_bot' => false, 'first_name' => 'Admin'],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Channel post should be handled as a message');
    }

    public function test_webhook_skips_messages_from_self(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('999');
        $chat = $this->makeMockChat();

        $dispatched = false;
        $chat->onNewMention(function ($thread, $message) use (&$dispatched) {
            $dispatched = true;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 999, 'is_bot' => true, 'first_name' => 'Bot'],
                'chat' => ['id' => 222, 'type' => 'private'],
                'date' => time(),
                'text' => 'My own message',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertFalse($dispatched, 'Messages from self should be skipped');
    }

    public function test_webhook_private_chat_messages_flagged_as_mention(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $capturedMessage = null;
        $chat->onNewMention(function ($thread, $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => 222, 'type' => 'private'],
                'date' => time(),
                'text' => 'Hello in private',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertNotNull($capturedMessage, 'Private chat message should trigger mention handler');
        $this->assertTrue($capturedMessage->isMention);
    }

    public function test_webhook_group_message_with_bot_mention_flagged_as_mention(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
            'bot_username' => 'MyTestBot',
        ]);
        $chat = $this->makeMockChat();

        $capturedMessage = null;
        $chat->onNewMention(function ($thread, $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => -100999, 'type' => 'supergroup'],
                'date' => time(),
                'text' => 'Hey @MyTestBot what do you think?',
                'entities' => [
                    ['type' => 'mention', 'offset' => 4, 'length' => 10],
                ],
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertNotNull($capturedMessage, 'Group message with @mention should trigger mention handler');
        $this->assertTrue($capturedMessage->isMention);
    }

    public function test_webhook_group_message_without_mention_not_flagged(): void
    {
        $adapter = TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
            'bot_username' => 'MyTestBot',
        ]);
        $chat = $this->makeMockChat();

        $mentionDispatched = false;
        $chat->onNewMention(function ($thread, $message) use (&$mentionDispatched) {
            $mentionDispatched = true;
        });

        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
                'chat' => ['id' => -100999, 'type' => 'supergroup'],
                'date' => time(),
                'text' => 'Just a normal group message',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        // Since thread is not subscribed and message is not a mention,
        // the onNewMention handler should NOT fire
        $this->assertFalse($mentionDispatched, 'Group message without mention should not trigger mention handler');
    }

    public function test_webhook_edited_channel_post_dispatches_edit(): void
    {
        $adapter = $this->makeAdapter();
        $chat = $this->makeMockChat();

        $dispatched = false;
        $chat->onMessageEdited(function ($event) use (&$dispatched) {
            $dispatched = true;
        });

        $payload = json_encode([
            'edited_channel_post' => [
                'message_id' => 5,
                'chat' => ['id' => -100123, 'type' => 'channel'],
                'date' => time(),
                'edit_date' => time(),
                'text' => 'Edited channel post',
            ],
        ]);
        $request = $this->makeTelegramRequest($payload, 'test-secret');

        $adapter->handleWebhook($request, $chat);

        $this->assertTrue($dispatched, 'Edited channel post should dispatch message edited event');
    }

    // ========================================================================
    // Message Parsing Tests
    // ========================================================================

    public function test_parses_basic_text_message(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 42,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Hello world',
        ]);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('42', $message->id);
        $this->assertSame('Hello world', $message->text);
        $this->assertSame('Hello world', $message->formatted);
    }

    public function test_parses_message_with_photo_attachment(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 43,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'caption' => 'Check this photo',
            'photo' => [
                ['file_id' => 'small-id', 'file_size' => 1000, 'width' => 90, 'height' => 90],
                ['file_id' => 'medium-id', 'file_size' => 5000, 'width' => 320, 'height' => 320],
                ['file_id' => 'large-id', 'file_size' => 20000, 'width' => 800, 'height' => 800],
            ],
        ]);

        $this->assertSame('Check this photo', $message->text);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('image', $message->attachments[0]['type']);
        $this->assertSame('large-id', $message->attachments[0]['fileId']);
        $this->assertSame(800, $message->attachments[0]['width']);
        $this->assertSame(800, $message->attachments[0]['height']);
    }

    public function test_parses_message_with_document_attachment(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 44,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'document' => [
                'file_id' => 'doc-file-id',
                'file_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 54321,
            ],
        ]);

        $this->assertCount(1, $message->attachments);
        $this->assertSame('file', $message->attachments[0]['type']);
        $this->assertSame('doc-file-id', $message->attachments[0]['fileId']);
        $this->assertSame('report.pdf', $message->attachments[0]['name']);
        $this->assertSame('application/pdf', $message->attachments[0]['mimeType']);
        $this->assertSame(54321, $message->attachments[0]['size']);
    }

    public function test_parses_message_with_video_attachment(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 45,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'video' => [
                'file_id' => 'video-file-id',
                'file_name' => 'clip.mp4',
                'mime_type' => 'video/mp4',
                'file_size' => 1000000,
                'width' => 1920,
                'height' => 1080,
            ],
        ]);

        $this->assertCount(1, $message->attachments);
        $this->assertSame('video', $message->attachments[0]['type']);
        $this->assertSame('video-file-id', $message->attachments[0]['fileId']);
        $this->assertSame(1920, $message->attachments[0]['width']);
        $this->assertSame(1080, $message->attachments[0]['height']);
    }

    public function test_parses_message_with_reply_to_message(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 46,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'This is a reply',
            'reply_to_message' => [
                'message_id' => 40,
                'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Jane'],
                'text' => 'Original message',
            ],
        ]);

        $this->assertArrayHasKey('replyToMessageId', $message->metadata);
        $this->assertSame('40', $message->metadata['replyToMessageId']);
    }

    public function test_parses_message_with_edit_date(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 47,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'edit_date' => 1700000060,
            'text' => 'Edited text',
        ]);

        $this->assertTrue($message->metadata['edited']);
        $this->assertArrayHasKey('editedAt', $message->metadata);
    }

    public function test_parses_message_from_bot(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 48,
            'from' => [
                'id' => 555,
                'is_bot' => true,
                'first_name' => 'SomeBot',
                'username' => 'some_bot',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Bot message',
        ]);

        $this->assertTrue($message->author->isBot);
    }

    public function test_parses_message_from_user(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 49,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'User message',
        ]);

        $this->assertFalse($message->author->isBot);
    }

    public function test_parses_author_fields_correctly(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 50,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Test',
        ]);

        $this->assertSame('111', $message->author->userId);
        $this->assertSame('johndoe', $message->author->userName);
        $this->assertSame('John Doe', $message->author->fullName);
    }

    public function test_parses_author_without_username_uses_full_name(): void
    {
        $adapter = $this->makeAdapter();

        $message = $adapter->parseMessage([
            'message_id' => 51,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Test',
        ]);

        $this->assertSame('John Doe', $message->author->userName);
        $this->assertSame('John Doe', $message->author->fullName);
    }

    public function test_parses_is_me_when_bot_user_id_matches(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('555');

        $message = $adapter->parseMessage([
            'message_id' => 52,
            'from' => ['id' => 555, 'is_bot' => true, 'first_name' => 'Bot'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Self message',
        ]);

        $this->assertTrue($message->author->isMe);
    }

    public function test_parses_is_me_false_when_different_user(): void
    {
        $adapter = $this->makeAdapterWithBotUserId('555');

        $message = $adapter->parseMessage([
            'message_id' => 53,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => 1700000000,
            'text' => 'Other user message',
        ]);

        $this->assertFalse($message->author->isMe);
    }

    // ========================================================================
    // NotImplementedError Tests
    // ========================================================================

    public function test_fetch_messages_throws_not_implemented_error(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(NotImplementedError::class);
        $adapter->fetchMessages('telegram:123456');
    }

    public function test_fetch_message_throws_not_implemented_error(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(NotImplementedError::class);
        $adapter->fetchMessage('telegram:123456', '1');
    }

    public function test_open_modal_throws_not_implemented_error(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(NotImplementedError::class);
        $adapter->openModal('trigger-id', new \OpenCompany\Chatogrator\Cards\Modal('test', 'Test'));
    }

    public function test_list_threads_throws_not_implemented_error(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(NotImplementedError::class);
        $adapter->listThreads('-100123');
    }

    // ========================================================================
    // Other Method Tests
    // ========================================================================

    public function test_open_dm_returns_telegram_user_id_format(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->openDM('123456');

        $this->assertSame('telegram:123456', $threadId);
    }

    public function test_post_ephemeral_returns_null(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->postEphemeral(
            'telegram:123456',
            '111',
            \OpenCompany\Chatogrator\Messages\PostableMessage::text('ephemeral text')
        );

        $this->assertNull($result);
    }

    public function test_fetch_thread_returns_thread_info(): void
    {
        $adapter = $this->makeAdapter();

        $threadInfo = $adapter->fetchThread('telegram:123456');

        $this->assertInstanceOf(ThreadInfo::class, $threadInfo);
        $this->assertSame('telegram:123456', $threadInfo->id);
        $this->assertSame('123456', $threadInfo->channelId);
    }

    public function test_fetch_thread_is_dm_for_positive_chat_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadInfo = $adapter->fetchThread('telegram:123456');

        $this->assertTrue($threadInfo->isDM);
    }

    public function test_fetch_thread_is_not_dm_for_negative_chat_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadInfo = $adapter->fetchThread('telegram:-987654');

        $this->assertFalse($threadInfo->isDM);
    }

    public function test_render_formatted_delegates_to_converter(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('**bold text**');

        // TelegramFormatConverter converts markdown bold to HTML <b> tags
        $this->assertSame('<b>bold text</b>', $result);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeAdapter(): TelegramAdapter
    {
        return TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
        ]);
    }

    private function makeAdapterWithBotUserId(string $botUserId): TelegramAdapter
    {
        return TelegramAdapter::fromConfig([
            'bot_token' => 'test-bot-token',
            'webhook_secret' => 'test-secret',
            'bot_user_id' => $botUserId,
        ]);
    }

    private function makeTelegramRequest(string $body, ?string $secret = null): Request
    {
        $serverParams = [
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($secret !== null) {
            $serverParams['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = $secret;
        }

        return Request::create(
            uri: '/webhook',
            method: 'POST',
            server: $serverParams,
            content: $body,
        );
    }

    private function makeMockChat(): Chat
    {
        $chat = Chat::make('test');
        $chat->state(new MockStateAdapter);

        return $chat;
    }

    private function makeBasicMessagePayload(array $overrides = []): array
    {
        return array_merge([
            'message_id' => 1,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
            ],
            'chat' => ['id' => 222, 'type' => 'private'],
            'date' => time(),
            'text' => 'Hello world',
        ], $overrides);
    }
}
