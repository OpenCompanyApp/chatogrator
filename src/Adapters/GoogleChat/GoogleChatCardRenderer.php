<?php

namespace OpenCompany\Chatogrator\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;

class GoogleChatCardRenderer
{
    /**
     * Render a Card to Google Chat Card v2 format.
     *
     * @param  array{cardId?: string, endpointUrl?: string}  $options
     * @return array<string, mixed>
     */
    public function render(Card $card, array $options = []): array
    {
        $gchatCard = [];
        $sections = [];

        // Header
        $header = $this->buildHeader($card);
        if (! empty($header)) {
            $gchatCard['header'] = $header;
        }

        // Process card elements into sections
        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $sections[] = $this->renderSection($element['elements']),
                'divider' => $sections[] = ['widgets' => [['divider' => []]]],
                'fields' => $sections[] = $this->renderFields($element['fields']),
                'actions' => $sections[] = $this->renderActions($element['actions'], $options),
                default => null,
            };
        }

        // Google Chat requires at least one section with at least one widget
        if (empty($sections)) {
            $sections[] = ['widgets' => [['textParagraph' => ['text' => ' ']]]];
        }

        $gchatCard['sections'] = $sections;

        $result = ['card' => $gchatCard];

        // Add cardId if provided
        if (isset($options['cardId'])) {
            $result['cardId'] = $options['cardId'];
        }

        return $result;
    }

    /**
     * Generate fallback plain text for a card.
     */
    public function fallbackText(Card $card): string
    {
        $parts = [];

        $title = $card->getTitle();
        if ($title !== '') {
            // Google Chat uses single asterisk for bold
            $parts[] = "*{$title}*";
        }

        if ($card->getSubtitle()) {
            $parts[] = $card->getSubtitle();
        }

        // Collect text from elements
        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $this->collectSectionText($element['elements'], $parts),
                'fields' => $this->collectFieldsText($element['fields'], $parts),
                default => null,
            };
        }

        return implode("\n", $parts);
    }

    /** @return array<string, mixed> */
    protected function buildHeader(Card $card): array
    {
        $header = [];

        $title = $card->getTitle();
        if ($title !== '') {
            $header['title'] = $title;
        }

        if ($card->getSubtitle()) {
            $header['subtitle'] = $card->getSubtitle();
        }

        if ($card->getImageUrl()) {
            $header['imageUrl'] = $card->getImageUrl();
            $header['imageType'] = 'SQUARE';
        }

        return $header;
    }

    /**
     * @param  list<mixed>  $elements
     * @return array<string, mixed>
     */
    protected function renderSection(array $elements): array
    {
        $widgets = [];

        foreach ($elements as $element) {
            if ($element instanceof Text) {
                $content = $this->convertBoldToGChat($element->content);

                if ($element->style === 'bold') {
                    $content = "*{$content}*";
                }

                $widgets[] = ['textParagraph' => ['text' => $content]];
            } elseif ($element instanceof Image) {
                $widget = [
                    'image' => [
                        'imageUrl' => $element->url,
                    ],
                ];
                if ($element->alt !== null) {
                    $widget['image']['altText'] = $element->alt;
                }
                $widgets[] = $widget;
            } elseif ($element instanceof Divider) {
                $widgets[] = ['divider' => []];
            }
        }

        return ['widgets' => $widgets];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    protected function renderFields(array $fields): array
    {
        $widgets = [];

        foreach ($fields as $label => $value) {
            $widgets[] = [
                'decoratedText' => [
                    'topLabel' => $this->convertBoldToGChat((string) $label),
                    'text' => $this->convertBoldToGChat((string) $value),
                ],
            ];
        }

        return ['widgets' => $widgets];
    }

    /**
     * @param  list<mixed>  $actions
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function renderActions(array $actions, array $options = []): array
    {
        $buttons = [];
        $endpointUrl = $options['endpointUrl'] ?? null;

        foreach ($actions as $action) {
            if ($action instanceof Button) {
                $button = [
                    'text' => $action->label,
                    'onClick' => [
                        'action' => [
                            'function' => $endpointUrl ?? $action->actionId,
                            'parameters' => [
                                ['key' => 'actionId', 'value' => $action->actionId],
                            ],
                        ],
                    ],
                ];

                // Add color for primary/danger styles
                $style = $action->getStyle();
                if ($style === 'primary') {
                    $button['color'] = [
                        'red' => 0.2,
                        'green' => 0.5,
                        'blue' => 0.9,
                        'alpha' => 1.0,
                    ];
                } elseif ($style === 'danger') {
                    $button['color'] = [
                        'red' => 0.9,
                        'green' => 0.2,
                        'blue' => 0.2,
                        'alpha' => 1.0,
                    ];
                }

                $buttons[] = $button;
            } elseif ($action instanceof LinkButton) {
                $buttons[] = [
                    'text' => $action->label,
                    'onClick' => [
                        'openLink' => [
                            'url' => $action->url,
                        ],
                    ],
                ];
            }
        }

        return ['widgets' => [['buttonList' => ['buttons' => $buttons]]]];
    }

    /**
     * Convert standard markdown double-asterisk bold to Google Chat single-asterisk bold.
     */
    protected function convertBoldToGChat(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);
    }

    /**
     * @param  list<mixed>  $elements
     * @param  list<string>  $parts
     */
    protected function collectSectionText(array $elements, array &$parts): void
    {
        foreach ($elements as $element) {
            if ($element instanceof Text) {
                $parts[] = $element->content;
            }
        }
    }

    /**
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
