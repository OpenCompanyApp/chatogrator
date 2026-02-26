<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class CardLink
{
    public function __construct(
        public readonly string $url,
        public readonly string $label,
    ) {}

    public static function make(string $url, string $label): static
    {
        return new static($url, $label);
    }
}
