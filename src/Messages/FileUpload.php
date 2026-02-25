<?php

namespace OpenCompany\Chatogrator\Messages;

/** @phpstan-consistent-constructor */
class FileUpload
{
    public function __construct(
        public readonly string $content,
        public readonly string $filename,
        public readonly string $mimeType,
    ) {}

    public static function make(string $content, string $filename, string $mimeType): static
    {
        return new static($content, $filename, $mimeType);
    }
}
