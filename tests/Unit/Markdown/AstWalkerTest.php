<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Markdown;

use OpenCompany\Chatogrator\Markdown\AstBuilder;
use OpenCompany\Chatogrator\Markdown\AstWalker;
use OpenCompany\Chatogrator\Markdown\Markdown;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group markdown
 */
class AstWalkerTest extends TestCase
{
    // =========================================================================
    // walk() — Visiting nodes
    // =========================================================================

    public function test_visits_all_nodes(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::strong([AstBuilder::text('bold')]),
                AstBuilder::text(' and '),
                AstBuilder::emphasis([AstBuilder::text('italic')]),
            ]),
        ]);

        $visited = [];
        AstWalker::walk($ast, function (array $node) use (&$visited) {
            $visited[] = $node['type'];
        });

        $this->assertContains('root', $visited);
        $this->assertContains('paragraph', $visited);
        $this->assertContains('strong', $visited);
        $this->assertContains('emphasis', $visited);
        $this->assertContains('text', $visited);
    }

    public function test_visits_root_first(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([AstBuilder::text('hello')]),
        ]);

        $visited = [];
        AstWalker::walk($ast, function (array $node) use (&$visited) {
            $visited[] = $node['type'];
        });

        $this->assertSame('root', $visited[0]);
    }

    public function test_visits_nodes_in_depth_first_order(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('hello'),
            ]),
        ]);

        $visited = [];
        AstWalker::walk($ast, function (array $node) use (&$visited) {
            $visited[] = $node['type'];
        });

        $this->assertSame(['root', 'paragraph', 'text'], $visited);
    }

    public function test_visits_all_text_nodes(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('first'),
                AstBuilder::text(' '),
                AstBuilder::text('second'),
            ]),
        ]);

        $texts = [];
        AstWalker::walk($ast, function (array $node) use (&$texts) {
            if ($node['type'] === 'text') {
                $texts[] = $node['value'];
            }
        });

        $this->assertSame(['first', ' ', 'second'], $texts);
    }

    // =========================================================================
    // walk() — Deeply nested structures
    // =========================================================================

    public function test_handles_deeply_nested_structures(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::blockquote([
                AstBuilder::paragraph([
                    AstBuilder::strong([
                        AstBuilder::emphasis([
                            AstBuilder::text('deep'),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $types = [];
        AstWalker::walk($ast, function (array $node) use (&$types) {
            $types[] = $node['type'];
        });

        $this->assertContains('root', $types);
        $this->assertContains('blockquote', $types);
        $this->assertContains('paragraph', $types);
        $this->assertContains('strong', $types);
        $this->assertContains('emphasis', $types);
        $this->assertContains('text', $types);
    }

    public function test_handles_multiple_levels_of_nesting(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::blockquote([
                AstBuilder::paragraph([
                    AstBuilder::strong([
                        AstBuilder::emphasis([
                            AstBuilder::strikethrough([
                                AstBuilder::text('deeply nested'),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $types = [];
        AstWalker::walk($ast, function (array $node) use (&$types) {
            $types[] = $node['type'];
        });

        $this->assertContains('delete', $types);
    }

    // =========================================================================
    // walk() — Empty AST
    // =========================================================================

    public function test_handles_empty_ast(): void
    {
        $ast = AstBuilder::root([]);

        $visited = [];
        AstWalker::walk($ast, function (array $node) use (&$visited) {
            // Only count non-root nodes
            if ($node['type'] !== 'root') {
                $visited[] = $node['type'];
            }
        });

        $this->assertEmpty($visited);
    }

    public function test_visits_root_even_when_empty(): void
    {
        $ast = AstBuilder::root([]);

        $visited = [];
        AstWalker::walk($ast, function (array $node) use (&$visited) {
            $visited[] = $node['type'];
        });

        $this->assertSame(['root'], $visited);
    }

    // =========================================================================
    // walk() — Node counting
    // =========================================================================

    public function test_counts_total_nodes(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('hello'),
                AstBuilder::strong([AstBuilder::text('bold')]),
            ]),
        ]);

        $count = 0;
        AstWalker::walk($ast, function () use (&$count) {
            $count++;
        });

        // root -> paragraph -> text, strong -> text = 5 nodes
        $this->assertSame(5, $count);
    }

    // =========================================================================
    // walk() — Collecting specific node types
    // =========================================================================

    public function test_collects_only_text_values(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Hello '),
                AstBuilder::strong([AstBuilder::text('world')]),
                AstBuilder::text('!'),
            ]),
        ]);

        $texts = [];
        AstWalker::walk($ast, function (array $node) use (&$texts) {
            if ($node['type'] === 'text') {
                $texts[] = $node['value'];
            }
        });

        $this->assertSame(['Hello ', 'world', '!'], $texts);
    }

    public function test_collects_link_urls(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::link('https://example.com', [AstBuilder::text('Example')]),
                AstBuilder::text(' and '),
                AstBuilder::link('https://other.com', [AstBuilder::text('Other')]),
            ]),
        ]);

        $urls = [];
        AstWalker::walk($ast, function (array $node) use (&$urls) {
            if ($node['type'] === 'link') {
                $urls[] = $node['url'];
            }
        });

        $this->assertSame(['https://example.com', 'https://other.com'], $urls);
    }

    // =========================================================================
    // walk() — Complex documents
    // =========================================================================

    public function test_walks_complex_document(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::text('Normal text. '),
                AstBuilder::strong([AstBuilder::text('Bold')]),
                AstBuilder::text('. '),
                AstBuilder::emphasis([AstBuilder::text('Italic')]),
                AstBuilder::text('. '),
                AstBuilder::inlineCode('code'),
            ]),
            AstBuilder::blockquote([
                AstBuilder::paragraph([
                    AstBuilder::text('A quote'),
                ]),
            ]),
            AstBuilder::paragraph([
                AstBuilder::link('https://example.com', [AstBuilder::text('A link')]),
            ]),
        ]);

        $nodeTypes = [];
        AstWalker::walk($ast, function (array $node) use (&$nodeTypes) {
            $nodeTypes[] = $node['type'];
        });

        $this->assertContains('root', $nodeTypes);
        $this->assertContains('paragraph', $nodeTypes);
        $this->assertContains('strong', $nodeTypes);
        $this->assertContains('emphasis', $nodeTypes);
        $this->assertContains('inlineCode', $nodeTypes);
        $this->assertContains('blockquote', $nodeTypes);
        $this->assertContains('link', $nodeTypes);
        $this->assertContains('text', $nodeTypes);
    }

    // =========================================================================
    // walk() — Leaf nodes
    // =========================================================================

    public function test_inline_code_is_leaf_node(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::paragraph([
                AstBuilder::inlineCode('var x'),
            ]),
        ]);

        $types = [];
        AstWalker::walk($ast, function (array $node) use (&$types) {
            $types[] = $node['type'];
        });

        $this->assertContains('inlineCode', $types);
    }

    public function test_code_block_is_leaf_node(): void
    {
        $ast = AstBuilder::root([
            AstBuilder::codeBlock('function() {}', 'javascript'),
        ]);

        $types = [];
        AstWalker::walk($ast, function (array $node) use (&$types) {
            $types[] = $node['type'];
        });

        $this->assertContains('code', $types);
    }
}
