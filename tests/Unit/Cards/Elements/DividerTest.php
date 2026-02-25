<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class DividerTest extends TestCase
{
    public function test_make_creates_divider(): void
    {
        $divider = Divider::make();

        $this->assertInstanceOf(Divider::class, $divider);
    }

    public function test_multiple_dividers_are_separate_instances(): void
    {
        $divider1 = Divider::make();
        $divider2 = Divider::make();

        $this->assertNotSame($divider1, $divider2);
    }

    public function test_divider_is_instance_of_divider(): void
    {
        $divider = Divider::make();

        $this->assertInstanceOf(Divider::class, $divider);
    }
}
