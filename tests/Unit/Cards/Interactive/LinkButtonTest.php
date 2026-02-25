<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Interactive;

use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class LinkButtonTest extends TestCase
{
    public function test_make_creates_link_button_with_url_and_label(): void
    {
        $button = LinkButton::make('https://example.com', 'Visit Site');

        $this->assertSame('https://example.com', $button->url);
        $this->assertSame('Visit Site', $button->label);
    }

    public function test_url_is_readonly(): void
    {
        $button = LinkButton::make('https://docs.example.com', 'View Docs');

        $this->assertSame('https://docs.example.com', $button->url);
    }

    public function test_label_is_readonly(): void
    {
        $button = LinkButton::make('https://example.com', 'Click Me');

        $this->assertSame('Click Me', $button->label);
    }

    public function test_is_instance_of_link_button(): void
    {
        $button = LinkButton::make('https://example.com', 'Link');

        $this->assertInstanceOf(LinkButton::class, $button);
    }

    public function test_link_button_with_complex_url(): void
    {
        $url = 'https://example.com/path?param=value&other=123#section';
        $button = LinkButton::make($url, 'Complex Link');

        $this->assertSame($url, $button->url);
    }

    public function test_link_button_with_special_characters_in_label(): void
    {
        $button = LinkButton::make('https://example.com', 'Docs & Reference');

        $this->assertSame('Docs & Reference', $button->label);
    }
}
