<?php

namespace OpenCompany\Chatogrator\Adapters\Discord;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;

class DiscordCardRenderer
{
    /**
     * Discord blurple color as integer: 0x5865F2 = 5793266
     */
    private const DISCORD_BLURPLE = 0x5865F2;

    /**
     * Discord button styles.
     */
    private const BUTTON_STYLE_PRIMARY = 1;

    private const BUTTON_STYLE_SECONDARY = 2;

    private const BUTTON_STYLE_DANGER = 4;

    private const BUTTON_STYLE_LINK = 5;

    /**
     * Discord component types.
     */
    private const COMPONENT_ACTION_ROW = 1;

    private const COMPONENT_BUTTON = 2;

    /**
     * Render a Card to Discord embeds + components payload.
     *
     * @return array<string, mixed>
     */
    public function render(Card $card): array
    {
        $embed = [
            'color' => self::DISCORD_BLURPLE,
        ];

        $title = $card->getTitle();
        if ($title !== '') {
            $embed['title'] = $title;
        }

        // Build description from subtitle + section content
        $descriptionParts = [];

        if ($card->getSubtitle()) {
            $descriptionParts[] = $card->getSubtitle();
        }

        if ($card->getImageUrl()) {
            $embed['image'] = ['url' => $card->getImageUrl()];
        }

        $components = [];
        $fields = [];

        foreach ($card->getElements() as $element) {
            switch ($element['type']) {
                case 'section':
                    $descriptionParts = array_merge(
                        $descriptionParts,
                        $this->renderSectionToDescription($element['elements'])
                    );
                    break;

                case 'divider':
                    $descriptionParts[] = '───────────────────────';
                    break;

                case 'fields':
                    foreach ($element['fields'] as $label => $value) {
                        $fields[] = [
                            'name' => $label,
                            'value' => $value,
                            'inline' => true,
                        ];
                    }
                    break;

                case 'actions':
                    $actionRow = $this->renderActions($element['actions']);
                    if (! empty($actionRow)) {
                        $components[] = $actionRow;
                    }
                    break;
            }
        }

        if (! empty($descriptionParts)) {
            $embed['description'] = implode("\n", $descriptionParts);
        }

        if (! empty($fields)) {
            $embed['fields'] = $fields;
        }

        $payload = [
            'embeds' => [$embed],
        ];

        if (! empty($components)) {
            $payload['components'] = $components;
        }

        return $payload;
    }

    /**
     * Convert section children to description text parts.
     *
     * @param list<mixed> $children
     * @return list<string>
     */
    protected function renderSectionToDescription(array $children): array
    {
        $parts = [];

        foreach ($children as $child) {
            if ($child instanceof Text) {
                if ($child->style === 'bold') {
                    $parts[] = '**' . $child->content . '**';
                } elseif ($child->style === 'muted') {
                    $parts[] = '*' . $child->content . '*';
                } else {
                    $parts[] = $child->content;
                }
            } elseif ($child instanceof Image) {
                // Images in sections are handled as part of the embed
                // We don't add text for them
            } elseif ($child instanceof Divider) {
                $parts[] = '───────────────────────';
            }
        }

        return $parts;
    }

    /**
     * Render action elements to a Discord action row.
     *
     * @param list<mixed> $actions
     * @return array<string, mixed>
     */
    protected function renderActions(array $actions): array
    {
        $buttons = [];

        foreach ($actions as $action) {
            if ($action instanceof Button) {
                $style = match ($action->getStyle()) {
                    'primary' => self::BUTTON_STYLE_PRIMARY,
                    'danger' => self::BUTTON_STYLE_DANGER,
                    default => self::BUTTON_STYLE_SECONDARY,
                };

                $buttons[] = [
                    'type' => self::COMPONENT_BUTTON,
                    'style' => $style,
                    'label' => $action->label,
                    'custom_id' => $action->actionId,
                ];
            } elseif ($action instanceof LinkButton) {
                $buttons[] = [
                    'type' => self::COMPONENT_BUTTON,
                    'style' => self::BUTTON_STYLE_LINK,
                    'label' => $action->label,
                    'url' => $action->url,
                ];
            }
        }

        if (empty($buttons)) {
            return [];
        }

        return [
            'type' => self::COMPONENT_ACTION_ROW,
            'components' => $buttons,
        ];
    }

    /**
     * Generate fallback plain-text representation of a card.
     */
    public function fallbackText(Card $card): string
    {
        $parts = [];

        $title = $card->getTitle();
        if ($title !== '') {
            $parts[] = $title;
        }

        if ($card->getSubtitle()) {
            $parts[] = $card->getSubtitle();
        }

        foreach ($card->getElements() as $element) {
            switch ($element['type']) {
                case 'section':
                    foreach ($element['elements'] as $child) {
                        if ($child instanceof Text) {
                            $parts[] = $child->content;
                        }
                    }
                    break;

                case 'divider':
                    $parts[] = '---';
                    break;

                case 'fields':
                    foreach ($element['fields'] as $label => $value) {
                        $parts[] = "{$label}: {$value}";
                    }
                    break;
            }
        }

        return implode("\n", $parts);
    }
}
