<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Actions;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class ActionsTest extends TestCase
{
    public function test_make_creates_actions_with_buttons(): void
    {
        $actions = Actions::make(
            Button::make('ok', 'OK'),
            Button::make('cancel', 'Cancel'),
        );

        $this->assertInstanceOf(Actions::class, $actions);
        $this->assertCount(2, $actions->actions);
    }

    public function test_actions_contain_correct_button_instances(): void
    {
        $okBtn = Button::make('ok', 'OK');
        $cancelBtn = Button::make('cancel', 'Cancel');

        $actions = Actions::make($okBtn, $cancelBtn);

        $this->assertSame($okBtn, $actions->actions[0]);
        $this->assertSame($cancelBtn, $actions->actions[1]);
    }

    public function test_actions_with_mixed_button_types(): void
    {
        $button = Button::make('submit', 'Submit')->primary();
        $link = LinkButton::make('https://example.com/help', 'Help');

        $actions = Actions::make($button, $link);

        $this->assertCount(2, $actions->actions);
        $this->assertInstanceOf(Button::class, $actions->actions[0]);
        $this->assertInstanceOf(LinkButton::class, $actions->actions[1]);
    }

    public function test_empty_actions(): void
    {
        $actions = Actions::make();

        $this->assertEmpty($actions->actions);
    }

    public function test_single_action(): void
    {
        $actions = Actions::make(
            Button::make('ok', 'OK'),
        );

        $this->assertCount(1, $actions->actions);
    }

    public function test_actions_with_styled_buttons(): void
    {
        $actions = Actions::make(
            Button::make('confirm', 'Confirm')->primary(),
            Button::make('delete', 'Delete')->danger(),
            Button::make('cancel', 'Cancel'),
        );

        $this->assertCount(3, $actions->actions);
        $this->assertSame('primary', $actions->actions[0]->getStyle());
        $this->assertSame('danger', $actions->actions[1]->getStyle());
        $this->assertSame('default', $actions->actions[2]->getStyle());
    }

    public function test_actions_is_readonly(): void
    {
        $actions = Actions::make(Button::make('ok', 'OK'));

        $this->assertIsArray($actions->actions);
    }
}
