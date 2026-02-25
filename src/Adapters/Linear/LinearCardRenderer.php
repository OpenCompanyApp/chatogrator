<?php

namespace OpenCompany\Chatogrator\Adapters\Linear;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;

class LinearCardRenderer
{
    public function render(Card $card): string
    {
        // Build header block (title + subtitle joined by single newline)
        $headerLines = [];
        $title = $card->getTitle();
        if ($title !== '') {
            $headerLines[] = "**{$title}**";
        }
        if ($card->getSubtitle()) {
            $headerLines[] = $card->getSubtitle();
        }

        $parts = [];
        if (! empty($headerLines)) {
            $parts[] = implode("\n", $headerLines);
        }

        // Image URL (card-level)
        if ($card->getImageUrl()) {
            $parts[] = '!['.$card->getTitle().']('.$card->getImageUrl().')';
        }

        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $parts = array_merge($parts, $this->renderSectionChildren($element['elements'])),
                'divider' => $parts[] = '---',
                'fields' => $parts[] = $this->renderFields($element['fields']),
                'actions' => $parts[] = $this->renderActions($element['actions']),
                default => null,
            };
        }

        return implode("\n\n", array_filter($parts, fn ($p) => $p !== ''));
    }

    /**
     * @param list<mixed> $children
     * @return list<string>
     */
    protected function renderSectionChildren(array $children): array
    {
        $blocks = [];

        foreach ($children as $child) {
            if ($child instanceof Text) {
                if ($child->style === 'bold') {
                    $blocks[] = '**'.$child->content.'**';
                } elseif ($child->style === 'muted') {
                    $blocks[] = '_'.$child->content.'_';
                } else {
                    $blocks[] = $child->content;
                }
            } elseif ($child instanceof Image) {
                $alt = $child->alt ?? '';
                $blocks[] = "![{$alt}]({$child->url})";
            } elseif ($child instanceof Divider) {
                $blocks[] = '---';
            }
        }

        return $blocks;
    }

    /** @param array<string, string> $fields */
    protected function renderFields(array $fields): string
    {
        $lines = [];

        foreach ($fields as $label => $value) {
            $lines[] = "**{$label}:** {$value}";
        }

        return implode("\n", $lines);
    }

    /** @param list<mixed> $actions */
    protected function renderActions(array $actions): string
    {
        $items = [];

        foreach ($actions as $action) {
            if ($action instanceof LinkButton) {
                $items[] = "[{$action->label}]({$action->url})";
            } elseif ($action instanceof Button) {
                $items[] = "**[{$action->label}]**";
            }
        }

        return implode(' | ', $items);
    }
}
