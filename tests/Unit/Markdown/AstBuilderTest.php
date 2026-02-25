<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Markdown;

use OpenCompany\Chatogrator\Markdown\AstBuilder;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group markdown
 */
class AstBuilderTest extends TestCase
{
    // =========================================================================
    // text()
    // =========================================================================

    public function test_text_creates_text_node(): void
    {
        $node = AstBuilder::text('hello');

        $this->assertSame('text', $node['type']);
        $this->assertSame('hello', $node['value']);
    }

    public function test_text_handles_empty_string(): void
    {
        $node = AstBuilder::text('');

        $this->assertSame('text', $node['type']);
        $this->assertSame('', $node['value']);
    }

    public function test_text_handles_special_characters(): void
    {
        $node = AstBuilder::text('hello & world < > "');

        $this->assertSame('hello & world < > "', $node['value']);
    }

    // =========================================================================
    // strong()
    // =========================================================================

    public function test_strong_creates_strong_node(): void
    {
        $node = AstBuilder::strong([AstBuilder::text('bold')]);

        $this->assertSame('strong', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_strong_handles_nested_content(): void
    {
        $node = AstBuilder::strong([
            AstBuilder::emphasis([AstBuilder::text('bold italic')]),
        ]);

        $this->assertSame('strong', $node['type']);
        $this->assertSame('emphasis', $node['children'][0]['type']);
    }

    public function test_strong_with_multiple_children(): void
    {
        $node = AstBuilder::strong([
            AstBuilder::text('part 1 '),
            AstBuilder::text('part 2'),
        ]);

        $this->assertCount(2, $node['children']);
    }

    // =========================================================================
    // emphasis()
    // =========================================================================

    public function test_emphasis_creates_emphasis_node(): void
    {
        $node = AstBuilder::emphasis([AstBuilder::text('italic')]);

        $this->assertSame('emphasis', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_emphasis_with_text_child(): void
    {
        $node = AstBuilder::emphasis([AstBuilder::text('hello')]);

        $this->assertSame('text', $node['children'][0]['type']);
        $this->assertSame('hello', $node['children'][0]['value']);
    }

    // =========================================================================
    // strikethrough()
    // =========================================================================

    public function test_strikethrough_creates_delete_node(): void
    {
        $node = AstBuilder::strikethrough([AstBuilder::text('deleted')]);

        $this->assertSame('delete', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_strikethrough_child_contains_text(): void
    {
        $node = AstBuilder::strikethrough([AstBuilder::text('removed')]);

        $this->assertSame('removed', $node['children'][0]['value']);
    }

    // =========================================================================
    // inlineCode()
    // =========================================================================

    public function test_inline_code_creates_inline_code_node(): void
    {
        $node = AstBuilder::inlineCode('const x = 1');

        $this->assertSame('inlineCode', $node['type']);
        $this->assertSame('const x = 1', $node['value']);
    }

    public function test_inline_code_preserves_value(): void
    {
        $node = AstBuilder::inlineCode('function() {}');

        $this->assertSame('function() {}', $node['value']);
    }

    // =========================================================================
    // codeBlock()
    // =========================================================================

    public function test_code_block_creates_code_node(): void
    {
        $node = AstBuilder::codeBlock('function() {}', 'javascript');

        $this->assertSame('code', $node['type']);
        $this->assertSame('function() {}', $node['value']);
        $this->assertSame('javascript', $node['lang']);
    }

    public function test_code_block_without_language(): void
    {
        $node = AstBuilder::codeBlock('plain code');

        $this->assertSame('code', $node['type']);
        $this->assertSame('plain code', $node['value']);
        $this->assertNull($node['lang']);
    }

    public function test_code_block_with_php_language(): void
    {
        $node = AstBuilder::codeBlock('<?php echo "hello";', 'php');

        $this->assertSame('php', $node['lang']);
    }

    // =========================================================================
    // link()
    // =========================================================================

    public function test_link_creates_link_node(): void
    {
        $node = AstBuilder::link('https://example.com', [AstBuilder::text('Example')]);

        $this->assertSame('link', $node['type']);
        $this->assertSame('https://example.com', $node['url']);
        $this->assertCount(1, $node['children']);
    }

    public function test_link_with_title(): void
    {
        $node = AstBuilder::link('https://example.com', [AstBuilder::text('Example')], 'Title');

        $this->assertSame('Title', $node['title']);
    }

    public function test_link_without_title_is_null(): void
    {
        $node = AstBuilder::link('https://example.com', [AstBuilder::text('Link')]);

        $this->assertNull($node['title']);
    }

    public function test_link_child_text(): void
    {
        $node = AstBuilder::link('https://example.com', [AstBuilder::text('Click here')]);

        $this->assertSame('Click here', $node['children'][0]['value']);
    }

    // =========================================================================
    // blockquote()
    // =========================================================================

    public function test_blockquote_creates_blockquote_node(): void
    {
        $node = AstBuilder::blockquote([
            AstBuilder::paragraph([AstBuilder::text('quoted')]),
        ]);

        $this->assertSame('blockquote', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_blockquote_with_nested_paragraph(): void
    {
        $node = AstBuilder::blockquote([
            AstBuilder::paragraph([AstBuilder::text('inside quote')]),
        ]);

        $this->assertSame('paragraph', $node['children'][0]['type']);
    }

    // =========================================================================
    // paragraph()
    // =========================================================================

    public function test_paragraph_creates_paragraph_node(): void
    {
        $node = AstBuilder::paragraph([AstBuilder::text('content')]);

        $this->assertSame('paragraph', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_paragraph_with_mixed_inline_content(): void
    {
        $node = AstBuilder::paragraph([
            AstBuilder::text('Hello '),
            AstBuilder::strong([AstBuilder::text('world')]),
            AstBuilder::text('!'),
        ]);

        $this->assertCount(3, $node['children']);
        $this->assertSame('text', $node['children'][0]['type']);
        $this->assertSame('strong', $node['children'][1]['type']);
        $this->assertSame('text', $node['children'][2]['type']);
    }

    // =========================================================================
    // root()
    // =========================================================================

    public function test_root_creates_root_node(): void
    {
        $node = AstBuilder::root([
            AstBuilder::paragraph([AstBuilder::text('content')]),
        ]);

        $this->assertSame('root', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    public function test_root_handles_empty_children(): void
    {
        $node = AstBuilder::root([]);

        $this->assertSame('root', $node['type']);
        $this->assertCount(0, $node['children']);
    }

    public function test_root_with_multiple_children(): void
    {
        $node = AstBuilder::root([
            AstBuilder::paragraph([AstBuilder::text('First')]),
            AstBuilder::paragraph([AstBuilder::text('Second')]),
            AstBuilder::paragraph([AstBuilder::text('Third')]),
        ]);

        $this->assertCount(3, $node['children']);
    }

    // =========================================================================
    // list() and listItem()
    // =========================================================================

    public function test_list_creates_list_node(): void
    {
        $node = AstBuilder::list([
            AstBuilder::listItem([AstBuilder::paragraph([AstBuilder::text('item 1')])]),
            AstBuilder::listItem([AstBuilder::paragraph([AstBuilder::text('item 2')])]),
        ]);

        $this->assertSame('list', $node['type']);
        $this->assertCount(2, $node['children']);
        $this->assertFalse($node['ordered']);
    }

    public function test_ordered_list(): void
    {
        $node = AstBuilder::list([
            AstBuilder::listItem([AstBuilder::paragraph([AstBuilder::text('first')])]),
        ], true);

        $this->assertTrue($node['ordered']);
    }

    public function test_list_item_creates_list_item_node(): void
    {
        $node = AstBuilder::listItem([
            AstBuilder::paragraph([AstBuilder::text('item content')]),
        ]);

        $this->assertSame('listItem', $node['type']);
        $this->assertCount(1, $node['children']);
    }

    // =========================================================================
    // Complex AST Composition
    // =========================================================================

    public function test_complex_document_structure(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Hello '),
                AstBuilder::strong([AstBuilder::text('world')]),
            ]),
            AstBuilder::blockquote([
                AstBuilder::paragraph([
                    AstBuilder::emphasis([AstBuilder::text('quoted')]),
                ]),
            ]),
            AstBuilder::paragraph([
                AstBuilder::inlineCode('code'),
                AstBuilder::text(' and '),
                AstBuilder::link('https://example.com', [AstBuilder::text('link')]),
            ]),
        ]);

        $this->assertSame('root', $ast['type']);
        $this->assertCount(3, $ast['children']);
        $this->assertSame('paragraph', $ast['children'][0]['type']);
        $this->assertSame('blockquote', $ast['children'][1]['type']);
        $this->assertSame('paragraph', $ast['children'][2]['type']);
    }
}
