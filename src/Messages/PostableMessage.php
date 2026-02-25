<?php

namespace OpenCompany\Chatogrator\Messages;

use OpenCompany\Chatogrator\Cards\Card;

/** @phpstan-consistent-constructor */
class PostableMessage
{
    protected ?string $textContent = null;

    protected ?string $markdownContent = null;

    protected mixed $formattedContent = null;

    protected ?Card $card = null;

    /** @var iterable<string>|null */
    protected ?iterable $stream = null;

    protected mixed $rawContent = null;

    /** @var FileUpload[] */
    protected array $files = [];

    public static function text(string $text): static
    {
        $message = new static;
        $message->textContent = $text;

        return $message;
    }

    public static function markdown(string $markdown): static
    {
        $message = new static;
        $message->markdownContent = $markdown;

        return $message;
    }

    public static function formatted(mixed $ast): static
    {
        $message = new static;
        $message->formattedContent = $ast;

        return $message;
    }

    public static function card(Card $card): static
    {
        $message = new static;
        $message->card = $card;

        return $message;
    }

    /** @param iterable<string> $stream */
    public static function streaming(iterable $stream): static
    {
        $message = new static;
        $message->stream = $stream;

        return $message;
    }

    public static function raw(mixed $content): static
    {
        $message = new static;
        $message->rawContent = $content;

        return $message;
    }

    public static function make(string $text): static
    {
        return static::text($text);
    }

    /** @param  FileUpload[]  $files */
    public function files(array $files): static
    {
        $this->files = $files;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->textContent;
    }

    public function getMarkdown(): ?string
    {
        return $this->markdownContent;
    }

    public function getFormatted(): mixed
    {
        return $this->formattedContent;
    }

    public function getCard(): ?Card
    {
        return $this->card;
    }

    /** @return iterable<string>|null */
    public function getStream(): ?iterable
    {
        return $this->stream;
    }

    public function getRaw(): mixed
    {
        return $this->rawContent;
    }

    /** @return FileUpload[] */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function isStreaming(): bool
    {
        return $this->stream !== null;
    }
}
