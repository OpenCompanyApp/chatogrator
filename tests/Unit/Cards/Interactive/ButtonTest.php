<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Interactive;

use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class ButtonTest extends TestCase
{
    public function test_make_creates_button_with_id_and_label(): void
    {
        $button = Button::make('submit', 'Submit');

        $this->assertSame('submit', $button->actionId);
        $this->assertSame('Submit', $button->label);
    }

    public function test_default_style_is_default(): void
    {
        $button = Button::make('ok', 'OK');

        $this->assertSame('default', $button->getStyle());
    }

    public function test_primary_sets_primary_style(): void
    {
        $button = Button::make('ok', 'OK')->primary();

        $this->assertSame('primary', $button->getStyle());
    }

    public function test_danger_sets_danger_style(): void
    {
        $button = Button::make('delete', 'Delete')->danger();

        $this->assertSame('danger', $button->getStyle());
    }

    public function test_primary_returns_same_instance(): void
    {
        $button = Button::make('ok', 'OK');
        $returned = $button->primary();

        $this->assertSame($button, $returned);
    }

    public function test_danger_returns_same_instance(): void
    {
        $button = Button::make('delete', 'Delete');
        $returned = $button->danger();

        $this->assertSame($button, $returned);
    }

    public function test_action_id_is_readonly(): void
    {
        $button = Button::make('submit', 'Submit');

        $this->assertSame('submit', $button->actionId);
    }

    public function test_label_is_readonly(): void
    {
        $button = Button::make('submit', 'Submit');

        $this->assertSame('Submit', $button->label);
    }

    public function test_style_can_be_changed_multiple_times(): void
    {
        $button = Button::make('btn', 'Button');

        $button->primary();
        $this->assertSame('primary', $button->getStyle());

        $button->danger();
        $this->assertSame('danger', $button->getStyle());
    }

    public function test_is_instance_of_button(): void
    {
        $button = Button::make('test', 'Test');

        $this->assertInstanceOf(Button::class, $button);
    }

    public function test_button_with_special_characters_in_label(): void
    {
        $button = Button::make('btn-special', 'Save & Continue');

        $this->assertSame('Save & Continue', $button->label);
    }
}
