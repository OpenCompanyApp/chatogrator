<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Actions;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Fields;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class CardTest extends TestCase
{
    public function test_make_creates_card_with_title(): void
    {
        $card = Card::make('My Card');

        $this->assertSame('My Card', $card->getTitle());
        $this->assertNull($card->getSubtitle());
        $this->assertNull($card->getImageUrl());
        $this->assertEmpty($card->getElements());
    }

    public function test_card_with_subtitle(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Processing');

        $this->assertSame('Order #1234', $card->getTitle());
        $this->assertSame('Processing', $card->getSubtitle());
    }

    public function test_card_with_image_url(): void
    {
        $card = Card::make('Order #1234')
            ->imageUrl('https://example.com/image.png');

        $this->assertSame('https://example.com/image.png', $card->getImageUrl());
    }

    public function test_card_with_all_options(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Processing')
            ->imageUrl('https://example.com/image.png');

        $this->assertSame('Order #1234', $card->getTitle());
        $this->assertSame('Processing', $card->getSubtitle());
        $this->assertSame('https://example.com/image.png', $card->getImageUrl());
    }

    public function test_card_with_section(): void
    {
        $card = Card::make('Test')
            ->section(Text::make('Content'), Divider::make());

        $elements = $card->getElements();
        $this->assertCount(1, $elements);
        $this->assertSame('section', $elements[0]['type']);
    }

    public function test_card_with_divider(): void
    {
        $card = Card::make('Test')
            ->divider();

        $elements = $card->getElements();
        $this->assertCount(1, $elements);
        $this->assertSame('divider', $elements[0]['type']);
    }

    public function test_card_with_fields(): void
    {
        $card = Card::make('Test')
            ->fields([
                'Status' => 'Active',
                'Priority' => 'High',
            ]);

        $elements = $card->getElements();
        $this->assertCount(1, $elements);
        $this->assertSame('fields', $elements[0]['type']);
    }

    public function test_card_with_actions(): void
    {
        $card = Card::make('Test')
            ->actions(
                Button::make('ok', 'OK'),
                Button::make('cancel', 'Cancel'),
            );

        $elements = $card->getElements();
        $this->assertCount(1, $elements);
        $this->assertSame('actions', $elements[0]['type']);
    }

    public function test_to_fallback_text_with_title_only(): void
    {
        $card = Card::make('Order Status');

        $this->assertSame('Order Status', $card->toFallbackText());
    }

    public function test_to_fallback_text_with_title_and_subtitle(): void
    {
        $card = Card::make('Order Status')
            ->subtitle('Your order details');

        $text = $card->toFallbackText();
        $this->assertStringContainsString('Order Status', $text);
        $this->assertStringContainsString('Your order details', $text);
    }

    public function test_fluent_api_returns_same_instance(): void
    {
        $card = Card::make('Test');
        $returned = $card->subtitle('Sub')
            ->imageUrl('https://example.com/img.png')
            ->divider()
            ->fields(['Key' => 'Value']);

        $this->assertSame($card, $returned);
    }

    public function test_full_card_composition_with_all_element_types(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Processing your order')
            ->imageUrl('https://example.com/order.png')
            ->section(
                Text::make('Thank you for your order!'),
            )
            ->divider()
            ->fields([
                'Order ID' => '#1234',
                'Total' => '$99.99',
            ])
            ->section(
                Text::bold('Items:'),
                Text::muted('2x Widget, 1x Gadget'),
            )
            ->divider()
            ->actions(
                Button::make('track', 'Track Order')->primary(),
                Button::make('cancel', 'Cancel Order')->danger(),
            );

        $this->assertSame('Order #1234', $card->getTitle());
        $this->assertSame('Processing your order', $card->getSubtitle());
        $this->assertSame('https://example.com/order.png', $card->getImageUrl());

        $elements = $card->getElements();
        $this->assertCount(6, $elements);

        // Verify structure
        $this->assertSame('section', $elements[0]['type']);
        $this->assertSame('divider', $elements[1]['type']);
        $this->assertSame('fields', $elements[2]['type']);
        $this->assertSame('section', $elements[3]['type']);
        $this->assertSame('divider', $elements[4]['type']);
        $this->assertSame('actions', $elements[5]['type']);
    }

    public function test_multiple_dividers(): void
    {
        $card = Card::make('Test')
            ->divider()
            ->divider()
            ->divider();

        $this->assertCount(3, $card->getElements());
    }

    public function test_card_with_complete_structure_has_correct_element_count(): void
    {
        $card = Card::make('Order #1234')
            ->subtitle('Processing your order')
            ->imageUrl('https://example.com/order.png')
            ->section(Text::make('Thank you for your order!'))
            ->divider()
            ->fields(['Order ID' => '#1234', 'Total' => '$99.99'])
            ->section(Text::bold('Items:'), Text::muted('2x Widget, 1x Gadget'))
            ->divider()
            ->actions(
                Button::make('track', 'Track Order')->primary(),
                Button::make('cancel', 'Cancel Order')->danger(),
                LinkButton::make('https://example.com/help', 'Help'),
            );

        $elements = $card->getElements();
        $this->assertCount(6, $elements);

        $this->assertSame('section', $elements[0]['type']);
        $this->assertSame('divider', $elements[1]['type']);
        $this->assertSame('fields', $elements[2]['type']);
        $this->assertSame('section', $elements[3]['type']);
        $this->assertSame('divider', $elements[4]['type']);
        $this->assertSame('actions', $elements[5]['type']);
    }
}
