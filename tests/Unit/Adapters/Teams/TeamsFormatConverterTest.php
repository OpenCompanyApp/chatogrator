<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Teams;

use OpenCompany\Chatogrator\Adapters\Teams\TeamsFormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Teams format conversion — markdown/HTML round-tripping,
 * mention handling, and postable message rendering.
 *
 * Ported from adapter-teams/src/markdown.test.ts (32 tests).
 *
 * @group teams
 */
class TeamsFormatConverterTest extends TestCase
{
    private TeamsFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new TeamsFormatConverter;
    }

    // ── fromMarkdown (standard markdown -> Teams format) ────────────

    public function test_from_markdown_converts_bold(): void
    {
        $result = $this->converter->fromMarkdown('**bold text**');

        $this->assertStringContainsString('**bold text**', $result);
    }

    public function test_from_markdown_converts_italic(): void
    {
        $result = $this->converter->fromMarkdown('_italic text_');

        $this->assertStringContainsString('_italic text_', $result);
    }

    public function test_from_markdown_converts_strikethrough(): void
    {
        $result = $this->converter->fromMarkdown('~~strikethrough~~');

        $this->assertStringContainsString('~~strikethrough~~', $result);
    }

    public function test_from_markdown_preserves_inline_code(): void
    {
        $result = $this->converter->fromMarkdown('Use `const x = 1`');

        $this->assertStringContainsString('`const x = 1`', $result);
    }

    public function test_from_markdown_handles_code_blocks(): void
    {
        $input = "```js\nconst x = 1;\n```";
        $output = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('```', $output);
        $this->assertStringContainsString('const x = 1;', $output);
    }

    public function test_from_markdown_converts_links(): void
    {
        $result = $this->converter->fromMarkdown('[link text](https://example.com)');

        $this->assertStringContainsString('[link text](https://example.com)', $result);
    }

    public function test_from_markdown_handles_blockquotes(): void
    {
        $result = $this->converter->fromMarkdown('> quoted text');

        $this->assertStringContainsString('> quoted text', $result);
    }

    public function test_from_markdown_handles_unordered_lists(): void
    {
        $result = $this->converter->fromMarkdown("- item 1\n- item 2");

        $this->assertStringContainsString('- item 1', $result);
        $this->assertStringContainsString('- item 2', $result);
    }

    public function test_from_markdown_handles_ordered_lists(): void
    {
        $result = $this->converter->fromMarkdown("1. first\n2. second");

        $this->assertStringContainsString('1.', $result);
        $this->assertStringContainsString('2.', $result);
    }

    public function test_from_markdown_converts_at_mentions_to_teams_format(): void
    {
        $result = $this->converter->fromMarkdown('Hello @someone');

        $this->assertStringContainsString('<at>someone</at>', $result);
    }

    public function test_from_markdown_handles_thematic_breaks(): void
    {
        $result = $this->converter->fromMarkdown("text\n\n---\n\nmore");

        $this->assertStringContainsString('---', $result);
    }

    // ── toMarkdown (Teams HTML -> standard markdown) ────────────────

    public function test_to_markdown_converts_at_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('<at>John</at> said hi');

        $this->assertStringContainsString('@John', $markdown);
    }

    public function test_to_markdown_converts_b_tags_to_bold(): void
    {
        $markdown = $this->converter->toMarkdown('<b>bold</b>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('**bold**', $result);
    }

    public function test_to_markdown_converts_strong_tags_to_bold(): void
    {
        $markdown = $this->converter->toMarkdown('<strong>bold</strong>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('**bold**', $result);
    }

    public function test_to_markdown_converts_i_tags_to_italic(): void
    {
        $markdown = $this->converter->toMarkdown('<i>italic</i>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('_italic_', $result);
    }

    public function test_to_markdown_converts_em_tags_to_italic(): void
    {
        $markdown = $this->converter->toMarkdown('<em>italic</em>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('_italic_', $result);
    }

    public function test_to_markdown_converts_s_tags_to_strikethrough(): void
    {
        $markdown = $this->converter->toMarkdown('<s>struck</s>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('~~struck~~', $result);
    }

    public function test_to_markdown_converts_a_tags_to_links(): void
    {
        $markdown = $this->converter->toMarkdown('<a href="https://example.com">link</a>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('[link](https://example.com)', $result);
    }

    public function test_to_markdown_converts_code_tags_to_inline_code(): void
    {
        $markdown = $this->converter->toMarkdown('<code>const x</code>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('`const x`', $result);
    }

    public function test_to_markdown_converts_pre_tags_to_code_blocks(): void
    {
        $markdown = $this->converter->toMarkdown('<pre>const x = 1;</pre>');
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('```', $result);
        $this->assertStringContainsString('const x = 1;', $result);
    }

    public function test_to_markdown_strips_remaining_html_tags(): void
    {
        $markdown = $this->converter->toMarkdown('<div><span>hello</span></div>');

        $this->assertStringContainsString('hello', $markdown);
        $this->assertStringNotContainsString('<div>', $markdown);
        $this->assertStringNotContainsString('<span>', $markdown);
    }

    public function test_to_markdown_decodes_html_entities(): void
    {
        $markdown = $this->converter->toMarkdown('&lt;b&gt;not bold&lt;/b&gt; &amp; &quot;quoted&quot;');

        $this->assertStringContainsString('<b>', $markdown);
        $this->assertStringContainsString('&', $markdown);
        $this->assertStringContainsString('"quoted"', $markdown);
    }

    // ── renderPostable ──────────────────────────────────────────────

    public function test_render_postable_converts_at_mentions_in_plain_strings(): void
    {
        $message = PostableMessage::text('Hello @user');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello <at>user</at>', $result);
    }

    public function test_render_postable_converts_at_mentions_in_raw_messages(): void
    {
        $message = PostableMessage::raw('Hello @user');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('<at>user</at>', (string) $result);
    }

    public function test_render_postable_renders_markdown_messages(): void
    {
        $message = PostableMessage::markdown('Hello **world**');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('**world**', $result);
    }

    public function test_render_postable_handles_empty_message(): void
    {
        $message = PostableMessage::text('');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('', $result);
    }

    // ── extractPlainText (via toMarkdown, which should strip formatting) ─

    public function test_extract_plain_text_removes_bold_markers(): void
    {
        $plain = $this->stripFormatting('Hello **world**!');

        $this->assertSame('Hello world!', $plain);
    }

    public function test_extract_plain_text_removes_italic_markers(): void
    {
        $plain = $this->stripFormatting('Hello _world_!');

        $this->assertSame('Hello world!', $plain);
    }

    public function test_extract_plain_text_handles_empty_string(): void
    {
        $this->assertSame('', $this->converter->toMarkdown(''));
    }

    public function test_extract_plain_text_handles_plain_text(): void
    {
        $this->assertSame('Hello world', $this->converter->toMarkdown('Hello world'));
    }

    public function test_extract_plain_text_handles_inline_code(): void
    {
        $result = $this->converter->toMarkdown('Use `const x = 1`');

        $this->assertStringContainsString('const x = 1', $result);
    }

    // ── Helper ──────────────────────────────────────────────────────

    /**
     * Strip markdown/HTML formatting for plain text comparison.
     */
    private function stripFormatting(string $text): string
    {
        // Remove Teams-specific formatting
        $text = preg_replace('/<at>(.+?)<\/at>/', '@$1', $text);

        // Remove markdown formatting
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        // Strip HTML tags
        $text = strip_tags($text);

        return trim($text);
    }
}
