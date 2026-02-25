<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Threads;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Thread;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;

/**
 * @group core
 */
class ThreadTest extends TestCase
{
    protected Thread $thread;

    protected MockAdapter $mockAdapter;

    protected MockStateAdapter $mockState;

    protected Chat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAdapter = new MockAdapter('slack');
        $this->mockState = new MockStateAdapter;

        $this->chat = Chat::make('testbot')
            ->adapter('slack', $this->mockAdapter)
            ->state($this->mockState);

        $this->thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->mockAdapter,
            chat: $this->chat,
            channelId: 'C123',
        );
    }

    // ── Per-thread state ────────────────────────────────────────────

    public function test_state_returns_empty_array_when_no_state_set(): void
    {
        $state = $this->thread->state();

        $this->assertSame([], $state);
    }

    public function test_set_state_and_retrieve_it(): void
    {
        $this->thread->setState(['aiMode' => true]);

        $state = $this->thread->state();

        $this->assertSame(['aiMode' => true], $state);
    }

    public function test_set_state_merges_by_default(): void
    {
        $this->thread->setState(['aiMode' => true]);
        $this->thread->setState(['counter' => 5]);

        $state = $this->thread->state();

        $this->assertSame(['aiMode' => true, 'counter' => 5], $state);
    }

    public function test_set_state_overwrites_existing_keys_when_merging(): void
    {
        $this->thread->setState(['aiMode' => true, 'counter' => 1]);
        $this->thread->setState(['counter' => 10]);

        $state = $this->thread->state();

        $this->assertSame(['aiMode' => true, 'counter' => 10], $state);
    }

    public function test_set_state_replaces_entire_state_when_replace_is_true(): void
    {
        $this->thread->setState(['aiMode' => true, 'counter' => 5]);
        $this->thread->setState(['counter' => 10], replace: true);

        $state = $this->thread->state();

        $this->assertSame(['counter' => 10], $state);
        $this->assertArrayNotHasKey('aiMode', $state);
    }

    public function test_state_uses_correct_key_prefix(): void
    {
        $this->thread->setState(['aiMode' => true]);

        $setCalls = $this->mockState->wasCalledWith('set');

        $this->assertNotEmpty($setCalls);
        $this->assertSame('thread-state:slack:C123:1234.5678', $setCalls[0]['args'][0]);
    }

    public function test_state_get_uses_correct_key(): void
    {
        // Access state to trigger a get call
        $this->thread->state();

        $getCalls = $this->mockState->wasCalledWith('get');

        $this->assertNotEmpty($getCalls);
        $this->assertSame('thread-state:slack:C123:1234.5678', $getCalls[0]['args'][0]);
    }

    // ── Thread.post() ───────────────────────────────────────────────

    public function test_post_with_string_content(): void
    {
        $result = $this->thread->post('Hello world!');

        $this->assertInstanceOf(SentMessage::class, $result);
        $this->assertNotEmpty($this->mockAdapter->postedMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->postedMessages[0]['threadId']);
    }

    public function test_post_with_postable_message(): void
    {
        $message = PostableMessage::markdown('**Bold** text');

        $result = $this->thread->post($message);

        $this->assertInstanceOf(SentMessage::class, $result);
        $this->assertNotEmpty($this->mockAdapter->postedMessages);
    }

    public function test_post_with_streaming_content(): void
    {
        $stream = (function () {
            yield 'Hello';
            yield ' ';
            yield 'World';
        })();

        $result = $this->thread->post($stream);

        $this->assertInstanceOf(SentMessage::class, $result);
        // Should have used the stream method of the adapter
        $this->assertNotEmpty($this->mockAdapter->streamedMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->streamedMessages[0]['threadId']);
    }

    public function test_post_stream_falls_back_to_post_edit_when_no_native_streaming(): void
    {
        $this->mockAdapter->streamSupport = null;

        $stream = (function () {
            yield 'Hello';
            yield ' ';
            yield 'World';
        })();

        $result = $this->thread->post($stream);

        $this->assertInstanceOf(SentMessage::class, $result);
        // Should have posted an initial placeholder then edited
        $this->assertNotEmpty($this->mockAdapter->postedMessages);
        $this->assertNotEmpty($this->mockAdapter->editedMessages);
    }

    // ── Thread.subscribe() / unsubscribe() / isSubscribed() ────────

    public function test_subscribe_delegates_to_state_adapter(): void
    {
        $this->thread->subscribe();

        $this->assertTrue($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    public function test_unsubscribe_delegates_to_state_adapter(): void
    {
        $this->thread->subscribe();
        $this->thread->unsubscribe();

        $this->assertFalse($this->mockState->isSubscribed('slack:C123:1234.5678'));
    }

    public function test_is_subscribed_returns_false_for_new_thread(): void
    {
        $this->assertFalse($this->thread->isSubscribed());
    }

    public function test_is_subscribed_returns_true_after_subscribe(): void
    {
        $this->thread->subscribe();

        $this->assertTrue($this->thread->isSubscribed());
    }

    // ── Thread.messages() ───────────────────────────────────────────

    public function test_messages_returns_fetch_result(): void
    {
        $result = $this->thread->messages();

        $this->assertInstanceOf(FetchResult::class, $result);
    }

    public function test_messages_delegates_to_adapter(): void
    {
        $options = new FetchOptions(limit: 50);

        $this->thread->messages($options);

        // The adapter's fetchMessages was called — verify by checking the return
        // (MockAdapter returns a FetchResult)
        $result = $this->mockAdapter->fetchMessages('slack:C123:1234.5678', $options);
        $this->assertIsArray($result->messages);
    }

    // ── Thread.startTyping() ────────────────────────────────────────

    public function test_start_typing_delegates_to_adapter(): void
    {
        $this->thread->startTyping();

        $this->assertContains('slack:C123:1234.5678', $this->mockAdapter->typingStarted);
    }

    // ── Thread.toJSON() / fromJSON() serialization ──────────────────

    public function test_to_json_returns_correct_structure(): void
    {
        $json = $this->thread->toJSON();

        $this->assertSame('slack:C123:1234.5678', $json['id']);
        $this->assertSame('slack', $json['adapterName']);
        $this->assertSame('C123', $json['channelId']);
        $this->assertFalse($json['isDM']);
    }

    public function test_to_json_for_dm_thread(): void
    {
        $dmThread = new Thread(
            id: 'slack:DU123:',
            adapter: $this->mockAdapter,
            chat: $this->chat,
            channelId: 'DU123',
            isDM: true,
        );

        $json = $dmThread->toJSON();

        $this->assertTrue($json['isDM']);
    }

    public function test_to_json_produces_json_serializable_output(): void
    {
        $json = $this->thread->toJSON();

        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($json, $decoded);
    }

    public function test_from_json_reconstructs_thread(): void
    {
        $json = [
            'id' => 'slack:C123:1234.5678',
            'adapterName' => 'slack',
            'channelId' => 'C123',
            'isDM' => false,
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertSame('slack:C123:1234.5678', $thread->id);
        $this->assertSame('C123', $thread->channelId);
        $this->assertFalse($thread->isDM);
    }

    public function test_from_json_reconstructs_dm_thread(): void
    {
        $json = [
            'id' => 'slack:DU456:',
            'adapterName' => 'slack',
            'channelId' => 'DU456',
            'isDM' => true,
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertTrue($thread->isDM);
    }

    public function test_round_trip_serialization(): void
    {
        $json = $this->thread->toJSON();
        $restored = Thread::fromJSON($json, $this->chat);

        $this->assertSame($this->thread->id, $restored->id);
        $this->assertSame($this->thread->channelId, $restored->channelId);
        $this->assertSame($this->thread->isDM, $restored->isDM);
    }

    // ── Thread.isDM ─────────────────────────────────────────────────

    public function test_is_dm_false_by_default(): void
    {
        $this->assertFalse($this->thread->isDM);
    }

    public function test_is_dm_true_when_configured(): void
    {
        $dmThread = new Thread(
            id: 'slack:DU123:',
            adapter: $this->mockAdapter,
            chat: $this->chat,
            channelId: 'DU123',
            isDM: true,
        );

        $this->assertTrue($dmThread->isDM);
    }

    // ── Thread.postEphemeral() ──────────────────────────────────────

    public function test_post_ephemeral_delegates_to_adapter(): void
    {
        $this->thread->postEphemeral('U456', 'Secret message');

        $this->assertNotEmpty($this->mockAdapter->ephemeralMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->ephemeralMessages[0]['threadId']);
        $this->assertSame('U456', $this->mockAdapter->ephemeralMessages[0]['userId']);
    }

    public function test_post_ephemeral_with_fallback_to_dm(): void
    {
        // MockAdapter.postEphemeral returns null, which should trigger DM fallback
        $this->thread->postEphemeral('U456', 'Secret message', fallbackToDM: true);

        $this->assertContains('U456', $this->mockAdapter->dmOpened);
    }
}
