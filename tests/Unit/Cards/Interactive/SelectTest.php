<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Interactive;

use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class SelectTest extends TestCase
{
    public function test_make_creates_select_with_action_id_and_placeholder(): void
    {
        $select = Select::make('s1', 'Pick one');

        $this->assertSame('s1', $select->actionId);
        $this->assertSame('Pick one', $select->placeholder);
    }

    public function test_select_with_options(): void
    {
        $select = Select::make('s1', 'Pick one')
            ->options([
                SelectOption::make('a', 'Option A'),
                SelectOption::make('b', 'Option B'),
            ]);

        $this->assertCount(2, $select->getOptions());
    }

    public function test_select_options_have_correct_values(): void
    {
        $select = Select::make('s1', 'Choose')
            ->options([
                SelectOption::make('a', 'Alpha'),
                SelectOption::make('b', 'Beta'),
            ]);

        $options = $select->getOptions();
        $this->assertSame('a', $options[0]->value);
        $this->assertSame('Alpha', $options[0]->label);
        $this->assertSame('b', $options[1]->value);
        $this->assertSame('Beta', $options[1]->label);
    }

    public function test_select_with_single_option(): void
    {
        $select = Select::make('s1', 'Pick')
            ->options([
                SelectOption::make('only', 'Only Option'),
            ]);

        $this->assertCount(1, $select->getOptions());
    }

    public function test_select_with_empty_options(): void
    {
        $select = Select::make('s1', 'Pick')
            ->options([]);

        $this->assertEmpty($select->getOptions());
    }

    public function test_select_with_many_options(): void
    {
        $options = [];
        for ($i = 1; $i <= 20; $i++) {
            $options[] = SelectOption::make("opt_{$i}", "Option {$i}");
        }

        $select = Select::make('s1', 'Pick one')
            ->options($options);

        $this->assertCount(20, $select->getOptions());
    }

    public function test_options_returns_same_instance(): void
    {
        $select = Select::make('s1', 'Pick');
        $returned = $select->options([
            SelectOption::make('a', 'A'),
        ]);

        $this->assertSame($select, $returned);
    }

    public function test_action_id_is_readonly(): void
    {
        $select = Select::make('my-select', 'Choose');

        $this->assertSame('my-select', $select->actionId);
    }

    public function test_placeholder_is_readonly(): void
    {
        $select = Select::make('s1', 'Choose an option');

        $this->assertSame('Choose an option', $select->placeholder);
    }

    public function test_is_instance_of_select(): void
    {
        $select = Select::make('s1', 'Pick');

        $this->assertInstanceOf(Select::class, $select);
    }
}
