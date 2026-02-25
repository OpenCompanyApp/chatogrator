<?php

namespace OpenCompany\Chatogrator\Adapters\Teams;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;

class TeamsCardRenderer
{
    /**
     * Render a Card to an Adaptive Card JSON structure.
     *
     * @return array<string, mixed>
     */
    public function render(Card $card): array
    {
        $body = [];
        $actions = [];

        // Title
        if ($card->getTitle() !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'text' => $card->getTitle(),
                'weight' => 'bolder',
                'size' => 'large',
                'wrap' => true,
            ];
        }

        // Subtitle
        if ($card->getSubtitle()) {
            $body[] = [
                'type' => 'TextBlock',
                'text' => $card->getSubtitle(),
                'isSubtle' => true,
                'wrap' => true,
            ];
        }

        // Header image
        if ($card->getImageUrl()) {
            $body[] = [
                'type' => 'Image',
                'url' => $card->getImageUrl(),
                'size' => 'stretch',
            ];
        }

        // Process elements
        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $body[] = $this->renderSection($element['elements']),
                'divider' => $body[] = $this->renderDivider(),
                'fields' => $body[] = $this->renderFields($element['fields']),
                'actions' => $actions = array_merge($actions, $this->renderActions($element['actions'])),
                default => null,
            };
        }

        $adaptive = [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.4',
            'body' => $body,
        ];

        if (! empty($actions)) {
            $adaptive['actions'] = $actions;
        }

        return $adaptive;
    }

    /**
     * Render section children wrapped in a Container.
     *
     * @param  list<mixed>  $children
     * @return array<string, mixed>
     */
    protected function renderSection(array $children): array
    {
        $items = [];

        foreach ($children as $child) {
            if ($child instanceof Text) {
                $block = [
                    'type' => 'TextBlock',
                    'text' => $child->content,
                    'wrap' => true,
                ];

                if ($child->style === 'bold') {
                    $block['weight'] = 'bolder';
                } elseif ($child->style === 'muted') {
                    $block['isSubtle'] = true;
                }

                $items[] = $block;
            } elseif ($child instanceof Image) {
                $item = [
                    'type' => 'Image',
                    'url' => $child->url,
                ];

                if ($child->alt !== null) {
                    $item['altText'] = $child->alt;
                }

                $items[] = $item;
            } elseif ($child instanceof Divider) {
                $items[] = $this->renderDivider();
            }
        }

        return [
            'type' => 'Container',
            'items' => $items,
        ];
    }

    /**
     * Render a divider as a Container with separator.
     *
     * @return array<string, mixed>
     */
    protected function renderDivider(): array
    {
        return [
            'type' => 'Container',
            'separator' => true,
            'items' => [],
        ];
    }

    /**
     * Render fields as a FactSet.
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    protected function renderFields(array $fields): array
    {
        $facts = [];

        foreach ($fields as $label => $value) {
            $facts[] = [
                'title' => $label,
                'value' => $value,
            ];
        }

        return [
            'type' => 'FactSet',
            'facts' => $facts,
        ];
    }

    /**
     * Render actions (buttons) to Adaptive Card actions.
     *
     * @param  list<mixed>  $actions
     * @return list<array<string, mixed>>
     */
    protected function renderActions(array $actions): array
    {
        $result = [];

        foreach ($actions as $action) {
            if ($action instanceof Button) {
                $btn = [
                    'type' => 'Action.Submit',
                    'title' => $action->label,
                    'data' => [
                        'actionId' => $action->actionId,
                    ],
                ];

                $style = $action->getStyle();
                if ($style === 'primary') {
                    $btn['style'] = 'positive';
                } elseif ($style === 'danger') {
                    $btn['style'] = 'destructive';
                }

                $result[] = $btn;
            } elseif ($action instanceof LinkButton) {
                $result[] = [
                    'type' => 'Action.OpenUrl',
                    'title' => $action->label,
                    'url' => $action->url,
                ];
            }
        }

        return $result;
    }

    /**
     * Generate plain-text fallback for a card.
     */
    public function fallbackText(Card $card): string
    {
        $parts = [];

        if ($card->getTitle() !== '') {
            $parts[] = $card->getTitle();
        }

        if ($card->getSubtitle()) {
            $parts[] = $card->getSubtitle();
        }

        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $this->collectSectionText($element['elements'], $parts),
                'fields' => $this->collectFieldsText($element['fields'], $parts),
                default => null,
            };
        }

        return implode("\n", $parts);
    }

    /**
     * Collect text from section children for fallback.
     *
     * @param  list<mixed>  $children
     * @param  list<string>  $parts
     */
    protected function collectSectionText(array $children, array &$parts): void
    {
        foreach ($children as $child) {
            if ($child instanceof Text) {
                $parts[] = $child->content;
            }
        }
    }

    /**
     * Collect text from fields for fallback.
     *
     * @param  array<string, string>  $fields
     * @param  list<string>  $parts
     */
    protected function collectFieldsText(array $fields, array &$parts): void
    {
        foreach ($fields as $label => $value) {
            $parts[] = "{$label}: {$value}";
        }
    }
}
