<?php

namespace OpenCompany\Chatogrator\Adapters;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Emoji\EmojiResolver;

class CardUtils
{
    /** @var array<string, array<string, string>> */
    protected static array $styleMappings = [
        'slack' => [
            'primary' => 'primary',
            'danger' => 'danger',
        ],
        'teams' => [
            'primary' => 'positive',
            'danger' => 'destructive',
        ],
        'gchat' => [
            'primary' => 'primary',
            'danger' => 'danger',
        ],
        'discord' => [
            'primary' => 'primary',
            'danger' => 'danger',
        ],
        'github' => [
            'primary' => 'primary',
            'danger' => 'danger',
        ],
        'linear' => [
            'primary' => 'primary',
            'danger' => 'danger',
        ],
    ];

    public static function mapButtonStyle(?string $style, string $platform): ?string
    {
        if ($style === null) {
            return null;
        }

        return static::$styleMappings[$platform][$style] ?? null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function buttonStyleMappings(): array
    {
        return static::$styleMappings;
    }

    public static function cardToFallbackText(
        Card $card,
        string $boldFormat = '*',
        string $lineBreak = "\n",
        ?string $platform = null,
    ): string {
        $parts = [];

        $title = $card->getTitle();
        if ($title !== '') {
            $parts[] = $boldFormat.$title.$boldFormat;
        }

        $subtitle = $card->getSubtitle();
        if ($subtitle !== null && $subtitle !== '') {
            $parts[] = $subtitle;
        }

        foreach ($card->getElements() as $element) {
            $type = $element['type'] ?? null;

            if ($type === 'section') {
                foreach ($element['elements'] ?? [] as $child) {
                    if ($child instanceof Text) {
                        $parts[] = $child->content;
                    }
                }
            } elseif ($type === 'fields') {
                foreach ($element['fields'] ?? [] as $label => $value) {
                    $parts[] = "$label: $value";
                }
            } elseif ($type === 'divider') {
                $parts[] = '---';
            }
            // 'actions' are intentionally excluded from fallback text
        }

        $result = implode($lineBreak, $parts);

        // Remove empty bold markers for empty titles
        if ($result === $boldFormat.$boldFormat) {
            return '';
        }

        if ($platform !== null) {
            $result = EmojiResolver::resolve($result, $platform);
        }

        return $result;
    }
}
