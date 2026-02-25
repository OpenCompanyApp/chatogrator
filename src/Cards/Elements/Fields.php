<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class Fields
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public readonly array $fields = [],
    ) {}

    /** @param  array<string, string>  $fields */
    public static function make(array $fields): static
    {
        return new static($fields);
    }
}
