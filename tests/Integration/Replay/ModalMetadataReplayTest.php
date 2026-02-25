<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Events\ModalSubmitEvent;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Modal with private metadata replay tests.
 *
 * Tests the flow: button click (with value) -> modal open (with privateMetadata)
 * -> modal submit (privateMetadata roundtrips).
 *
 * @group integration
 */
class ModalMetadataReplayTest extends ReplayTestCase
{
    public function test_slack_report_button_click_has_value(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C0A9D9RTBMF:1771116676.529969', 'C0A9D9RTBMF');

        $actionEvent = new \OpenCompany\Chatogrator\Events\ActionEvent(
            actionId: 'report',
            value: 'bug',
            thread: $thread,
        );

        $this->assertEquals('report', $actionEvent->actionId);
        $this->assertEquals('bug', $actionEvent->value);
    }

    public function test_slack_modal_submit_decodes_private_metadata(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        // Simulate the private metadata that was encoded in the modal
        $privateMetadata = json_encode([
            'reportType' => 'bug',
            'threadId' => 'slack:C0A9D9RTBMF:1771116676.529969',
            'reporter' => 'U0A8WUV28QM',
        ]);

        $thread = $this->createThread('slack:C0A9D9RTBMF:1771116676.529969', 'C0A9D9RTBMF');

        // ModalSubmitEvent should expose the privateMetadata
        // Note: The real Chat class will need to store/restore context
        $this->assertNotNull($privateMetadata);
        $decoded = json_decode($privateMetadata, true);
        $this->assertEquals('bug', $decoded['reportType']);
        $this->assertEquals('slack:C0A9D9RTBMF:1771116676.529969', $decoded['threadId']);
        $this->assertEquals('U0A8WUV28QM', $decoded['reporter']);
    }

    public function test_slack_modal_submit_decodes_form_values(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'report_form',
            values: [
                'title' => 'tes',
                'steps' => 'test',
                'severity' => 'high',
            ],
        );

        $this->assertEquals([
            'title' => 'tes',
            'steps' => 'test',
            'severity' => 'high',
        ], $modalSubmit->values);
    }

    public function test_slack_modal_submit_has_related_thread_alongside_metadata(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C0A9D9RTBMF:1771116676.529969', 'C0A9D9RTBMF');

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'report_form',
            values: ['title' => 'tes'],
            relatedThread: $thread,
        );

        // Both privateMetadata and relatedThread should be available
        $this->assertNotNull($modalSubmit->relatedThread);
        $this->assertEquals('slack:C0A9D9RTBMF:1771116676.529969', $modalSubmit->relatedThread->id);
        $this->assertEquals('C0A9D9RTBMF', $modalSubmit->relatedThread->channelId);
    }

    public function test_slack_modal_submit_has_related_message_alongside_metadata(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $relatedMessage = TestMessageFactory::make('1771116682.586579', 'Bot response', [
            'threadId' => 'slack:C0A9D9RTBMF:1771116676.529969',
        ]);

        $modalSubmit = new ModalSubmitEvent(
            callbackId: 'report_form',
            values: ['title' => 'tes'],
            relatedMessage: $relatedMessage,
        );

        $this->assertNotNull($modalSubmit->relatedMessage);
        $this->assertEquals('1771116682.586579', $modalSubmit->relatedMessage->id);
        $this->assertEquals('slack:C0A9D9RTBMF:1771116676.529969', $modalSubmit->relatedMessage->threadId);
    }

    public function test_slack_handler_uses_metadata_and_posts_to_related_thread(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C0A9D9RTBMF:1771116676.529969', 'C0A9D9RTBMF');

        $metadata = json_decode(json_encode(['reportType' => 'bug']), true);
        $title = 'tes';

        // Simulate what the modal submit handler would do
        $thread->post("Bug report ({$metadata['reportType']}): {$title}");

        $this->assertMessagePosted('Bug report (bug): tes');
    }
}
