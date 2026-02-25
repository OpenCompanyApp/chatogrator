<?php

namespace OpenCompany\Chatogrator\Types;

/** @phpstan-consistent-constructor */
readonly class FetchOptions
{
    public function __construct(
        public ?string $cursor = null,
        public ?int $limit = null,
        public string $direction = 'backward',
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromArray(array $options): static
    {
        return new static(
            cursor: $options['cursor'] ?? null,
            limit: $options['limit'] ?? null,
            direction: $options['direction'] ?? 'backward',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'cursor' => $this->cursor,
            'limit' => $this->limit,
            'direction' => $this->direction,
        ], fn ($v) => $v !== null);
    }
}
