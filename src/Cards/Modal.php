<?php

namespace OpenCompany\Chatogrator\Cards;

/** @phpstan-consistent-constructor */
class Modal
{
    protected string $callbackId;

    protected string $title;

    protected ?string $submitLabel = null;

    protected ?string $closeLabel = null;

    protected bool $notifyOnClose = false;

    /** @var array<string, mixed> */
    protected array $privateMetadata = [];

    /** @var list<mixed> */
    protected array $inputs = [];

    public static function make(string $callbackId, string $title): static
    {
        $modal = new static;
        $modal->callbackId = $callbackId;
        $modal->title = $title;

        return $modal;
    }

    public function submitLabel(string $label): static
    {
        $this->submitLabel = $label;

        return $this;
    }

    public function closeLabel(string $label): static
    {
        $this->closeLabel = $label;

        return $this;
    }

    public function notifyOnClose(bool $notify = true): static
    {
        $this->notifyOnClose = $notify;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function privateMetadata(array $data): static
    {
        $this->privateMetadata = $data;

        return $this;
    }

    public function input(mixed $input): static
    {
        $this->inputs[] = $input;

        return $this;
    }

    public function getCallbackId(): string
    {
        return $this->callbackId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubmitLabel(): ?string
    {
        return $this->submitLabel;
    }

    public function getCloseLabel(): ?string
    {
        return $this->closeLabel;
    }

    public function shouldNotifyOnClose(): bool
    {
        return $this->notifyOnClose;
    }

    /** @return array<string, mixed> */
    public function getPrivateMetadata(): array
    {
        return $this->privateMetadata;
    }

    /** @return list<mixed> */
    public function getInputs(): array
    {
        return $this->inputs;
    }
}
