<?php

namespace OpenCompany\Chatogrator\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;

class SlackFormatConverter extends BaseFormatConverter
{
    /**
     * Convert standard markdown to Slack mrkdwn.
     */
    public function fromMarkdown(string $markdown): string
    {
        $text = $markdown;

        // Bold: **text** → *text*
        $text = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);

        // Strikethrough: ~~text~~ → ~text~
        $text = preg_replace('/~~(.+?)~~/', '~$1~', $text);

        // Links: [text](url) → <url|text>
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<$2|$1>', $text);

        // Bare @mentions → <@mention> (don't double-wrap existing <@...>)
        $text = preg_replace('/(?<!<)@(\w+)/', '<@$1>', $text);

        return $text;
    }

    /**
     * Convert Slack mrkdwn to standard markdown.
     */
    public function toMarkdown(string $platformText): string
    {
        $text = $platformText;

        // User mentions with labels: <@U123|john> → @john
        $text = preg_replace('/<@(\w+)\|([^>]+)>/', '@$2', $text);
        // User mentions without labels: <@U123> → @U123
        $text = preg_replace('/<@(\w+)>/', '@$1', $text);

        // Channel mentions: <#C123|general> → #general
        $text = preg_replace('/<#(\w+)\|([^>]+)>/', '#$2', $text);
        $text = preg_replace('/<#(\w+)>/', '#$1', $text);

        // Links with text: <url|text> → [text](url)
        $text = preg_replace('/<([^>@#|]+)\|([^>]+)>/', '[$2]($1)', $text);

        // Bare links: <url> → url
        $text = preg_replace('/<([^>@#]+)>/', '$1', $text);

        // Bold: *text* → **text** (single * only, not **)
        $text = preg_replace('/(?<!\*)\*(?!\*)([^*]+?)\*(?!\*)/', '**$1**', $text);

        // Strikethrough: ~text~ → ~~text~~
        $text = preg_replace('/(?<!~)~(?!~)([^~]+?)~(?!~)/', '~~$1~~', $text);

        return $text;
    }

    /**
     * Strip all Slack mrkdwn formatting to plain text.
     */
    public function toPlainText(string $platformText): string
    {
        $text = $platformText;

        // User mentions with labels: <@U123|user> → @user
        $text = preg_replace('/<@(\w+)\|([^>]+)>/', '@$2', $text);
        // User mentions without labels: <@U123> → @U123
        $text = preg_replace('/<@(\w+)>/', '@$1', $text);

        // Channel mentions with labels: <#C123|general> → #general
        $text = preg_replace('/<#(\w+)\|([^>]+)>/', '#$2', $text);
        $text = preg_replace('/<#(\w+)>/', '#$1', $text);

        // Links with text: <url|text> → text
        $text = preg_replace('/<([^>|]+)\|([^>]+)>/', '$2', $text);

        // Bare links: <url> → url
        $text = preg_replace('/<([^>]+)>/', '$1', $text);

        // Bold: *text* → text
        $text = preg_replace('/(?<!\*)\*(?!\*)([^*]+?)\*(?!\*)/', '$1', $text);

        // Italic: _text_ → text
        $text = preg_replace('/(?<!_)_(?!_)([^_]+?)_(?!_)/', '$1', $text);

        // Strikethrough: ~text~ → text
        $text = preg_replace('/(?<!~)~(?!~)([^~]+?)~(?!~)/', '$1', $text);

        return $text;
    }

    protected function renderCard(Card $card): mixed
    {
        return (new SlackCardRenderer)->render($card);
    }
}
