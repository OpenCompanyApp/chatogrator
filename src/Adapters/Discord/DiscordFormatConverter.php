<?php

namespace OpenCompany\Chatogrator\Adapters\Discord;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Messages\PostableMessage;

class DiscordFormatConverter extends BaseFormatConverter
{
    /**
     * Convert Discord-flavored markdown to standard markdown.
     *
     * Discord uses standard markdown with some additions:
     * - User mentions: <@123456789> or <@!123456789>
     * - Channel mentions: <#987654321>
     * - Role mentions: <@&111222333>
     * - Custom emoji: <:name:id> or <a:name:id>
     * - Spoiler tags: ||hidden text||
     * - Timestamps: <t:unix:format>
     */
    public function toMarkdown(string $platformText): string
    {
        if ($platformText === '') {
            return '';
        }

        $text = $platformText;

        // User mentions with nickname marker: <@!123456789> -> @123456789
        $text = preg_replace('/<@!(\w+)>/', '@$1', $text);

        // User mentions: <@123456789> -> @123456789
        $text = preg_replace('/<@(\w+)>/', '@$1', $text);

        // Channel mentions: <#987654321> -> #987654321
        $text = preg_replace('/<#(\w+)>/', '#$1', $text);

        // Role mentions: <@&111222333> -> @&111222333
        $text = preg_replace('/<@&(\w+)>/', '@&$1', $text);

        // Animated custom emoji: <a:name:id> -> :name:
        $text = preg_replace('/<a:(\w+):\d+>/', ':$1:', $text);

        // Custom emoji: <:name:id> -> :name:
        $text = preg_replace('/<:(\w+):\d+>/', ':$1:', $text);

        // Spoiler tags: ||text|| -> text (strip for markdown)
        $text = preg_replace('/\|\|(.+?)\|\|/', '$1', $text);

        // Discord timestamps: <t:unix:format> -> (timestamp)
        $text = preg_replace('/<t:\d+(?::[tTdDfFR])?>/', '(timestamp)', $text);

        return $text;
    }

    /**
     * Convert standard markdown to Discord-flavored markdown.
     *
     * Discord natively supports most standard markdown, so this is mostly
     * a pass-through with mention conversion.
     */
    public function fromMarkdown(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        $text = $markdown;

        // Convert bare @mentions to Discord format: @someone -> <@someone>
        // Don't double-wrap existing <@...> mentions
        $text = preg_replace('/(?<!<)@(\w+)/', '<@$1>', $text);

        return $text;
    }

    /**
     * Extract plain text from Discord markdown, stripping all formatting.
     */
    public function toPlainText(string $platformText): string
    {
        // First convert Discord-specific syntax to markdown
        $text = $this->toMarkdown($platformText);

        // Then strip markdown formatting
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        return $text;
    }

    /**
     * Render a PostableMessage to Discord-native format.
     *
     * Overrides the base to ensure mention conversion happens for all message types.
     */
    public function renderPostable(PostableMessage $message): mixed
    {
        if ($message->getCard()) {
            return $this->renderCard($message->getCard());
        }

        if ($message->getMarkdown()) {
            return $this->fromMarkdown($message->getMarkdown());
        }

        if ($message->getRaw() !== null) {
            $raw = $message->getRaw();
            if (is_string($raw)) {
                return $this->fromMarkdown($raw);
            }

            return (string) $raw;
        }

        $text = $message->getText() ?? '';
        if ($text !== '') {
            return $this->fromMarkdown($text);
        }

        return $text;
    }

    protected function renderCard(Card $card): mixed
    {
        return (new DiscordCardRenderer)->render($card);
    }
}
