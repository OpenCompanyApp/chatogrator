<?php

namespace OpenCompany\Chatogrator\Markdown;

class TypeGuards
{
    /** @param array<string, mixed> $node */
    public static function isTextNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'text';
    }

    /** @param array<string, mixed> $node */
    public static function isParagraphNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'paragraph';
    }

    /** @param array<string, mixed> $node */
    public static function isStrongNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'strong';
    }

    /** @param array<string, mixed> $node */
    public static function isEmphasisNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'emphasis';
    }

    /** @param array<string, mixed> $node */
    public static function isDeleteNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'delete';
    }

    /** @param array<string, mixed> $node */
    public static function isInlineCodeNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'inlineCode';
    }

    /** @param array<string, mixed> $node */
    public static function isCodeNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'code';
    }

    /** @param array<string, mixed> $node */
    public static function isLinkNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'link';
    }

    /** @param array<string, mixed> $node */
    public static function isBlockquoteNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'blockquote';
    }

    /** @param array<string, mixed> $node */
    public static function isListNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'list';
    }

    /** @param array<string, mixed> $node */
    public static function isListItemNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'listItem';
    }

    /** @param array<string, mixed> $node */
    public static function isRootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'root';
    }
}
