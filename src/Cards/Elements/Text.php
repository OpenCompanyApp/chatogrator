<?php

namespace OpenCompany\Chatogrator\Cards\Elements;

/** @phpstan-consistent-constructor */
class Text
{
    public function __construct(
        public readonly string $content,
        public readonly string $style = 'plain',
    ) {}

    public static function make(string $content): static
    {
        return new static($content);
    }

    public static function bold(string $content): static
    {
        return new static($content, 'bold');
    }

    public static function muted(string $content): static
    {
        return new static($content, 'muted');
    }
}
