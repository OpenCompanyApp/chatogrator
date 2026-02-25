<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class Section
{
    /** @param  array<mixed>  $elements */
    public function __construct(
        public readonly array $elements = [],
    ) {}

    public static function make(mixed ...$elements): static
    {
        return new static($elements);
    }
}
