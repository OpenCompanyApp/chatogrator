<?php

namespace OpenCompany\Chatogrator\Messages;

/** @phpstan-consistent-constructor */
class Author
{
    public function __construct(
        public readonly string $userId,
        public readonly string $userName,
        public readonly string $fullName,
        public readonly bool|string $isBot,
        public readonly bool $isMe,
    ) {}

    /** @return array<string, mixed> */
    public function toJSON(): array
    {
        return [
            'userId' => $this->userId,
            'userName' => $this->userName,
            'fullName' => $this->fullName,
            'isBot' => $this->isBot,
            'isMe' => $this->isMe,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromJSON(array $data): static
    {
        return new static(
            userId: $data['userId'],
            userName: $data['userName'] ?? '',
            fullName: $data['fullName'] ?? '',
            isBot: $data['isBot'] ?? 'unknown',
            isMe: $data['isMe'] ?? false,
        );
    }
}
