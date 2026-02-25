<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;

/**
 * Teams fetch-messages replay tests.
 *
 * Verifies message fetching in Microsoft Teams, including:
 * - Fetching thread messages via the adapter
 * - Fetching channel-level messages
 * - Teams conversation ID parsing (stripping ;messageid= suffix)
 * - Service URL resolution for API calls
 * - Pagination
 *
 * @group integration
 */
class FetchMessagesTeamsReplayTest extends ReplayTestCase
{
    private const BOT_NAME = 'Chat SDK Demo';

    private const APP_ID = '11111111-2222-3333-4444-555555555555';

    private const BOT_ID = '28:11111111-2222-3333-4444-555555555555';

    private const SERVICE_URL = 'https://smba.trafficmanager.net/amer/a1b2c3d4-e5f6-7890-abcd-ef1234567890/';

    private const CONV_ID = '19:d441d38c655c47a085215b2726e76927@thread.tacv2';

    private const CONV_ID_WITH_MSG = '19:d441d38c655c47a085215b2726e76927@thread.tacv2;messageid=1767224924615';

    private function teamsThreadId(): string
    {
        return 'teams:' . base64_encode(self::CONV_ID) . ':' . base64_encode(self::SERVICE_URL);
    }

    // -----------------------------------------------------------------------
    // Conversation ID Parsing
    // -----------------------------------------------------------------------

    public function test_teams_conversation_id_strips_messageid_suffix(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');

        $convId = $fixture['mention']['conversation']['id'];

        // The raw conversation ID includes ;messageid= suffix
        $this->assertStringContainsString(';messageid=', $convId);

        // Stripping the suffix gives the base conversation ID
        $baseConvId = explode(';messageid=', $convId)[0];
        $this->assertEquals(self::CONV_ID, $baseConvId);
    }

    public function test_teams_service_url_resolution(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');

        $serviceUrl = $fixture['mention']['serviceUrl'];

        $this->assertEquals(self::SERVICE_URL, $serviceUrl);
        $this->assertStringStartsWith('https://', $serviceUrl);
    }

    // -----------------------------------------------------------------------
    // Thread Message Fetching
    // -----------------------------------------------------------------------

    public function test_teams_fetch_thread_messages_returns_result(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->teamsThreadId());

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_teams_fetch_thread_messages_default_empty(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->teamsThreadId());

        $this->assertCount(0, $result->messages);
        $this->assertNull($result->nextCursor);
    }

    public function test_teams_fetch_thread_messages_with_limit(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchMessages($this->teamsThreadId(), new FetchOptions(limit: 20));

        $this->assertInstanceOf(FetchResult::class, $result);
    }

    // -----------------------------------------------------------------------
    // Channel Message Fetching
    // -----------------------------------------------------------------------

    public function test_teams_fetch_channel_messages(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $result = $this->mockAdapter->fetchChannelMessages(self::CONV_ID);

        $this->assertInstanceOf(FetchResult::class, $result);
        $this->assertIsArray($result->messages);
    }

    public function test_teams_fetch_channel_info(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $info = $this->mockAdapter->fetchChannelInfo(self::CONV_ID);

        $this->assertInstanceOf(ChannelInfo::class, $info);
        $this->assertEquals(self::CONV_ID, $info->id);
        $this->assertIsBool($info->isDM);
    }

    // -----------------------------------------------------------------------
    // Fetch Messages Action Handler
    // -----------------------------------------------------------------------

    public function test_teams_fetch_messages_handler_posts_result(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $threadId = $this->teamsThreadId();
        $this->stateAdapter->subscribe($threadId);

        $thread = $this->createThread($threadId);

        $result = $this->mockAdapter->fetchMessages($threadId);
        $count = count($result->messages);

        $thread->post("Found {$count} message(s) in this thread.");

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Found 0 message(s)');
    }

    public function test_teams_fetch_individual_message(): void
    {
        $chat = $this->createChat('teams', ['botName' => self::BOT_NAME]);

        $message = $this->mockAdapter->fetchMessage($this->teamsThreadId(), '1767224924615');

        // MockAdapter returns null; real adapter calls Bot Framework API
        $this->assertNull($message);
    }

    // -----------------------------------------------------------------------
    // Teams-Specific Payload Structure
    // -----------------------------------------------------------------------

    public function test_teams_mention_payload_has_entities(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');

        $mention = $fixture['mention'];

        $this->assertArrayHasKey('entities', $mention);
        $this->assertNotEmpty($mention['entities']);

        $mentionEntity = $mention['entities'][0];
        $this->assertEquals('mention', $mentionEntity['type']);
        $this->assertEquals(self::BOT_ID, $mentionEntity['mentioned']['id']);
    }

    public function test_teams_mention_has_channel_data_tenant(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');

        $mention = $fixture['mention'];

        $this->assertArrayHasKey('channelData', $mention);
        $this->assertArrayHasKey('tenant', $mention['channelData']);
        $this->assertNotEmpty($mention['channelData']['tenant']['id']);
    }
}
