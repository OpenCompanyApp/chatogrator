<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class TextTest extends TestCase
{
    public function test_make_creates_text_element(): void
    {
        $text = Text::make('Hello, world!');

        $this->assertSame('Hello, world!', $text->content);
        $this->assertSame('plain', $text->style);
    }

    public function test_bold_creates_bold_text_element(): void
    {
        $text = Text::bold('Important');

        $this->assertSame('Important', $text->content);
        $this->assertSame('bold', $text->style);
    }

    public function test_muted_creates_muted_text_element(): void
    {
        $text = Text::muted('Subtle note');

        $this->assertSame('Subtle note', $text->content);
        $this->assertSame('muted', $text->style);
    }

    public function test_make_defaults_to_plain_style(): void
    {
        $text = Text::make('Regular text');

        $this->assertSame('plain', $text->style);
    }

    public function test_content_is_readonly(): void
    {
        $text = Text::make('Immutable');

        $this->assertSame('Immutable', $text->content);
    }

    public function test_style_is_readonly(): void
    {
        $text = Text::bold('Bold text');

        $this->assertSame('bold', $text->style);
    }

    public function test_empty_string_content(): void
    {
        $text = Text::make('');

        $this->assertSame('', $text->content);
    }

    public function test_special_characters_in_content(): void
    {
        $text = Text::make('Hello & world < > "quotes"');

        $this->assertSame('Hello & world < > "quotes"', $text->content);
    }

    public function test_multiline_content(): void
    {
        $text = Text::make("Line 1\nLine 2\nLine 3");

        $this->assertSame("Line 1\nLine 2\nLine 3", $text->content);
    }

    public function test_text_is_instance_of_text_class(): void
    {
        $plain = Text::make('plain');
        $bold = Text::bold('bold');
        $muted = Text::muted('muted');

        $this->assertInstanceOf(Text::class, $plain);
        $this->assertInstanceOf(Text::class, $bold);
        $this->assertInstanceOf(Text::class, $muted);
    }
}
