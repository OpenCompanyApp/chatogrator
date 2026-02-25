<?php

namespace OpenCompany\Chatogrator\Events;

use OpenCompany\Chatogrator\Threads\Thread;

class ReactionEvent
{
    public function __construct(
        public readonly string $emoji,
        public readonly string $messageId,
        public readonly string $userId,
        public readonly string $type,
        public readonly Thread $thread,
    ) {}

    public function isAdded(): bool
    {
        return $this->type === 'added';
    }

    public function isRemoved(): bool
    {
        return $this->type === 'removed';
    }
}
