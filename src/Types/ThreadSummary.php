<?php

namespace OpenCompany\Chatogrator\Types;

readonly class ThreadSummary
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $lastActivity = null,
        public int $messageCount = 0,
    ) {}
}
