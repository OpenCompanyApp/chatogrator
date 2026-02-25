<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class Actions
{
    /** @param  array<mixed>  $actions */
    public function __construct(
        public readonly array $actions = [],
    ) {}

    public static function make(mixed ...$actions): static
    {
        return new static($actions);
    }
}
