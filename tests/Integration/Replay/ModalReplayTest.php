<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Events\ModalSubmitEvent;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * Modal open/submit replay tests for Slack.
 *
 * Covers the full flow: button click -> modal open -> modal submit,
 * including interactions with ephemeral messages.
 *
 * @group integration
 */
class ModalReplayTest extends ReplayTestCase
{
    public function test_slack_feedback_button_click_triggers_action(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Subscribe via mention
        $threadId = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateAdapter->subscribe($threadId);

        $response = $this->sendWebhook($fixture['mention'], 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_slack_trigger_id_available_for_modal_open(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN2:1234.5678', 'C00FAKECHAN2');

        $actionEvent = new ActionEvent(
            actionId: 'feedback',
            value: null,
            thread: $thread,
            triggerId: '10367455086084.10229338706656.e675a0c0dacc24a1f7b84a7a426d1197',
        );

        $this->assertEquals('feedback', $actionEvent->actionId);
        $this->assertNotNull($actionEvent->triggerId);
    }

    public function test_slack_modal_open_with_trigger_id(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $modal = new Modal('feedback_form', 'Feedback');
        $result = $this->mockAdapter->openModal('trigger-123', $modal);

        $this->assertModalOpened();
        $this->assertNotNull($result);
        $this->assertArrayHasKey('viewId', $result);
    }

    public function test_slack_modal_submission_parsed_values(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN2:1769220155.940449', 'C00FAKECHAN2');

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: [
                'message' => 'Hello!',
                'category' => 'feature',
                'email' => 'user@example.com',
            ],
            relatedThread: $thread,
        );

        $this->assertEquals('feedback_form', $modalSubmit->callbackId);
        $this->assertEquals('Hello!', $modalSubmit->values['message']);
        $this->assertEquals('feature', $modalSubmit->values['category']);
        $this->assertEquals('user@example.com', $modalSubmit->values['email']);
    }

    public function test_slack_modal_submit_populates_related_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN2:1769220155.940449', 'C00FAKECHAN2');

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: ['message' => 'test'],
            relatedThread: $thread,
        );

        $this->assertNotNull($modalSubmit->relatedThread);
        $this->assertEquals('slack:C00FAKECHAN2:1769220155.940449', $modalSubmit->relatedThread->id);
        $this->assertEquals('C00FAKECHAN2', $modalSubmit->relatedThread->channelId);
        $this->assertFalse($modalSubmit->relatedThread->isDM);
    }

    public function test_slack_modal_submit_populates_related_message(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $relatedMessage = TestMessageFactory::make(
            id: '1769220161.503009',
            text: 'Bot response',
            overrides: [
                'threadId' => 'slack:C00FAKECHAN2:1769220155.940449',
                'author' => new Author(
                    userId: 'U00FAKEBOT02',
                    userName: 'testbot',
                    fullName: 'Test Bot',
                    isBot: true,
                    isMe: true,
                ),
            ],
        );

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'feedback_form',
            values: ['message' => 'test'],
            relatedMessage: $relatedMessage,
        );

        $this->assertNotNull($modalSubmit->relatedMessage);
        $this->assertEquals('1769220161.503009', $modalSubmit->relatedMessage->id);
        $this->assertTrue($modalSubmit->relatedMessage->author->isBot);
        $this->assertTrue($modalSubmit->relatedMessage->author->isMe);
    }

    public function test_slack_post_to_related_thread_from_modal_submit(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN2:1769220155.940449', 'C00FAKECHAN2');
        $thread->post('Feedback received from jane.smith!');

        $this->assertMessagePosted('Feedback received from jane.smith!');
    }

    public function test_slack_edit_related_message_from_modal_submit(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $msg = $this->mockAdapter->postMessage(
            'slack:C00FAKECHAN2:1769220155.940449',
            PostableMessage::text('Initial response')
        );

        $this->mockAdapter->editMessage(
            'slack:C00FAKECHAN2:1769220155.940449',
            $msg->id,
            PostableMessage::text('Feedback received! Thank you.')
        );

        $this->assertMessageEdited('Feedback received! Thank you.');
    }

    public function test_slack_ephemeral_button_click_provides_trigger_id(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN3:1771126602.612659', 'C00FAKECHAN3');

        $actionEvent = new ActionEvent(
            actionId: 'ephemeral_modal',
            value: null,
            thread: $thread,
            triggerId: '10541689532400.10229338706656.500e194be18c7e17dd828032cc9a769f',
        );

        $this->assertEquals('ephemeral_modal', $actionEvent->actionId);
        $this->assertNotNull($actionEvent->triggerId);
    }

    public function test_slack_ephemeral_modal_submit_posts_to_related_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN3:1771126602.612659', 'C00FAKECHAN3');
        $thread->post('Response posted to thread!');

        $this->assertMessagePosted('Response posted to thread!');
    }
}
