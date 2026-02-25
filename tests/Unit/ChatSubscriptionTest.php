<?php

namespace OpenCompany\Chatogrator\Tests\Unit;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * @group core
 */
class ChatSubscriptionTest extends TestCase
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

    // ── Subscribe / Unsubscribe flow ────────────────────────────────

    public function test_subscribe_marks_thread_as_subscribed(): void
    {
        $this->mockState->subscribe('slack:C123:1234.5678');

        $this->assertTrue($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    public function test_unsubscribe_marks_thread_as_not_subscribed(): void
    {
        $this->mockState->subscribe('slack:C123:1234.5678');
        $this->mockState->unsubscribe('slack:C123:1234.5678');

        $this->assertFalse($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    public function test_is_subscribed_returns_false_for_unknown_thread(): void
    {
        $this->assertFalse($this->mockState->isSubscribed('slack:C999:never.subscribed'));
    }

    // ── onSubscribedMessage gets called for subscribed threads ──────

    public function test_on_subscribed_message_called_for_subscribed_thread(): void
    {
        $called = false;

        $this->chat->onSubscribedMessage(function (Thread $thread, Message $message) use (&$called) {
            $called = true;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Follow up message');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($called, 'onSubscribedMessage should be called for subscribed threads');
    }

    public function test_on_subscribed_message_not_called_for_unsubscribed_thread(): void
    {
        $called = false;

        $this->chat->onSubscribedMessage(function () use (&$called) {
            $called = true;
        });

        $message = TestMessageFactory::make('msg-1', 'Random message');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertFalse($called, 'onSubscribedMessage should NOT be called for unsubscribed threads');
    }

    // ── onNewMention NOT called when thread is subscribed ───────────

    public function test_on_new_mention_not_called_when_thread_is_subscribed(): void
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

        $this->assertTrue($subscribedCalled, 'onSubscribedMessage should fire');
        $this->assertFalse($mentionCalled, 'onNewMention should NOT fire when subscribed');
    }

    // ── Thread.isSubscribed() from handler context ──────────────────

    public function test_thread_is_subscribed_returns_true_in_subscribed_context(): void
    {
        $capturedThread = null;

        $this->chat->onSubscribedMessage(function (Thread $thread) use (&$capturedThread) {
            $capturedThread = $thread;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Follow up');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedThread);
        $this->assertTrue($capturedThread->isSubscribed());
    }

    public function test_thread_is_subscribed_returns_false_in_mention_context(): void
    {
        $capturedThread = null;

        $this->chat->onNewMention(function (Thread $thread) use (&$capturedThread) {
            $capturedThread = $thread;
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedThread);
        $this->assertFalse($capturedThread->isSubscribed());
    }

    // ── Subscribe from handler and verify persistence ───────────────

    public function test_thread_subscribe_from_handler_persists(): void
    {
        $this->chat->onNewMention(function (Thread $thread) {
            $thread->subscribe();
        });

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot help', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertTrue($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    public function test_thread_unsubscribe_from_handler_removes_subscription(): void
    {
        $this->mockState->subscribe('slack:C123:1234.5678');

        $this->chat->onSubscribedMessage(function (Thread $thread) {
            $thread->unsubscribe();
        });

        $message = TestMessageFactory::make('msg-1', 'Goodbye');

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertFalse($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    // ── Multiple subscriptions ──────────────────────────────────────

    public function test_different_threads_have_independent_subscriptions(): void
    {
        $this->mockState->subscribe('slack:C123:1111');

        $this->assertTrue($this->mockState->isSubscribed('slack:C123:1111'));
        $this->assertFalse($this->mockState->isSubscribed('slack:C123:2222'));

        $this->mockState->subscribe('slack:C123:2222');

        $this->assertTrue($this->mockState->isSubscribed('slack:C123:1111'));
        $this->assertTrue($this->mockState->isSubscribed('slack:C123:2222'));

        $this->mockState->unsubscribe('slack:C123:1111');

        $this->assertFalse($this->mockState->isSubscribed('slack:C123:1111'));
        $this->assertTrue($this->mockState->isSubscribed('slack:C123:2222'));
    }

    // ── isMention flag preserved in subscribed handler ───────────────

    public function test_is_mention_true_in_subscribed_thread_when_mentioned(): void
    {
        $capturedMessage = null;

        $this->chat->onSubscribedMessage(function (Thread $thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Hey @slack-bot what about this?', ['isMention' => true]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedMessage);
        $this->assertTrue($capturedMessage->isMention);
    }

    public function test_is_mention_false_in_subscribed_thread_when_not_mentioned(): void
    {
        $capturedMessage = null;

        $this->chat->onSubscribedMessage(function (Thread $thread, Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;
        });

        $this->mockState->subscribe('slack:C123:1234.5678');

        $message = TestMessageFactory::make('msg-1', 'Just a follow-up', ['isMention' => false]);

        $this->chat->handleIncomingMessage($this->mockAdapter, 'slack:C123:1234.5678', $message);

        $this->assertNotNull($capturedMessage);
        $this->assertFalse($capturedMessage->isMention);
    }
}
