<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GitHub;

use OpenCompany\Chatogrator\Adapters\GitHub\GitHubCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for rendering Card objects to GitHub-flavored markdown.
 *
 * Ported from adapter-github/src/cards.test.ts.
 *
 * @group github
 */
class GitHubCardRendererTest extends TestCase
{
    private GitHubCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new GitHubCardRenderer;
    }

    // ── cardToGitHubMarkdown ────────────────────────────────────────

    public function test_renders_simple_card_with_title(): void
    {
        $card = Card::make('Hello World');
        $result = $this->renderer->render($card);

        $this->assertSame('**Hello World**', $result);
    }

    public function test_renders_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Status update');
        $result = $this->renderer->render($card);

        $this->assertSame("**Order #1234**\nStatus update", $result);
    }

    public function test_renders_card_with_text_content(): void
    {
        $card = Card::make('Notification')
            ->section(Text::make('Your order has been shipped!'));
        $result = $this->renderer->render($card);

        $this->assertSame("**Notification**\n\nYour order has been shipped!", $result);
    }

    public function test_renders_card_with_fields(): void
    {
        $card = Card::make('Order Details')
            ->fields([
                'Order ID' => '12345',
                'Status' => 'Shipped',
            ]);
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('**Order ID:** 12345', $result);
        $this->assertStringContainsString('**Status:** Shipped', $result);
    }

    public function test_renders_card_with_link_buttons(): void
    {
        $card = Card::make('Actions')
            ->actions(
                LinkButton::make('https://example.com/track', 'Track Order'),
                LinkButton::make('https://example.com/help', 'Get Help'),
            );
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('[Track Order](https://example.com/track)', $result);
        $this->assertStringContainsString('[Get Help](https://example.com/help)', $result);
    }

    public function test_renders_card_with_action_buttons_as_bold_text(): void
    {
        $card = Card::make('Approve?')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
            );
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('**[Approve]**', $result);
        $this->assertStringContainsString('**[Reject]**', $result);
    }

    public function test_renders_card_with_image(): void
    {
        $card = Card::make('Image Card')
            ->section(Image::make('https://example.com/image.png', 'Example image'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('![Example image](https://example.com/image.png)', $result);
    }

    public function test_renders_card_with_divider(): void
    {
        $card = Card::make('')
            ->section(Text::make('Before'))
            ->divider()
            ->section(Text::make('After'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('---', $result);
    }

    public function test_renders_card_with_section(): void
    {
        $card = Card::make('')
            ->section(Text::make('Section content'));
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('Section content', $result);
    }

    public function test_handles_text_with_different_styles(): void
    {
        $card = Card::make('')
            ->section(
                Text::make('Normal text'),
                Text::bold('Bold text'),
                Text::muted('Muted text'),
            );
        $result = $this->renderer->render($card);

        $this->assertStringContainsString('Normal text', $result);
        $this->assertStringContainsString('**Bold text**', $result);
        $this->assertStringContainsString('_Muted text_', $result);
    }

    // ── cardToPlainText (fallback) ──────────────────────────────────

    public function test_generates_plain_text_from_card(): void
    {
        $card = Card::make('Hello')
            ->subtitle('World')
            ->section(Text::make('Some content'))
            ->fields([
                'Key' => 'Value',
            ]);

        $text = $this->renderer->render($card);

        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
        $this->assertStringContainsString('Some content', $text);
        $this->assertStringContainsString('Key', $text);
        $this->assertStringContainsString('Value', $text);
    }
}
