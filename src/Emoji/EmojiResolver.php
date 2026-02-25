<?php

namespace OpenCompany\Chatogrator\Emoji;

class EmojiResolver
{
    /**
     * Resolve {{emoji:name}} placeholders in text for a given platform.
     */
    public static function resolve(string $text, string $platform): string
    {
        return preg_replace_callback('/\{\{emoji:(\w+)\}\}/', function ($matches) use ($platform) {
            $emojiName = $matches[1];

            return match ($platform) {
                'slack' => ':'.Emoji::toSlack($emojiName).':',
                'discord' => Emoji::toDiscord($emojiName),
                'telegram' => Emoji::toTelegram($emojiName),
                default => Emoji::toUnicode($emojiName),
            };
        }, $text);
    }
}
