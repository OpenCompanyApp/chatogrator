<?php

namespace OpenCompany\Chatogrator\Cards;

/** @phpstan-consistent-constructor */
class Card
{
    protected string $title;

    protected ?string $subtitle = null;

    protected ?string $imageUrl = null;

    /** @var list<array<string, mixed>> */
    protected array $elements = [];

    public static function make(string $title): static
    {
        $card = new static;
        $card->title = $title;

        return $card;
    }

    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function section(mixed ...$elements): static
    {
        $this->elements[] = ['type' => 'section', 'elements' => $elements];

        return $this;
    }

    public function divider(): static
    {
        $this->elements[] = ['type' => 'divider'];

        return $this;
    }

    /** @param array<string, string> $fields */
    public function fields(array $fields): static
    {
        $this->elements[] = ['type' => 'fields', 'fields' => $fields];

        return $this;
    }

    public function actions(mixed ...$actions): static
    {
        $this->elements[] = ['type' => 'actions', 'actions' => $actions];

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /** @return list<array<string, mixed>> */
    public function getElements(): array
    {
        return $this->elements;
    }

    public function toFallbackText(): string
    {
        $parts = [$this->title];

        if ($this->subtitle) {
            $parts[] = $this->subtitle;
        }

        return implode(' — ', $parts);
    }
}
