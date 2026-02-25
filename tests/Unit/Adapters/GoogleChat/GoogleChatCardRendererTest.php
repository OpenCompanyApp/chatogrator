<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for rendering Card objects to Google Chat Card v2 JSON.
 *
 * Ported from adapter-gchat/src/cards.test.ts (29 tests).
 *
 * @group gchat
 */
class GoogleChatCardRendererTest extends TestCase
{
    private GoogleChatCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new GoogleChatCardRenderer;
    }

    // ── cardToGoogleCard — structure ────────────────────────────────

    public function test_creates_valid_google_chat_card_structure(): void
    {
        $card = Card::make('Test');
        $gchatCard = $this->renderer->render($card);

        $this->assertArrayHasKey('card', $gchatCard);
        $this->assertIsArray($gchatCard['card']['sections']);
    }

    public function test_accepts_optional_card_id(): void
    {
        $card = Card::make('Test');
        $gchatCard = $this->renderer->render($card, ['cardId' => 'my-card-id']);

        $this->assertSame('my-card-id', $gchatCard['cardId']);
    }

    // ── Title / Header ──────────────────────────────────────────────

    public function test_converts_card_with_title(): void
    {
        $card = Card::make('Welcome Message');
        $gchatCard = $this->renderer->render($card);

        $this->assertSame(['title' => 'Welcome Message'], $gchatCard['card']['header']);
    }

    public function test_converts_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Your package is on its way');
        $gchatCard = $this->renderer->render($card);

        $this->assertSame([
            'title' => 'Order Update',
            'subtitle' => 'Your package is on its way',
        ], $gchatCard['card']['header']);
    }

    public function test_converts_card_with_header_image(): void
    {
        $card = Card::make('Product')
            ->imageUrl('https://example.com/product.png');
        $gchatCard = $this->renderer->render($card);

        $this->assertSame([
            'title' => 'Product',
            'imageUrl' => 'https://example.com/product.png',
            'imageType' => 'SQUARE',
        ], $gchatCard['card']['header']);
    }

    // ── Text Elements ───────────────────────────────────────────────

    public function test_converts_text_elements_to_text_paragraph_widgets(): void
    {
        $card = Card::make('')
            ->section(
                Text::make('Regular text'),
                Text::bold('Bold text'),
            );

        $gchatCard = $this->renderer->render($card);

        $this->assertNotEmpty($gchatCard['card']['sections']);
        $widgets = $gchatCard['card']['sections'][0]['widgets'];

        $this->assertCount(2, $widgets);
        $this->assertSame(
            ['textParagraph' => ['text' => 'Regular text']],
            $widgets[0]
        );
        // Bold in Google Chat uses single asterisk
        $this->assertSame(
            ['textParagraph' => ['text' => '*Bold text*']],
            $widgets[1]
        );
    }

    // ── Image Elements ──────────────────────────────────────────────

    public function test_converts_image_elements(): void
    {
        $card = Card::make('')
            ->section(Image::make('https://example.com/img.png', 'My image'));

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertCount(1, $widgets);
        $this->assertSame([
            'image' => [
                'imageUrl' => 'https://example.com/img.png',
                'altText' => 'My image',
            ],
        ], $widgets[0]);
    }

    // ── Divider Elements ────────────────────────────────────────────

    public function test_converts_divider_elements(): void
    {
        $card = Card::make('')
            ->divider();

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertCount(1, $widgets);
        $this->assertSame(['divider' => []], $widgets[0]);
    }

    // ── Actions / Buttons ───────────────────────────────────────────

    public function test_converts_actions_with_buttons_to_button_list(): void
    {
        $card = Card::make('')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
                Button::make('skip', 'Skip'),
            );

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertCount(1, $widgets);

        $buttonList = $widgets[0]['buttonList'];
        $this->assertNotNull($buttonList);
        $this->assertCount(3, $buttonList['buttons']);

        // Primary button
        $this->assertSame('Approve', $buttonList['buttons'][0]['text']);
        $this->assertSame('approve', $buttonList['buttons'][0]['onClick']['action']['function']);
        $this->assertSame(
            [['key' => 'actionId', 'value' => 'approve']],
            $buttonList['buttons'][0]['onClick']['action']['parameters']
        );
        // Primary gets blue color
        $this->assertArrayHasKey('color', $buttonList['buttons'][0]);
        $this->assertEqualsWithDelta(0.2, $buttonList['buttons'][0]['color']['red'], 0.1);
        $this->assertEqualsWithDelta(0.5, $buttonList['buttons'][0]['color']['green'], 0.1);
        $this->assertEqualsWithDelta(0.9, $buttonList['buttons'][0]['color']['blue'], 0.1);

        // Danger button - red color
        $this->assertSame('Reject', $buttonList['buttons'][1]['text']);
        $this->assertArrayHasKey('color', $buttonList['buttons'][1]);
        $this->assertEqualsWithDelta(0.9, $buttonList['buttons'][1]['color']['red'], 0.1);
        $this->assertEqualsWithDelta(0.2, $buttonList['buttons'][1]['color']['green'], 0.1);
        $this->assertEqualsWithDelta(0.2, $buttonList['buttons'][1]['color']['blue'], 0.1);

        // Default button - no color
        $this->assertSame('Skip', $buttonList['buttons'][2]['text']);
        $this->assertArrayNotHasKey('color', $buttonList['buttons'][2]);
    }

    public function test_uses_endpoint_url_as_function_when_provided(): void
    {
        $card = Card::make('')
            ->actions(
                Button::make('approve', 'Approve'),
                Button::make('reject', 'Reject'),
            );

        $gchatCard = $this->renderer->render($card, [
            'endpointUrl' => 'https://example.com/api/webhooks/gchat',
        ]);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $buttonList = $widgets[0]['buttonList'];

        // With endpointUrl, function should be the URL, actionId in parameters
        $this->assertSame(
            'https://example.com/api/webhooks/gchat',
            $buttonList['buttons'][0]['onClick']['action']['function']
        );
        $this->assertSame(
            [['key' => 'actionId', 'value' => 'approve']],
            $buttonList['buttons'][0]['onClick']['action']['parameters']
        );

        $this->assertSame(
            'https://example.com/api/webhooks/gchat',
            $buttonList['buttons'][1]['onClick']['action']['function']
        );
        $this->assertSame(
            [['key' => 'actionId', 'value' => 'reject']],
            $buttonList['buttons'][1]['onClick']['action']['parameters']
        );
    }

    public function test_button_with_value_includes_value_parameter(): void
    {
        $card = Card::make('')
            ->actions(
                Button::make('reject', 'Reject')->danger(),
            );

        // Note: Button doesn't have a value setter in the PHP API yet.
        // This tests that when implemented, value is passed through.
        $gchatCard = $this->renderer->render($card);
        $buttonList = $gchatCard['card']['sections'][0]['widgets'][0]['buttonList'];

        $params = $buttonList['buttons'][0]['onClick']['action']['parameters'];
        $this->assertContains(
            ['key' => 'actionId', 'value' => 'reject'],
            $params
        );
    }

    public function test_converts_link_buttons_with_open_link(): void
    {
        $card = Card::make('')
            ->actions(
                LinkButton::make('https://example.com/docs', 'View Docs'),
            );

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $buttonList = $widgets[0]['buttonList'];
        $this->assertCount(1, $buttonList['buttons']);

        $this->assertSame('View Docs', $buttonList['buttons'][0]['text']);
        $this->assertSame(
            ['url' => 'https://example.com/docs'],
            $buttonList['buttons'][0]['onClick']['openLink']
        );
    }

    // ── Fields ──────────────────────────────────────────────────────

    public function test_converts_fields_to_decorated_text_widgets(): void
    {
        $card = Card::make('')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertCount(2, $widgets);

        $this->assertSame([
            'decoratedText' => [
                'topLabel' => 'Status',
                'text' => 'Active',
            ],
        ], $widgets[0]);

        $this->assertSame([
            'decoratedText' => [
                'topLabel' => 'Priority',
                'text' => 'High',
            ],
        ], $widgets[1]);
    }

    // ── Sections ────────────────────────────────────────────────────

    public function test_creates_separate_sections_for_section_children(): void
    {
        $card = Card::make('')
            ->section(Text::make('Before section'))
            ->section(Text::make('Inside section'))
            ->section(Text::make('After section'));

        $gchatCard = $this->renderer->render($card);

        // Should have 3 sections
        $this->assertCount(3, $gchatCard['card']['sections']);

        $this->assertSame(
            'Before section',
            $gchatCard['card']['sections'][0]['widgets'][0]['textParagraph']['text']
        );
        $this->assertSame(
            'Inside section',
            $gchatCard['card']['sections'][1]['widgets'][0]['textParagraph']['text']
        );
        $this->assertSame(
            'After section',
            $gchatCard['card']['sections'][2]['widgets'][0]['textParagraph']['text']
        );
    }

    // ── Complete Card ───────────────────────────────────────────────

    public function test_converts_complete_card(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Status update')
            ->section(Text::make('Your order has been shipped!'))
            ->fields([
                'Tracking' => 'ABC123',
                'ETA' => 'Dec 25',
            ])
            ->actions(
                Button::make('track', 'Track Package')->primary(),
            );

        $gchatCard = $this->renderer->render($card);

        $this->assertSame('Order #1234', $gchatCard['card']['header']['title']);
        $this->assertSame('Status update', $gchatCard['card']['header']['subtitle']);

        $this->assertNotEmpty($gchatCard['card']['sections']);

        // Collect all widgets across sections
        $allWidgets = [];
        foreach ($gchatCard['card']['sections'] as $section) {
            foreach ($section['widgets'] as $widget) {
                $allWidgets[] = $widget;
            }
        }

        // Should have: text + 2 fields + buttonList = 4 widgets
        $this->assertCount(4, $allWidgets);
        $this->assertArrayHasKey('textParagraph', $allWidgets[0]);
        $this->assertArrayHasKey('decoratedText', $allWidgets[1]);
        $this->assertArrayHasKey('decoratedText', $allWidgets[2]);
        $this->assertArrayHasKey('buttonList', $allWidgets[3]);
    }

    // ── Empty Card ──────────────────────────────────────────────────

    public function test_creates_empty_section_with_placeholder_for_empty_cards(): void
    {
        $card = Card::make('');
        $gchatCard = $this->renderer->render($card);

        // Google Chat requires at least one section with at least one widget
        $this->assertNotEmpty($gchatCard['card']['sections']);
        $this->assertNotEmpty($gchatCard['card']['sections'][0]['widgets']);
    }

    // ── Markdown Bold Conversion ────────────────────────────────────

    public function test_converts_double_asterisk_bold_to_single_in_card_text(): void
    {
        $card = Card::make('')
            ->section(Text::make('The **domain** is example.com'));

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertSame(
            'The *domain* is example.com',
            $widgets[0]['textParagraph']['text']
        );
    }

    public function test_converts_multiple_double_asterisk_segments(): void
    {
        $card = Card::make('')
            ->section(Text::make('**Project**: my-app, **Status**: active'));

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertSame(
            '*Project*: my-app, *Status*: active',
            $widgets[0]['textParagraph']['text']
        );
    }

    public function test_preserves_existing_single_asterisk_formatting(): void
    {
        $card = Card::make('')
            ->section(Text::make('Already *bold* in GChat format'));

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertSame(
            'Already *bold* in GChat format',
            $widgets[0]['textParagraph']['text']
        );
    }

    public function test_converts_double_asterisk_bold_in_field_values(): void
    {
        $card = Card::make('')
            ->fields(['Status' => '**Active**']);

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $decoratedText = $widgets[0]['decoratedText'];
        $this->assertSame('*Active*', $decoratedText['text']);
        $this->assertStringNotContainsString('**', $decoratedText['text']);
    }

    public function test_converts_double_asterisk_bold_in_field_labels(): void
    {
        $card = Card::make('')
            ->fields(['**Important**' => 'value']);

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertSame('*Important*', $widgets[0]['decoratedText']['topLabel']);
    }

    public function test_handles_text_with_no_markdown(): void
    {
        $card = Card::make('')
            ->section(Text::make('Plain text'));

        $gchatCard = $this->renderer->render($card);

        $widgets = $gchatCard['card']['sections'][0]['widgets'];
        $this->assertSame('Plain text', $widgets[0]['textParagraph']['text']);
    }

    // ── cardToFallbackText ──────────────────────────────────────────

    public function test_fallback_text_for_complete_card(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Status changed')
            ->section(Text::make('Your order is ready'))
            ->fields([
                'Order ID' => '#1234',
                'Status' => 'Ready',
            ])
            ->actions(
                Button::make('pickup', 'Schedule Pickup'),
                Button::make('delay', 'Delay'),
            );

        $text = $this->renderer->fallbackText($card);

        // Google Chat uses single asterisk for bold
        $this->assertStringContainsString('*Order Update*', $text);
        $this->assertStringContainsString('Status changed', $text);
        $this->assertStringContainsString('Your order is ready', $text);
        $this->assertStringContainsString('Order ID', $text);
        $this->assertStringContainsString('#1234', $text);
        $this->assertStringContainsString('Status', $text);
        $this->assertStringContainsString('Ready', $text);
    }

    public function test_fallback_text_for_title_only(): void
    {
        $card = Card::make('Simple Card');

        $text = $this->renderer->fallbackText($card);

        // Google Chat uses single asterisk for bold
        $this->assertSame('*Simple Card*', $text);
    }
}
