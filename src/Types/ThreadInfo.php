<?php

namespace OpenCompany\Chatogrator\Types;

readonly class ThreadInfo
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public ?string $channelId = null,
        public bool $isDM = false,
        public ?string $title = null,
        public array $metadata = [],
    ) {}
}
