<?php

namespace OpenCompany\Chatogrator\Adapters\Telegram;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;

class TelegramCardRenderer
{
    /**
     * Render a Card to Telegram format: ['text' => HTML, 'reply_markup' => InlineKeyboardMarkup].
     *
     * @return array<string, mixed>
     */
    public function render(Card $card): array
    {
        $lines = [];
        $keyboardRows = [];

        // Title
        $lines[] = '<b>'.TelegramFormatConverter::escapeHtml($card->getTitle()).'</b>';

        // Subtitle
        if ($card->getSubtitle()) {
            $lines[] = '<i>'.TelegramFormatConverter::escapeHtml($card->getSubtitle()).'</i>';
        }

        // Image — include as link
        if ($card->getImageUrl()) {
            $lines[] = '<a href="'.TelegramFormatConverter::escapeHtml($card->getImageUrl()).'">&#8205;</a>';
        }

        // Process card elements
        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $this->renderSection($element['elements'], $lines),
                'divider' => $lines[] = '———',
                'fields' => $this->renderFields($element['fields'], $lines),
                'actions' => $this->renderActions($element['actions'], $keyboardRows),
                default => null,
            };
        }

        $result = ['text' => implode("\n", $lines)];

        if (! empty($keyboardRows)) {
            $result['reply_markup'] = ['inline_keyboard' => $keyboardRows];
        }

        return $result;
    }

    /**
     * @param list<mixed> $children
     * @param list<string> $lines
     */
    protected function renderSection(array $children, array &$lines): void
    {
        foreach ($children as $child) {
            if ($child instanceof Text) {
                $content = TelegramFormatConverter::escapeHtml($child->content);

                if ($child->style === 'bold') {
                    $lines[] = '<b>'.$content.'</b>';
                } elseif ($child->style === 'muted') {
                    $lines[] = '<i>'.$content.'</i>';
                } else {
                    $lines[] = $content;
                }
            } elseif ($child instanceof Image) {
                $alt = $child->alt ?? 'Image';
                $lines[] = '<a href="'.TelegramFormatConverter::escapeHtml($child->url).'">'.TelegramFormatConverter::escapeHtml($alt).'</a>';
            } elseif ($child instanceof Divider) {
                $lines[] = '———';
            }
        }
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $lines
     */
    protected function renderFields(array $fields, array &$lines): void
    {
        foreach ($fields as $label => $value) {
            $lines[] = '<b>'.TelegramFormatConverter::escapeHtml($label).':</b> '.TelegramFormatConverter::escapeHtml($value);
        }
    }

    /**
     * @param list<mixed> $actions
     * @param list<list<array<string, mixed>>> $keyboardRows
     */
    protected function renderActions(array $actions, array &$keyboardRows): void
    {
        $row = [];

        foreach ($actions as $action) {
            if ($action instanceof Button) {
                // Telegram limits callback_data to 64 bytes
                $callbackData = mb_strcut($action->actionId, 0, 64);

                $row[] = [
                    'text' => $action->label,
                    'callback_data' => $callbackData,
                ];
            } elseif ($action instanceof LinkButton) {
                $row[] = [
                    'text' => $action->label,
                    'url' => $action->url,
                ];
            } elseif ($action instanceof Select || $action instanceof RadioSelect) {
                // Flush current row
                if (! empty($row)) {
                    $keyboardRows[] = $row;
                    $row = [];
                }

                // Expand options as individual button rows
                foreach ($action->getOptions() as $option) {
                    $callbackData = $action->actionId.':'.$option->value;
                    $callbackData = mb_strcut($callbackData, 0, 64);

                    $keyboardRows[] = [[
                        'text' => $option->label,
                        'callback_data' => $callbackData,
                    ]];
                }
            }
        }

        if (! empty($row)) {
            $keyboardRows[] = $row;
        }
    }

    public function toFallbackText(Card $card): string
    {
        return $card->toFallbackText();
    }
}
