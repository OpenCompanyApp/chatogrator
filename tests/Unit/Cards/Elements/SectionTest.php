<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Section;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class SectionTest extends TestCase
{
    public function test_make_creates_section_with_elements(): void
    {
        $section = Section::make(
            Text::make('Content'),
            Divider::make(),
        );

        $this->assertInstanceOf(Section::class, $section);
        $this->assertCount(2, $section->elements);
    }

    public function test_section_contains_correct_child_types(): void
    {
        $text = Text::make('Hello');
        $divider = Divider::make();

        $section = Section::make($text, $divider);

        $this->assertInstanceOf(Text::class, $section->elements[0]);
        $this->assertInstanceOf(Divider::class, $section->elements[1]);
    }

    public function test_empty_section(): void
    {
        $section = Section::make();

        $this->assertEmpty($section->elements);
    }

    public function test_section_with_single_element(): void
    {
        $section = Section::make(Text::make('Only child'));

        $this->assertCount(1, $section->elements);
    }

    public function test_section_with_mixed_elements(): void
    {
        $section = Section::make(
            Text::bold('Header'),
            Text::muted('Subtitle'),
            Text::make('Regular text'),
        );

        $this->assertCount(3, $section->elements);
        $this->assertSame('bold', $section->elements[0]->style);
        $this->assertSame('muted', $section->elements[1]->style);
        $this->assertSame('plain', $section->elements[2]->style);
    }

    public function test_elements_is_readonly(): void
    {
        $section = Section::make(Text::make('Test'));

        $this->assertIsArray($section->elements);
        $this->assertCount(1, $section->elements);
    }
}
