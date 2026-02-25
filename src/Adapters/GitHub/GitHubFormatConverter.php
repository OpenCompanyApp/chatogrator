<?php

namespace OpenCompany\Chatogrator\Adapters\GitHub;

use OpenCompany\Chatogrator\Adapters\BaseFormatConverter;
use OpenCompany\Chatogrator\Cards\Card;

class GitHubFormatConverter extends BaseFormatConverter
{
    /**
     * Convert GitHub-flavored markdown to standard markdown (passthrough).
     */
    public function toMarkdown(string $platformText): string
    {
        return $platformText;
    }

    /**
     * Convert standard markdown to GitHub-flavored markdown (passthrough).
     */
    public function fromMarkdown(string $markdown): string
    {
        return $markdown;
    }

    protected function renderCard(Card $card): mixed
    {
        return (new GitHubCardRenderer)->render($card);
    }
}
