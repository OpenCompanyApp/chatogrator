<?php

namespace OpenCompany\Chatogrator\Adapters\Concerns;

use OpenCompany\Chatogrator\Errors\RateLimitError;

trait HandlesRateLimits
{
    protected function handleRateLimit(int $retryAfter): never
    {
        throw new RateLimitError(
            "Rate limited. Retry after {$retryAfter} seconds.",
            $retryAfter,
        );
    }
}
