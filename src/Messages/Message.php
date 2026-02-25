<?php

namespace OpenCompany\Chatogrator\Messages;

/** @phpstan-consistent-constructor */
class Message
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<mixed> $attachments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly string $text,
        public readonly mixed $formatted,
        public readonly mixed $raw,
        public readonly Author $author,
        public readonly array $metadata,
        public readonly array $attachments,
        public readonly bool $isMention,
    ) {}

    /** @return array<string, mixed> */
    public function toJSON(): array
    {
        $data = [
            'id' => $this->id,
            'threadId' => $this->threadId,
            'text' => $this->text,
            'isMention' => $this->isMention,
            'author' => $this->author->toJSON(),
            'metadata' => $this->metadata,
        ];

        if (! empty($this->attachments)) {
            $data['attachments'] = $this->attachments;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public static function fromJSON(array $data): static
    {
        return new static(
            id: $data['id'],
            threadId: $data['threadId'],
            text: $data['text'] ?? '',
            formatted: null,
            raw: null,
            author: Author::fromJSON($data['author']),
            metadata: $data['metadata'] ?? [],
            attachments: $data['attachments'] ?? [],
            isMention: $data['isMention'] ?? false,
        );
    }
}
