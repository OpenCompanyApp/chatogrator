<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Markdown;

use OpenCompany\Chatogrator\Markdown\AstBuilder;
use OpenCompany\Chatogrator\Markdown\Markdown;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group markdown
 */
class MarkdownTest extends TestCase
{
    // =========================================================================
    // parse() Tests
    // =========================================================================

    public function test_parse_creates_ast_from_plain_text(): void
    {
        $ast = Markdown::parse('Hello, world!');

        $this->assertSame('root', $ast['type']);
        $this->assertNotEmpty($ast['children']);
    }

    public function test_parse_root_has_paragraph_child(): void
    {
        $ast = Markdown::parse('Hello, world!');

        $this->assertSame('paragraph', $ast['children'][0]['type']);
    }

    public function test_parse_bold_text(): void
    {
        $ast = Markdown::parse('**bold**');

        $this->assertSame('root', $ast['type']);
        // The first child should be a paragraph containing a strong node
        $para = $ast['children'][0];
        $this->assertSame('paragraph', $para['type']);
    }

    public function test_parse_italic_text(): void
    {
        $ast = Markdown::parse('_italic_');

        $para = $ast['children'][0];
        $this->assertSame('paragraph', $para['type']);
    }

    public function test_parse_inline_code(): void
    {
        $ast = Markdown::parse('`code`');

        $this->assertSame('root', $ast['type']);
    }

    public function test_parse_handles_empty_string(): void
    {
        $ast = Markdown::parse('');

        $this->assertSame('root', $ast['type']);
    }

    // =========================================================================
    // stringify() Tests
    // =========================================================================

    public function test_stringify_converts_simple_ast_to_text(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Hello'),
            ]),
        ]);

        $result = Markdown::stringify($ast);

        $this->assertStringContainsString('Hello', $result);
    }

    public function test_stringify_handles_empty_root(): void
    {
        $ast = AstBuilder::root([]);
        $result = Markdown::stringify($ast);

        $this->assertSame('', $result);
    }

    public function test_stringify_handles_multiple_paragraphs(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([AstBuilder::text('First')]),
            AstBuilder::paragraph([AstBuilder::text('Second')]),
        ]);

        $result = Markdown::stringify($ast);

        $this->assertStringContainsString('First', $result);
        $this->assertStringContainsString('Second', $result);
    }

    // =========================================================================
    // toPlainText() Tests
    // =========================================================================

    public function test_to_plain_text_extracts_text_from_ast(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Hello, world!'),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('Hello, world!', $result);
    }

    public function test_to_plain_text_strips_formatting(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::strong([AstBuilder::text('bold')]),
                AstBuilder::text(' and '),
                AstBuilder::emphasis([AstBuilder::text('italic')]),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('bold and italic', $result);
    }

    public function test_to_plain_text_handles_nested_formatting(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::strong([
                    AstBuilder::emphasis([
                        AstBuilder::text('bold italic'),
                    ]),
                ]),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('bold italic', $result);
    }

    public function test_to_plain_text_handles_empty_ast(): void
    {
        $ast = AstBuilder::root([]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('', $result);
    }

    public function test_to_plain_text_extracts_text_from_links(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::link('https://example.com', [
                    AstBuilder::text('link text'),
                ]),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('link text', $result);
    }

    public function test_to_plain_text_extracts_text_from_blockquote(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::blockquote([
                AstBuilder::paragraph([
                    AstBuilder::text('quoted text'),
                ]),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('quoted text', $result);
    }

    public function test_to_plain_text_extracts_text_from_strikethrough(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::strikethrough([AstBuilder::text('deleted')]),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('deleted', $result);
    }

    public function test_to_plain_text_handles_complex_document(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Hello '),
                AstBuilder::strong([AstBuilder::text('world')]),
                AstBuilder::text('! '),
                AstBuilder::emphasis([AstBuilder::text('great')]),
                AstBuilder::text(' day.'),
            ]),
        ]);

        $result = Markdown::toPlainText($ast);

        $this->assertSame('Hello world! great day.', $result);
    }

    // =========================================================================
    // Round-trip Tests (parse -> toPlainText)
    // =========================================================================

    public function test_parse_then_to_plain_text_preserves_content(): void
    {
        $ast = Markdown::parse('Hello, world!');
        $result = Markdown::toPlainText($ast);

        $this->assertStringContainsString('Hello, world!', $result);
    }

    public function test_convenience_parse_and_stringify(): void
    {
        $original = 'Simple text content';
        $ast = Markdown::parse($original);
        $result = Markdown::stringify($ast);

        $this->assertStringContainsString('Simple text content', $result);
    }
}
