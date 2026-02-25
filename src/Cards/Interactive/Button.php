<?php

namespace OpenCompany\Chatogrator\Cards\Interactive;

/** @phpstan-consistent-constructor */
class Button
{
    protected string $style = 'default';

    public function __construct(
        public readonly string $actionId,
        public readonly string $label,
    ) {}

    public static function make(string $actionId, string $label): static
    {
        return new static($actionId, $label);
    }

    public function primary(): static
    {
        $this->style = 'primary';

        return $this;
    }

    public function danger(): static
    {
        $this->style = 'danger';

        return $this;
    }

    public function getStyle(): string
    {
        return $this->style;
    }
}
