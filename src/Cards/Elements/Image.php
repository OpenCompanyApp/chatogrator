<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class Image
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $alt = null,
    ) {}

    public static function make(string $url, ?string $alt = null): static
    {
        return new static($url, $alt);
    }
}
