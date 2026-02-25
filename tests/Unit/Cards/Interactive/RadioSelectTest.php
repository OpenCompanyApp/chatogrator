<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Interactive;

use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class RadioSelectTest extends TestCase
{
    public function test_make_creates_radio_select_with_action_id_and_label(): void
    {
        $radio = RadioSelect::make('r1', 'Choose');

        $this->assertSame('r1', $radio->actionId);
        $this->assertSame('Choose', $radio->label);
    }

    public function test_radio_select_with_options(): void
    {
        $radio = RadioSelect::make('r1', 'Choose')
            ->options([
                SelectOption::make('x', 'Option X'),
                SelectOption::make('y', 'Option Y'),
            ]);

        $this->assertCount(2, $radio->getOptions());
    }

    public function test_radio_select_options_have_correct_values(): void
    {
        $radio = RadioSelect::make('r1', 'Choose')
            ->options([
                SelectOption::make('a', 'Alpha'),
                SelectOption::make('b', 'Beta'),
            ]);

        $options = $radio->getOptions();
        $this->assertSame('a', $options[0]->value);
        $this->assertSame('Alpha', $options[0]->label);
    }

    public function test_radio_select_with_empty_options(): void
    {
        $radio = RadioSelect::make('r1', 'Choose')
            ->options([]);

        $this->assertEmpty($radio->getOptions());
    }

    public function test_radio_select_default_not_optional(): void
    {
        $radio = RadioSelect::make('r1', 'Choose');

        $this->assertFalse($radio->isOptional());
    }

    public function test_radio_select_can_be_made_optional(): void
    {
        $radio = RadioSelect::make('r1', 'Choose')
            ->optional();

        $this->assertTrue($radio->isOptional());
    }

    public function test_radio_select_optional_can_be_toggled(): void
    {
        $radio = RadioSelect::make('r1', 'Choose')
            ->optional()
            ->optional(false);

        $this->assertFalse($radio->isOptional());
    }

    public function test_options_returns_same_instance(): void
    {
        $radio = RadioSelect::make('r1', 'Choose');
        $returned = $radio->options([
            SelectOption::make('a', 'A'),
        ]);

        $this->assertSame($radio, $returned);
    }

    public function test_optional_returns_same_instance(): void
    {
        $radio = RadioSelect::make('r1', 'Choose');
        $returned = $radio->optional();

        $this->assertSame($radio, $returned);
    }

    public function test_action_id_is_readonly(): void
    {
        $radio = RadioSelect::make('my-radio', 'Pick');

        $this->assertSame('my-radio', $radio->actionId);
    }

    public function test_label_is_readonly(): void
    {
        $radio = RadioSelect::make('r1', 'Choose one');

        $this->assertSame('Choose one', $radio->label);
    }

    public function test_is_instance_of_radio_select(): void
    {
        $radio = RadioSelect::make('r1', 'Choose');

        $this->assertInstanceOf(RadioSelect::class, $radio);
    }
}
