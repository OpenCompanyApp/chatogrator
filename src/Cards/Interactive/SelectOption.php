<?php

namespace OpenCompany\Chatogrator\Cards\Interactive;

/** @phpstan-consistent-constructor */
class SelectOption
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
    ) {}

    public static function make(string $value, string $label): static
    {
        return new static($value, $label);
    }
}
