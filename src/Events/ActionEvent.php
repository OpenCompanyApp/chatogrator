<?php

namespace OpenCompany\Chatogrator\Events;

use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Threads\Thread;

class ActionEvent
{
    public function __construct(
        public readonly string $actionId,
        public readonly ?string $value,
        public readonly Thread $thread,
        public readonly ?string $triggerId = null,
    ) {}

    /** @return array<string, mixed>|null */
    public function openModal(Modal $modal): ?array
    {
        if (! $this->triggerId) {
            return null;
        }

        return $this->thread->adapter->openModal($this->triggerId, $modal);
    }
}
