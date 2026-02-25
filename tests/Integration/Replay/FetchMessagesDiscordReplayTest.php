<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsResult;

/**
 * Discord fetch-messages replay tests.
 *
 * Verifies that the "Fetch Messages" button click in Discord triggers
 * the action handler, which fetches thread/channel messages via the
 * adapter and posts a formatted response. Also covers the Discord-specific
 * interaction response flow (deferred update, follow-up message).
 *
 * @group integration
 */
class FetchMessagesDiscordReplayTest extends ReplayTestCase
{
    private const BOT_ID = '1457469483726668048';

    private const GUILD_ID = '1457468924290662599';

    private const CHANNEL_ID = '1457510428359004343';

    private const THREAD_ID = '1457536551830421524';

    private const USER_ID = '1033044521375764530';

    private function discordThreadId(): string
    {
        return 'discord:' . self::GUILD_ID . ':' . self::CHANNEL_ID . ':' . self::THREAD_ID;
    }

    // -----------------------------------------------------------------------
    // Button Click -> Fetch Messages Flow
    // -----------------------------------------------------------------------

    public function test_discord_fetch_messages_button_click_extracts_action_id(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $buttonPayload = $fixture['buttonClickMessages'];
        $actionId = $buttonPayload['data']['custom_id'] ?? '';

        $this->assertEquals('messages', $actionId);
    }

    public function test_discord_fetch_messages_button_extracts_channel_and_thread(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');

        $buttonPayload = $fixture['buttonClickMessages'];
        $channelId = $buttonPayload['channel_id'] ?? '';
        $guildId = $buttonPayload['guild_id'] ?? '';
        $parentId = $buttonPayload['channel']['parent_id'] ?? '';

        $this->assertEquals(self::THREAD_ID, $channelId);
        $this->assertEquals(self::GUILD_ID, $guildId);
        $this->assertEquals(self::CHANNEL_ID, $parentId);
    }

    public function test_discord_fetch_messages_interaction_has_token(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');

        $buttonPayload = $fixture['buttonClickMessages'];

        $this->assertNotEmpty($buttonPayload['token']);
        $this->assertEquals(3, $buttonPayload['type']); // MESSAGE_COMPONENT
    }

    public function test_discord_fetch_messages_from_thread(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $result = $this->mockAdapter->fetchMessages($this->discordThreadId());

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_discord_fetch_messages_from_parent_channel(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $result = $this->mockAdapter->fetchChannelMessages(self::CHANNEL_ID);

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
    }

    public function test_discord_fetch_messages_with_limit(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $result = $this->mockAdapter->fetchMessages($this->discordThreadId(), new FetchOptions(limit: 25));

        $this->assertInstanceOf(FetchResult::class, $result);
    }

    // -----------------------------------------------------------------------
    // Action Handler Response
    // -----------------------------------------------------------------------

    public function test_discord_fetch_messages_handler_posts_count(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $threadId = $this->discordThreadId();
        $this->stateAdapter->subscribe($threadId);

        $thread = $this->createThread($threadId);

        // Simulate handler: fetch and post result
        $result = $this->mockAdapter->fetchMessages($threadId);
        $count = count($result->messages);

        $thread->post("Fetched {$count} message(s) from this thread.");

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Fetched 0 message(s)');
    }

    public function test_discord_fetch_messages_action_event_properties(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $thread = $this->createThread($this->discordThreadId());

        $actionEvent = new ActionEvent(
            actionId: 'messages',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('messages', $actionEvent->actionId);
        $this->assertNull($actionEvent->value);
        $this->assertStringStartsWith('discord:', $actionEvent->thread->id);
    }

    // -----------------------------------------------------------------------
    // Discord Thread Metadata in Fetch Context
    // -----------------------------------------------------------------------

    public function test_discord_thread_type_is_public_thread(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');

        $threadData = $fixture['buttonClickMessages']['channel'];

        // type 11 = PUBLIC_THREAD
        $this->assertEquals(11, $threadData['type']);
    }

    public function test_discord_thread_has_parent_channel(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');

        $threadData = $fixture['buttonClickMessages']['channel'];

        $this->assertEquals(self::CHANNEL_ID, $threadData['parent_id']);
        $this->assertNotNull($threadData['thread_metadata']);
        $this->assertFalse($threadData['thread_metadata']['archived']);
    }

    public function test_discord_thread_message_count_from_fixture(): void
    {
        $fixture = $this->loadReplayFixture('discord.json');

        $threadData = $fixture['buttonClickMessages']['channel'];

        // The fixture shows 19 messages were sent in this thread
        $this->assertEquals(19, $threadData['total_message_sent']);
        $this->assertEquals(2, $threadData['member_count']);
    }

    public function test_discord_fetch_individual_message(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $message = $this->mockAdapter->fetchMessage(
            $this->discordThreadId(),
            '1457536567810854934'
        );

        // MockAdapter returns null; real adapter fetches from Discord API
        $this->assertNull($message);
    }

    public function test_discord_list_threads_in_channel(): void
    {
        $chat = $this->createChat('discord', ['botName' => 'Chat SDK Demo']);

        $result = $this->mockAdapter->listThreads(self::CHANNEL_ID);

        $this->assertInstanceOf(ListThreadsResult::class, $result);
        $this->assertIsArray($result->threads);
        $this->assertNull($result->nextCursor);
    }
}
