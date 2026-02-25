<?php

namespace OpenCompany\Chatogrator\Adapters\Concerns;

trait ConvertsMarkdown
{
    /**
     * Strip all markdown formatting, leaving plain text.
     */
    protected function stripMarkdown(string $markdown): string
    {
        // Code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $markdown);
        // Inline code
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        // Strikethrough
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);
        // Italic
        $text = preg_replace('/[_*](.+?)[_*]/', '$1', $text);
        // Links
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        // Blockquote markers
        $text = preg_replace('/^>\s*/m', '', $text);

        return trim($text);
    }

    /**
     * Replace markdown links [text](url) with a callable transform.
     */
    protected function replaceMarkdownLinks(string $markdown, callable $transform): string
    {
        return preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) use ($transform) {
            return $transform($matches[1], $matches[2]);
        }, $markdown);
    }
}
