<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;

/**
 * Google Chat fetch-messages replay tests.
 *
 * Verifies message fetching in Google Chat spaces, including:
 * - Fetching thread messages via the adapter
 * - Fetching space-level messages
 * - Handling Pub/Sub-based message delivery
 * - Pagination with cursors
 * - Space metadata resolution
 *
 * @group integration
 */
class FetchMessagesGchatReplayTest extends ReplayTestCase
{
    private const BOT_NAME = 'Chat SDK Demo';

    private const BOT_USER_ID = 'users/100000000000000000002';

    private const SPACE_NAME = 'spaces/AAQAJ9CXYcg';

    private const THREAD_NAME = 'spaces/AAQAJ9CXYcg/threads/kVOtO797ZPI';

    private function gchatThreadId(): string
    {
        return 'gchat:' . self::SPACE_NAME . ':' . base64_encode(self::THREAD_NAME);
    }

    // -----------------------------------------------------------------------
    // Thread Message Fetching
    // -----------------------------------------------------------------------

    public function test_gchat_fetch_thread_messages_returns_result(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->gchatThreadId());

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_gchat_fetch_thread_messages_default_empty(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->gchatThreadId());

        $this->assertCount(0, $result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_gchat_fetch_thread_messages_with_limit(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->gchatThreadId(), new FetchOptions(limit: 10));

        $this->assertInstanceOf(FetchResult::class, $result);
    }

    // -----------------------------------------------------------------------
    // Space-Level Message Fetching
    // -----------------------------------------------------------------------

    public function test_gchat_fetch_space_messages(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchChannelMessages(self::SPACE_NAME);

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_gchat_fetch_space_info(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $info = $this->mockAdapter->fetchChannelInfo(self::SPACE_NAME);

        $this->assertInstanceOf(ChannelInfo::class, $info);
        $this->assertEquals(self::SPACE_NAME, $info->id);
        $this->assertNotNull($info->name);
    }

    // -----------------------------------------------------------------------
    // Fetch Message Action Handler
    // -----------------------------------------------------------------------

    public function test_gchat_fetch_messages_handler_posts_result(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $threadId = $this->gchatThreadId();
        $this->stateAdapter->subscribe($threadId);

        $thread = $this->createThread($threadId);

        $result = $this->mockAdapter->fetchMessages($threadId);
        $count = count($result->messages);

        $thread->post("Found {$count} message(s) in this thread.");

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Found 0 message(s)');
    }

    public function test_gchat_fetch_individual_message(): void
    {
        $chat = $this->createChat('gchat', ['botName' => self::BOT_NAME]);

        $messageId = 'spaces/AAQAJ9CXYcg/messages/kVOtO797ZPI.kVOtO797ZPI';
        $message = $this->mockAdapter->fetchMessage($this->gchatThreadId(), $messageId);

        // MockAdapter returns null; real adapter calls Chat API
        $this->assertNull($message);
    }

    // -----------------------------------------------------------------------
    // Pub/Sub Message Context
    // -----------------------------------------------------------------------

    public function test_gchat_pubsub_follow_up_contains_thread_name(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');

        // Decode the Pub/Sub data field
        $data = json_decode(
            base64_decode($fixture['followUp']['message']['data']),
            true
        );

        $this->assertNotNull($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(self::THREAD_NAME, $data['message']['thread']['name']);
        $this->assertEquals(self::SPACE_NAME, $data['message']['space']['name']);
        $this->assertTrue($data['message']['threadReply']);
    }

    public function test_gchat_pubsub_follow_up_sender_is_human(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');

        $data = json_decode(
            base64_decode($fixture['followUp']['message']['data']),
            true
        );

        $sender = $data['message']['sender'];
        $this->assertEquals('users/100000000000000000001', $sender['name']);
        $this->assertEquals('HUMAN', $sender['type']);
    }
}
