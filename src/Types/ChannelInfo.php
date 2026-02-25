<?php

namespace OpenCompany\Chatogrator\Types;

readonly class ChannelInfo
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $topic = null,
        public ?int $memberCount = null,
        public bool $isDM = false,
    ) {}
}
