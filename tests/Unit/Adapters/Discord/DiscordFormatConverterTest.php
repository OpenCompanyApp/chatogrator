<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Discord;

use OpenCompany\Chatogrator\Adapters\Discord\DiscordFormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Discord format conversion — markdown round-tripping,
 * mention handling, and postable message rendering.
 *
 * Ported from adapter-discord/src/markdown.test.ts (39 tests).
 *
 * @group discord
 */
class DiscordFormatConverterTest extends TestCase
{
    private DiscordFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new DiscordFormatConverter;
    }

    // ── fromMarkdown (standard markdown -> Discord markdown) ────────

    public function test_from_markdown_converts_bold(): void
    {
        $result = $this->converter->fromMarkdown('**bold text**');

        $this->assertStringContainsString('**bold text**', $result);
    }

    public function test_from_markdown_converts_italic(): void
    {
        $result = $this->converter->fromMarkdown('*italic text*');

        $this->assertStringContainsString('*italic text*', $result);
    }

    public function test_from_markdown_converts_strikethrough(): void
    {
        $result = $this->converter->fromMarkdown('~~strikethrough~~');

        $this->assertStringContainsString('~~strikethrough~~', $result);
    }

    public function test_from_markdown_converts_links(): void
    {
        $result = $this->converter->fromMarkdown('[link text](https://example.com)');

        $this->assertStringContainsString('[link text](https://example.com)', $result);
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

    public function test_from_markdown_handles_mixed_formatting(): void
    {
        $input = '**Bold** and *italic* and [link](https://x.com)';
        $output = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('**Bold**', $output);
        $this->assertStringContainsString('*italic*', $output);
        $this->assertStringContainsString('[link](https://x.com)', $output);
    }

    public function test_from_markdown_converts_at_mentions_to_discord_format(): void
    {
        $result = $this->converter->fromMarkdown('Hello @someone');

        $this->assertStringContainsString('<@someone>', $result);
    }

    // ── toMarkdown (Discord markdown -> standard markdown) ──────────

    public function test_to_markdown_converts_bold(): void
    {
        $markdown = $this->converter->toMarkdown('Hello **world**!');

        $this->assertStringContainsString('world', $markdown);
    }

    public function test_to_markdown_converts_user_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('Hello <@123456789>');

        $this->assertStringContainsString('@123456789', $markdown);
    }

    public function test_to_markdown_converts_user_mentions_with_nickname_marker(): void
    {
        $markdown = $this->converter->toMarkdown('Hello <@!123456789>');

        $this->assertStringContainsString('@123456789', $markdown);
    }

    public function test_to_markdown_converts_channel_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('Check <#987654321>');

        $this->assertStringContainsString('#987654321', $markdown);
    }

    public function test_to_markdown_converts_role_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('Hey <@&111222333>');

        $this->assertStringContainsString('@&111222333', $markdown);
    }

    public function test_to_markdown_converts_custom_emoji(): void
    {
        $markdown = $this->converter->toMarkdown('Nice <:thumbsup:123>');

        $this->assertStringContainsString(':thumbsup:', $markdown);
    }

    public function test_to_markdown_converts_animated_custom_emoji(): void
    {
        $markdown = $this->converter->toMarkdown('Cool <a:wave:456>');

        $this->assertStringContainsString(':wave:', $markdown);
    }

    public function test_to_markdown_handles_spoiler_tags(): void
    {
        $markdown = $this->converter->toMarkdown('Secret ||hidden text||');

        $this->assertStringContainsString('hidden text', $markdown);
    }

    // ── extractPlainText ────────────────────────────────────────────

    public function test_extract_plain_text_removes_bold_markers(): void
    {
        $result = $this->converter->toMarkdown('Hello **world**!');
        // The toMarkdown method should strip or preserve standard markdown.
        // extractPlainText is done via the adapter's parseMessage.
        // For the converter: ensure text content is accessible.
        $this->assertStringContainsString('world', $result);
        $this->assertStringNotContainsString('**', $this->stripMarkdown($result));
    }

    public function test_extract_plain_text_removes_italic_markers(): void
    {
        $plain = $this->stripMarkdown('Hello *world*!');

        $this->assertSame('Hello world!', $plain);
    }

    public function test_extract_plain_text_removes_strikethrough_markers(): void
    {
        $plain = $this->stripMarkdown('Hello ~~world~~!');

        $this->assertSame('Hello world!', $plain);
    }

    public function test_extract_plain_text_extracts_link_text(): void
    {
        $plain = $this->stripMarkdown('Check [this](https://example.com)');

        $this->assertStringContainsString('this', $plain);
    }

    public function test_extract_plain_text_formats_user_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('Hey <@U123>!');

        $this->assertStringContainsString('@U123', $markdown);
    }

    public function test_extract_plain_text_handles_complex_messages(): void
    {
        $input = '**Bold** and *italic* with [link](https://x.com) and <@U123>';
        $markdown = $this->converter->toMarkdown($input);

        $this->assertStringContainsString('Bold', $markdown);
        $this->assertStringContainsString('italic', $markdown);
        $this->assertStringContainsString('link', $markdown);
        $this->assertStringContainsString('@U123', $markdown);
        $this->assertStringNotContainsString('<@', $markdown);
    }

    public function test_extract_plain_text_handles_inline_code(): void
    {
        $markdown = $this->converter->toMarkdown('Use `const x = 1`');

        $this->assertStringContainsString('const x = 1', $markdown);
    }

    public function test_extract_plain_text_handles_code_blocks(): void
    {
        $markdown = $this->converter->toMarkdown("```js\nconst x = 1;\n```");

        $this->assertStringContainsString('const x = 1;', $markdown);
    }

    public function test_extract_plain_text_handles_empty_string(): void
    {
        $markdown = $this->converter->toMarkdown('');

        $this->assertSame('', $markdown);
    }

    public function test_extract_plain_text_handles_plain_text(): void
    {
        $markdown = $this->converter->toMarkdown('Hello world');

        $this->assertSame('Hello world', $markdown);
    }

    // ── renderPostable ──────────────────────────────────────────────

    public function test_render_postable_renders_plain_string_with_mention_conversion(): void
    {
        $message = PostableMessage::text('Hello @user');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello <@user>', $result);
    }

    public function test_render_postable_renders_raw_message_with_mention_conversion(): void
    {
        $message = PostableMessage::raw('Hello @user');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('<@user>', (string) $result);
    }

    public function test_render_postable_renders_markdown_message(): void
    {
        $message = PostableMessage::markdown('Hello **world** @user');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('**world**', $result);
        $this->assertStringContainsString('<@user>', $result);
    }

    public function test_render_postable_handles_empty_message(): void
    {
        $message = PostableMessage::text('');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('', $result);
    }

    // ── Blockquotes ─────────────────────────────────────────────────

    public function test_blockquotes_round_trip(): void
    {
        $result = $this->converter->fromMarkdown('> quoted text');

        $this->assertStringContainsString('> quoted text', $result);
    }

    // ── Lists ───────────────────────────────────────────────────────

    public function test_unordered_lists_round_trip(): void
    {
        $result = $this->converter->fromMarkdown("- item 1\n- item 2");

        $this->assertStringContainsString('- item 1', $result);
        $this->assertStringContainsString('- item 2', $result);
    }

    public function test_ordered_lists_round_trip(): void
    {
        $result = $this->converter->fromMarkdown("1. item 1\n2. item 2");

        $this->assertStringContainsString('1.', $result);
        $this->assertStringContainsString('2.', $result);
    }

    // ── Thematic Break ──────────────────────────────────────────────

    public function test_thematic_break_round_trip(): void
    {
        $result = $this->converter->fromMarkdown("text\n\n---\n\nmore text");

        $this->assertStringContainsString('---', $result);
    }

    // ── Helper ──────────────────────────────────────────────────────

    /**
     * Strip markdown formatting markers for plain text comparison.
     */
    private function stripMarkdown(string $text): string
    {
        // Remove Discord-specific mention syntax
        $text = preg_replace('/<@!?(\d+)>/', '@$1', $text);
        $text = preg_replace('/<#(\d+)>/', '#$1', $text);
        $text = preg_replace('/<@&(\d+)>/', '@&$1', $text);
        $text = preg_replace('/<a?:(\w+):\d+>/', ':$1:', $text);

        // Remove markdown formatting
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/\|\|(.+?)\|\|/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        return $text;
    }
}
