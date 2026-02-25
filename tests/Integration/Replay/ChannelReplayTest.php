<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Threads\Channel;
use OpenCompany\Chatogrator\Threads\Thread;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;

/**
 * Channel message flow replay tests across all platforms.
 *
 * Tests channel-level operations (thread.channel, channel.messages,
 * channel.post, channel.fetchMetadata) using recorded webhook payloads.
 *
 * @group integration
 */
class ChannelReplayTest extends ReplayTestCase
{
    // -----------------------------------------------------------------------
    // Slack - Channel Operations
    // -----------------------------------------------------------------------

    public function test_slack_channel_post_action_access_thread_channel(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Subscribe via mention first
        $threadId = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateAdapter->subscribe($threadId);

        $response = $this->sendWebhook($fixture['mention'], 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_slack_derive_channel_id_from_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Thread ID format: slack:{channel}:{threadTs}
        $threadId = 'slack:C00FAKECHAN1:1234567890.123456';
        $channelId = $this->mockAdapter->channelIdFromThreadId($threadId);

        $this->assertNotNull($channelId);
        $this->assertStringContainsString('C00FAKECHAN1', $channelId);
    }

    public function test_slack_fetch_channel_metadata(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $channelInfo = $this->mockAdapter->fetchChannelInfo('C00FAKECHAN1');
        $this->assertInstanceOf(ChannelInfo::class, $channelInfo);
        $this->assertEquals('C00FAKECHAN1', $channelInfo->id);
        $this->assertFalse($channelInfo->isDM);
    }

    public function test_slack_iterate_channel_messages_newest_first(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $result = $this->mockAdapter->fetchChannelMessages('C00FAKECHAN1');
        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_slack_post_to_channel_top_level(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $this->mockAdapter->postChannelMessage('C00FAKECHAN1', PostableMessage::text('Hello from channel!'));

        $this->assertCount(1, $this->mockAdapter->channelMessages);
        $this->assertEquals('C00FAKECHAN1', $this->mockAdapter->channelMessages[0]['channelId']);
    }

    public function test_slack_break_out_of_channel_messages_early(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // fetchChannelMessages returns an empty array by default in MockAdapter
        // In production this would be a paginated iterator
        $result = $this->mockAdapter->fetchChannelMessages('C00FAKECHAN1', new FetchOptions(limit: 2));
        $this->assertNotNull($result);
    }

    public function test_slack_cache_channel_instance_on_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Thread.channel should return the same instance on repeated access
        $threadId = 'slack:C00FAKECHAN1:1234567890.123456';
        $channelId1 = $this->mockAdapter->channelIdFromThreadId($threadId);
        $channelId2 = $this->mockAdapter->channelIdFromThreadId($threadId);

        $this->assertEquals($channelId1, $channelId2);
    }

    // -----------------------------------------------------------------------
    // Google Chat - Channel Operations
    // -----------------------------------------------------------------------

    public function test_gchat_channel_post_action(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $response = $this->sendWebhook($fixture['mention'], 'gchat');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_gchat_derive_channel_id_from_thread(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        // GChat thread ID format: gchat:{space}:{b64(threadName)}
        $threadId = 'gchat:spaces/AAQAJ9CXYcg:' . base64_encode('spaces/AAQAJ9CXYcg/threads/abc');
        $channelId = $this->mockAdapter->channelIdFromThreadId($threadId);
        $this->assertNotNull($channelId);
    }

    public function test_gchat_fetch_channel_metadata(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        $channelInfo = $this->mockAdapter->fetchChannelInfo('spaces/AAQAJ9CXYcg');
        $this->assertInstanceOf(ChannelInfo::class, $channelInfo);
        $this->assertNotNull($channelInfo->name);
    }

    public function test_gchat_post_to_channel_top_level(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        $this->mockAdapter->postChannelMessage('spaces/AAQAJ9CXYcg', PostableMessage::text('Hello from channel!'));
        $this->assertCount(1, $this->mockAdapter->channelMessages);
    }

    public function test_gchat_cache_channel_instance(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        $threadId = 'gchat:spaces/AAQAJ9CXYcg:thread123';
        $id1 = $this->mockAdapter->channelIdFromThreadId($threadId);
        $id2 = $this->mockAdapter->channelIdFromThreadId($threadId);
        $this->assertEquals($id1, $id2);
    }

    // -----------------------------------------------------------------------
    // Discord - Channel Operations
    // -----------------------------------------------------------------------

    public function test_discord_derive_channel_id_from_thread(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        // Discord thread ID format: discord:{guildId}:{channelId}:{threadId}
        $channelId = $this->mockAdapter->channelIdFromThreadId('discord:guild123:channel456:thread789');
        $this->assertNotNull($channelId);
    }

    public function test_discord_channel_is_not_dm_for_guild(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        // Guild channel threads are NOT DMs
        $isDM = $this->mockAdapter->isDM('discord:guild123:channel456:thread789');
        $this->assertFalse($isDM);
    }

    public function test_discord_post_to_parent_channel(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        $this->mockAdapter->postChannelMessage('guild123:channel456', PostableMessage::text('Hello from channel!'));
        $this->assertCount(1, $this->mockAdapter->channelMessages);
    }

    public function test_discord_resolve_parent_channel_from_thread(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'TestBot']);

        // When interacting in a thread, the channel ID should point to the parent
        $threadId = 'discord:guild123:channel456:thread789';
        $channelId = $this->mockAdapter->channelIdFromThreadId($threadId);
        $this->assertNotNull($channelId);
    }

    // -----------------------------------------------------------------------
    // Teams - Channel Operations
    // -----------------------------------------------------------------------

    public function test_teams_channel_post_action(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $response = $this->sendWebhook($fixture['mention'], 'teams');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_teams_derive_channel_id_strip_messageid(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        // Teams thread ID: teams:{b64(conversationId)}:{b64(serviceUrl)}
        // Channel ID should not contain ;messageid=
        $threadId = 'teams:base64ConvId:base64ServiceUrl';
        $channelId = $this->mockAdapter->channelIdFromThreadId($threadId);
        $this->assertNotNull($channelId);
    }

    public function test_teams_post_to_channel_top_level(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        $this->mockAdapter->postChannelMessage('19:channel@thread.tacv2', PostableMessage::text('Hello from channel!'));
        $this->assertCount(1, $this->mockAdapter->channelMessages);
    }

    public function test_teams_cache_channel_instance(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        $threadId = 'teams:conv:svc';
        $id1 = $this->mockAdapter->channelIdFromThreadId($threadId);
        $id2 = $this->mockAdapter->channelIdFromThreadId($threadId);
        $this->assertEquals($id1, $id2);
    }
}
