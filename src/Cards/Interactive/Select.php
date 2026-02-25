<?php

namespace OpenCompany\Chatogrator\Cards\Interactive;

/** @phpstan-consistent-constructor */
class Select
{
    /** @var SelectOption[] */
    protected array $options = [];

    public function __construct(
        public readonly string $actionId,
        public readonly string $placeholder,
    ) {}

    public static function make(string $actionId, string $placeholder): static
    {
        return new static($actionId, $placeholder);
    }

    /** @param  SelectOption[]  $options */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @return SelectOption[] */
    public function getOptions(): array
    {
        return $this->options;
    }
}
