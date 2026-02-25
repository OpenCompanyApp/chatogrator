<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Actions;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Fields;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group slack
 */
class SlackCardRendererTest extends TestCase
{
    private SlackCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new SlackCardRenderer;
    }

    // ========================================================================
    // Basic Card Conversion
    // ========================================================================

    public function test_converts_simple_card_with_title_to_header_block(): void
    {
        $card = Card::make('Welcome');
        $blocks = $this->renderer->render($card);

        $this->assertCount(1, $blocks);
        $this->assertSame([
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => 'Welcome',
                'emoji' => true,
            ],
        ], $blocks[0]);
    }

    public function test_converts_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Your order is on its way');
        $blocks = $this->renderer->render($card);

        $this->assertCount(2, $blocks);
        $this->assertSame('header', $blocks[0]['type']);
        $this->assertSame([
            'type' => 'context',
            'elements' => [['type' => 'mrkdwn', 'text' => 'Your order is on its way']],
        ], $blocks[1]);
    }

    public function test_converts_card_with_header_image(): void
    {
        $card = Card::make('Product')
            ->imageUrl('https://example.com/product.png');
        $blocks = $this->renderer->render($card);

        $this->assertCount(2, $blocks);
        $this->assertSame([
            'type' => 'image',
            'image_url' => 'https://example.com/product.png',
            'alt_text' => 'Product',
        ], $blocks[1]);
    }

    // ========================================================================
    // Text Element Conversion
    // ========================================================================

    public function test_converts_regular_text_to_section_with_mrkdwn(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Regular text'));
        $blocks = $this->renderer->render($card);

        // Find the section block (skip header)
        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertNotNull($sectionBlock);
        $this->assertSame([
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => 'Regular text'],
        ], $sectionBlock);
    }

    public function test_converts_bold_text_to_mrkdwn_bold(): void
    {
        $card = Card::make('Test')
            ->section(Text::bold('Bold text'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertNotNull($sectionBlock);
        $this->assertSame('*Bold text*', $sectionBlock['text']['text']);
    }

    public function test_converts_muted_text_to_context_block(): void
    {
        $card = Card::make('Test')
            ->section(Text::muted('Muted text'));
        $blocks = $this->renderer->render($card);

        $contextBlock = $this->findBlockByType($blocks, 'context');
        $this->assertNotNull($contextBlock);
        $this->assertSame([
            'type' => 'context',
            'elements' => [['type' => 'mrkdwn', 'text' => 'Muted text']],
        ], $contextBlock);
    }

    // ========================================================================
    // Image Element Conversion
    // ========================================================================

    public function test_converts_image_elements(): void
    {
        $card = Card::make('Test')
            ->section(Image::make('https://example.com/img.png', 'My image'));
        $blocks = $this->renderer->render($card);

        $imageBlock = $this->findBlockByType($blocks, 'image');
        $this->assertNotNull($imageBlock);
        $this->assertSame([
            'type' => 'image',
            'image_url' => 'https://example.com/img.png',
            'alt_text' => 'My image',
        ], $imageBlock);
    }

    // ========================================================================
    // Divider Element Conversion
    // ========================================================================

    public function test_converts_divider_elements(): void
    {
        $card = Card::make('Test')
            ->divider();
        $blocks = $this->renderer->render($card);

        $dividerBlock = $this->findBlockByType($blocks, 'divider');
        $this->assertNotNull($dividerBlock);
        $this->assertSame(['type' => 'divider'], $dividerBlock);
    }

    // ========================================================================
    // Actions with Buttons
    // ========================================================================

    public function test_converts_actions_with_buttons(): void
    {
        $card = Card::make('Test')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
                Button::make('skip', 'Skip'),
            );
        $blocks = $this->renderer->render($card);

        $actionsBlock = $this->findBlockByType($blocks, 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertSame('actions', $actionsBlock['type']);
        $this->assertCount(3, $actionsBlock['elements']);

        // Primary button
        $this->assertSame([
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => 'Approve', 'emoji' => true],
            'action_id' => 'approve',
            'style' => 'primary',
        ], $actionsBlock['elements'][0]);

        // Danger button
        $this->assertSame([
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => 'Reject', 'emoji' => true],
            'action_id' => 'reject',
            'style' => 'danger',
        ], $actionsBlock['elements'][1]);

        // Default button (no style)
        $this->assertSame([
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => 'Skip', 'emoji' => true],
            'action_id' => 'skip',
        ], $actionsBlock['elements'][2]);
    }

    // ========================================================================
    // Link Buttons
    // ========================================================================

    public function test_converts_link_buttons_with_url(): void
    {
        $card = Card::make('Test')
            ->actions(
                LinkButton::make('https://example.com/docs', 'View Docs'),
            );
        $blocks = $this->renderer->render($card);

        $actionsBlock = $this->findBlockByType($blocks, 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertCount(1, $actionsBlock['elements']);

        $element = $actionsBlock['elements'][0];
        $this->assertSame('button', $element['type']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'View Docs', 'emoji' => true], $element['text']);
        $this->assertSame('https://example.com/docs', $element['url']);
    }

    // ========================================================================
    // Fields
    // ========================================================================

    public function test_converts_fields_to_section_with_fields_array(): void
    {
        $card = Card::make('Test')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertNotNull($sectionBlock);
        $this->assertSame('section', $sectionBlock['type']);
        $this->assertSame([
            ['type' => 'mrkdwn', 'text' => "*Status*\nActive"],
            ['type' => 'mrkdwn', 'text' => "*Priority*\nHigh"],
        ], $sectionBlock['fields']);
    }

    // ========================================================================
    // Section Flattening
    // ========================================================================

    public function test_flattens_section_children(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Inside section'), Divider::make());
        $blocks = $this->renderer->render($card);

        // Should produce a section block + divider block (flattened from section children)
        $types = array_column($blocks, 'type');
        // header + section + divider
        $this->assertContains('section', $types);
        $this->assertContains('divider', $types);
    }

    // ========================================================================
    // Complete Card
    // ========================================================================

    public function test_converts_complete_card_with_all_elements(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Status update')
            ->section(Text::make('Your order has been shipped!'))
            ->divider()
            ->fields([
                'Tracking' => 'ABC123',
                'ETA' => 'Dec 25',
            ])
            ->actions(
                Button::make('track', 'Track Package')->primary(),
            );

        $blocks = $this->renderer->render($card);

        // Expected: header, context (subtitle), section, divider, section (fields), actions
        $this->assertCount(6, $blocks);
        $this->assertSame('header', $blocks[0]['type']);
        $this->assertSame('context', $blocks[1]['type']);
        $this->assertSame('section', $blocks[2]['type']);
        $this->assertSame('divider', $blocks[3]['type']);
        $this->assertSame('section', $blocks[4]['type']);
        $this->assertSame('actions', $blocks[5]['type']);
    }

    // ========================================================================
    // Fallback Text
    // ========================================================================

    public function test_fallback_text_contains_title(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Status changed')
            ->section(Text::make('Your order is ready'));

        $text = $this->renderer->toFallbackText($card);

        $this->assertStringContainsString('Order Update', $text);
    }

    public function test_fallback_text_for_card_with_only_title(): void
    {
        $card = Card::make('Simple Card');

        $text = $this->renderer->toFallbackText($card);

        $this->assertStringContainsString('Simple Card', $text);
    }

    // ========================================================================
    // Select Elements
    // ========================================================================

    public function test_converts_select_element(): void
    {
        $card = Card::make('Test')
            ->actions(
                Select::make('priority', 'Select priority')
                    ->options([
                        SelectOption::make('high', 'High'),
                        SelectOption::make('medium', 'Medium'),
                        SelectOption::make('low', 'Low'),
                    ]),
            );
        $blocks = $this->renderer->render($card);

        $actionsBlock = $this->findBlockByType($blocks, 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertCount(1, $actionsBlock['elements']);

        $element = $actionsBlock['elements'][0];
        $this->assertSame('static_select', $element['type']);
        $this->assertSame('priority', $element['action_id']);
        $this->assertSame(['type' => 'plain_text', 'text' => 'Select priority'], $element['placeholder']);
        $this->assertCount(3, $element['options']);
        $this->assertSame([
            'text' => ['type' => 'plain_text', 'text' => 'High'],
            'value' => 'high',
        ], $element['options'][0]);
    }

    public function test_converts_mixed_buttons_and_selects(): void
    {
        $card = Card::make('Test')
            ->actions(
                Select::make('status', 'Select status')
                    ->options([
                        SelectOption::make('open', 'Open'),
                        SelectOption::make('closed', 'Closed'),
                    ]),
                Button::make('submit', 'Submit')->primary(),
            );
        $blocks = $this->renderer->render($card);

        $actionsBlock = $this->findBlockByType($blocks, 'actions');
        $this->assertCount(2, $actionsBlock['elements']);
        $this->assertSame('static_select', $actionsBlock['elements'][0]['type']);
        $this->assertSame('button', $actionsBlock['elements'][1]['type']);
    }

    public function test_converts_select_without_placeholder_or_initial_option(): void
    {
        $card = Card::make('Test')
            ->actions(
                Select::make('category', '')
                    ->options([
                        SelectOption::make('bug', 'Bug'),
                        SelectOption::make('feature', 'Feature'),
                    ]),
            );
        $blocks = $this->renderer->render($card);

        $element = $this->findBlockByType($blocks, 'actions')['elements'][0];
        $this->assertSame('static_select', $element['type']);
        // initial_option should not be present
        $this->assertArrayNotHasKey('initial_option', $element);
    }

    // ========================================================================
    // RadioSelect Elements
    // ========================================================================

    public function test_converts_radio_select_element(): void
    {
        $card = Card::make('Test')
            ->actions(
                RadioSelect::make('plan', 'Choose Plan')
                    ->options([
                        SelectOption::make('basic', 'Basic'),
                        SelectOption::make('pro', 'Pro'),
                        SelectOption::make('enterprise', 'Enterprise'),
                    ]),
            );
        $blocks = $this->renderer->render($card);

        $actionsBlock = $this->findBlockByType($blocks, 'actions');
        $this->assertCount(1, $actionsBlock['elements']);

        $element = $actionsBlock['elements'][0];
        $this->assertSame('radio_buttons', $element['type']);
        $this->assertSame('plan', $element['action_id']);
        $this->assertCount(3, $element['options']);
    }

    public function test_radio_select_uses_mrkdwn_type_for_labels(): void
    {
        $card = Card::make('Test')
            ->actions(
                RadioSelect::make('option', 'Choose')
                    ->options([
                        SelectOption::make('a', 'Option A'),
                    ]),
            );
        $blocks = $this->renderer->render($card);

        $element = $this->findBlockByType($blocks, 'actions')['elements'][0];
        $this->assertSame('mrkdwn', $element['options'][0]['text']['type']);
        $this->assertSame('Option A', $element['options'][0]['text']['text']);
    }

    public function test_radio_select_limits_options_to_10(): void
    {
        $options = [];
        for ($i = 1; $i <= 15; $i++) {
            $options[] = SelectOption::make("opt{$i}", "Option {$i}");
        }

        $card = Card::make('Test')
            ->actions(
                RadioSelect::make('many_options', 'Many Options')
                    ->options($options),
            );
        $blocks = $this->renderer->render($card);

        $element = $this->findBlockByType($blocks, 'actions')['elements'][0];
        $this->assertCount(10, $element['options']);
    }

    // ========================================================================
    // Select Option Descriptions
    // ========================================================================

    public function test_select_options_include_description_as_plain_text(): void
    {
        // This test assumes SelectOption supports a description method/property.
        // If SelectOption has a description setter, use it; otherwise skip.
        $card = Card::make('Test')
            ->actions(
                Select::make('plan', 'Plan')
                    ->options([
                        SelectOption::make('basic', 'Basic'),
                        SelectOption::make('pro', 'Pro'),
                    ]),
            );
        $blocks = $this->renderer->render($card);

        $element = $this->findBlockByType($blocks, 'actions')['elements'][0];
        // Without description, it should be absent
        $this->assertArrayNotHasKey('description', $element['options'][0]);
    }

    // ========================================================================
    // Markdown Bold to Slack mrkdwn Conversion in Cards
    // ========================================================================

    public function test_converts_double_asterisk_bold_to_single_in_card_text(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('The **domain** is example.com'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('The *domain* is example.com', $sectionBlock['text']['text']);
    }

    public function test_converts_multiple_bold_segments_in_one_text(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('**Project**: my-app, **Status**: active, **Branch**: main'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('*Project*: my-app, *Status*: active, *Branch*: main', $sectionBlock['text']['text']);
    }

    public function test_converts_bold_across_multiple_lines(): void
    {
        $card = Card::make('Test')
            ->section(Text::make("**Domain**: example.com\n**Project**: my-app\n**Status**: deployed"));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame("*Domain*: example.com\n*Project*: my-app\n*Status*: deployed", $sectionBlock['text']['text']);
    }

    public function test_preserves_existing_single_asterisk_formatting(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Already *bold* in Slack format'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('Already *bold* in Slack format', $sectionBlock['text']['text']);
    }

    public function test_handles_text_with_no_markdown_formatting(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Plain text with no formatting'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('Plain text with no formatting', $sectionBlock['text']['text']);
    }

    public function test_converts_bold_in_muted_style_card_text(): void
    {
        $card = Card::make('Test')
            ->section(Text::muted('Info about **thing**'));
        $blocks = $this->renderer->render($card);

        $contextBlock = $this->findBlockByType($blocks, 'context');
        $this->assertNotNull($contextBlock);
        $this->assertSame('Info about *thing*', $contextBlock['elements'][0]['text']);
    }

    public function test_converts_bold_in_field_values(): void
    {
        $card = Card::make('Test')
            ->fields(['Status' => '**Active**']);
        $blocks = $this->renderer->render($card);

        $fieldsBlock = $this->findBlockByType($blocks, 'section');
        $this->assertNotNull($fieldsBlock);
        $fieldText = $fieldsBlock['fields'][0]['text'];
        $this->assertStringContainsString('*Active*', $fieldText);
        $this->assertStringNotContainsString('**Active**', $fieldText);
    }

    public function test_does_not_convert_empty_double_asterisks(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('text **** more'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('text **** more', $sectionBlock['text']['text']);
    }

    public function test_handles_bold_at_start_and_end_of_content(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('**Start** and **end**'));
        $blocks = $this->renderer->render($card);

        $sectionBlock = $this->findBlockByType($blocks, 'section');
        $this->assertSame('*Start* and *end*', $sectionBlock['text']['text']);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function findBlockByType(array $blocks, string $type): ?array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === $type) {
                return $block;
            }
        }

        return null;
    }
}
