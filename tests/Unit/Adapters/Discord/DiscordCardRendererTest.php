<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Discord;

use OpenCompany\Chatogrator\Adapters\Discord\DiscordCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Actions;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Fields;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for rendering Card objects to Discord Embeds + Components JSON.
 *
 * Ported from adapter-discord/src/cards.test.ts (23 tests).
 *
 * @group discord
 */
class DiscordCardRendererTest extends TestCase
{
    private DiscordCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new DiscordCardRenderer;
    }

    // ── cardToDiscordPayload ────────────────────────────────────────

    public function test_converts_simple_card_with_title(): void
    {
        $card = Card::make('Welcome');
        $payload = $this->renderer->render($card);

        $this->assertArrayHasKey('embeds', $payload);
        $this->assertCount(1, $payload['embeds']);
        $this->assertSame('Welcome', $payload['embeds'][0]['title']);
        $this->assertEmpty($payload['components'] ?? []);
    }

    public function test_converts_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Your order is on its way');
        $payload = $this->renderer->render($card);

        $this->assertCount(1, $payload['embeds']);
        $this->assertSame('Order Update', $payload['embeds'][0]['title']);
        $this->assertStringContainsString(
            'Your order is on its way',
            $payload['embeds'][0]['description']
        );
    }

    public function test_converts_card_with_header_image(): void
    {
        $card = Card::make('Product')
            ->imageUrl('https://example.com/product.png');
        $payload = $this->renderer->render($card);

        $this->assertCount(1, $payload['embeds']);
        $this->assertSame(
            ['url' => 'https://example.com/product.png'],
            $payload['embeds'][0]['image']
        );
    }

    public function test_sets_default_color_to_discord_blurple(): void
    {
        $card = Card::make('Test');
        $payload = $this->renderer->render($card);

        // Discord blurple = 0x5865F2 = 5793266
        $this->assertSame(0x5865F2, $payload['embeds'][0]['color']);
    }

    public function test_converts_text_elements(): void
    {
        $card = Card::make('Test')
            ->section(
                Text::make('Regular text'),
                Text::bold('Bold text'),
                Text::muted('Muted text'),
            );

        $payload = $this->renderer->render($card);

        $description = $payload['embeds'][0]['description'] ?? '';
        $this->assertStringContainsString('Regular text', $description);
        $this->assertStringContainsString('**Bold text**', $description);
        $this->assertStringContainsString('*Muted text*', $description);
    }

    public function test_converts_image_elements_in_children(): void
    {
        $card = Card::make('Test')
            ->section(Image::make('https://example.com/img.png', 'My image'));

        $payload = $this->renderer->render($card);

        $this->assertCount(1, $payload['embeds']);
    }

    public function test_converts_divider_to_horizontal_line(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Before'))
            ->divider()
            ->section(Text::make('After'));

        $payload = $this->renderer->render($card);

        $description = $payload['embeds'][0]['description'] ?? '';
        $this->assertStringContainsString('Before', $description);
        // Discord uses a unicode line or dashes for dividers
        $this->assertTrue(
            str_contains($description, '───') || str_contains($description, '---'),
            'Divider should produce a separator line'
        );
        $this->assertStringContainsString('After', $description);
    }

    public function test_converts_actions_with_buttons(): void
    {
        $card = Card::make('Test')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
                Button::make('skip', 'Skip'),
            );

        $payload = $this->renderer->render($card);

        $this->assertNotEmpty($payload['components']);
        $this->assertSame(1, $payload['components'][0]['type']); // Action Row

        $buttons = $payload['components'][0]['components'];
        $this->assertCount(3, $buttons);

        // Primary button: style = 1
        $this->assertSame(2, $buttons[0]['type']); // Button component type
        $this->assertSame(1, $buttons[0]['style']); // ButtonStyle.Primary
        $this->assertSame('Approve', $buttons[0]['label']);
        $this->assertSame('approve', $buttons[0]['custom_id']);

        // Danger button: style = 4
        $this->assertSame(4, $buttons[1]['style']); // ButtonStyle.Danger
        $this->assertSame('Reject', $buttons[1]['label']);
        $this->assertSame('reject', $buttons[1]['custom_id']);

        // Default button: style = 2
        $this->assertSame(2, $buttons[2]['style']); // ButtonStyle.Secondary
        $this->assertSame('Skip', $buttons[2]['label']);
        $this->assertSame('skip', $buttons[2]['custom_id']);
    }

    public function test_converts_link_buttons_with_link_style(): void
    {
        $card = Card::make('Test')
            ->actions(
                LinkButton::make('https://example.com/docs', 'View Docs'),
            );

        $payload = $this->renderer->render($card);

        $this->assertNotEmpty($payload['components']);
        $this->assertSame(1, $payload['components'][0]['type']); // Action Row

        $buttons = $payload['components'][0]['components'];
        $this->assertCount(1, $buttons);

        $this->assertSame(2, $buttons[0]['type']); // Button component type
        $this->assertSame(5, $buttons[0]['style']); // ButtonStyle.Link
        $this->assertSame('View Docs', $buttons[0]['label']);
        $this->assertSame('https://example.com/docs', $buttons[0]['url']);
    }

    public function test_converts_fields_to_embed_fields(): void
    {
        $card = Card::make('Test')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);

        $payload = $this->renderer->render($card);

        $this->assertCount(2, $payload['embeds'][0]['fields']);

        $this->assertSame([
            'name' => 'Status',
            'value' => 'Active',
            'inline' => true,
        ], $payload['embeds'][0]['fields'][0]);

        $this->assertSame([
            'name' => 'Priority',
            'value' => 'High',
            'inline' => true,
        ], $payload['embeds'][0]['fields'][1]);
    }

    public function test_flattens_section_children(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Inside section'), Divider::make());

        $payload = $this->renderer->render($card);

        $description = $payload['embeds'][0]['description'] ?? '';
        $this->assertStringContainsString('Inside section', $description);
        $this->assertTrue(
            str_contains($description, '───') || str_contains($description, '---'),
            'Divider inside section should produce separator'
        );
    }

    public function test_converts_complete_card(): void
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

        $payload = $this->renderer->render($card);

        $this->assertCount(1, $payload['embeds']);
        $this->assertSame('Order #1234', $payload['embeds'][0]['title']);
        $this->assertStringContainsString('Status update', $payload['embeds'][0]['description']);
        $this->assertStringContainsString('Your order has been shipped!', $payload['embeds'][0]['description']);
        $this->assertCount(2, $payload['embeds'][0]['fields']);
        $this->assertNotEmpty($payload['components']);
        $this->assertCount(1, $payload['components'][0]['components']);
    }

    public function test_handles_card_with_no_title_or_subtitle(): void
    {
        // Card::make requires a title, but render should handle minimal data
        $card = Card::make('')
            ->section(Text::make('Just content'));

        $payload = $this->renderer->render($card);

        $this->assertStringContainsString('Just content', $payload['embeds'][0]['description'] ?? '');
    }

    public function test_combines_title_subtitle_and_content(): void
    {
        $card = Card::make('Title')
            ->subtitle('Subtitle')
            ->section(Text::make('Content'));

        $payload = $this->renderer->render($card);

        $this->assertSame('Title', $payload['embeds'][0]['title']);
        $description = $payload['embeds'][0]['description'] ?? '';
        $this->assertStringContainsString('Subtitle', $description);
        $this->assertStringContainsString('Content', $description);
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

        $this->assertStringContainsString('Order Update', $text);
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

        $this->assertStringContainsString('Simple Card', $text);
    }

    public function test_fallback_text_for_subtitle_only(): void
    {
        $card = Card::make('')
            ->subtitle('Just a subtitle');

        $text = $this->renderer->fallbackText($card);

        $this->assertStringContainsString('Just a subtitle', $text);
    }

    public function test_fallback_text_handles_divider(): void
    {
        $card = Card::make('')
            ->section(Text::make('Before'))
            ->divider()
            ->section(Text::make('After'));

        $text = $this->renderer->fallbackText($card);

        $this->assertStringContainsString('Before', $text);
        $this->assertStringContainsString('After', $text);
    }

    public function test_fallback_text_handles_section_content(): void
    {
        $card = Card::make('')
            ->section(Text::make('Section content'));

        $text = $this->renderer->fallbackText($card);

        $this->assertStringContainsString('Section content', $text);
    }

    public function test_fallback_text_handles_multiple_fields(): void
    {
        $card = Card::make('')
            ->fields([
                'A' => '1',
                'B' => '2',
                'C' => '3',
            ]);

        $text = $this->renderer->fallbackText($card);

        $this->assertStringContainsString('A', $text);
        $this->assertStringContainsString('1', $text);
        $this->assertStringContainsString('B', $text);
        $this->assertStringContainsString('2', $text);
        $this->assertStringContainsString('C', $text);
        $this->assertStringContainsString('3', $text);
    }
}
