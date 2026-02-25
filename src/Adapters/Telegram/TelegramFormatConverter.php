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
}
