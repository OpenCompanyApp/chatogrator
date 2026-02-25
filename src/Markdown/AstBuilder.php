<?php

namespace OpenCompany\Chatogrator\Markdown;

class AstBuilder
{
    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function root(array $children): array
    {
        return ['type' => 'root', 'children' => $children];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function paragraph(array $children): array
    {
        return ['type' => 'paragraph', 'children' => $children];
    }

    /** @return array<string, mixed> */
    public static function text(string $value): array
    {
        return ['type' => 'text', 'value' => $value];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function strong(array $children): array
    {
        return ['type' => 'strong', 'children' => $children];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function emphasis(array $children): array
    {
        return ['type' => 'emphasis', 'children' => $children];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function strikethrough(array $children): array
    {
        return ['type' => 'delete', 'children' => $children];
    }

    /** @return array<string, mixed> */
    public static function inlineCode(string $value): array
    {
        return ['type' => 'inlineCode', 'value' => $value];
    }

    /** @return array<string, mixed> */
    public static function codeBlock(string $value, ?string $lang = null): array
    {
        return ['type' => 'code', 'value' => $value, 'lang' => $lang];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function link(string $url, array $children, ?string $title = null): array
    {
        return ['type' => 'link', 'url' => $url, 'children' => $children, 'title' => $title];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function blockquote(array $children): array
    {
        return ['type' => 'blockquote', 'children' => $children];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function list(array $children, bool $ordered = false): array
    {
        return ['type' => 'list', 'children' => $children, 'ordered' => $ordered];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function listItem(array $children): array
    {
        return ['type' => 'listItem', 'children' => $children];
    }
}
