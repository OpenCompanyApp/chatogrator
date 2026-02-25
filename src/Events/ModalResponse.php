<?php

namespace OpenCompany\Chatogrator\Events;

use OpenCompany\Chatogrator\Cards\Modal;

/** @phpstan-consistent-constructor */
class ModalResponse
{
    /** @param array<string, string>|null $errors */
    protected function __construct(
        public readonly string $action,
        public readonly ?array $errors = null,
        public readonly ?Modal $modal = null,
    ) {}

    public static function close(): static
    {
        return new static('close');
    }

    /** @param  array<string, string>  $errors */
    public static function errors(array $errors): static
    {
        return new static('errors', errors: $errors);
    }

    public static function update(Modal $modal): static
    {
        return new static('update', modal: $modal);
    }

    public static function push(Modal $modal): static
    {
        return new static('push', modal: $modal);
    }
}
