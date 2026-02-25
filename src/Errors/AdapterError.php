<?php

namespace OpenCompany\Chatogrator\Errors;

class AdapterError extends ChatError
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly array $metadata = [],
    ) {
        parent::__construct($message);
    }
}
