<?php

namespace OpenCompany\Chatogrator\Adapters\Linear;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;

class LinearFormatConverter extends BaseFormatConverter
{
    /**
     * Convert Linear markdown to standard markdown (passthrough).
     */
    public function toMarkdown(string $platformText): string
    {
        return $platformText;
    }

    /**
     * Convert standard markdown to Linear markdown (passthrough).
     */
    public function fromMarkdown(string $markdown): string
    {
        return $markdown;
    }

    protected function renderCard(Card $card): mixed
    {
        return (new LinearCardRenderer)->render($card);
    }
}
