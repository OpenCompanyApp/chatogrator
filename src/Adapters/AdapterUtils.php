<?php

namespace OpenCompany\Chatogrator\Adapters;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Messages\PostableMessage;

class AdapterUtils
{
    public static function extractCard(?PostableMessage $message): ?Card
    {
        if ($message === null) {
            return null;
        }

        return $message->getCard();
    }

    /**
     * @return array<int, mixed>
     */
    public static function extractFiles(?PostableMessage $message): array
    {
        if ($message === null) {
            return [];
        }

        return $message->getFiles();
    }

    public static function extractFallbackText(?PostableMessage $message): string
    {
        if ($message === null) {
            return '';
        }

        // Text content
        if ($message->getText() !== null) {
            return $message->getText();
        }

        // Raw content
        if ($message->getRaw() !== null) {
            return is_string($message->getRaw()) ? $message->getRaw() : '';
        }

        // Markdown content
        if ($message->getMarkdown() !== null) {
            // Strip markdown formatting for fallback
            $text = $message->getMarkdown();
            $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
            $text = preg_replace('/\*(.+?)\*/', '$1', $text);
            $text = preg_replace('/~~(.+?)~~/', '$1', $text);
            $text = preg_replace('/`(.+?)`/', '$1', $text);

            return $text;
        }

        // Card content
        if ($message->getCard() !== null) {
            return $message->getCard()->toFallbackText();
        }

        return '';
    }
}
