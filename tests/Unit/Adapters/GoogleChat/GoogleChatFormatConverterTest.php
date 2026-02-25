<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatFormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Google Chat format conversion — markdown round-tripping
 * (including Google Chat's single-asterisk bold), and postable rendering.
 *
 * Ported from adapter-gchat/src/markdown.test.ts (23 tests).
 *
 * @group gchat
 */
class GoogleChatFormatConverterTest extends TestCase
{
    private GoogleChatFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new GoogleChatFormatConverter;
    }

    // ── fromMarkdown (standard markdown -> Google Chat format) ──────

    public function test_from_markdown_converts_bold_double_to_single_asterisk(): void
    {
        // Google Chat uses *text* for bold (not **text**)
        $result = $this->converter->fromMarkdown('**bold text**');

        $this->assertStringContainsString('*bold text*', $result);
    }

    public function test_from_markdown_converts_italic(): void
    {
        $result = $this->converter->fromMarkdown('_italic text_');

        $this->assertStringContainsString('_italic text_', $result);
    }

    public function test_from_markdown_converts_strikethrough_double_to_single_tilde(): void
    {
        // Google Chat uses ~text~ for strikethrough (not ~~text~~)
        $result = $this->converter->fromMarkdown('~~strikethrough~~');

        $this->assertStringContainsString('~strikethrough~', $result);
    }

    public function test_from_markdown_preserves_inline_code(): void
    {
        $result = $this->converter->fromMarkdown('Use `const x = 1`');

        $this->assertStringContainsString('`const x = 1`', $result);
    }

    public function test_from_markdown_handles_code_blocks(): void
    {
        $input = "```\nconst x = 1;\n```";
        $output = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('```', $output);
        $this->assertStringContainsString('const x = 1;', $output);
    }

    public function test_from_markdown_outputs_url_directly_when_link_text_matches_url(): void
    {
        $result = $this->converter->fromMarkdown('[https://example.com](https://example.com)');

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function test_from_markdown_outputs_text_url_when_link_text_differs(): void
    {
        $result = $this->converter->fromMarkdown('[click here](https://example.com)');

        // Google Chat cannot render markdown links, so output "text (url)"
        $this->assertStringContainsString('click here', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function test_from_markdown_handles_blockquotes(): void
    {
        $result = $this->converter->fromMarkdown('> quoted text');

        $this->assertStringContainsString('> quoted text', $result);
    }

    public function test_from_markdown_handles_unordered_lists(): void
    {
        $result = $this->converter->fromMarkdown("- item 1\n- item 2");

        $this->assertStringContainsString('item 1', $result);
        $this->assertStringContainsString('item 2', $result);
    }

    public function test_from_markdown_handles_ordered_lists(): void
    {
        $result = $this->converter->fromMarkdown("1. first\n2. second");

        $this->assertStringContainsString('1.', $result);
        $this->assertStringContainsString('2.', $result);
    }

    public function test_from_markdown_handles_line_breaks(): void
    {
        $result = $this->converter->fromMarkdown("line1  \nline2");

        $this->assertStringContainsString('line1', $result);
        $this->assertStringContainsString('line2', $result);
    }

    public function test_from_markdown_handles_thematic_breaks(): void
    {
        $result = $this->converter->fromMarkdown("text\n\n---\n\nmore");

        $this->assertStringContainsString('---', $result);
    }

    // ── toMarkdown (Google Chat format -> standard markdown) ────────

    public function test_to_markdown_parses_google_chat_bold(): void
    {
        // Google Chat uses *text* for bold
        $markdown = $this->converter->toMarkdown('*bold*');

        $this->assertStringContainsString('bold', $markdown);
    }

    public function test_to_markdown_parses_google_chat_strikethrough(): void
    {
        // Google Chat uses ~text~ for strikethrough
        $markdown = $this->converter->toMarkdown('~struck~');

        $this->assertStringContainsString('struck', $markdown);
    }

    public function test_to_markdown_parses_code_blocks(): void
    {
        $markdown = $this->converter->toMarkdown("```\ncode\n```");

        $this->assertStringContainsString('code', $markdown);
    }

    // ── extractPlainText (via toMarkdown) ───────────────────────────

    public function test_extract_removes_formatting_markers(): void
    {
        $markdown = $this->converter->toMarkdown('*bold* _italic_ ~struck~');

        $this->assertStringContainsString('bold', $markdown);
        $this->assertStringContainsString('italic', $markdown);
        $this->assertStringContainsString('struck', $markdown);
    }

    public function test_extract_handles_empty_string(): void
    {
        $this->assertSame('', $this->converter->toMarkdown(''));
    }

    public function test_extract_handles_plain_text(): void
    {
        $this->assertSame('Hello world', $this->converter->toMarkdown('Hello world'));
    }

    public function test_extract_handles_inline_code(): void
    {
        $result = $this->converter->toMarkdown('Use `const x = 1`');

        $this->assertStringContainsString('const x = 1', $result);
    }

    // ── renderPostable ──────────────────────────────────────────────

    public function test_render_postable_renders_plain_string(): void
    {
        $message = PostableMessage::text('Hello world');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello world', $result);
    }

    public function test_render_postable_renders_raw_message(): void
    {
        $message = PostableMessage::raw('raw text');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('raw text', $result);
    }

    public function test_render_postable_renders_markdown_message(): void
    {
        $message = PostableMessage::markdown('**bold** text');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('bold', $result);
    }

    public function test_render_postable_renders_empty_message(): void
    {
        $message = PostableMessage::text('');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('', $result);
    }
}
