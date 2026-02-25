<?php

namespace OpenCompany\Chatogrator\Cards\Interactive;

/** @phpstan-consistent-constructor */
class TextInput
{
    protected bool $isMultiline = false;

    protected ?int $maxLength = null;

    protected bool $isOptional = false;

    public function __construct(
        public readonly string $actionId,
        public readonly string $label,
    ) {}

    public static function make(string $actionId, string $label): static
    {
        return new static($actionId, $label);
    }

    public function multiline(bool $multiline = true): static
    {
        $this->isMultiline = $multiline;

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function optional(bool $optional = true): static
    {
        $this->isOptional = $optional;

        return $this;
    }

    public function isMultiline(): bool
    {
        return $this->isMultiline;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }
}
