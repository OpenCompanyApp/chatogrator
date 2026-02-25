<?php

namespace OpenCompany\Chatogrator\Errors;

class NotImplementedError extends ChatError
{
    public function __construct(
        string $message,
        public readonly ?string $method = null,
    ) {
        parent::__construct($message);
    }
}
