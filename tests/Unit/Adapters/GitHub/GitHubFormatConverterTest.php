<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GitHub;

use OpenCompany\Chatogrator\Adapters\GitHub\GitHubFormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for GitHub format conversion — markdown round-tripping,
 * mention handling, and postable message rendering.
 *
 * Ported from adapter-github/src/markdown.test.ts.
 *
 * @group github
 */
class GitHubFormatConverterTest extends TestCase
{
    private GitHubFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new GitHubFormatConverter;
    }

    // ── toMarkdown (GitHub markdown -> standard markdown) ───────────

    public function test_to_markdown_parses_plain_text(): void
    {
        $markdown = $this->converter->toMarkdown('Hello world');

        $this->assertSame('Hello world', $markdown);
    }

    public function test_to_markdown_parses_bold_text(): void
    {
        $markdown = $this->converter->toMarkdown('**bold text**');

        $this->assertStringContainsString('bold text', $markdown);
    }

    public function test_to_markdown_preserves_at_mentions(): void
    {
        $markdown = $this->converter->toMarkdown('Hey @username, check this out');

        $this->assertStringContainsString('@username', $markdown);
    }

    public function test_to_markdown_preserves_code_blocks(): void
    {
        $markdown = $this->converter->toMarkdown("```javascript\nconsole.log('hello');\n```");

        $this->assertStringContainsString('console.log', $markdown);
    }

    public function test_to_markdown_preserves_links(): void
    {
        $markdown = $this->converter->toMarkdown('[link text](https://example.com)');

        $this->assertStringContainsString('[link text](https://example.com)', $markdown);
    }

    public function test_to_markdown_preserves_strikethrough(): void
    {
        $markdown = $this->converter->toMarkdown('~~deleted~~');

        $this->assertStringContainsString('deleted', $markdown);
    }

    public function test_to_markdown_preserves_issue_references(): void
    {
        $markdown = $this->converter->toMarkdown('Fixed in #123');

        $this->assertStringContainsString('#123', $markdown);
    }

    public function test_to_markdown_preserves_task_lists(): void
    {
        $markdown = $this->converter->toMarkdown("- [x] Done\n- [ ] Todo");

        $this->assertStringContainsString('Done', $markdown);
        $this->assertStringContainsString('Todo', $markdown);
    }

    // ── fromMarkdown (standard markdown -> GitHub markdown) ─────────

    public function test_from_markdown_renders_bold(): void
    {
        $result = $this->converter->fromMarkdown('**bold text**');

        $this->assertStringContainsString('**bold text**', $result);
    }

    public function test_from_markdown_renders_italic(): void
    {
        $result = $this->converter->fromMarkdown('*italic text*');

        $this->assertStringContainsString('*italic text*', $result);
    }

    public function test_from_markdown_renders_links(): void
    {
        $result = $this->converter->fromMarkdown('[link text](https://example.com)');

        $this->assertStringContainsString('[link text](https://example.com)', $result);
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

        $this->assertStringContainsString('Bold', $output);
        $this->assertStringContainsString('italic', $output);
        $this->assertStringContainsString('link', $output);
    }

    // ── extractPlainText ────────────────────────────────────────────

    public function test_extract_plain_text_strips_bold_markers(): void
    {
        $plain = $this->stripMarkdown('**bold** and _italic_');

        $this->assertStringContainsString('bold', $plain);
        $this->assertStringContainsString('italic', $plain);
        $this->assertStringNotContainsString('**', $plain);
    }

    public function test_extract_plain_text_preserves_at_mentions(): void
    {
        $plain = $this->stripMarkdown('Hey @user, **thanks**!');

        $this->assertStringContainsString('@user', $plain);
        $this->assertStringContainsString('thanks', $plain);
    }

    public function test_extract_plain_text_handles_code_blocks(): void
    {
        $plain = $this->stripMarkdown("```\ncode\n```");

        $this->assertStringContainsString('code', $plain);
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
        $message = PostableMessage::markdown('**bold**');
        $result = $this->converter->renderPostable($message);

        $this->assertStringContainsString('**bold**', (string) $result);
    }

    public function test_render_postable_handles_empty_message(): void
    {
        $message = PostableMessage::text('');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('', $result);
    }

    // ── Roundtrip ───────────────────────────────────────────────────

    public function test_roundtrip_simple_text(): void
    {
        $original = 'Hello world';
        $markdown = $this->converter->toMarkdown($original);
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('Hello world', $result);
    }

    public function test_roundtrip_markdown_with_formatting(): void
    {
        $original = '**bold** and *italic*';
        $markdown = $this->converter->toMarkdown($original);
        $result = $this->converter->fromMarkdown($markdown);

        $this->assertStringContainsString('bold', $result);
        $this->assertStringContainsString('italic', $result);
    }

    // ── Helper ──────────────────────────────────────────────────────

    /**
     * Strip markdown formatting markers for plain text comparison.
     */
    private function stripMarkdown(string $text): string
    {
        // Remove markdown formatting
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        $text = preg_replace('/```[\s\S]*?```/', '', $text);

        return trim($text);
    }
}
