<?php

namespace OpenCompany\Chatogrator\Events;

use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Threads\Thread;

class SlashCommandEvent
{
    public function __construct(
        public readonly string $command,
        public readonly string $text,
        public readonly Thread $thread,
        public readonly string $userId,
        public readonly ?string $triggerId = null,
    ) {}

    public function respond(string|PostableMessage $content): void
    {
        $message = is_string($content) ? PostableMessage::text($content) : $content;
        $this->thread->post($message);
    }
}
