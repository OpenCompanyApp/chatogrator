<?php

namespace OpenCompany\Chatogrator\Adapters\Teams;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Messages\PostableMessage;

class TeamsFormatConverter extends BaseFormatConverter
{
    /**
     * Convert Teams HTML/text to standard markdown.
     *
     * Teams sends messages as HTML with <at>Name</at> mentions
     * and standard HTML tags for formatting.
     */
    public function toMarkdown(string $platformText): string
    {
        if ($platformText === '') {
            return '';
        }

        $text = $platformText;

        // Convert <at>Name</at> mentions to @Name
        $text = preg_replace('/<at>(.+?)<\/at>/', '@$1', $text);

        // Convert HTML formatting tags to markdown
        // Bold: <b> and <strong>
        $text = preg_replace('/<b>(.*?)<\/b>/s', '**$1**', $text);
        $text = preg_replace('/<strong>(.*?)<\/strong>/s', '**$1**', $text);

        // Italic: <i> and <em>
        $text = preg_replace('/<i>(.*?)<\/i>/s', '_$1_', $text);
        $text = preg_replace('/<em>(.*?)<\/em>/s', '_$1_', $text);

        // Strikethrough: <s>
        $text = preg_replace('/<s>(.*?)<\/s>/s', '~~$1~~', $text);

        // Links: <a href="url">text</a>
        $text = preg_replace('/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/s', '[$2]($1)', $text);

        // Inline code: <code>
        $text = preg_replace('/<code>(.*?)<\/code>/s', '`$1`', $text);

        // Code blocks: <pre>
        $text = preg_replace('/<pre>(.*?)<\/pre>/s', "```\n$1\n```", $text);

        // Strip any remaining HTML tags first (before entity decoding)
        $text = strip_tags($text);

        // Decode HTML entities after stripping tags so &lt;b&gt; becomes literal <b>
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * Convert standard markdown to Teams format.
     *
     * Teams supports a subset of markdown. Most markdown passes through.
     * @mentions are converted to <at>name</at> format.
     */
    public function fromMarkdown(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        $text = $markdown;

        // Convert @mentions to <at>name</at> format
        // Match @word but not inside existing <at> tags
        $text = preg_replace('/(?<!<at>)@(\w+)/', '<at>$1</at>', $text);

        return $text;
    }

    /**
     * Render a PostableMessage to Teams-native format.
     */
    public function renderPostable(PostableMessage $message): mixed
    {
        if ($message->getCard()) {
            return $this->renderCard($message->getCard());
        }

        if ($message->getMarkdown()) {
            return $this->fromMarkdown($message->getMarkdown());
        }

        // Handle raw messages - convert @mentions
        if ($message->getRaw() !== null) {
            $raw = $message->getRaw();
            if (is_string($raw)) {
                return $this->fromMarkdown($raw);
            }

            return $raw;
        }

        $text = $message->getText();
        if ($text === null) {
            return '';
        }

        // Convert @mentions in plain text too
        return $this->fromMarkdown($text);
    }

    protected function renderCard(Card $card): mixed
    {
        return (new TeamsCardRenderer)->render($card);
    }
}
