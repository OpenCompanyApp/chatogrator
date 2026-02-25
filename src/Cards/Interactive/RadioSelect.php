<?php

namespace OpenCompany\Chatogrator\Cards\Interactive;

/** @phpstan-consistent-constructor */
class RadioSelect
{
    /** @var SelectOption[] */
    protected array $options = [];

    protected bool $isOptional = false;

    public function __construct(
        public readonly string $actionId,
        public readonly string $label,
    ) {}

    public static function make(string $actionId, string $label): static
    {
        return new static($actionId, $label);
    }

    /** @param  SelectOption[]  $options */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function optional(bool $optional = true): static
    {
        $this->isOptional = $optional;

        return $this;
    }

    /** @return SelectOption[] */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }
}
