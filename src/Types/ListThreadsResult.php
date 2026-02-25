<?php

namespace OpenCompany\Chatogrator\Types;

readonly class ListThreadsResult
{
    /**
     * @param  array<int, ThreadSummary>  $threads
     */
    public function __construct(
        public array $threads = [],
        public ?string $nextCursor = null,
        public bool $hasMore = false,
    ) {}
}
