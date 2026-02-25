<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Interactive;

use OpenCompany\Chatogrator\Cards\Interactive\TextInput;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class TextInputTest extends TestCase
{
    public function test_make_creates_text_input_with_action_id_and_label(): void
    {
        $input = TextInput::make('t1', 'Name');

        $this->assertSame('t1', $input->actionId);
        $this->assertSame('Name', $input->label);
    }

    public function test_default_is_not_multiline(): void
    {
        $input = TextInput::make('t1', 'Name');

        $this->assertFalse($input->isMultiline());
    }

    public function test_multiline_sets_multiline_flag(): void
    {
        $input = TextInput::make('t1', 'Description')
            ->multiline();

        $this->assertTrue($input->isMultiline());
    }

    public function test_multiline_can_be_toggled(): void
    {
        $input = TextInput::make('t1', 'Description')
            ->multiline()
            ->multiline(false);

        $this->assertFalse($input->isMultiline());
    }

    public function test_default_max_length_is_null(): void
    {
        $input = TextInput::make('t1', 'Name');

        $this->assertNull($input->getMaxLength());
    }

    public function test_max_length_sets_limit(): void
    {
        $input = TextInput::make('t1', 'Name')
            ->maxLength(100);

        $this->assertSame(100, $input->getMaxLength());
    }

    public function test_default_is_not_optional(): void
    {
        $input = TextInput::make('t1', 'Name');

        $this->assertFalse($input->isOptional());
    }

    public function test_optional_makes_input_optional(): void
    {
        $input = TextInput::make('t1', 'Name')
            ->optional();

        $this->assertTrue($input->isOptional());
    }

    public function test_optional_can_be_toggled(): void
    {
        $input = TextInput::make('t1', 'Name')
            ->optional()
            ->optional(false);

        $this->assertFalse($input->isOptional());
    }

    public function test_fluent_api_chaining(): void
    {
        $input = TextInput::make('t1', 'Description')
            ->multiline()
            ->maxLength(500)
            ->optional();

        $this->assertTrue($input->isMultiline());
        $this->assertSame(500, $input->getMaxLength());
        $this->assertTrue($input->isOptional());
    }

    public function test_multiline_returns_same_instance(): void
    {
        $input = TextInput::make('t1', 'Name');
        $returned = $input->multiline();

        $this->assertSame($input, $returned);
    }

    public function test_max_length_returns_same_instance(): void
    {
        $input = TextInput::make('t1', 'Name');
        $returned = $input->maxLength(50);

        $this->assertSame($input, $returned);
    }

    public function test_optional_returns_same_instance(): void
    {
        $input = TextInput::make('t1', 'Name');
        $returned = $input->optional();

        $this->assertSame($input, $returned);
    }

    public function test_action_id_is_readonly(): void
    {
        $input = TextInput::make('my-input', 'Label');

        $this->assertSame('my-input', $input->actionId);
    }

    public function test_label_is_readonly(): void
    {
        $input = TextInput::make('t1', 'Full Name');

        $this->assertSame('Full Name', $input->label);
    }

    public function test_is_instance_of_text_input(): void
    {
        $input = TextInput::make('t1', 'Name');

        $this->assertInstanceOf(TextInput::class, $input);
    }

    public function test_all_optional_fields_together(): void
    {
        $input = TextInput::make('t1', 'Bio')
            ->multiline()
            ->maxLength(1000)
            ->optional();

        $this->assertSame('t1', $input->actionId);
        $this->assertSame('Bio', $input->label);
        $this->assertTrue($input->isMultiline());
        $this->assertSame(1000, $input->getMaxLength());
        $this->assertTrue($input->isOptional());
    }
}
