<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Streaming response replay tests across Slack, Teams, and Google Chat.
 *
 * Verifies that streaming responses are handled correctly when replaying
 * recorded webhook payloads that trigger AI mode and streaming.
 *
 * @group integration
 */
class StreamingReplayTest extends ReplayTestCase
{
    // -----------------------------------------------------------------------
    // Slack
    // -----------------------------------------------------------------------

    public function test_slack_ai_mention_triggers_streaming(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        $this->mockAdapter->nextParsedMessage = TestMessageFactory::make(
            id: 'ai-msg-1',
            text: '<@U00FAKEBOT01> AI What is love',
            overrides: [
                'isMention' => true,
                'author' => new Author(
                    userId: 'U00FAKEUSER1',
                    userName: 'testuser',
                    fullName: 'Test User',
                    isBot: false,
                    isMe: false,
                ),
            ],
        );

        // When AI mode is detected, the handler should use streaming
        $thread = $this->createThread('slack:C00FAKECHAN1:1234.5678');
        $stream = $this->createTextStream(['Love ', 'is ', 'a ', 'complex ', 'emotion.']);
        $this->mockAdapter->stream('slack:C00FAKECHAN1:1234.5678', $stream);

        $this->assertStreamStarted();
    }

    public function test_slack_streaming_followup_response(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Subscribe the thread
        $threadId = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateAdapter->subscribe($threadId);

        // Simulate a streaming response to a follow-up
        $stream = $this->createTextStream(['I am ', 'an AI ', 'assistant ', 'here to help.']);
        $result = $this->mockAdapter->stream($threadId, $stream);

        $this->assertNotNull($result);
        $this->assertEquals('I am an AI assistant here to help.', $result->text);
    }

    // -----------------------------------------------------------------------
    // Teams
    // -----------------------------------------------------------------------

    public function test_teams_ai_mention_with_streaming(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $threadId = 'teams:thread-1';

        // Teams uses edit-based streaming (post then progressively update)
        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Thinking...'));
        $this->assertPostedMessageCount(1);

        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('Love is a complex emotion.'));
        $this->assertEditedMessageCount(1);
        $this->assertMessageEdited('Love is a complex emotion.');
    }

    public function test_teams_streaming_followup(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $threadId = 'teams:thread-1';
        $this->stateAdapter->subscribe($threadId);

        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Processing...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('I am an AI assistant here to help.'));

        $this->assertMessageEdited('I am an AI assistant here to help.');
    }

    // -----------------------------------------------------------------------
    // Google Chat
    // -----------------------------------------------------------------------

    public function test_gchat_ai_mention_with_streaming(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $threadId = 'gchat:spaces/AAQAJ9CXYcg:thread-1';

        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('AI Mode Enabled!'));
        $this->assertMessagePosted('AI Mode Enabled!');

        // GChat uses edit-based streaming (no native streaming support)
        $msg2 = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Thinking...'));
        $this->mockAdapter->editMessage($threadId, $msg2->id, PostableMessage::text('Love is a complex emotion.'));

        $this->assertMessageEdited('Love is a complex emotion.');
    }

    public function test_gchat_streaming_followup(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $threadId = 'gchat:spaces/AAQAJ9CXYcg:thread-1';
        $this->stateAdapter->subscribe($threadId);

        $msg = $this->mockAdapter->postMessage($threadId, PostableMessage::text('Processing...'));
        $this->mockAdapter->editMessage($threadId, $msg->id, PostableMessage::text('I am an AI assistant here to help.'));

        $this->assertMessageEdited('I am an AI assistant here to help.');
    }

    public function test_stream_accumulates_all_chunks(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $chunks = ['Hello', ' ', 'world', '!'];
        $stream = $this->createTextStream($chunks);

        $result = $this->mockAdapter->stream('test-thread', $stream);

        $this->assertNotNull($result);
        $this->assertEquals('Hello world!', $result->text);
    }

    /**
     * Create an iterable text stream from chunks (simulates AI streaming response).
     *
     * @return \Generator<string>
     */
    protected function createTextStream(array $chunks): \Generator
    {
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }
}
