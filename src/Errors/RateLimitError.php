<?php

namespace OpenCompany\Chatogrator\Errors;

class RateLimitError extends ChatError
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
