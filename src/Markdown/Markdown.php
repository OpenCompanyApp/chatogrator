<?php

namespace OpenCompany\Chatogrator\Markdown;

class Markdown
{
    /**
     * Parse a markdown string into an AST (mdast-compatible).
     *
     * @return array<string, mixed>
     */
    public static function parse(string $markdown): array
    {
        if ($markdown === '') {
            return AstBuilder::root([]);
        }

        $children = self::parseBlocks($markdown);

        return AstBuilder::root($children);
    }

    /**
     * Parse block-level elements (paragraphs, code blocks, blockquotes, lists).
     *
     * @return list<array<string, mixed>>
     */
    protected static function parseBlocks(string $text): array
    {
        $blocks = [];
        $lines = explode("\n", $text);
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // Fenced code block
            if (preg_match('/^```(\w*)/', $line, $m)) {
                $lang = $m[1] ?: null;
                $codeLines = [];
                $i++;
                while ($i < $count && ! preg_match('/^```\s*$/', $lines[$i])) {
                    $codeLines[] = $lines[$i];
                    $i++;
                }
                $i++; // skip closing ```
                $node = AstBuilder::codeBlock(implode("\n", $codeLines));
                if ($lang) {
                    $node['lang'] = $lang;
                }
                $blocks[] = $node;

                continue;
            }

            // Blockquote
            if (str_starts_with($line, '> ')) {
                $quoteLines = [];
                while ($i < $count && str_starts_with($lines[$i], '> ')) {
                    $quoteLines[] = substr($lines[$i], 2);
                    $i++;
                }
                $innerBlocks = self::parseBlocks(implode("\n", $quoteLines));
                $blocks[] = AstBuilder::blockquote($innerBlocks);

                continue;
            }

            // Unordered list
            if (preg_match('/^[-*+] /', $line)) {
                $items = [];
                while ($i < $count && preg_match('/^[-*+] (.*)/', $lines[$i], $m)) {
                    $items[] = AstBuilder::listItem([
                        AstBuilder::paragraph(self::parseInline($m[1])),
                    ]);
                    $i++;
                }
                $blocks[] = AstBuilder::list($items, ordered: false);

                continue;
            }

            // Ordered list
            if (preg_match('/^\d+\. /', $line)) {
                $items = [];
                while ($i < $count && preg_match('/^\d+\. (.*)/', $lines[$i], $m)) {
                    $items[] = AstBuilder::listItem([
                        AstBuilder::paragraph(self::parseInline($m[1])),
                    ]);
                    $i++;
                }
                $blocks[] = AstBuilder::list($items, ordered: true);

                continue;
            }

            // Empty line (paragraph separator)
            if (trim($line) === '') {
                $i++;

                continue;
            }

            // Paragraph: collect lines until empty line or block-level element
            $paraLines = [];
            while ($i < $count && trim($lines[$i]) !== '' &&
                   ! preg_match('/^```/', $lines[$i]) &&
                   ! str_starts_with($lines[$i], '> ') &&
                   ! preg_match('/^[-*+] /', $lines[$i]) &&
                   ! preg_match('/^\d+\. /', $lines[$i])) {
                $paraLines[] = $lines[$i];
                $i++;
            }
            if (! empty($paraLines)) {
                $blocks[] = AstBuilder::paragraph(
                    self::parseInline(implode("\n", $paraLines))
                );
            }
        }

        return $blocks;
    }

    /**
     * Parse inline elements (bold, italic, strikethrough, code, links).
     *
     * @return list<array<string, mixed>>
     */
    protected static function parseInline(string $text): array
    {
        $nodes = [];
        $pos = 0;
        $len = strlen($text);

        while ($pos < $len) {
            // Inline code
            if ($text[$pos] === '`') {
                $end = strpos($text, '`', $pos + 1);
                if ($end !== false) {
                    $nodes[] = AstBuilder::inlineCode(substr($text, $pos + 1, $end - $pos - 1));
                    $pos = $end + 1;

                    continue;
                }
            }

            // Bold: **text**
            if ($pos + 1 < $len && $text[$pos] === '*' && $text[$pos + 1] === '*') {
                $end = strpos($text, '**', $pos + 2);
                if ($end !== false) {
                    $inner = substr($text, $pos + 2, $end - $pos - 2);
                    $nodes[] = AstBuilder::strong(self::parseInline($inner));
                    $pos = $end + 2;

                    continue;
                }
            }

            // Strikethrough: ~~text~~
            if ($pos + 1 < $len && $text[$pos] === '~' && $text[$pos + 1] === '~') {
                $end = strpos($text, '~~', $pos + 2);
                if ($end !== false) {
                    $inner = substr($text, $pos + 2, $end - $pos - 2);
                    $nodes[] = AstBuilder::strikethrough(self::parseInline($inner));
                    $pos = $end + 2;

                    continue;
                }
            }

            // Link: [text](url)
            if ($text[$pos] === '[') {
                if (preg_match('/\[([^\]]*)\]\(([^)]*)\)/', $text, $m, 0, $pos)) {
                    $nodes[] = AstBuilder::link($m[2], self::parseInline($m[1]));
                    $pos += strlen($m[0]);

                    continue;
                }
            }

            // Emphasis (italic): _text_ or *text* (single)
            if ($text[$pos] === '_' || ($text[$pos] === '*' && ($pos + 1 >= $len || $text[$pos + 1] !== '*'))) {
                $marker = $text[$pos];
                $end = strpos($text, $marker, $pos + 1);
                if ($end !== false && $end > $pos + 1) {
                    // Make sure it's not ** (already handled above)
                    $inner = substr($text, $pos + 1, $end - $pos - 1);
                    $nodes[] = AstBuilder::emphasis(self::parseInline($inner));
                    $pos = $end + 1;

                    continue;
                }
            }

            // Plain text: collect until next special character
            $next = $len;
            foreach (['**', '~~', '`', '[', '_', '*'] as $marker) {
                $found = strpos($text, $marker, $pos + 1);
                if ($found !== false && $found < $next) {
                    $next = $found;
                }
            }

            $nodes[] = AstBuilder::text(substr($text, $pos, $next - $pos));
            $pos = $next;
        }

        return $nodes;
    }

    /**
     * Stringify an AST back to markdown.
     *
     * @param array<string, mixed> $ast
     */
    public static function stringify(array $ast): string
    {
        return self::stringifyNode($ast);
    }

    /** @param array<string, mixed> $node */
    protected static function stringifyNode(array $node): string
    {
        $type = $node['type'] ?? '';

        return match ($type) {
            'root' => self::stringifyChildren($node, "\n\n"),
            'paragraph' => self::stringifyChildren($node),
            'text' => $node['value'] ?? '',
            'strong' => '**'.self::stringifyChildren($node).'**',
            'emphasis' => '_'.self::stringifyChildren($node).'_',
            'delete' => '~~'.self::stringifyChildren($node).'~~',
            'inlineCode' => '`'.($node['value'] ?? '').'`',
            'code' => '```'.($node['lang'] ?? '')."\n".($node['value'] ?? '')."\n".'```',
            'link' => '['.self::stringifyChildren($node).']('.($node['url'] ?? '').')',
            'blockquote' => self::stringifyBlockquote($node),
            'list' => self::stringifyList($node),
            'listItem' => self::stringifyChildren($node),
            default => self::stringifyChildren($node),
        };
    }

    /** @param array<string, mixed> $node */
    protected static function stringifyChildren(array $node, string $separator = ''): string
    {
        $children = $node['children'] ?? [];
        $parts = array_map(fn ($child) => self::stringifyNode($child), $children);

        return implode($separator, $parts);
    }

    /** @param array<string, mixed> $node */
    protected static function stringifyBlockquote(array $node): string
    {
        $inner = self::stringifyChildren($node, "\n");
        $lines = explode("\n", $inner);

        return implode("\n", array_map(fn ($l) => '> '.$l, $lines));
    }

    /** @param array<string, mixed> $node */
    protected static function stringifyList(array $node): string
    {
        $ordered = $node['ordered'] ?? false;
        $items = $node['children'] ?? [];
        $result = [];
        foreach ($items as $i => $item) {
            $prefix = $ordered ? ($i + 1).'. ' : '- ';
            $result[] = $prefix.self::stringifyNode($item);
        }

        return implode("\n", $result);
    }

    /**
     * Convert an AST to plain text (strip all formatting).
     *
     * @param array<string, mixed> $ast
     */
    public static function toPlainText(array $ast): string
    {
        $text = '';

        AstWalker::walk($ast, function (array $node) use (&$text) {
            if (TypeGuards::isTextNode($node)) {
                $text .= $node['value'] ?? '';
            }
        });

        return $text;
    }
}
