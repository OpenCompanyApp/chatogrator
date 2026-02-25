<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Linear;

use OpenCompany\Chatogrator\Adapters\Linear\LinearFormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Linear format conversion — markdown round-tripping,
 * mention handling, and postable message rendering.
 *
 * Ported from adapter-linear/src/markdown.test.ts.
 *
 * @group linear
 */
class LinearFormatConverterTest extends TestCase
{
    private LinearFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new LinearFormatConverter;
    }

    // ── toMarkdown (Linear markdown -> standard markdown) ───────────

    public function test_to_markdown_parses_plain_text(): void
    {
        $markdown = $this->converter->toMarkdown('Hello world');

        $this->assertSame('Hello world', $markdown);
    }

    public function test_to_markdown_parses_bold(): void
    {
        $markdown = $this->converter->toMarkdown('**bold text**');

        $this->assertStringContainsString('bold text', $markdown);
    }

    public function test_to_markdown_parses_italic(): void
    {
        $markdown = $this->converter->toMarkdown('_italic text_');

        $this->assertStringContainsString('italic text', $markdown);
    }

    public function test_to_markdown_parses_links(): void
    {
        $markdown = $this->converter->toMarkdown('[Link](https://example.com)');

        $this->assertStringContainsString('[Link](https://example.com)', $markdown);
    }

    public function test_to_markdown_parses_code_blocks(): void
    {
        $markdown = $this->converter->toMarkdown("```\ncode\n```");

        $this->assertStringContainsString('code', $markdown);
    }

    public function test_to_markdown_parses_lists(): void
    {
        $markdown = $this->converter->toMarkdown("- item 1\n- item 2\n- item 3");

        $this->assertStringContainsString('item 1', $markdown);
        $this->assertStringContainsString('item 2', $markdown);
        $this->assertStringContainsString('item 3', $markdown);
    }

    // ── fromMarkdown (standard markdown -> Linear markdown) ─────────

    public function test_from_markdown_round_trips_simple_text(): void
    {
        $markdown = $this->converter->toMarkdown('Hello world');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('Hello world', $result);
    }

    public function test_from_markdown_round_trips_bold_text(): void
    {
        $markdown = $this->converter->toMarkdown('**bold text**');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('**bold text**', $result);
    }

    public function test_from_markdown_round_trips_links(): void
    {
        $markdown = $this->converter->toMarkdown('[Link](https://example.com)');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('[Link](https://example.com)', $result);
    }

    // ── renderPostable ──────────────────────────────────────────────

    public function test_render_postable_renders_plain_string(): void
    {
        $message = PostableMessage::text('Hello world');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello world', $result);
    }

    public function test_render_postable_renders_markdown_message(): void
    {
        $message = PostableMessage::markdown('**bold** text');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('bold', (string) $result);
    }

    public function test_render_postable_handles_empty_message(): void
    {
        $message = PostableMessage::text('');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('', $result);
    }

    // ── Roundtrip ───────────────────────────────────────────────────

    public function test_roundtrip_preserves_formatting(): void
    {
        $original = '**bold** and *italic*';
        $markdown = $this->converter->toMarkdown($original);
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('bold', $result);
        $this->assertStringContainsString('italic', $result);
    }
}
