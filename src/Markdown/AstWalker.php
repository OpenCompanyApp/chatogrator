<?php

namespace OpenCompany\Chatogrator\Markdown;

class AstWalker
{
    /**
     * Walk an AST tree, calling the visitor for each node.
     *
     * @param array<string, mixed> $node
     */
    public static function walk(array $node, callable $visitor): void
    {
        $visitor($node);

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                static::walk($child, $visitor);
            }
        }
    }
}
