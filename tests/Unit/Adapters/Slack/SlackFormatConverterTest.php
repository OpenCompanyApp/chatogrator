<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackFormatConverter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group slack
 */
class SlackFormatConverterTest extends TestCase
{
    private SlackFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new SlackFormatConverter;
    }

    // ========================================================================
    // fromMarkdown (markdown -> Slack mrkdwn)
    // ========================================================================

    public function test_from_markdown_converts_bold(): void
    {
        $result = $this->converter->fromMarkdown('Hello **world**!');

        $this->assertSame('Hello *world*!', $result);
    }

    public function test_from_markdown_converts_italic(): void
    {
        $result = $this->converter->fromMarkdown('Hello _world_!');

        $this->assertSame('Hello _world_!', $result);
    }

    public function test_from_markdown_converts_strikethrough(): void
    {
        $result = $this->converter->fromMarkdown('Hello ~~world~~!');

        $this->assertSame('Hello ~world~!', $result);
    }

    public function test_from_markdown_converts_links(): void
    {
        $result = $this->converter->fromMarkdown('Check [this](https://example.com)');

        $this->assertSame('Check <https://example.com|this>', $result);
    }

    public function test_from_markdown_preserves_inline_code(): void
    {
        $result = $this->converter->fromMarkdown('Use `const x = 1`');

        $this->assertSame('Use `const x = 1`', $result);
    }

    public function test_from_markdown_handles_code_blocks(): void
    {
        $input = "```js\nconst x = 1;\n```";
        $result = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('```', $result);
        $this->assertStringContainsString('const x = 1;', $result);
    }

    public function test_from_markdown_handles_mixed_formatting(): void
    {
        $input = '**Bold** and _italic_ and [link](https://x.com)';
        $result = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('*Bold*', $result);
        $this->assertStringContainsString('_italic_', $result);
        $this->assertStringContainsString('<https://x.com|link>', $result);
    }

    // ========================================================================
    // toMarkdown (Slack mrkdwn -> markdown)
    // ========================================================================

    public function test_to_markdown_converts_bold(): void
    {
        $result = $this->converter->toMarkdown('Hello *world*!');

        $this->assertStringContainsString('**world**', $result);
    }

    public function test_to_markdown_converts_strikethrough(): void
    {
        $result = $this->converter->toMarkdown('Hello ~world~!');

        $this->assertStringContainsString('~~world~~', $result);
    }

    public function test_to_markdown_converts_links_with_text(): void
    {
        $result = $this->converter->toMarkdown('Check <https://example.com|this>');

        $this->assertStringContainsString('[this](https://example.com)', $result);
    }

    public function test_to_markdown_converts_bare_links(): void
    {
        $result = $this->converter->toMarkdown('Visit <https://example.com>');

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function test_to_markdown_converts_user_mentions(): void
    {
        $result = $this->converter->toMarkdown('Hey <@U123|john>!');

        $this->assertStringContainsString('@john', $result);
    }

    public function test_to_markdown_converts_channel_mentions(): void
    {
        $result = $this->converter->toMarkdown('Join <#C123|general>');

        $this->assertStringContainsString('#general', $result);
    }

    // ========================================================================
    // Mention Handling
    // ========================================================================

    public function test_does_not_double_wrap_slack_mentions_in_string(): void
    {
        $result = $this->converter->fromMarkdown('Hey <@U12345>. Please select');

        $this->assertSame('Hey <@U12345>. Please select', $result);
    }

    public function test_does_not_double_wrap_slack_mentions_via_from_markdown(): void
    {
        $result = $this->converter->fromMarkdown('Hey <@U12345>');

        $this->assertSame('Hey <@U12345>', $result);
    }

    public function test_converts_bare_at_mentions_to_slack_format(): void
    {
        $result = $this->converter->fromMarkdown('Hey @george. Please select');

        $this->assertSame('Hey <@george>. Please select', $result);
    }

    // ========================================================================
    // toPlainText
    // ========================================================================

    public function test_to_plain_text_removes_bold_markers(): void
    {
        $result = $this->converter->toPlainText('Hello *world*!');

        $this->assertSame('Hello world!', $result);
    }

    public function test_to_plain_text_removes_italic_markers(): void
    {
        $result = $this->converter->toPlainText('Hello _world_!');

        $this->assertSame('Hello world!', $result);
    }

    public function test_to_plain_text_extracts_link_text(): void
    {
        $result = $this->converter->toPlainText('Check <https://example.com|this>');

        $this->assertSame('Check this', $result);
    }

    public function test_to_plain_text_formats_user_mentions(): void
    {
        $result = $this->converter->toPlainText('Hey <@U123>!');

        $this->assertStringContainsString('@U123', $result);
    }

    public function test_to_plain_text_handles_complex_messages(): void
    {
        $input = '*Bold* and _italic_ with <https://x.com|link> and <@U123|user>';
        $result = $this->converter->toPlainText($input);

        $this->assertStringContainsString('Bold', $result);
        $this->assertStringContainsString('italic', $result);
        $this->assertStringContainsString('link', $result);
        $this->assertStringContainsString('user', $result);

        // Should not contain formatting characters
        $this->assertStringNotContainsString('*', $result);
        $this->assertStringNotContainsString('<', $result);
    }
}
