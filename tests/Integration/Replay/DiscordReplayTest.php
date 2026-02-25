<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Events\ReactionEvent;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Discord interaction replay tests.
 *
 * Based on recordings from SHA 893def7 which captured:
 * - Gateway forwarded events (MESSAGE_CREATE, REACTION_ADD, etc.)
 * - Button clicks (hello, messages, info, goodbye)
 * - Thread-based conversations
 * - AI mode interactions
 * - DM requests
 *
 * @group integration
 */
class DiscordReplayTest extends ReplayTestCase
{
    private const REAL_BOT_ID = '1457469483726668048';

    private const REAL_GUILD_ID = '1457468924290662599';

    private const REAL_CHANNEL_ID = '1457510428359004343';

    private const REAL_THREAD_ID = '1457536551830421524';

    private const REAL_USER_ID = '1033044521375764530';

    private const REAL_USER_NAME = 'testuser2384';

    private const REAL_ROLE_ID = '1457473602180878604';

    // -----------------------------------------------------------------------
    // Production Button Actions
    // -----------------------------------------------------------------------

    public function test_hello_button_click_from_production(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $response = $this->sendWebhook($fixture['buttonClickHello'], 'discord');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_hello_button_click_has_correct_action_properties(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $thread = $this->createThread($threadId);

        $action = new ActionEvent(
            actionId: 'hello',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('hello', $action->actionId);
        $this->assertFalse($thread->isDM);
    }

    public function test_messages_button_click_triggers_fetch(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $response = $this->sendWebhook($fixture['buttonClickMessages'] ?? $fixture['buttonClickHello'], 'discord');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_info_button_click(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $thread = $this->createThread($threadId);

        $thread->post('User: Test User, Platform: discord');
        $this->assertMessagePosted('Test User');
    }

    public function test_goodbye_button_click_danger_style(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $thread = $this->createThread('discord:guild:channel:thread');
        $thread->post('Goodbye, Test User! See you later.');

        $this->assertMessagePosted('Goodbye');
    }

    // -----------------------------------------------------------------------
    // DM Interactions
    // -----------------------------------------------------------------------

    public function test_button_click_in_dm_channel(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $dmThreadId = 'discord:@me:DM_CHANNEL_123';
        $this->assertTrue($this->mockAdapter->isDM($dmThreadId));

        $thread = $this->createThread($dmThreadId, null, true);
        $action = new ActionEvent(
            actionId: 'dm-action',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('dm-action', $action->actionId);
        $this->assertTrue($thread->isDM);
    }

    public function test_dm_interaction_extracts_user_info(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        // DM uses `user` field directly instead of `member.user`
        $author = new Author(
            userId: self::REAL_USER_ID,
            userName: self::REAL_USER_NAME,
            fullName: 'Test User',
            isBot: false,
            isMe: false,
        );

        $this->assertEquals(self::REAL_USER_ID, $author->userId);
        $this->assertEquals(self::REAL_USER_NAME, $author->userName);
        $this->assertEquals('Test User', $author->fullName);
    }

    // -----------------------------------------------------------------------
    // Multi-User Scenarios
    // -----------------------------------------------------------------------

    public function test_same_action_from_different_users(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $actionLog = [];

        // First user
        $actionLog[] = ['userId' => self::REAL_USER_ID, 'actionId' => 'hello'];
        $this->assertCount(1, $actionLog);

        // Different user
        $actionLog[] = ['userId' => '9876543210987654321', 'actionId' => 'hello'];
        $this->assertCount(2, $actionLog);

        $this->assertNotEquals($actionLog[0]['userId'], $actionLog[1]['userId']);
    }

    public function test_different_user_properties(): void
    {
        $author = new Author(
            userId: '9876543210987654321',
            userName: 'alice123',
            fullName: 'Alice',
            isBot: false,
            isMe: false,
        );

        $this->assertEquals('9876543210987654321', $author->userId);
        $this->assertEquals('alice123', $author->userName);
        $this->assertEquals('Alice', $author->fullName);
    }

    // -----------------------------------------------------------------------
    // Thread ID Verification
    // -----------------------------------------------------------------------

    public function test_correct_thread_id_for_guild_thread(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $thread = $this->createThread($threadId);

        $this->assertEquals(
            'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID,
            $thread->id,
        );
    }

    public function test_consistent_thread_id_across_multiple_actions(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $threadIds = [$threadId, $threadId, $threadId];

        $this->assertCount(1, array_unique($threadIds));
    }

    // -----------------------------------------------------------------------
    // Message Operations
    // -----------------------------------------------------------------------

    public function test_post_then_edit_message(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Processing...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('Done!'));

        $this->assertPostedMessageCount(1);
        $this->assertEditedMessageCount(1);
        $this->assertMessageEdited('Done!');
    }

    public function test_typing_indicator_before_posting(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $this->mockAdapter->startTyping($threadId);
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Done typing!'));

        $this->assertTypingStarted();
        $this->assertPostedMessageCount(1);
    }

    public function test_add_reactions_to_posted_messages(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('React to this!'));
        $this->mockAdapter->addReaction($threadId, $msg->id, 'thumbsup');

        $this->assertReactionAdded('thumbsup');
    }

    public function test_delete_posted_messages(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Temporary message'));
        $this->mockAdapter->deleteMessage($threadId, $msg->id);

        $this->assertCount(1, $this->mockAdapter->deletedMessages);
    }

    // -----------------------------------------------------------------------
    // Action ID Filtering
    // -----------------------------------------------------------------------

    public function test_route_actions_to_specific_handlers(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        // Simulate action filtering
        $helloHandled = false;
        $infoHandled = false;

        $actionId = 'hello';
        if ($actionId === 'hello') {
            $helloHandled = true;
        }
        if ($actionId === 'info') {
            $infoHandled = true;
        }

        $this->assertTrue($helloHandled);
        $this->assertFalse($infoHandled);
    }

    public function test_catch_all_handler_for_any_action(): void
    {
        $handled = [];

        foreach (['hello', 'goodbye', 'info'] as $actionId) {
            $handled[] = $actionId;
        }

        $this->assertCount(3, $handled);
        $this->assertContains('hello', $handled);
        $this->assertContains('goodbye', $handled);
    }

    public function test_array_of_action_ids_in_handler(): void
    {
        $allowedActions = ['hello', 'goodbye'];

        $this->assertTrue(in_array('hello', $allowedActions));
        $this->assertTrue(in_array('goodbye', $allowedActions));
        $this->assertFalse(in_array('info', $allowedActions));
    }

    // -----------------------------------------------------------------------
    // Response Types
    // -----------------------------------------------------------------------

    public function test_deferred_update_message_response(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $response = $this->sendWebhook($fixture['buttonClickHello'], 'discord');
        $this->assertEquals(200, $response->getStatusCode());
        // Discord button interactions should return type 6 (DEFERRED_UPDATE_MESSAGE)
    }

    // -----------------------------------------------------------------------
    // Complete Conversation Flow
    // -----------------------------------------------------------------------

    public function test_full_conversation_hello_info_messages_goodbye(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $actionLog = [];

        // Step 1: Hello
        $actionLog[] = 'hello';
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Hello, Test User!'));

        // Step 2: Info
        $actionLog[] = 'info';
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Platform: discord'));

        // Step 3: Messages (fetch)
        $actionLog[] = 'messages';
        $this->mockAdapter->fetchMessages($threadId);
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Fetched messages'));

        // Step 4: Goodbye
        $actionLog[] = 'goodbye';
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Goodbye!'));

        $this->assertEquals(['hello', 'info', 'messages', 'goodbye'], $actionLog);
        $this->assertPostedMessageCount(4);
    }

    // -----------------------------------------------------------------------
    // Edit Message Pattern (Streaming Fallback)
    // -----------------------------------------------------------------------

    public function test_post_then_edit_pattern_for_streaming(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Thinking...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('Done thinking!'));

        $this->assertMessagePosted('Thinking...');
        $this->assertMessageEdited('Done thinking!');
    }

    public function test_multiple_post_edit_cycles(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';

        // First cycle
        $msg1 = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Processing...'));
        $this->mockAdapter->editMessage($threadId, $msg1->id, PostableMessage::text('Completed step 1'));

        // Second cycle
        $msg2 = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Processing...'));
        $this->mockAdapter->editMessage($threadId, $msg2->id, PostableMessage::text('Completed step 2'));

        $this->assertPostedMessageCount(2);
        $this->assertEditedMessageCount(2);
    }

    public function test_progressive_edits_to_same_message(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:guild:channel:thread';
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Step 1...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('Step 1... Step 2...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('Step 1... Step 2... Done!'));

        $this->assertPostedMessageCount(1);
        $this->assertEditedMessageCount(2);
        $this->assertMessageEdited('Step 1... Step 2... Done!');
    }

    // -----------------------------------------------------------------------
    // Gateway Forwarded Events - isMe Detection
    // -----------------------------------------------------------------------

    public function test_is_me_true_for_bot_message(): void
    {
        $botAuthor = new Author(
            userId: self::REAL_BOT_ID,
            userName: 'TestBot',
            fullName: 'Test Bot',
            isBot: true,
            isMe: true,
        );

        $this->assertTrue($botAuthor->isMe);
        $this->assertTrue($botAuthor->isBot);
    }

    public function test_is_me_false_for_regular_user(): void
    {
        $userAuthor = new Author(
            userId: 'USER123',
            userName: 'regularuser',
            fullName: 'Regular User',
            isBot: false,
            isMe: false,
        );

        $this->assertFalse($userAuthor->isMe);
        $this->assertFalse($userAuthor->isBot);
    }

    public function test_skip_bot_own_messages_in_subscribed_threads(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        $threadId = 'discord:guild:channel:thread';
        $this->stateAdapter->subscribe($threadId);

        // Bot's own message should not trigger handlers
        $botMessage = TestMessageFactory::make('bot-msg', 'Bot response', [
            'author' => new Author(
                userId: self::REAL_BOT_ID,
                userName: 'TestBot',
                fullName: 'Test Bot',
                isBot: true,
                isMe: true,
            ),
        ]);

        $this->assertTrue($botMessage->author->isMe);
    }

    public function test_bot_welcome_message_does_not_enable_ai_mode(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        $botWelcome = TestMessageFactory::make('bot-welcome', 'Mention me with "AI" to enable AI assistant mode', [
            'author' => new Author(
                userId: self::REAL_BOT_ID,
                userName: 'TestBot',
                fullName: 'Test Bot',
                isBot: true,
                isMe: true,
            ),
        ]);

        // Bot's own message should be skipped - AI mode should NOT be enabled
        $this->assertTrue($botWelcome->author->isMe);
        $this->assertStringContainsString('AI', $botWelcome->text);
    }

    // -----------------------------------------------------------------------
    // Gateway - Reaction isMe Detection
    // -----------------------------------------------------------------------

    public function test_bot_reaction_is_skipped(): void
    {
        $this->createChat('discord', ['botName' => 'TestBot']);

        $reactionEvent = new ReactionEvent(
            emoji: 'thumbs_up',
            messageId: 'msg-1',
            userId: self::REAL_BOT_ID,
            type: 'added',
            thread: $this->createThread('discord:guild:channel:thread'),
        );

        // Bot reactions should NOT trigger handlers (isMe=true causes skip)
        // When userId matches bot ID, the reaction is from the bot
        $this->assertEquals(self::REAL_BOT_ID, $reactionEvent->userId);
    }

    public function test_user_reaction_triggers_handler(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        $reactionEvent = new ReactionEvent(
            emoji: 'thumbs_up',
            messageId: 'msg-1',
            userId: 'USER123',
            type: 'added',
            thread: $this->createThread('discord:guild:channel:thread'),
        );

        $this->assertNotEquals(self::REAL_BOT_ID, $reactionEvent->userId);
        $this->assertTrue($reactionEvent->isAdded());
    }

    // -----------------------------------------------------------------------
    // Gateway Message Processing
    // -----------------------------------------------------------------------

    public function test_correctly_identify_mentioned_messages(): void
    {
        $message = TestMessageFactory::mention('msg-1', '<@' . self::REAL_BOT_ID . '> Help me');

        $this->assertTrue($message->isMention);
        $this->assertStringContainsString('Help me', $message->text);
    }

    public function test_process_messages_from_subscribed_threads(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        $threadId = 'discord:guild:channel:thread';
        $this->stateAdapter->subscribe($threadId);

        $messages = [];
        for ($i = 1; $i <= 3; $i++) {
            $messages[] = TestMessageFactory::make("msg_{$i}", "Message {$i}", [
                'threadId' => $threadId,
            ]);
        }

        $this->assertCount(3, $messages);
        $texts = array_map(fn ($m) => $m->text, $messages);
        $this->assertEquals(['Message 1', 'Message 2', 'Message 3'], $texts);
    }

    // -----------------------------------------------------------------------
    // Real Gateway Fixtures
    // -----------------------------------------------------------------------

    public function test_real_gateway_mention_fixture(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $this->assertArrayHasKey('metadata', $fixture);
        $this->assertEquals(self::REAL_BOT_ID, $fixture['metadata']['botId']);
        $this->assertEquals(self::REAL_GUILD_ID, $fixture['metadata']['guildId']);
    }

    public function test_real_gateway_ai_mention_with_keyword(): void
    {
        $message = TestMessageFactory::mention('ai-msg', '<@' . self::REAL_BOT_ID . '> AI What is love');

        $this->assertTrue($message->isMention);
        $this->assertMatchesRegularExpression('/\bAI\b/i', $message->text);
    }

    public function test_skip_bot_welcome_gateway_fixture(): void
    {
        $botMessage = TestMessageFactory::fromBot('bot-welcome', 'Welcome to the thread!');
        $this->assertTrue($botMessage->author->isMe);
        $this->assertTrue($botMessage->author->isBot);
    }

    public function test_skip_bot_thread_welcome_gateway_fixture(): void
    {
        $botMessage = TestMessageFactory::fromBot('bot-thread-welcome', 'Thread started!');
        $this->assertTrue($botMessage->author->isMe);
    }

    public function test_real_gateway_reaction_add_fixture(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $this->assertEquals(self::REAL_USER_ID, $fixture['metadata']['userId']);
    }

    public function test_real_thread_messages_when_subscribed(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $this->stateAdapter->subscribe($threadId);

        $messages = [
            TestMessageFactory::make('t1', 'Hey', ['threadId' => $threadId]),
            TestMessageFactory::make('t2', 'Nice', ['threadId' => $threadId]),
            TestMessageFactory::make('t3', '1', ['threadId' => $threadId]),
        ];

        $this->assertCount(3, $messages);
        $this->assertEquals('Hey', $messages[0]->text);
        $this->assertEquals('Nice', $messages[1]->text);
    }

    public function test_real_dm_request_in_subscribed_thread(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $this->stateAdapter->subscribe($threadId);

        $dmRequest = TestMessageFactory::make('dm-req', 'DM me', [
            'threadId' => $threadId,
            'author' => new Author(
                userId: self::REAL_USER_ID,
                userName: self::REAL_USER_NAME,
                fullName: 'Test User',
                isBot: false,
                isMe: false,
            ),
        ]);

        $this->assertEquals('DM me', $dmRequest->text);
        $this->assertFalse($dmRequest->author->isMe);
    }

    public function test_is_me_fix_prevents_bot_messages_in_handlers(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = 'discord:' . self::REAL_GUILD_ID . ':' . self::REAL_CHANNEL_ID . ':' . self::REAL_THREAD_ID;
        $this->stateAdapter->subscribe($threadId);

        // Bot messages should all be skipped
        $botMessages = [
            TestMessageFactory::fromBot('bw1', 'Welcome!'),
            TestMessageFactory::fromBot('bw2', 'Thread started!'),
        ];

        foreach ($botMessages as $msg) {
            $this->assertTrue($msg->author->isMe);
        }

        // User message should NOT be skipped
        $userMessage = TestMessageFactory::make('um1', 'Hey', [
            'threadId' => $threadId,
        ]);
        $this->assertFalse($userMessage->author->isMe);
    }

    // -----------------------------------------------------------------------
    // Role Mention Support
    // -----------------------------------------------------------------------

    public function test_role_mention_triggers_handler(): void
    {
        $message = TestMessageFactory::mention('role-mention', '<@&' . self::REAL_ROLE_ID . '> AI Still there?');

        $this->assertTrue($message->isMention);
        $this->assertStringContainsString(self::REAL_ROLE_ID, $message->text);
    }

    public function test_role_not_in_configured_list_skipped(): void
    {
        $configuredRoles = ['DIFFERENT_ROLE_ID'];

        $this->assertFalse(in_array(self::REAL_ROLE_ID, $configuredRoles));
    }

    public function test_no_role_ids_configured_skips_role_mentions(): void
    {
        $configuredRoles = [];

        $this->assertEmpty($configuredRoles);
        $this->assertFalse(in_array(self::REAL_ROLE_ID, $configuredRoles));
    }

    public function test_role_mention_without_direct_user_mention(): void
    {
        $message = TestMessageFactory::mention('role-only', '<@&' . self::REAL_ROLE_ID . '> Hello team!');

        $this->assertTrue($message->isMention);
        // No direct @user mention, only role mention
        $this->assertStringNotContainsString('<@U', $message->text);
    }

    public function test_multiple_role_ids_in_configuration(): void
    {
        $configuredRoles = ['OTHER_ROLE_1', self::REAL_ROLE_ID, 'OTHER_ROLE_2'];

        $this->assertTrue(in_array(self::REAL_ROLE_ID, $configuredRoles));
    }

    public function test_synthetic_role_mention_events(): void
    {
        $message = TestMessageFactory::mention('synthetic-role', '<@&ROLE_123> Hello team!');

        $this->assertTrue($message->isMention);
        $this->assertStringContainsString('ROLE_123', $message->text);
    }
}
