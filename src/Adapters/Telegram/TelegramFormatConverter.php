<?php

namespace OpenCompany\Chatogrator\Adapters\Telegram;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;

class TelegramFormatConverter extends BaseFormatConverter
{
    /**
     * Convert standard markdown to Telegram HTML.
     */
    public function fromMarkdown(string $markdown): string
    {
        $text = $markdown;

        // Extract fenced code blocks first to protect them from other transforms
        $codeBlocks = [];
        $text = preg_replace_callback('/```(?:\w+)?\n(.*?)```/s', function ($match) use (&$codeBlocks) {
            $placeholder = "\x00CODEBLOCK".count($codeBlocks)."\x00";
            $codeBlocks[] = '<pre>'.self::escapeHtml($match[1]).'</pre>';

            return $placeholder;
        }, $text);

        // Convert markdown tables to pre-formatted monospace blocks
        $text = preg_replace_callback('/^(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/m', function ($match) use (&$codeBlocks) {
            $placeholder = "\x00CODEBLOCK".count($codeBlocks)."\x00";
            $codeBlocks[] = '<pre>'.self::escapeHtml(self::formatTable($match[0])).'</pre>';

            return $placeholder;
        }, $text);

        // Extract inline code to protect it
        $inlineCode = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($match) use (&$inlineCode) {
            $placeholder = "\x00INLINECODE".count($inlineCode)."\x00";
            $inlineCode[] = '<code>'.self::escapeHtml($match[1]).'</code>';

            return $placeholder;
        }, $text);

        // Escape HTML entities in remaining text
        $text = self::escapeHtml($text);

        // Headings: # heading → <b>heading</b>
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $text);

        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $text);
        $text = preg_replace('/__(.+?)__/', '<b>$1</b>', $text);

        // Italic: *text* or _text_ (but not inside bold markers)
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/', '<i>$1</i>', $text);
        $text = preg_replace('/(?<!_)_(?!_)(.+?)_(?!_)/', '<i>$1</i>', $text);

        // Strikethrough: ~~text~~
        $text = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $text);

        // Links: [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);

        // Blockquotes: > text (multi-line support)
        $text = preg_replace_callback('/(?:^|\n)(&gt;\s?.+(?:\n&gt;\s?.+)*)/', function ($match) {
            $content = preg_replace('/^&gt;\s?/m', '', $match[1]);

            return "\n<blockquote>".$content.'</blockquote>';
        }, $text);

        // Restore inline code
        foreach ($inlineCode as $i => $code) {
            $text = str_replace("\x00INLINECODE{$i}\x00", $code, $text);
        }

        // Restore code blocks
        foreach ($codeBlocks as $i => $block) {
            $text = str_replace("\x00CODEBLOCK{$i}\x00", $block, $text);
        }

        return trim($text);
    }

    /**
     * Convert Telegram HTML to standard markdown.
     */
    public function toMarkdown(string $platformText): string
    {
        $text = $platformText;

        // Extract <pre> blocks first
        $preBlocks = [];
        $text = preg_replace_callback('/<pre>(.*?)<\/pre>/s', function ($match) use (&$preBlocks) {
            $placeholder = "\x{F0000}PREBLOCK".count($preBlocks)."\x{F0000}";
            $preBlocks[] = "```\n".self::unescapeHtml($match[1])."\n```";

            return $placeholder;
        }, $text);

        // Extract <code> first
        $codeBlocks = [];
        $text = preg_replace_callback('/<code>(.*?)<\/code>/', function ($match) use (&$codeBlocks) {
            $placeholder = "\x{F0000}CODE".count($codeBlocks)."\x{F0000}";
            $codeBlocks[] = '`'.self::unescapeHtml($match[1]).'`';

            return $placeholder;
        }, $text);

        // Bold: <b>text</b> or <strong>text</strong>
        $text = preg_replace('/<b>(.*?)<\/b>/s', '**$1**', $text);
        $text = preg_replace('/<strong>(.*?)<\/strong>/s', '**$1**', $text);

        // Italic: <i>text</i> or <em>text</em>
        $text = preg_replace('/<i>(.*?)<\/i>/s', '*$1*', $text);
        $text = preg_replace('/<em>(.*?)<\/em>/s', '*$1*', $text);

        // Strikethrough: <s>text</s> or <del>text</del>
        $text = preg_replace('/<s>(.*?)<\/s>/s', '~~$1~~', $text);
        $text = preg_replace('/<del>(.*?)<\/del>/s', '~~$1~~', $text);

        // Underline: <u>text</u> → just strip (no markdown equivalent)
        $text = preg_replace('/<u>(.*?)<\/u>/s', '$1', $text);

        // Links: <a href="url">text</a>
        $text = preg_replace('/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/s', '[$2]($1)', $text);

        // Blockquotes: <blockquote>text</blockquote>
        $text = preg_replace_callback('/<blockquote>(.*?)<\/blockquote>/s', function ($match) {
            $lines = explode("\n", trim($match[1]));

            return implode("\n", array_map(fn ($line) => '> '.$line, $lines));
        }, $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Unescape HTML entities
        $text = self::unescapeHtml($text);

        // Restore code
        foreach ($codeBlocks as $i => $code) {
            $text = str_replace("\x{F0000}CODE{$i}\x{F0000}", $code, $text);
        }

        // Restore pre blocks
        foreach ($preBlocks as $i => $block) {
            $text = str_replace("\x{F0000}PREBLOCK{$i}\x{F0000}", $block, $text);
        }

        return trim($text);
    }

    /**
     * Strip all formatting to plain text.
     */
    public function toPlainText(string $platformText): string
    {
        $text = $platformText;

        // Strip all HTML tags
        $text = strip_tags($text);

        // Unescape HTML entities
        $text = self::unescapeHtml($text);

        return trim($text);
    }

    protected function renderCard(Card $card): mixed
    {
        return (new TelegramCardRenderer)->render($card);
    }

    /**
     * Escape special HTML characters.
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Unescape HTML entities back to plain text.
     */
    public static function unescapeHtml(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format a markdown table into aligned plain-text columns.
     */
    private static function formatTable(string $tableMarkdown): string
    {
        $lines = array_filter(explode("\n", trim($tableMarkdown)), fn ($l) => trim($l) !== '');
        $rows = [];

        foreach ($lines as $line) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            // Skip separator rows (----, :---:, etc.)
            if (preg_match('/^[-: ]+$/', $cells[0])) {
                continue;
            }
            $cells = array_map([self::class, 'stripMarkdown'], $cells);
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return $tableMarkdown;
        }

        // Calculate column widths using visual width (handles emoji/CJK)
        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, self::visualWidth($cell));
            }
        }

        // Build aligned output
        $output = [];
        foreach ($rows as $ri => $row) {
            $parts = [];
            foreach ($row as $i => $cell) {
                $parts[] = self::visualPad($cell, $colWidths[$i] ?? 0);
            }
            $output[] = implode('  ', $parts);
            // Add separator after header
            if ($ri === 0) {
                $sep = [];
                foreach ($colWidths as $w) {
                    $sep[] = str_repeat('-', $w);
                }
                $output[] = implode('  ', $sep);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Strip markdown formatting markers from text.
     */
    private static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);
        $text = preg_replace('/(?<!\w)\*([^*]+?)\*(?!\w)/', '$1', $text);
        $text = preg_replace('/(?<!\w)_([^_]+?)_(?!\w)/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);

        return $text;
    }

    /**
     * Calculate visual width of a string, accounting for double-width characters.
     */
    private static function visualWidth(string $text): int
    {
        $width = mb_strwidth($text, 'UTF-8');

        // Emoji ranges that mb_strwidth may miss
        preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}]/u', $text, $emoji);
        $width += count($emoji[0]);

        return $width;
    }

    /**
     * Pad a string to a target visual width.
     */
    private static function visualPad(string $text, int $targetWidth): string
    {
        $currentWidth = self::visualWidth($text);
        $padding = $targetWidth - $currentWidth;

        return $padding > 0 ? $text.str_repeat(' ', $padding) : $text;
    }
}
