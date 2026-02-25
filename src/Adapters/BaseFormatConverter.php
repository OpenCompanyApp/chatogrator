<?php

namespace OpenCompany\Chatogrator\Adapters;

use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Contracts\FormatConverter;
use OpenCompany\Chatogrator\Messages\PostableMessage;

abstract class BaseFormatConverter implements FormatConverter
{
    public function renderPostable(PostableMessage $message): mixed
    {
        if ($message->getCard()) {
            return $this->renderCard($message->getCard());
        }

        if ($message->getMarkdown()) {
            return $this->fromMarkdown($message->getMarkdown());
        }

        return $message->getText() ?? '';
    }

    abstract protected function renderCard(Card $card): mixed;

    public function cardToFallbackText(Card $card): string
    {
        return $card->toFallbackText();
    }
}
