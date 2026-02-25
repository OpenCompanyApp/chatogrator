<?php

namespace OpenCompany\Chatogrator\Types;

readonly class FetchResult
{
    /**
     * @param  array<int, \OpenCompany\Chatogrator\Messages\Message>  $messages
     */
    public function __construct(
        public array $messages = [],
        public ?string $nextCursor = null,
        public bool $hasMore = false,
    ) {}
}
