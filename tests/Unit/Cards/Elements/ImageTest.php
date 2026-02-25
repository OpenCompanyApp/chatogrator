<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class ImageTest extends TestCase
{
    public function test_make_creates_image_with_url(): void
    {
        $image = Image::make('https://example.com/img.png');

        $this->assertSame('https://example.com/img.png', $image->url);
        $this->assertNull($image->alt);
    }

    public function test_make_creates_image_with_alt_text(): void
    {
        $image = Image::make('https://example.com/img.png', 'A beautiful sunset');

        $this->assertSame('https://example.com/img.png', $image->url);
        $this->assertSame('A beautiful sunset', $image->alt);
    }

    public function test_url_is_readonly(): void
    {
        $image = Image::make('https://example.com/img.png');

        $this->assertSame('https://example.com/img.png', $image->url);
    }

    public function test_alt_is_readonly(): void
    {
        $image = Image::make('https://example.com/img.png', 'Alt text');

        $this->assertSame('Alt text', $image->alt);
    }

    public function test_image_without_alt_has_null_alt(): void
    {
        $image = Image::make('https://example.com/photo.jpg');

        $this->assertNull($image->alt);
    }

    public function test_is_instance_of_image(): void
    {
        $image = Image::make('https://example.com/img.png');

        $this->assertInstanceOf(Image::class, $image);
    }

    public function test_image_with_long_url(): void
    {
        $url = 'https://cdn.example.com/images/uploads/2024/01/15/photo-with-very-long-filename-that-goes-on-and-on.jpg?w=1200&h=800&fit=crop';
        $image = Image::make($url);

        $this->assertSame($url, $image->url);
    }
}
