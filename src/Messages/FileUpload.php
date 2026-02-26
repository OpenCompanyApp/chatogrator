<?php

namespace OpenCompany\Chatogrator\Messages;

/** @phpstan-consistent-constructor */
class FileUpload
{
    public function __construct(
        public readonly ?string $content = null,
        public readonly string $filename = '',
        public readonly string $mimeType = 'application/octet-stream',
        public readonly ?string $path = null,
        public readonly ?string $url = null,
        public readonly ?string $caption = null,
        public readonly bool $forceDocument = false,
    ) {}

    public static function make(string $content, string $filename, string $mimeType): static
    {
        return new static(content: $content, filename: $filename, mimeType: $mimeType);
    }

    public static function fromContent(string $content, string $filename, string $mimeType, ?string $caption = null): static
    {
        return new static(content: $content, filename: $filename, mimeType: $mimeType, caption: $caption);
    }

    public static function fromPath(string $path, ?string $filename = null, ?string $mimeType = null, ?string $caption = null, bool $forceDocument = false): static
    {
        return new static(
            filename: $filename ?? basename($path),
            mimeType: $mimeType ?? (function_exists('mime_content_type') && file_exists($path) ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream'),
            path: $path,
            caption: $caption,
            forceDocument: $forceDocument,
        );
    }

    public static function fromUrl(string $url, ?string $filename = null, ?string $mimeType = null, ?string $caption = null): static
    {
        return new static(
            filename: $filename ?? basename(parse_url($url, PHP_URL_PATH) ?: 'file'),
            mimeType: $mimeType ?? 'application/octet-stream',
            url: $url,
            caption: $caption,
        );
    }

    /**
     * Get the file contents from whichever source is available.
     */
    public function getContents(): string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        if ($this->path !== null && file_exists($this->path)) {
            return file_get_contents($this->path);
        }

        if ($this->url !== null) {
            return file_get_contents($this->url);
        }

        return '';
    }
}
