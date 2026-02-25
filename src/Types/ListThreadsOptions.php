<?php

namespace OpenCompany\Chatogrator\Types;

/** @phpstan-consistent-constructor */
readonly class ListThreadsOptions
{
    public function __construct(
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromArray(array $options): static
    {
        return new static(
            cursor: $options['cursor'] ?? null,
            limit: $options['limit'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'cursor' => $this->cursor,
            'limit' => $this->limit,
        ], fn ($v) => $v !== null);
    }
}
