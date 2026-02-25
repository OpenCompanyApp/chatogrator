<?php

namespace OpenCompany\Chatogrator\Adapters\Slack;

use OpenCompany\Chatogrator\Cards\Interactive\RadioSelect;
use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\TextInput;
use OpenCompany\Chatogrator\Cards\Modal;

class SlackModalRenderer
{
    /**
     * Convert a Modal to a Slack view payload.
     *
     * @return array<string, mixed>
     */
    public static function toSlackView(Modal $modal, ?string $contextId = null): array
    {
        $view = [
            'type' => 'modal',
            'callback_id' => $modal->getCallbackId(),
            'title' => [
                'type' => 'plain_text',
                'text' => mb_substr($modal->getTitle(), 0, 24),
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => $modal->getSubmitLabel() ?? 'Submit',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => $modal->getCloseLabel() ?? 'Cancel',
            ],
            'blocks' => self::renderInputs($modal->getInputs()),
        ];

        if ($modal->shouldNotifyOnClose()) {
            $view['notify_on_close'] = true;
        }

        if ($contextId !== null) {
            $view['private_metadata'] = $contextId;
        }

        return $view;
    }

    /**
     * @param  list<mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    protected static function renderInputs(array $inputs): array
    {
        $blocks = [];

        foreach ($inputs as $input) {
            if ($input instanceof TextInput) {
                $element = [
                    'type' => 'plain_text_input',
                    'action_id' => $input->actionId,
                    'multiline' => $input->isMultiline(),
                ];

                if ($input->getMaxLength() !== null) {
                    $element['max_length'] = $input->getMaxLength();
                }

                $blocks[] = [
                    'type' => 'input',
                    'block_id' => $input->actionId,
                    'optional' => $input->isOptional(),
                    'label' => ['type' => 'plain_text', 'text' => $input->label],
                    'element' => $element,
                ];
            } elseif ($input instanceof Select) {
                $element = [
                    'type' => 'static_select',
                    'action_id' => $input->actionId,
                ];

                if ($input->placeholder !== '') {
                    $element['placeholder'] = ['type' => 'plain_text', 'text' => $input->placeholder];
                }

                $element['options'] = array_map(fn ($opt) => [
                    'text' => ['type' => 'plain_text', 'text' => $opt->label],
                    'value' => $opt->value,
                ], $input->getOptions());

                $blocks[] = [
                    'type' => 'input',
                    'block_id' => $input->actionId,
                    'optional' => false,
                    'label' => ['type' => 'plain_text', 'text' => $input->placeholder],
                    'element' => $element,
                ];
            } elseif ($input instanceof RadioSelect) {
                $options = array_slice($input->getOptions(), 0, 10);

                $element = [
                    'type' => 'radio_buttons',
                    'action_id' => $input->actionId,
                    'options' => array_map(fn ($opt) => [
                        'text' => ['type' => 'mrkdwn', 'text' => $opt->label],
                        'value' => $opt->value,
                    ], $options),
                ];

                $blocks[] = [
                    'type' => 'input',
                    'block_id' => $input->actionId,
                    'optional' => $input->isOptional(),
                    'label' => ['type' => 'plain_text', 'text' => $input->label],
                    'element' => $element,
                ];
            }
        }

        return $blocks;
    }

    /**
     * Encode metadata for Slack private_metadata field.
     *
     * @param  array<string, mixed>  $data
     */
    public static function encodeMetadata(array $data): ?string
    {
        $encoded = [];

        if (isset($data['contextId']) && $data['contextId'] !== '') {
            $encoded['c'] = $data['contextId'];
        }

        if (isset($data['privateMetadata']) && $data['privateMetadata'] !== '') {
            $encoded['m'] = $data['privateMetadata'];
        }

        if (empty($encoded)) {
            return null;
        }

        return json_encode($encoded);
    }

    /**
     * Decode metadata from Slack private_metadata field.
     *
     * @return array<string, mixed>
     */
    public static function decodeMetadata(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }

        $parsed = json_decode($encoded, true);

        if ($parsed === null || (! isset($parsed['c']) && ! isset($parsed['m']))) {
            return ['contextId' => $encoded];
        }

        $result = [];

        if (isset($parsed['c'])) {
            $result['contextId'] = $parsed['c'];
        }

        if (isset($parsed['m'])) {
            $result['privateMetadata'] = $parsed['m'];
        }

        return $result;
    }
}
