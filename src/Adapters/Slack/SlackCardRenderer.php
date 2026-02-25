<?php

namespace OpenCompany\Chatogrator\Adapters\Slack;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Image;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Cards\Interactive\LinkButton;
use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;

class SlackCardRenderer
{
    /** @return list<array<string, mixed>> */
    public function render(Card $card): array
    {
        $blocks = [];

        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $card->getTitle(),
                'emoji' => true,
            ],
        ];

        if ($card->getSubtitle()) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => $card->getSubtitle()],
                ],
            ];
        }

        if ($card->getImageUrl()) {
            $blocks[] = [
                'type' => 'image',
                'image_url' => $card->getImageUrl(),
                'alt_text' => $card->getTitle(),
            ];
        }

        foreach ($card->getElements() as $element) {
            match ($element['type']) {
                'section' => $blocks = array_merge($blocks, $this->renderSectionChildren($element['elements'])),
                'divider' => $blocks[] = ['type' => 'divider'],
                'fields' => $blocks[] = $this->renderFields($element['fields']),
                'actions' => $blocks[] = $this->renderActions($element['actions']),
                default => null,
            };
        }

        return $blocks;
    }

    /**
     * @param  list<mixed>  $children
     * @return list<array<string, mixed>>
     */
    protected function renderSectionChildren(array $children): array
    {
        $blocks = [];

        foreach ($children as $child) {
            if ($child instanceof Text) {
                $content = $this->convertBoldToSlack($child->content);

                if ($child->style === 'bold') {
                    $blocks[] = [
                        'type' => 'section',
                        'text' => ['type' => 'mrkdwn', 'text' => '*'.$content.'*'],
                    ];
                } elseif ($child->style === 'muted') {
                    $blocks[] = [
                        'type' => 'context',
                        'elements' => [['type' => 'mrkdwn', 'text' => $content]],
                    ];
                } else {
                    $blocks[] = [
                        'type' => 'section',
                        'text' => ['type' => 'mrkdwn', 'text' => $content],
                    ];
                }
            } elseif ($child instanceof Image) {
                $blocks[] = [
                    'type' => 'image',
                    'image_url' => $child->url,
                    'alt_text' => $child->alt ?? '',
                ];
            } elseif ($child instanceof Divider) {
                $blocks[] = ['type' => 'divider'];
            }
        }

        return $blocks;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    protected function renderFields(array $fields): array
    {
        $slackFields = [];

        foreach ($fields as $label => $value) {
            $slackFields[] = [
                'type' => 'mrkdwn',
                'text' => '*'.$label."*\n".$this->convertBoldToSlack($value),
            ];
        }

        return ['type' => 'section', 'fields' => $slackFields];
    }

    /**
     * @param  list<mixed>  $actions
     * @return array<string, mixed>
     */
    protected function renderActions(array $actions): array
    {
        $elements = [];

        foreach ($actions as $action) {
            if ($action instanceof Button) {
                $btn = [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => $action->label, 'emoji' => true],
                    'action_id' => $action->actionId,
                ];

                if ($action->getStyle() !== 'default') {
                    $btn['style'] = $action->getStyle();
                }

                $elements[] = $btn;
            } elseif ($action instanceof LinkButton) {
                $elements[] = [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => $action->label, 'emoji' => true],
                    'url' => $action->url,
                ];
            } elseif ($action instanceof Select) {
                $element = [
                    'type' => 'static_select',
                    'action_id' => $action->actionId,
                ];

                if ($action->placeholder !== '') {
                    $element['placeholder'] = ['type' => 'plain_text', 'text' => $action->placeholder];
                }

                $element['options'] = array_map(fn ($opt) => [
                    'text' => ['type' => 'plain_text', 'text' => $opt->label],
                    'value' => $opt->value,
                ], $action->getOptions());

                $elements[] = $element;
            } elseif ($action instanceof RadioSelect) {
                $options = array_slice($action->getOptions(), 0, 10);

                $elements[] = [
                    'type' => 'radio_buttons',
                    'action_id' => $action->actionId,
                    'options' => array_map(fn ($opt) => [
                        'text' => ['type' => 'mrkdwn', 'text' => $opt->label],
                        'value' => $opt->value,
                    ], $options),
                ];
            }
        }

        return ['type' => 'actions', 'elements' => $elements];
    }

    protected function convertBoldToSlack(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);
    }

    public function toFallbackText(Card $card): string
    {
        return $card->toFallbackText();
    }
}
