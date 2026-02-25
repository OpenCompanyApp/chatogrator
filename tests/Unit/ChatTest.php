<?php

namespace OpenCompany\Chatogrator\Tests\Unit;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * @group core
 */
class ChatTest extends TestCase
{
    protected Chat $chat;

    protected MockAdapter $mockAdapter;

    protected MockStateAdapter $mockState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAdapter = new MockAdapter('slack');
        $this->mockState = new MockStateAdapter;

        $this->chat = Chat::make('testbot')
            ->adapter('slack', $this->mockAdapter)
            ->state($this->mockState);
    }

    // ── Initialization ──────────────────────────────────────────────

    public function test_it_registers_adapters(): void
    {
        $this->assertSame($this->mockAdapter, $this->chat->getAdapter('slack'));
    }

    public function test_it_registers_state_adapter(): void
    {
        $this->assertSame($this->mockState, $this->chat->getStateAdapter());
    }

    public function test_it_returns_null_for_unknown_adapter(): void
    {
        $this->assertNull($this->chat->getAdapter('teams'));
    }

    public function test_it_has_a_name(): void
    {
        $this->assertSame('testbot', $this->chat->getName());
    }

    // ── Handler registration ────────────────────────────────────────

    public function test_on_new_mention_registers_handler(): void
    {
        $called = false;

        $this->chat->onNewMention(function (Thread $thread, Message $message) use (&$called) {
            $called = true;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help me');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($called);
    }

    public function test_on_subscribed_message_registers_handler(): void
    {
        $called = false;

        $this->chat->onSubscribedMessage(function (Thread $thread, Message $message) use (&$called) {
            $called = true;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Follow up message');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($called);
    }

    public function test_on_new_message_registers_handler_with_pattern(): void
    {
        $called = false;

        $this->chat->onNewMessage('/help/i', function (Thread $thread, Message $message) use (&$called) {
            $called = true;
        });

        $message = TestMessageFactory::make('msg-1', 'Can someone help me?');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($called);
    }

    // ── Message handling ────────────────────────────────────────────

    public function test_it_acquires_and_releases_lock_during_message_handling(): void
    {
        $this->chat->onNewMention(function () {});

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $acquireCalls = $this->mockState->wasCalledWith('acquireLock');
        $releaseCalls = $this->mockState->wasCalledWith('releaseLock');

        $this->assertNotEmpty($acquireCalls, 'acquireLock should have been called');
        $this->assertNotEmpty($releaseCalls, 'releaseLock should have been called');
    }

    public function test_it_skips_messages_from_self(): void
    {
        $called = false;

        $this->chat->onNewMention(function () use (&$called) {
            $called = true;
        });

        $message = TestMessageFactory::fromBot('msg-1', 'I am the bot');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertFalse($called, 'Handler should not be called for self-messages');
    }

    public function test_it_deduplicates_messages(): void
    {
        $callCount = 0;

        $this->chat->onNewMention(function () use (&$callCount) {
            $callCount++;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);
        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertSame(1, $callCount, 'Duplicate messages should be skipped');
    }

    public function test_pattern_matching_does_not_trigger_for_non_matching_text(): void
    {
        $called = false;

        $this->chat->onNewMessage('/deploy/i', function () use (&$called) {
            $called = true;
        });

        $message = TestMessageFactory::make('msg-1', 'What is the weather?');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertFalse($called, 'Handler should not be called for non-matching pattern');
    }

    // ── isMention detection ─────────────────────────────────────────

    public function test_is_mention_true_when_bot_is_mentioned(): void
    {
        $capturedMessage = null;

        $this->chat->onNewMention(function (Thread $thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help me', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedMessage);
        $this->assertTrue($capturedMessage->isMention);
    }

    public function test_is_mention_false_when_bot_not_mentioned(): void
    {
        $capturedMessage = null;

        $this->chat->onNewMessage('/help/i', function (Thread $thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $message = TestMessageFactory::make('msg-1', 'I need help', ['isMention' => false]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedMessage);
        $this->assertFalse($capturedMessage->isMention);
    }

    // ── Subscribed thread routing ───────────────────────────────────

    public function test_subscribed_message_handler_takes_priority_over_mention(): void
    {
        $mentionCalled = false;
        $subscribedCalled = false;

        $this->chat->onNewMention(function () use (&$mentionCalled) {
            $mentionCalled = true;
        });

        $this->chat->onSubscribedMessage(function () use (&$subscribedCalled) {
            $subscribedCalled = true;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot are you there?', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($subscribedCalled, 'onSubscribedMessage should be called for subscribed threads');
        $this->assertFalse($mentionCalled, 'onNewMention should NOT be called when thread is subscribed');
    }

    public function test_mention_handler_fires_for_unsubscribed_threads(): void
    {
        $mentionCalled = false;
        $subscribedCalled = false;

        $this->chat->onNewMention(function () use (&$mentionCalled) {
            $mentionCalled = true;
        });

        $this->chat->onSubscribedMessage(function () use (&$subscribedCalled) {
            $subscribedCalled = true;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help me', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($mentionCalled, 'onNewMention should fire for unsubscribed threads');
        $this->assertFalse($subscribedCalled, 'onSubscribedMessage should NOT fire for unsubscribed threads');
    }

    // ── Thread provided to handler ──────────────────────────────────

    public function test_handler_receives_thread_with_correct_id(): void
    {
        $capturedThread = null;

        $this->chat->onNewMention(function (Thread $thread) use (&$capturedThread) {
            $capturedThread = $thread;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedThread);
        $this->assertSame('slack:C123:1234.5678', $capturedThread->id);
    }

    public function test_thread_can_post_messages(): void
    {
        $this->chat->onNewMention(function (Thread $thread) {
            $thread->post('Hello from the bot!');
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotEmpty($this->mockAdapter->postedMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->postedMessages[0]['threadId']);
    }

    // ── Action handling ─────────────────────────────────────────────

    public function test_on_action_handler_receives_all_actions(): void
    {
        $capturedActionId = null;
        $capturedValue = null;

        $this->chat->onAction(function ($event) use (&$capturedActionId, &$capturedValue) {
            $capturedActionId = $event['actionId'];
            $capturedValue = $event['value'] ?? null;
        });

        $this->chat->processAction([
            'actionId' => 'approve',
            'value' => 'order-123',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertSame('approve', $capturedActionId);
        $this->assertSame('order-123', $capturedValue);
    }

    public function test_on_action_filters_by_action_id(): void
    {
        $approveCount = 0;

        $this->chat->onAction(['approve', 'reject'], function () use (&$approveCount) {
            $approveCount++;
        });

        $this->chat->processAction([
            'actionId' => 'approve',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->chat->processAction([
            'actionId' => 'skip',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertSame(1, $approveCount, 'Only matching action IDs should fire the handler');
    }

    public function test_on_action_filters_by_single_action_id(): void
    {
        $called = false;

        $this->chat->onAction('approve', function () use (&$called) {
            $called = true;
        });

        $this->chat->processAction([
            'actionId' => 'approve',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertTrue($called);
    }

    public function test_on_action_skips_self_actions(): void
    {
        $called = false;

        $this->chat->onAction(function () use (&$called) {
            $called = true;
        });

        $this->chat->processAction([
            'actionId' => 'approve',
            'user' => new Author('BOT', 'testbot', 'Test Bot', true, true),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertFalse($called, 'Actions from self should be skipped');
    }

    public function test_on_action_provides_thread_in_event(): void
    {
        $capturedThread = null;

        $this->chat->onAction(function ($event) use (&$capturedThread) {
            $capturedThread = $event['thread'] ?? null;
        });

        $this->chat->processAction([
            'actionId' => 'approve',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertNotNull($capturedThread);
        $this->assertInstanceOf(Thread::class, $capturedThread);
        $this->assertSame('slack:C123:1234.5678', $capturedThread->id);
    }

    // ── Reaction handling ───────────────────────────────────────────

    public function test_on_reaction_handler_receives_all_reactions(): void
    {
        $capturedEmoji = null;

        $this->chat->onReaction(function ($event) use (&$capturedEmoji) {
            $capturedEmoji = $event['emoji'];
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => true,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertSame('thumbs_up', $capturedEmoji);
    }

    public function test_on_reaction_filters_by_emoji(): void
    {
        $callCount = 0;

        $this->chat->onReaction(['thumbs_up', 'heart'], function () use (&$callCount) {
            $callCount++;
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => true,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->chat->processReaction([
            'emoji' => 'fire',
            'rawEmoji' => 'fire',
            'added' => true,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertSame(1, $callCount, 'Only matching emojis should fire the handler');
    }

    public function test_on_reaction_skips_self_reactions(): void
    {
        $called = false;

        $this->chat->onReaction(function () use (&$called) {
            $called = true;
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => true,
            'user' => new Author('BOT', 'testbot', 'Test Bot', true, true),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertFalse($called, 'Reactions from self should be skipped');
    }

    public function test_on_reaction_handles_removed_reactions(): void
    {
        $wasAdded = null;

        $this->chat->onReaction(function ($event) use (&$wasAdded) {
            $wasAdded = $event['added'];
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => false,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertFalse($wasAdded);
    }

    public function test_on_reaction_provides_thread(): void
    {
        $capturedThread = null;

        $this->chat->onReaction(function ($event) use (&$capturedThread) {
            $capturedThread = $event['thread'] ?? null;
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => true,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertNotNull($capturedThread);
        $this->assertInstanceOf(Thread::class, $capturedThread);
        $this->assertSame('slack:C123:1234.5678', $capturedThread->id);
    }

    public function test_on_reaction_matches_raw_emoji(): void
    {
        $called = false;

        $this->chat->onReaction(['+1'], function () use (&$called) {
            $called = true;
        });

        $this->chat->processReaction([
            'emoji' => 'thumbs_up',
            'rawEmoji' => '+1',
            'added' => true,
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'messageId' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertTrue($called, 'Should match by rawEmoji when specified');
    }

    // ── Slash command handling ───────────────────────────────────────

    public function test_on_slash_command_receives_all_commands(): void
    {
        $capturedCommand = null;
        $capturedText = null;

        $this->chat->onSlashCommand(function ($event) use (&$capturedCommand, &$capturedText) {
            $capturedCommand = $event['command'];
            $capturedText = $event['text'] ?? null;
        });

        $this->chat->processSlashCommand([
            'command' => '/help',
            'text' => 'topic',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => ['channel_id' => 'C456'],
            'channelId' => 'slack:C456',
        ]);

        $this->assertSame('/help', $capturedCommand);
        $this->assertSame('topic', $capturedText);
    }

    public function test_on_slash_command_filters_by_command_name(): void
    {
        $helpCalled = false;
        $statusCalled = false;

        $this->chat->onSlashCommand('/help', function () use (&$helpCalled) {
            $helpCalled = true;
        });

        $this->chat->onSlashCommand('/status', function () use (&$statusCalled) {
            $statusCalled = true;
        });

        $this->chat->processSlashCommand([
            'command' => '/help',
            'text' => '',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => ['channel_id' => 'C456'],
            'channelId' => 'slack:C456',
        ]);

        $this->assertTrue($helpCalled);
        $this->assertFalse($statusCalled);
    }

    public function test_on_slash_command_filters_by_multiple_commands(): void
    {
        $callCount = 0;

        $this->chat->onSlashCommand(['/status', '/health'], function () use (&$callCount) {
            $callCount++;
        });

        foreach (['/status', '/health', '/help'] as $command) {
            $this->chat->processSlashCommand([
                'command' => $command,
                'text' => '',
                'user' => new Author('U123', 'user', 'Test User', false, false),
                'adapter' => $this->mockAdapter,
                'raw' => ['channel_id' => 'C456'],
                'channelId' => 'slack:C456',
            ]);
        }

        $this->assertSame(2, $callCount, 'Only /status and /health should match');
    }

    public function test_on_slash_command_skips_self_commands(): void
    {
        $called = false;

        $this->chat->onSlashCommand(function () use (&$called) {
            $called = true;
        });

        $this->chat->processSlashCommand([
            'command' => '/help',
            'text' => '',
            'user' => new Author('BOT', 'testbot', 'Test Bot', true, true),
            'adapter' => $this->mockAdapter,
            'raw' => ['channel_id' => 'C456'],
            'channelId' => 'slack:C456',
        ]);

        $this->assertFalse($called, 'Slash commands from self should be skipped');
    }

    public function test_on_slash_command_normalizes_without_leading_slash(): void
    {
        $called = false;

        $this->chat->onSlashCommand('help', function () use (&$called) {
            $called = true;
        });

        $this->chat->processSlashCommand([
            'command' => '/help',
            'text' => '',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => ['channel_id' => 'C456'],
            'channelId' => 'slack:C456',
        ]);

        $this->assertTrue($called, 'Command registered without slash should match /help');
    }

    public function test_on_slash_command_runs_both_specific_and_catch_all(): void
    {
        $specificCalled = false;
        $catchAllCalled = false;

        $this->chat->onSlashCommand('/help', function () use (&$specificCalled) {
            $specificCalled = true;
        });

        $this->chat->onSlashCommand(function () use (&$catchAllCalled) {
            $catchAllCalled = true;
        });

        $this->chat->processSlashCommand([
            'command' => '/help',
            'text' => '',
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => ['channel_id' => 'C456'],
            'channelId' => 'slack:C456',
        ]);

        $this->assertTrue($specificCalled);
        $this->assertTrue($catchAllCalled);
    }

    // ── Modal submit handling ───────────────────────────────────────

    public function test_on_modal_submit_handler_receives_event(): void
    {
        $capturedValues = null;

        $this->chat->onModalSubmit('feedback_modal', function ($event) use (&$capturedValues) {
            $capturedValues = $event['values'] ?? null;
        });

        $this->chat->processModalSubmit([
            'callbackId' => 'feedback_modal',
            'viewId' => 'V123',
            'values' => ['message' => 'Great feedback!'],
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertSame(['message' => 'Great feedback!'], $capturedValues);
    }

    public function test_on_modal_submit_filters_by_callback_id(): void
    {
        $feedbackCalled = false;
        $settingsCalled = false;

        $this->chat->onModalSubmit('feedback', function () use (&$feedbackCalled) {
            $feedbackCalled = true;
        });

        $this->chat->onModalSubmit('settings', function () use (&$settingsCalled) {
            $settingsCalled = true;
        });

        $this->chat->processModalSubmit([
            'callbackId' => 'feedback',
            'viewId' => 'V123',
            'values' => [],
            'user' => new Author('U123', 'user', 'Test User', false, false),
            'adapter' => $this->mockAdapter,
            'raw' => [],
        ]);

        $this->assertTrue($feedbackCalled);
        $this->assertFalse($settingsCalled);
    }
}
