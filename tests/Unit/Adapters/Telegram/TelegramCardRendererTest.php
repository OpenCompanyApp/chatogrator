<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Telegram;

use OpenCompany\Chatogrator\Adapters\Telegram\TelegramCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group telegram
 */
class TelegramCardRendererTest extends TestCase
{
    private TelegramCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TelegramCardRenderer;
    }

    // ========================================================================
    // Basic Card Conversion
    // ========================================================================

    public function test_renders_simple_card_with_title_only(): void
    {
        $card = Card::make('Welcome');
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('text', $result);
        $this->assertStringContainsString('<b>Welcome</b>', $result['text']);
    }

    public function test_renders_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Your order is on its way');
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<b>Order Update</b>', $result['text']);
        $this->assertStringContainsString('<i>Your order is on its way</i>', $result['text']);
    }

    public function test_renders_card_with_image_url(): void
    {
        $card = Card::make('Product')
            ->imageUrl('https://example.com/product.png');
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<a href="https://example.com/product.png">', $result['text']);
    }

    // ========================================================================
    // Text Element Conversion
    // ========================================================================

    public function test_renders_card_with_text_section(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Regular text'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('Regular text', $result['text']);
    }

    public function test_renders_card_with_bold_text_section(): void
    {
        $card = Card::make('Test')
            ->section(Text::bold('Bold text'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<b>Bold text</b>', $result['text']);
    }

    public function test_renders_card_with_muted_text_section(): void
    {
        $card = Card::make('Test')
            ->section(Text::muted('Muted text'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<i>Muted text</i>', $result['text']);
    }

    // ========================================================================
    // Image Element Conversion
    // ========================================================================

    public function test_renders_card_with_image_element(): void
    {
        $card = Card::make('Test')
            ->section(Image::make('https://example.com/img.png', 'My image'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<a href="https://example.com/img.png">My image</a>', $result['text']);
    }

    // ========================================================================
    // Divider Element Conversion
    // ========================================================================

    public function test_renders_card_with_divider(): void
    {
        $card = Card::make('Test')
            ->divider();
        $result = $this->renderer->render($card);

        $lines = explode("\n", $result['text']);
        $this->assertContains('———', $lines);
    }

    // ========================================================================
    // Fields
    // ========================================================================

    public function test_renders_card_with_fields(): void
    {
        $card = Card::make('Test')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('<b>Status:</b> Active', $result['text']);
        $this->assertStringContainsString('<b>Priority:</b> High', $result['text']);
    }

    // ========================================================================
    // Button Actions
    // ========================================================================

    public function test_renders_card_with_button_actions(): void
    {
        $card = Card::make('Test')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
            );
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('reply_markup', $result);
        $this->assertArrayHasKey('inline_keyboard', $result['reply_markup']);

        $keyboard = $result['reply_markup']['inline_keyboard'];
        $this->assertCount(1, $keyboard); // Both buttons in one row

        $row = $keyboard[0];
        $this->assertCount(2, $row);
        $this->assertSame('Approve', $row[0]['text']);
        $this->assertSame('approve', $row[0]['callback_data']);
        $this->assertSame('Reject', $row[1]['text']);
        $this->assertSame('reject', $row[1]['callback_data']);
    }

    // ========================================================================
    // Link Buttons
    // ========================================================================

    public function test_renders_card_with_link_button(): void
    {
        $card = Card::make('Test')
            ->actions(
                LinkButton::make('https://example.com/docs', 'View Docs'),
            );
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('reply_markup', $result);
        $keyboard = $result['reply_markup']['inline_keyboard'];
        $this->assertCount(1, $keyboard);

        $button = $keyboard[0][0];
        $this->assertSame('View Docs', $button['text']);
        $this->assertSame('https://example.com/docs', $button['url']);
        $this->assertArrayNotHasKey('callback_data', $button);
    }

    // ========================================================================
    // Select Elements
    // ========================================================================

    public function test_renders_card_with_select_as_button_rows(): void
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
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('reply_markup', $result);
        $keyboard = $result['reply_markup']['inline_keyboard'];

        // Each option gets its own row
        $this->assertCount(3, $keyboard);
        $this->assertSame('High', $keyboard[0][0]['text']);
        $this->assertSame('priority:high', $keyboard[0][0]['callback_data']);
        $this->assertSame('Medium', $keyboard[1][0]['text']);
        $this->assertSame('priority:medium', $keyboard[1][0]['callback_data']);
        $this->assertSame('Low', $keyboard[2][0]['text']);
        $this->assertSame('priority:low', $keyboard[2][0]['callback_data']);
    }

    // ========================================================================
    // RadioSelect Elements
    // ========================================================================

    public function test_renders_card_with_radio_select_as_button_rows(): void
    {
        $card = Card::make('Test')
            ->actions(
                RadioSelect::make('plan', 'Choose Plan')
                    ->options([
                        SelectOption::make('basic', 'Basic'),
                        SelectOption::make('pro', 'Pro'),
                    ]),
            );
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('reply_markup', $result);
        $keyboard = $result['reply_markup']['inline_keyboard'];

        $this->assertCount(2, $keyboard);
        $this->assertSame('Basic', $keyboard[0][0]['text']);
        $this->assertSame('plan:basic', $keyboard[0][0]['callback_data']);
        $this->assertSame('Pro', $keyboard[1][0]['text']);
        $this->assertSame('plan:pro', $keyboard[1][0]['callback_data']);
    }

    // ========================================================================
    // Mixed Buttons and Links
    // ========================================================================

    public function test_renders_card_with_mixed_buttons_and_links(): void
    {
        $card = Card::make('Test')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                LinkButton::make('https://example.com', 'View'),
            );
        $result = $this->renderer->render($card);

        $keyboard = $result['reply_markup']['inline_keyboard'];
        // Both in the same row since they are inline actions
        $this->assertCount(1, $keyboard);
        $row = $keyboard[0];
        $this->assertCount(2, $row);

        $this->assertSame('Approve', $row[0]['text']);
        $this->assertArrayHasKey('callback_data', $row[0]);

        $this->assertSame('View', $row[1]['text']);
        $this->assertArrayHasKey('url', $row[1]);
    }

    // ========================================================================
    // Callback Data Truncation
    // ========================================================================

    public function test_callback_data_truncated_to_64_bytes(): void
    {
        $longActionId = str_repeat('a', 100);
        $card = Card::make('Test')
            ->actions(
                Button::make($longActionId, 'Click Me'),
            );
        $result = $this->renderer->render($card);

        $keyboard = $result['reply_markup']['inline_keyboard'];
        $callbackData = $keyboard[0][0]['callback_data'];

        $this->assertLessThanOrEqual(64, strlen($callbackData));
    }

    // ========================================================================
    // Full Card with All Elements
    // ========================================================================

    public function test_renders_full_card_with_all_elements(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Status update')
            ->imageUrl('https://example.com/img.png')
            ->section(Text::make('Your order has been shipped!'))
            ->divider()
            ->fields([
                'Tracking' => 'ABC123',
                'ETA' => 'Dec 25',
            ])
            ->actions(
                Button::make('track', 'Track Package')->primary(),
                LinkButton::make('https://example.com/order', 'View Order'),
            );

        $result = $this->renderer->render($card);

        // Text content
        $this->assertStringContainsString('<b>Order #1234</b>', $result['text']);
        $this->assertStringContainsString('<i>Status update</i>', $result['text']);
        $this->assertStringContainsString('<a href="https://example.com/img.png">', $result['text']);
        $this->assertStringContainsString('Your order has been shipped!', $result['text']);
        $this->assertStringContainsString('———', $result['text']);
        $this->assertStringContainsString('<b>Tracking:</b> ABC123', $result['text']);
        $this->assertStringContainsString('<b>ETA:</b> Dec 25', $result['text']);

        // Keyboard
        $this->assertArrayHasKey('reply_markup', $result);
        $keyboard = $result['reply_markup']['inline_keyboard'];
        $this->assertCount(1, $keyboard);
        $this->assertSame('Track Package', $keyboard[0][0]['text']);
        $this->assertSame('track', $keyboard[0][0]['callback_data']);
        $this->assertSame('View Order', $keyboard[0][1]['text']);
        $this->assertSame('https://example.com/order', $keyboard[0][1]['url']);
    }

    // ========================================================================
    // Fallback Text
    // ========================================================================

    public function test_fallback_text_returns_plain_text(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Status changed');

        $text = $this->renderer->toFallbackText($card);

        $this->assertStringContainsString('Order Update', $text);
        $this->assertStringNotContainsString('<b>', $text);
    }

    // ========================================================================
    // No Actions = No reply_markup
    // ========================================================================

    public function test_card_with_no_actions_has_no_reply_markup(): void
    {
        $card = Card::make('Simple Card')
            ->section(Text::make('Just some text'));
        $result = $this->renderer->render($card);

        $this->assertArrayHasKey('text', $result);
        $this->assertArrayNotHasKey('reply_markup', $result);
    }
}
