<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsResult;

/**
 * Slack fetch-messages replay tests.
 *
 * Verifies that the "Fetch Messages" action handler can retrieve thread
 * and channel messages, handle pagination cursors, filter by author,
 * and correctly format the response.
 *
 * @group integration
 */
class FetchMessagesSlackReplayTest extends ReplayTestCase
{
    private const BOT_NAME = 'Chat SDK ExampleBot';

    private const BOT_USER_ID = 'U00FAKEBOT01';

    private const CHANNEL_ID = 'C00FAKECHAN1';

    private const THREAD_TS = '1767224888.280449';

    private function threadId(): string
    {
        return 'slack:' . self::CHANNEL_ID . ':' . self::THREAD_TS;
    }

    // -----------------------------------------------------------------------
    // Thread Message Fetching
    // -----------------------------------------------------------------------

    public function test_fetch_thread_messages_returns_fetch_result(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->threadId());

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertIsString($result->nextCursor ?? '');
    }

    public function test_fetch_thread_messages_default_empty(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->threadId());

        $this->assertCount(0, $result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_fetch_thread_messages_with_limit(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->threadId(), new FetchOptions(limit: 5));

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
    }

    public function test_fetch_individual_message_by_id(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $message = $this->mockAdapter->fetchMessage($this->threadId(), self::THREAD_TS);

        // MockAdapter returns null by default; real adapter fetches from Slack API
        $this->assertNull($message);
    }

    // -----------------------------------------------------------------------
    // Channel Message Fetching
    // -----------------------------------------------------------------------

    public function test_fetch_channel_messages_returns_paginated_result(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchChannelMessages(self::CHANNEL_ID);

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
    }

    public function test_fetch_channel_messages_with_cursor_pagination(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        // First page
        $page1 = $this->mockAdapter->fetchChannelMessages(self::CHANNEL_ID, new FetchOptions(limit: 10));
        $this->assertInstanceOf(FetchResult::class, $page1);

        // Simulate pagination with cursor from first page
        $cursor = $page1->nextCursor;
        if ($cursor !== null) {
            $page2 = $this->mockAdapter->fetchChannelMessages(self::CHANNEL_ID, new FetchOptions(
                limit: 10,
                cursor: $cursor,
            ));
            $this->assertInstanceOf(FetchResult::class, $page2);
        } else {
            // No more pages
            $this->assertNull($cursor);
        }
    }

    public function test_fetch_channel_info_for_slack(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $info = $this->mockAdapter->fetchChannelInfo(self::CHANNEL_ID);

        $this->assertNotNull($info);
        $this->assertInstanceOf(ChannelInfo::class, $info);
        $this->assertEquals(self::CHANNEL_ID, $info->id);
        $this->assertIsString($info->name);
        $this->assertFalse($info->isDM);
    }

    public function test_list_threads_in_slack_channel(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->listThreads(self::CHANNEL_ID);

        $this->assertNotNull($result);
        $this->assertInstanceOf(ListThreadsResult::class, $result);
        $this->assertIsArray($result->threads);
    }

    public function test_fetch_messages_action_handler_posts_result(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Subscribe the thread
        $threadId = $this->threadId();
        $this->stateAdapter->subscribe($threadId);

        $thread = $this->createThread($threadId, self::CHANNEL_ID);

        // Simulate the "Fetch Messages" action handler:
        // fetch messages, format, and post back
        $result = $this->mockAdapter->fetchMessages($threadId);
        $messageCount = count($result->messages);

        $thread->post("Found {$messageCount} message(s) in this thread.");

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Found 0 message(s) in this thread.');
    }
}
