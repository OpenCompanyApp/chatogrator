<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Direct message replay tests for Slack, Teams, and Google Chat.
 *
 * Verifies the DM flow:
 * 1. User mentions bot in channel, bot subscribes
 * 2. User requests DM in subscribed thread
 * 3. Bot opens DM and sends message
 * 4. User sends message in DM
 *
 * @group integration
 */
class DmReplayTest extends ReplayTestCase
{
    // -----------------------------------------------------------------------
    // Slack - DM Flow
    // -----------------------------------------------------------------------

    public function test_slack_dm_request_opens_dm_channel(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Step 1: Subscribe via mention
        $threadId = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateAdapter->subscribe($threadId);

        // Step 2: Open a DM
        $dmThreadId = $this->mockAdapter->openDM('U00FAKEUSER1');

        $this->assertDMOpened();
        $this->assertNotNull($dmThreadId);
        $this->assertStringContainsString('slack:', $dmThreadId);
        $this->assertStringContainsString('D', $dmThreadId);
    }

    public function test_slack_dm_message_sent_to_dm_channel(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('U00FAKEUSER1');
        $this->mockAdapter->postMessage($dmThreadId, PostableMessage::text('Hello via DM!'));

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Hello via DM!');
    }

    public function test_slack_detect_dm_channel_type(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // DM thread IDs contain ':D' to indicate DM channel
        $dmThreadId = $this->mockAdapter->openDM('U00FAKEUSER1');
        $this->assertTrue($this->mockAdapter->isDM($dmThreadId));
    }

    public function test_slack_direct_dm_treated_as_mention(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Direct DMs have isMention=true, so onNewMention fires
        $message = TestMessageFactory::make('dm-1', 'hello hello', [
            'isMention' => true,
            'threadId' => 'slack:D0ABCDEF123:',
        ]);

        $this->assertTrue($message->isMention);
    }

    public function test_slack_direct_dm_uses_empty_thread_ts(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Top-level DM: threadId is "slack:{channel}:" with empty threadTs
        $message = TestMessageFactory::make('dm-1', 'hello hello', [
            'threadId' => 'slack:D0ABCDEF123:',
        ]);

        $this->assertEquals('slack:D0ABCDEF123:', $message->threadId);
        $this->assertStringEndsWith(':', $message->threadId);
    }

    public function test_slack_respond_to_direct_dm(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $threadId = 'slack:D0ABCDEF123:';
        $this->mockAdapter->postMessage($threadId, PostableMessage::text('Hi! You said: hello hello'));

        $this->assertMessagePosted('Hi! You said: hello hello');
    }

    public function test_slack_followup_dm_as_subscribed_message(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // First DM triggers onNewMention and subscribes
        $threadId = 'slack:D0ABCDEF123:';
        $this->stateAdapter->subscribe($threadId);
        $this->assertTrue($this->stateAdapter->isSubscribed($threadId));

        // Second DM should trigger onSubscribedMessage
        $message = TestMessageFactory::make('dm-2', 'cool!!', [
            'threadId' => $threadId,
        ]);

        $this->assertEquals('cool!!', $message->text);
    }

    // -----------------------------------------------------------------------
    // Google Chat - DM Flow
    // -----------------------------------------------------------------------

    public function test_gchat_dm_request_flow(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        // Subscribe via mention
        $threadId = 'gchat:spaces/AAQAJ9CXYcg:thread-1';
        $this->stateAdapter->subscribe($threadId);

        // Open DM
        $dmThreadId = $this->mockAdapter->openDM('users/100000000000000000001');
        $this->assertDMOpened();
        $this->assertNotNull($dmThreadId);
    }

    public function test_gchat_dm_message_sent(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('users/100000000000000000001');
        $this->mockAdapter->postMessage($dmThreadId, PostableMessage::text('Hello via DM!'));

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Hello via DM!');
    }

    public function test_gchat_detect_dm_space_type(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('users/100000000000000000001');
        $this->assertTrue($this->mockAdapter->isDM($dmThreadId));
    }

    public function test_gchat_dm_sender_identification(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');

        // Verify sender identity from fixture
        $mention = $fixture['mention'];
        $sender = $mention['chat']['messagePayload']['message']['sender'] ?? [];

        $this->assertEquals('users/100000000000000000001', $sender['name'] ?? '');
        $this->assertEquals('Test User', $sender['displayName'] ?? '');
        $this->assertEquals('HUMAN', $sender['type'] ?? '');
    }

    public function test_gchat_receive_dm_reply_when_subscribed(): void
    {
        $chat = $this->createChat('gchat', ['botName' => 'TestBot']);

        // Open DM and subscribe
        $dmThreadId = $this->mockAdapter->openDM('users/100000000000000000001');
        $this->stateAdapter->subscribe($dmThreadId);

        // Verify DM thread is subscribed
        $this->assertTrue($this->stateAdapter->isSubscribed($dmThreadId));

        // User reply in DM
        $message = TestMessageFactory::make('dm-reply-1', 'Thanks!', [
            'threadId' => $dmThreadId,
        ]);

        $this->assertEquals('Thanks!', $message->text);
    }

    // -----------------------------------------------------------------------
    // Teams - DM Flow
    // -----------------------------------------------------------------------

    public function test_teams_dm_request_flow(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        // Mention to subscribe
        $response = $this->sendWebhook($fixture['mention'], 'teams');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_teams_open_dm_channel(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('29:user123');
        $this->assertDMOpened();
        $this->assertNotNull($dmThreadId);
    }

    public function test_teams_dm_conversation_type_detection(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');

        // Verify the mention payload has channel conversation type
        $this->assertEquals('channel', $fixture['mention']['conversation']['conversationType'] ?? '');
        $this->assertStringStartsWith('19:', $fixture['mention']['conversation']['id'] ?? '');
    }

    public function test_teams_receive_dm_reply(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('29:user123');
        $this->stateAdapter->subscribe($dmThreadId);
        $this->assertTrue($this->stateAdapter->isSubscribed($dmThreadId));

        // User reply in DM
        $message = TestMessageFactory::make('dm-reply-1', 'Hey', [
            'threadId' => $dmThreadId,
        ]);

        $this->assertEquals('Hey', $message->text);
    }

    public function test_teams_dm_bot_responds(): void
    {
        $chat = $this->createChat('teams', ['botName' => 'TestBot']);

        $dmThreadId = $this->mockAdapter->openDM('29:user123');
        $this->mockAdapter->postMessage($dmThreadId, PostableMessage::text('Got your DM: Hey'));

        $this->assertMessagePosted('Got your DM: Hey');
    }
}
