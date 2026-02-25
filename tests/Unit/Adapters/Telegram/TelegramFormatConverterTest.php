<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Telegram;

use OpenCompany\Chatogrator\Adapters\Telegram\TelegramFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group telegram
 */
class TelegramFormatConverterTest extends TestCase
{
    private TelegramFormatConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new TelegramFormatConverter;
    }

    // ========================================================================
    // fromMarkdown (markdown -> Telegram HTML)
    // ========================================================================

    public function test_from_markdown_converts_bold_with_double_asterisks(): void
    {
        $result = $this->converter->fromMarkdown('Hello **world**!');

        $this->assertSame('Hello <b>world</b>!', $result);
    }

    public function test_from_markdown_converts_bold_with_double_underscores(): void
    {
        $result = $this->converter->fromMarkdown('Hello __world__!');

        $this->assertSame('Hello <b>world</b>!', $result);
    }

    public function test_from_markdown_converts_italic_with_single_asterisk(): void
    {
        $result = $this->converter->fromMarkdown('Hello *world*!');

        $this->assertSame('Hello <i>world</i>!', $result);
    }

    public function test_from_markdown_converts_italic_with_single_underscore(): void
    {
        $result = $this->converter->fromMarkdown('Hello _world_!');

        $this->assertSame('Hello <i>world</i>!', $result);
    }

    public function test_from_markdown_converts_strikethrough(): void
    {
        $result = $this->converter->fromMarkdown('Hello ~~world~~!');

        $this->assertSame('Hello <s>world</s>!', $result);
    }

    public function test_from_markdown_converts_inline_code(): void
    {
        $result = $this->converter->fromMarkdown('Use `const x = 1`');

        $this->assertSame('Use <code>const x = 1</code>', $result);
    }

    public function test_from_markdown_converts_code_blocks(): void
    {
        $input = "```php\n\$x = 1;\n```";
        $result = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('<pre>', $result);
        $this->assertStringContainsString('</pre>', $result);
        $this->assertStringContainsString('$x = 1;', $result);
    }

    public function test_from_markdown_converts_links(): void
    {
        $result = $this->converter->fromMarkdown('Check [this](https://example.com)');

        $this->assertSame('Check <a href="https://example.com">this</a>', $result);
    }

    public function test_from_markdown_converts_headings_to_bold(): void
    {
        $result = $this->converter->fromMarkdown('# Title');

        $this->assertSame('<b>Title</b>', $result);
    }

    public function test_from_markdown_converts_blockquotes(): void
    {
        $result = $this->converter->fromMarkdown('> This is quoted');

        $this->assertStringContainsString('<blockquote>', $result);
        $this->assertStringContainsString('This is quoted', $result);
        $this->assertStringContainsString('</blockquote>', $result);
    }

    public function test_from_markdown_escapes_html_entities(): void
    {
        $result = $this->converter->fromMarkdown('Use <script>alert("xss")</script>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_from_markdown_handles_mixed_formatting(): void
    {
        $input = '**Bold** and *italic* and [link](https://x.com)';
        $result = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('<b>Bold</b>', $result);
        $this->assertStringContainsString('<i>italic</i>', $result);
        $this->assertStringContainsString('<a href="https://x.com">link</a>', $result);
    }

    public function test_from_markdown_handles_empty_string(): void
    {
        $result = $this->converter->fromMarkdown('');

        $this->assertSame('', $result);
    }

    public function test_from_markdown_code_blocks_preserve_content(): void
    {
        $input = "```\n<b>not bold</b>\n```";
        $result = $this->converter->fromMarkdown($input);

        // The content inside the code block should be escaped, not interpreted as HTML
        $this->assertStringContainsString('&lt;b&gt;not bold&lt;/b&gt;', $result);
        // It should be wrapped in <pre> only once
        $this->assertSame(1, substr_count($result, '<pre>'));
    }

    public function test_from_markdown_multi_line_blockquotes(): void
    {
        $input = "> Line one\n> Line two\n> Line three";
        $result = $this->converter->fromMarkdown($input);

        $this->assertStringContainsString('<blockquote>', $result);
        $this->assertStringContainsString('Line one', $result);
        $this->assertStringContainsString('Line two', $result);
        $this->assertStringContainsString('Line three', $result);
        $this->assertStringContainsString('</blockquote>', $result);
    }

    // ========================================================================
    // toMarkdown (Telegram HTML -> markdown)
    // ========================================================================

    public function test_to_markdown_converts_b_tag_to_bold(): void
    {
        $result = $this->converter->toMarkdown('Hello <b>world</b>!');

        $this->assertSame('Hello **world**!', $result);
    }

    public function test_to_markdown_converts_strong_tag_to_bold(): void
    {
        $result = $this->converter->toMarkdown('Hello <strong>world</strong>!');

        $this->assertSame('Hello **world**!', $result);
    }

    public function test_to_markdown_converts_i_tag_to_italic(): void
    {
        $result = $this->converter->toMarkdown('Hello <i>world</i>!');

        $this->assertSame('Hello *world*!', $result);
    }

    public function test_to_markdown_converts_em_tag_to_italic(): void
    {
        $result = $this->converter->toMarkdown('Hello <em>world</em>!');

        $this->assertSame('Hello *world*!', $result);
    }

    public function test_to_markdown_converts_s_tag_to_strikethrough(): void
    {
        $result = $this->converter->toMarkdown('Hello <s>world</s>!');

        $this->assertSame('Hello ~~world~~!', $result);
    }

    public function test_to_markdown_converts_del_tag_to_strikethrough(): void
    {
        $result = $this->converter->toMarkdown('Hello <del>world</del>!');

        $this->assertSame('Hello ~~world~~!', $result);
    }

    public function test_to_markdown_converts_code_tag_to_inline_code(): void
    {
        $result = $this->converter->toMarkdown('Use <code>const x</code>');

        $this->assertSame('Use `const x`', $result);
    }

    public function test_to_markdown_converts_pre_tag_to_code_block(): void
    {
        $result = $this->converter->toMarkdown('<pre>const x = 1;</pre>');

        $this->assertStringContainsString('```', $result);
        $this->assertStringContainsString('const x = 1;', $result);
    }

    public function test_to_markdown_converts_a_tag_to_link(): void
    {
        $result = $this->converter->toMarkdown('Check <a href="https://example.com">this</a>');

        $this->assertStringContainsString('[this](https://example.com)', $result);
    }

    public function test_to_markdown_converts_blockquote_tag(): void
    {
        $result = $this->converter->toMarkdown('<blockquote>Quoted text</blockquote>');

        $this->assertStringContainsString('> Quoted text', $result);
    }

    public function test_to_markdown_strips_underline_tag(): void
    {
        $result = $this->converter->toMarkdown('Hello <u>underlined</u> text');

        $this->assertStringContainsString('underlined', $result);
        $this->assertStringNotContainsString('<u>', $result);
        $this->assertStringNotContainsString('</u>', $result);
    }

    public function test_to_markdown_unescapes_html_entities(): void
    {
        $result = $this->converter->toMarkdown('5 &lt; 10 &amp; 10 &gt; 5');

        $this->assertSame('5 < 10 & 10 > 5', $result);
    }

    public function test_to_markdown_strips_unknown_html_tags(): void
    {
        $result = $this->converter->toMarkdown('Hello <span class="custom">styled</span> text');

        $this->assertStringContainsString('styled', $result);
        $this->assertStringNotContainsString('<span', $result);
        $this->assertStringNotContainsString('</span>', $result);
    }

    public function test_to_markdown_handles_empty_string(): void
    {
        $result = $this->converter->toMarkdown('');

        $this->assertSame('', $result);
    }

    public function test_to_markdown_handles_mixed_content(): void
    {
        $input = '<b>Bold</b> and <i>italic</i> with <a href="https://x.com">link</a>';
        $result = $this->converter->toMarkdown($input);

        $this->assertStringContainsString('**Bold**', $result);
        $this->assertStringContainsString('*italic*', $result);
        $this->assertStringContainsString('[link](https://x.com)', $result);
    }

    // ========================================================================
    // toPlainText
    // ========================================================================

    public function test_to_plain_text_strips_all_html_tags(): void
    {
        $result = $this->converter->toPlainText('<b>Bold</b> and <i>italic</i> text');

        $this->assertSame('Bold and italic text', $result);
    }

    public function test_to_plain_text_unescapes_html_entities(): void
    {
        $result = $this->converter->toPlainText('5 &lt; 10 &amp; 10 &gt; 5');

        $this->assertSame('5 < 10 & 10 > 5', $result);
    }

    // ========================================================================
    // renderPostable
    // ========================================================================

    public function test_render_postable_with_text_returns_text(): void
    {
        $message = PostableMessage::text('Hello world');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello world', $result);
    }

    public function test_render_postable_with_markdown_returns_html(): void
    {
        $message = PostableMessage::markdown('Hello **world**');
        $result = $this->converter->renderPostable($message);

        $this->assertSame('Hello <b>world</b>', $result);
    }

    public function test_render_postable_with_card_returns_rendered_array(): void
    {
        $card = Card::make('Test Card');
        $message = PostableMessage::card($card);
        $result = $this->converter->renderPostable($message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('text', $result);
        $this->assertStringContainsString('<b>Test Card</b>', $result['text']);
    }

    // ========================================================================
    // escapeHtml / unescapeHtml
    // ========================================================================

    public function test_escape_html_escapes_special_characters(): void
    {
        $result = TelegramFormatConverter::escapeHtml('<b>"Hello" & \'World\'</b>');

        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&gt;', $result);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringContainsString('&#039;', $result);
    }

    public function test_unescape_html_restores_entities(): void
    {
        $result = TelegramFormatConverter::unescapeHtml('&lt;b&gt;&quot;Hello&quot; &amp; &#039;World&#039;&lt;/b&gt;');

        $this->assertSame('<b>"Hello" & \'World\'</b>', $result);
    }

    public function test_escape_unescape_round_trips_correctly(): void
    {
        $original = '<script>alert("xss" & \'test\')</script>';
        $escaped = TelegramFormatConverter::escapeHtml($original);
        $restored = TelegramFormatConverter::unescapeHtml($escaped);

        $this->assertSame($original, $restored);
    }
}
