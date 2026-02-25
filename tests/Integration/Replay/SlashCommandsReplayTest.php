<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Events\ModalSubmitEvent;
use OpenCompany\Chatogrator\Events\SlashCommandEvent;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Threads\Channel;

/**
 * Slash command replay tests for Slack.
 *
 * Tests the full flow: slash command -> optional modal -> response to channel.
 *
 * @group integration
 */
class SlashCommandsReplayTest extends ReplayTestCase
{
    public function test_slack_slash_command_has_correct_properties(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);
        $thread = $this->createThread('slack:C00FAKECHAN3:', 'C00FAKECHAN3');

        $event = new SlashCommandEvent(
            command: '/test-feedback',
            text: '',
            thread: $thread,
            userId: 'U00FAKEUSER2',
            triggerId: '10520020890661.10229338706656.2e2188a074adf3bf9f8456b30180f405',
        );

        $this->assertEquals('/test-feedback', $event->command);
        $this->assertEquals('', $event->text);
        $this->assertEquals('U00FAKEUSER2', $event->userId);
    }

    public function test_slack_slash_command_with_arguments(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);
        $thread = $this->createThread('slack:C00FAKECHAN3:', 'C00FAKECHAN3');

        $event = new SlashCommandEvent(
            command: '/test-feedback',
            text: 'some arguments here',
            thread: $thread,
            userId: 'U00FAKEUSER2',
        );

        $this->assertEquals('some arguments here', $event->text);
    }

    public function test_slack_slash_command_provides_trigger_id(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);
        $thread = $this->createThread('slack:C00FAKECHAN3:', 'C00FAKECHAN3');

        $event = new SlashCommandEvent(
            command: '/test-feedback',
            text: '',
            thread: $thread,
            userId: 'U00FAKEUSER2',
            triggerId: '10520020890661.10229338706656.2e2188a074adf3bf9f8456b30180f405',
        );

        $this->assertEquals(
            '10520020890661.10229338706656.2e2188a074adf3bf9f8456b30180f405',
            $event->triggerId,
        );
    }

    public function test_slack_slash_command_post_to_channel(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Slash command should allow posting to the source channel
        $this->mockAdapter->postChannelMessage('C00FAKECHAN3', PostableMessage::text('Hello from slash command!'));

        $this->assertCount(1, $this->mockAdapter->channelMessages);
        $channelMsg = $this->mockAdapter->channelMessages[0];
        $this->assertEquals('C00FAKECHAN3', $channelMsg['channelId']);
    }

    public function test_slack_slash_command_post_ephemeral(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN3:', 'C00FAKECHAN3');
        $thread->postEphemeral('U00FAKEUSER2', 'This is just for you!');

        $this->assertCount(1, $this->mockAdapter->ephemeralMessages);
        $this->assertEquals('U00FAKEUSER2', $this->mockAdapter->ephemeralMessages[0]['userId']);
    }

    public function test_slack_slash_command_modal_submission(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: [
                'message' => 'Hello!',
                'category' => 'feature',
                'email' => 'user@example.com',
            ],
        );

        $this->assertEquals('feedback_form', $modalSubmit->callbackId);
        $this->assertEquals('Hello!', $modalSubmit->values['message']);
        $this->assertEquals('feature', $modalSubmit->values['category']);
        $this->assertEquals('user@example.com', $modalSubmit->values['email']);
    }

    public function test_slack_modal_submit_populates_related_channel(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $channel = new Channel(
            id: 'slack:C00FAKECHAN3',
            adapter: $this->mockAdapter,
            chat: $this->chat,
        );

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: ['message' => 'test'],
            relatedChannel: $channel,
        );

        $this->assertNotNull($modalSubmit->relatedChannel);
        $this->assertEquals('slack:C00FAKECHAN3', $modalSubmit->relatedChannel->id);
    }

    public function test_slack_modal_submit_post_to_related_channel(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $this->mockAdapter->postChannelMessage('C00FAKECHAN3', PostableMessage::text('Feedback received from testuser!'));

        $this->assertCount(1, $this->mockAdapter->channelMessages);
    }

    public function test_slack_modal_from_slash_command_has_no_related_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $channel = new Channel(
            id: 'slack:C00FAKECHAN3',
            adapter: $this->mockAdapter,
            chat: $this->chat,
        );

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: ['message' => 'test'],
            relatedChannel: $channel,
        );

        $this->assertNull($modalSubmit->relatedThread);
        $this->assertNull($modalSubmit->relatedMessage);
        $this->assertNotNull($modalSubmit->relatedChannel);
    }

    public function test_slack_slash_command_returns_200(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Simulate a slash command webhook
        $slashPayload = [
            'command' => '/test-feedback',
            'text' => '',
            'user_id' => 'U00FAKEUSER2',
            'user_name' => 'testuser',
            'channel_id' => 'C00FAKECHAN3',
            'trigger_id' => '10520020890661.10229338706656.2e2188a074adf3bf9f8456b30180f405',
            'team_id' => 'T00FAKE00AA',
        ];

        $response = $this->sendWebhook($slashPayload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_slack_slash_command_respond_helper(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);
        $thread = $this->createThread('slack:C00FAKECHAN3:', 'C00FAKECHAN3');

        $event = new SlashCommandEvent(
            command: '/test-feedback',
            text: '',
            thread: $thread,
            userId: 'U00FAKEUSER2',
        );

        // The respond() method posts to the thread
        $event->respond('Command received!');

        $this->assertPostedMessageCount(1);
        $this->assertMessagePosted('Command received!');
    }
}
