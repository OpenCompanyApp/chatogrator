<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Teams;

use OpenCompany\Chatogrator\Adapters\Teams\TeamsCardRenderer;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for rendering Card objects to Adaptive Card JSON for Teams.
 *
 * Ported from adapter-teams/src/cards.test.ts (13 tests).
 *
 * @group teams
 */
class TeamsCardRendererTest extends TestCase
{
    private TeamsCardRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TeamsCardRenderer;
    }

    // ── cardToAdaptiveCard ──────────────────────────────────────────

    public function test_creates_valid_adaptive_card_structure(): void
    {
        $card = Card::make('Test');
        $adaptive = $this->renderer->render($card);

        $this->assertSame('AdaptiveCard', $adaptive['type']);
        $this->assertSame('http://adaptivecards.io/schemas/adaptive-card.json', $adaptive['$schema']);
        $this->assertSame('1.4', $adaptive['version']);
        $this->assertIsArray($adaptive['body']);
    }

    public function test_converts_card_with_title(): void
    {
        $card = Card::make('Welcome Message');
        $adaptive = $this->renderer->render($card);

        $this->assertCount(1, $adaptive['body']);
        $this->assertSame([
            'type' => 'TextBlock',
            'text' => 'Welcome Message',
            'weight' => 'bolder',
            'size' => 'large',
            'wrap' => true,
        ], $adaptive['body'][0]);
    }

    public function test_converts_card_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Update')
            ->subtitle('Your package is on its way');
        $adaptive = $this->renderer->render($card);

        $this->assertCount(2, $adaptive['body']);
        $this->assertSame([
            'type' => 'TextBlock',
            'text' => 'Your package is on its way',
            'isSubtle' => true,
            'wrap' => true,
        ], $adaptive['body'][1]);
    }

    public function test_converts_card_with_header_image(): void
    {
        $card = Card::make('Product')
            ->imageUrl('https://example.com/product.png');
        $adaptive = $this->renderer->render($card);

        $this->assertCount(2, $adaptive['body']);
        $this->assertSame([
            'type' => 'Image',
            'url' => 'https://example.com/product.png',
            'size' => 'stretch',
        ], $adaptive['body'][1]);
    }

    public function test_converts_text_elements(): void
    {
        $card = Card::make('')
            ->section(
                Text::make('Regular text'),
                Text::bold('Bold text'),
                Text::muted('Muted text'),
            );

        $adaptive = $this->renderer->render($card);

        // Find text blocks in body (skip title if present)
        $textBlocks = array_filter($adaptive['body'], fn ($el) => ($el['type'] ?? '') === 'TextBlock' && ! isset($el['weight']));
        $textBlocks = array_values($textBlocks);

        // Look for the text blocks in the body
        $found = [];
        foreach ($adaptive['body'] as $element) {
            if (($element['type'] ?? '') === 'TextBlock') {
                $found[] = $element;
            }
            if (($element['type'] ?? '') === 'Container') {
                foreach ($element['items'] ?? [] as $item) {
                    if (($item['type'] ?? '') === 'TextBlock') {
                        $found[] = $item;
                    }
                }
            }
        }

        $texts = array_column($found, 'text');
        $this->assertContains('Regular text', $texts);
        $this->assertContains('Bold text', $texts);
        $this->assertContains('Muted text', $texts);
    }

    public function test_converts_image_elements(): void
    {
        $card = Card::make('')
            ->section(Image::make('https://example.com/img.png', 'My image'));

        $adaptive = $this->renderer->render($card);

        // Find image element somewhere in the adaptive card body
        $found = $this->findElementByType($adaptive['body'], 'Image');
        $this->assertNotNull($found, 'Image element should be present');
        $this->assertSame('https://example.com/img.png', $found['url']);
        $this->assertSame('My image', $found['altText']);
    }

    public function test_converts_divider_elements(): void
    {
        $card = Card::make('')
            ->divider();

        $adaptive = $this->renderer->render($card);

        // Divider in Adaptive Cards = Container with separator
        $found = $this->findElementByType($adaptive['body'], 'Container');
        $this->assertNotNull($found, 'Container element for divider should be present');
        $this->assertTrue($found['separator']);
    }

    public function test_converts_actions_with_buttons(): void
    {
        $card = Card::make('')
            ->actions(
                Button::make('approve', 'Approve')->primary(),
                Button::make('reject', 'Reject')->danger(),
                Button::make('skip', 'Skip'),
            );

        $adaptive = $this->renderer->render($card);

        $this->assertArrayHasKey('actions', $adaptive);
        $this->assertCount(3, $adaptive['actions']);

        // Primary -> positive style
        $this->assertSame('Action.Submit', $adaptive['actions'][0]['type']);
        $this->assertSame('Approve', $adaptive['actions'][0]['title']);
        $this->assertSame('approve', $adaptive['actions'][0]['data']['actionId']);
        $this->assertSame('positive', $adaptive['actions'][0]['style']);

        // Danger -> destructive style
        $this->assertSame('Action.Submit', $adaptive['actions'][1]['type']);
        $this->assertSame('Reject', $adaptive['actions'][1]['title']);
        $this->assertSame('reject', $adaptive['actions'][1]['data']['actionId']);
        $this->assertSame('destructive', $adaptive['actions'][1]['style']);

        // Default -> no style
        $this->assertSame('Action.Submit', $adaptive['actions'][2]['type']);
        $this->assertSame('Skip', $adaptive['actions'][2]['title']);
        $this->assertSame('skip', $adaptive['actions'][2]['data']['actionId']);
        $this->assertArrayNotHasKey('style', $adaptive['actions'][2]);
    }

    public function test_converts_link_buttons_to_action_open_url(): void
    {
        $card = Card::make('')
            ->actions(
                LinkButton::make('https://example.com/docs', 'View Docs'),
            );

        $adaptive = $this->renderer->render($card);

        $this->assertArrayHasKey('actions', $adaptive);
        $this->assertCount(1, $adaptive['actions']);
        $this->assertSame('Action.OpenUrl', $adaptive['actions'][0]['type']);
        $this->assertSame('View Docs', $adaptive['actions'][0]['title']);
        $this->assertSame('https://example.com/docs', $adaptive['actions'][0]['url']);
    }

    public function test_converts_fields_to_fact_set(): void
    {
        $card = Card::make('')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);

        $adaptive = $this->renderer->render($card);

        $factSet = $this->findElementByType($adaptive['body'], 'FactSet');
        $this->assertNotNull($factSet, 'FactSet should be present');
        $this->assertSame([
            ['title' => 'Status', 'value' => 'Active'],
            ['title' => 'Priority', 'value' => 'High'],
        ], $factSet['facts']);
    }

    public function test_wraps_section_children_in_container(): void
    {
        $card = Card::make('')
            ->section(Text::make('Inside section'));

        $adaptive = $this->renderer->render($card);

        $container = $this->findElementByType($adaptive['body'], 'Container');
        $this->assertNotNull($container, 'Container wrapping section should be present');
        $this->assertNotEmpty($container['items']);
    }

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

        $adaptive = $this->renderer->render($card);

        // Body should contain title, subtitle, text, and fields
        $this->assertGreaterThanOrEqual(4, count($adaptive['body']));

        $types = array_column($adaptive['body'], 'type');
        $this->assertContains('TextBlock', $types);

        // Actions at card level
        $this->assertArrayHasKey('actions', $adaptive);
        $this->assertCount(1, $adaptive['actions']);
        $this->assertSame('Track Package', $adaptive['actions'][0]['title']);
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

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Find the first element with a given type in a flat or nested body array.
     */
    private function findElementByType(array $body, string $type): ?array
    {
        foreach ($body as $element) {
            if (($element['type'] ?? '') === $type) {
                return $element;
            }
            // Check nested items (Container, etc.)
            if (isset($element['items'])) {
                $found = $this->findElementByType($element['items'], $type);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
