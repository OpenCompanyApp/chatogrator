<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Threads;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Channel;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsResult;

/**
 * @group core
 */
class ChannelTest extends TestCase
{
    protected Channel $channel;

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

        $this->channel = new Channel(
            id: 'slack:C123',
            adapter: $this->mockAdapter,
            chat: $this->chat,
        );
    }

    // ── Basic properties ────────────────────────────────────────────

    public function test_channel_has_correct_id(): void
    {
        $this->assertSame('slack:C123', $this->channel->id);
    }

    public function test_channel_has_correct_adapter(): void
    {
        $this->assertSame($this->mockAdapter, $this->channel->adapter);
    }

    public function test_channel_is_dm_false_by_default(): void
    {
        $this->assertFalse($this->channel->isDM);
    }

    public function test_channel_is_dm_when_configured(): void
    {
        $dmChannel = new Channel(
            id: 'slack:D123',
            adapter: $this->mockAdapter,
            chat: $this->chat,
            isDM: true,
        );

        $this->assertTrue($dmChannel->isDM);
    }

    // ── Channel.post() ──────────────────────────────────────────────

    public function test_post_with_string_content(): void
    {
        $this->channel->post('Hello channel!');

        $this->assertNotEmpty($this->mockAdapter->channelMessages);
        $this->assertSame('slack:C123', $this->mockAdapter->channelMessages[0]['channelId']);
    }

    public function test_post_with_postable_message(): void
    {
        $message = PostableMessage::markdown('**Bold** message');

        $this->channel->post($message);

        $this->assertNotEmpty($this->mockAdapter->channelMessages);
    }

    // ── Channel.state() / setState() ────────────────────────────────

    public function test_state_returns_empty_array_when_no_state_set(): void
    {
        $state = $this->channel->state();

        $this->assertSame([], $state);
    }

    public function test_set_state_and_retrieve_it(): void
    {
        $this->channel->setState(['topic' => 'general']);

        $state = $this->channel->state();

        $this->assertSame(['topic' => 'general'], $state);
    }

    public function test_set_state_merges_by_default(): void
    {
        $this->channel->setState(['topic' => 'general']);
        $this->channel->setState(['count' => 5]);

        $state = $this->channel->state();

        $this->assertSame(['topic' => 'general', 'count' => 5], $state);
    }

    public function test_set_state_replaces_when_option_is_set(): void
    {
        $this->channel->setState(['topic' => 'general', 'count' => 5]);
        $this->channel->setState(['count' => 10], replace: true);

        $state = $this->channel->state();

        $this->assertSame(['count' => 10], $state);
        $this->assertArrayNotHasKey('topic', $state);
    }

    public function test_state_uses_channel_state_key_prefix(): void
    {
        $this->channel->setState(['topic' => 'general']);

        $setCalls = $this->mockState->wasCalledWith('set');

        $this->assertNotEmpty($setCalls);
        $this->assertSame('channel-state:slack:C123', $setCalls[0]['args'][0]);
    }

    // ── Channel.threads() ───────────────────────────────────────────

    public function test_threads_returns_list_threads_result(): void
    {
        $result = $this->channel->threads();

        $this->assertInstanceOf(ListThreadsResult::class, $result);
    }

    public function test_threads_delegates_to_adapter(): void
    {
        $result = $this->channel->threads();

        $this->assertIsArray($result->threads);
    }

    // ── Channel.messages() ──────────────────────────────────────────

    public function test_messages_returns_fetch_result(): void
    {
        $result = $this->channel->messages();

        $this->assertInstanceOf(FetchResult::class, $result);
    }

    public function test_messages_delegates_to_adapter(): void
    {
        $result = $this->channel->messages();

        $this->assertIsArray($result->messages);
    }

    // ── Channel.fetchMetadata() ─────────────────────────────────────

    public function test_fetch_metadata_returns_channel_info(): void
    {
        $result = $this->channel->fetchMetadata();

        $this->assertInstanceOf(ChannelInfo::class, $result);
        $this->assertSame('slack:C123', $result->id);
    }

    public function test_fetch_metadata_includes_name(): void
    {
        $result = $this->channel->fetchMetadata();

        $this->assertInstanceOf(ChannelInfo::class, $result);
        $this->assertSame('#slack:C123', $result->name);
    }

    // ── Channel.startTyping() ───────────────────────────────────────

    public function test_start_typing_delegates_to_adapter(): void
    {
        $this->channel->startTyping();

        $this->assertContains('slack:C123', $this->mockAdapter->typingStarted);
    }

    // ── Channel.toJSON() / fromJSON() ───────────────────────────────

    public function test_to_json_returns_correct_structure(): void
    {
        $json = $this->channel->toJSON();

        $this->assertSame('slack:C123', $json['id']);
        $this->assertSame('slack', $json['adapterName']);
        $this->assertFalse($json['isDM']);
    }

    public function test_to_json_for_dm_channel(): void
    {
        $dmChannel = new Channel(
            id: 'slack:D123',
            adapter: $this->mockAdapter,
            chat: $this->chat,
            isDM: true,
        );

        $json = $dmChannel->toJSON();

        $this->assertTrue($json['isDM']);
    }

    public function test_to_json_produces_json_serializable_output(): void
    {
        $json = $this->channel->toJSON();

        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($json, $decoded);
    }

    public function test_from_json_reconstructs_channel(): void
    {
        $json = [
            'id' => 'slack:C123',
            'adapterName' => 'slack',
            'isDM' => false,
        ];

        $channel = Channel::fromJSON($json, $this->chat);

        $this->assertSame('slack:C123', $channel->id);
        $this->assertFalse($channel->isDM);
    }

    public function test_from_json_reconstructs_dm_channel(): void
    {
        $json = [
            'id' => 'slack:D456',
            'adapterName' => 'slack',
            'isDM' => true,
        ];

        $channel = Channel::fromJSON($json, $this->chat);

        $this->assertTrue($channel->isDM);
    }

    public function test_round_trip_serialization(): void
    {
        $json = $this->channel->toJSON();
        $restored = Channel::fromJSON($json, $this->chat);

        $this->assertSame($this->channel->id, $restored->id);
        $this->assertSame($this->channel->isDM, $restored->isDM);
    }
}
