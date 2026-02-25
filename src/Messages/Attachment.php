<?php

namespace OpenCompany\Chatogrator\Messages;

use Closure;

class Attachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly ?int $size,
        public readonly ?string $url,
        protected ?Closure $fetchCallback = null,
    ) {}

    public function fetchData(): ?string
    {
        if ($this->fetchCallback) {
            return ($this->fetchCallback)();
        }

        return null;
    }
}
