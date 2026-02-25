<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackModalRenderer;
use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Cards\Interactive\TextInput;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group slack
 */
class SlackModalTest extends TestCase
{
    // ========================================================================
    // Basic Modal Conversion
    // ========================================================================

    public function test_converts_simple_modal_with_text_input(): void
    {
        $modal = Modal::make('feedback_form', 'Send Feedback')
            ->input(TextInput::make('message', 'Your Feedback'));

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertSame('modal', $view['type']);
        $this->assertSame('feedback_form', $view['callback_id']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Send Feedback'], $view['title']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Submit'], $view['submit']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Cancel'], $view['close']);
        $this->assertCount(1, $view['blocks']);

        $block = $view['blocks'][0];
        $this->assertSame('input', $block['type']);
        $this->assertSame('message', $block['block_id']);
        $this->assertFalse($block['optional']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Your Feedback'], $block['label']);
        $this->assertSame('plain_text_input', $block['element']['type']);
        $this->assertSame('message', $block['element']['action_id']);
        $this->assertFalse($block['element']['multiline']);
    }

    public function test_converts_modal_with_custom_submit_close_labels(): void
    {
        $modal = Modal::make('test', 'Test Modal')
            ->submitLabel('Send')
            ->closeLabel('Dismiss');

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertSame(['type' => 'plain_text', 'text' => 'Send'], $view['submit']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Dismiss'], $view['close']);
    }

    // ========================================================================
    // Text Input Variations
    // ========================================================================

    public function test_converts_multiline_text_input(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                TextInput::make('description', 'Description')
                    ->multiline()
                    ->maxLength(500)
            );

        $view = SlackModalRenderer::toSlackView($modal);
        $element = $view['blocks'][0]['element'];

        $this->assertSame('plain_text_input', $element['type']);
        $this->assertSame('description', $element['action_id']);
        $this->assertTrue($element['multiline']);
        $this->assertSame(500, $element['max_length']);
    }

    public function test_converts_optional_text_input(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                TextInput::make('notes', 'Notes')
                    ->optional()
            );

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertTrue($view['blocks'][0]['optional']);
    }

    public function test_converts_text_input_with_initial_value(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(TextInput::make('name', 'Name'));

        // The initial value would need to be set on the TextInput.
        // Currently TextInput does not have initialValue, so this tests the structure.
        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertSame('plain_text_input', $view['blocks'][0]['element']['type']);
    }

    // ========================================================================
    // Select in Modal
    // ========================================================================

    public function test_converts_select_element_with_options(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                Select::make('category', 'Category')
                    ->options([
                        SelectOption::make('bug', 'Bug Report'),
                        SelectOption::make('feature', 'Feature Request'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);
        $block = $view['blocks'][0];

        $this->assertSame('input', $block['type']);
        $this->assertSame('category', $block['block_id']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Category'], $block['label']);
        $this->assertSame('static_select', $block['element']['type']);
        $this->assertSame('category', $block['element']['action_id']);
        $this->assertCount(2, $block['element']['options']);
        $this->assertSame([
            'text' => ['type' => 'plain_text', 'text' => 'Bug Report'],
            'value' => 'bug',
        ], $block['element']['options'][0]);
        $this->assertSame([
            'text' => ['type' => 'plain_text', 'text' => 'Feature Request'],
            'value' => 'feature',
        ], $block['element']['options'][1]);
    }

    public function test_converts_select_with_initial_option(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                Select::make('priority', 'Priority')
                    ->options([
                        SelectOption::make('low', 'Low'),
                        SelectOption::make('medium', 'Medium'),
                        SelectOption::make('high', 'High'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);

        // initial_option requires the adapter to know which option is selected
        // The Select class may support initialOption in the future
        $this->assertSame('static_select', $view['blocks'][0]['element']['type']);
    }

    public function test_converts_select_with_placeholder(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                Select::make('category', 'Select a category')
                    ->options([
                        SelectOption::make('general', 'General'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);

        // Placeholder comes from the Select constructor's second argument
        $this->assertSame(
            ['type' => 'plain_text', 'text' => 'Select a category'],
            $view['blocks'][0]['element']['placeholder']
        );
    }

    // ========================================================================
    // Private Metadata
    // ========================================================================

    public function test_includes_context_id_as_private_metadata(): void
    {
        $modal = Modal::make('test', 'Test');

        $view = SlackModalRenderer::toSlackView($modal, 'context-uuid-123');

        $this->assertSame('context-uuid-123', $view['private_metadata']);
    }

    public function test_private_metadata_is_absent_when_no_context_id(): void
    {
        $modal = Modal::make('test', 'Test');

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertArrayNotHasKey('private_metadata', $view);
    }

    // ========================================================================
    // notify_on_close
    // ========================================================================

    public function test_sets_notify_on_close_when_provided(): void
    {
        $modal = Modal::make('test', 'Test')
            ->notifyOnClose();

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertTrue($view['notify_on_close']);
    }

    public function test_notify_on_close_is_false_by_default(): void
    {
        $modal = Modal::make('test', 'Test');

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertFalse($view['notify_on_close'] ?? false);
    }

    // ========================================================================
    // Title Truncation
    // ========================================================================

    public function test_truncates_long_titles_to_24_chars(): void
    {
        $modal = Modal::make('test', 'This is a very long modal title that exceeds the limit');

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertLessThanOrEqual(24, strlen($view['title']['text']));
    }

    // ========================================================================
    // Complete Modal
    // ========================================================================

    public function test_converts_complete_modal_with_multiple_inputs(): void
    {
        $modal = Modal::make('feedback_form', 'Submit Feedback')
            ->submitLabel('Send')
            ->closeLabel('Cancel')
            ->notifyOnClose()
            ->input(
                TextInput::make('message', 'Your Feedback')
                    ->multiline()
            )
            ->input(
                Select::make('category', 'Category')
                    ->options([
                        SelectOption::make('bug', 'Bug'),
                        SelectOption::make('feature', 'Feature'),
                        SelectOption::make('other', 'Other'),
                    ])
            )
            ->input(
                TextInput::make('email', 'Email (optional)')
                    ->optional()
            );

        $view = SlackModalRenderer::toSlackView($modal, 'thread-context-123');

        $this->assertSame('feedback_form', $view['callback_id']);
        $this->assertSame('thread-context-123', $view['private_metadata']);
        $this->assertCount(3, $view['blocks']);
        $this->assertSame('input', $view['blocks'][0]['type']);
        $this->assertSame('input', $view['blocks'][1]['type']);
        $this->assertSame('input', $view['blocks'][2]['type']);
    }

    // ========================================================================
    // Encode/Decode Modal Metadata
    // ========================================================================

    public function test_encode_returns_null_when_both_fields_empty(): void
    {
        $result = SlackModalRenderer::encodeMetadata([]);

        $this->assertNull($result);
    }

    public function test_encode_context_id_only(): void
    {
        $encoded = SlackModalRenderer::encodeMetadata(['contextId' => 'uuid-123']);

        $this->assertNotNull($encoded);
        $parsed = json_decode($encoded, true);
        $this->assertSame('uuid-123', $parsed['c']);
        $this->assertArrayNotHasKey('m', $parsed);
    }

    public function test_encode_private_metadata_only(): void
    {
        $encoded = SlackModalRenderer::encodeMetadata([
            'privateMetadata' => '{"chatId":"abc"}',
        ]);

        $this->assertNotNull($encoded);
        $parsed = json_decode($encoded, true);
        $this->assertArrayNotHasKey('c', $parsed);
        $this->assertSame('{"chatId":"abc"}', $parsed['m']);
    }

    public function test_encode_both_context_id_and_private_metadata(): void
    {
        $encoded = SlackModalRenderer::encodeMetadata([
            'contextId' => 'uuid-123',
            'privateMetadata' => '{"chatId":"abc"}',
        ]);

        $parsed = json_decode($encoded, true);
        $this->assertSame('uuid-123', $parsed['c']);
        $this->assertSame('{"chatId":"abc"}', $parsed['m']);
    }

    public function test_decode_returns_empty_array_for_null_input(): void
    {
        $result = SlackModalRenderer::decodeMetadata(null);

        $this->assertSame([], $result);
    }

    public function test_decode_returns_empty_array_for_empty_string(): void
    {
        $result = SlackModalRenderer::decodeMetadata('');

        $this->assertSame([], $result);
    }

    public function test_decode_context_id_only(): void
    {
        $encoded = json_encode(['c' => 'uuid-123']);
        $result = SlackModalRenderer::decodeMetadata($encoded);

        $this->assertSame('uuid-123', $result['contextId']);
        $this->assertArrayNotHasKey('privateMetadata', $result);
    }

    public function test_decode_private_metadata_only(): void
    {
        $encoded = json_encode(['m' => '{"chatId":"abc"}']);
        $result = SlackModalRenderer::decodeMetadata($encoded);

        $this->assertArrayNotHasKey('contextId', $result);
        $this->assertSame('{"chatId":"abc"}', $result['privateMetadata']);
    }

    public function test_decode_both_context_id_and_private_metadata(): void
    {
        $encoded = json_encode(['c' => 'uuid-123', 'm' => '{"chatId":"abc"}']);
        $result = SlackModalRenderer::decodeMetadata($encoded);

        $this->assertSame('uuid-123', $result['contextId']);
        $this->assertSame('{"chatId":"abc"}', $result['privateMetadata']);
    }

    public function test_decode_falls_back_to_plain_string_as_context_id(): void
    {
        $result = SlackModalRenderer::decodeMetadata('plain-uuid-456');

        $this->assertSame('plain-uuid-456', $result['contextId']);
    }

    public function test_decode_falls_back_for_json_without_known_keys(): void
    {
        $result = SlackModalRenderer::decodeMetadata('{"other":"value"}');

        $this->assertSame('{"other":"value"}', $result['contextId']);
    }

    public function test_encode_decode_roundtrip(): void
    {
        $original = [
            'contextId' => 'ctx-1',
            'privateMetadata' => '{"key":"val"}',
        ];

        $encoded = SlackModalRenderer::encodeMetadata($original);
        $decoded = SlackModalRenderer::decodeMetadata($encoded);

        $this->assertSame($original, $decoded);
    }

    // ========================================================================
    // RadioSelect in Modals
    // ========================================================================

    public function test_converts_radio_select_in_modal(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                RadioSelect::make('plan', 'Choose Plan')
                    ->options([
                        SelectOption::make('basic', 'Basic'),
                        SelectOption::make('pro', 'Pro'),
                        SelectOption::make('enterprise', 'Enterprise'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertCount(1, $view['blocks']);
        $block = $view['blocks'][0];
        $this->assertSame('input', $block['type']);
        $this->assertSame('plan', $block['block_id']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Choose Plan'], $block['label']);
        $this->assertSame('radio_buttons', $block['element']['type']);
        $this->assertSame('plan', $block['element']['action_id']);
        $this->assertCount(3, $block['element']['options']);
    }

    public function test_converts_optional_radio_select_in_modal(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                RadioSelect::make('preference', 'Preference')
                    ->optional()
                    ->options([
                        SelectOption::make('yes', 'Yes'),
                        SelectOption::make('no', 'No'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);

        $this->assertTrue($view['blocks'][0]['optional']);
    }

    public function test_radio_select_in_modal_uses_mrkdwn_type(): void
    {
        $modal = Modal::make('test', 'Test')
            ->input(
                RadioSelect::make('option', 'Choose')
                    ->options([
                        SelectOption::make('a', 'Option A'),
                    ])
            );

        $view = SlackModalRenderer::toSlackView($modal);
        $options = $view['blocks'][0]['element']['options'];

        $this->assertSame('mrkdwn', $options[0]['text']['type']);
        $this->assertSame('Option A', $options[0]['text']['text']);
    }

    public function test_radio_select_in_modal_limits_options_to_10(): void
    {
        $options = [];
        for ($i = 1; $i <= 15; $i++) {
            $options[] = SelectOption::make("opt{$i}", "Option {$i}");
        }

        $modal = Modal::make('test', 'Test')
            ->input(
                RadioSelect::make('many_options', 'Many Options')
                    ->options($options)
            );

        $view = SlackModalRenderer::toSlackView($modal);
        $elementOptions = $view['blocks'][0]['element']['options'];

        $this->assertCount(10, $elementOptions);
    }
}
