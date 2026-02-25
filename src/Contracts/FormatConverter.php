<?php

namespace OpenCompany\Chatogrator\Contracts;

use OpenCompany\Chatogrator\Messages\PostableMessage;

interface FormatConverter
{
    /**
     * Convert a platform-specific message body to standard markdown.
     */
    public function toMarkdown(string $platformText): string;

    /**
     * Convert standard markdown to platform-specific format.
     */
    public function fromMarkdown(string $markdown): string;

    /**
     * Render a PostableMessage to platform-native format.
     */
    public function renderPostable(PostableMessage $message): mixed;
}
