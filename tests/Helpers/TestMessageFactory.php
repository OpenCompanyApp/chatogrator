<?php

namespace OpenCompany\Chatogrator\Tests\Helpers;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;

class TestMessageFactory
{
    public static function make(string $id, string $text, array $overrides = []): Message
    {
        $defaults = [
            'id' => $id,
            'threadId' => 'slack:C123:1234.5678',
            'text' => $text,
            'formatted' => null,
            'raw' => [],
            'author' => new Author(
                userId: 'U123',
                userName: 'testuser',
                fullName: 'Test User',
                isBot: false,
                isMe: false,
            ),
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => false,
            ],
            'attachments' => [],
            'isMention' => false,
        ];

        $data = array_merge($defaults, $overrides);

        // If author is passed as array, convert to Author object
        if (is_array($data['author'])) {
            $data['author'] = new Author(
                userId: $data['author']['userId'] ?? 'U123',
                userName: $data['author']['userName'] ?? 'testuser',
                fullName: $data['author']['fullName'] ?? 'Test User',
                isBot: $data['author']['isBot'] ?? false,
                isMe: $data['author']['isMe'] ?? false,
            );
        }

        return new Message(
            id: $data['id'],
            threadId: $data['threadId'],
            text: $data['text'],
            formatted: $data['formatted'],
            raw: $data['raw'],
            author: $data['author'],
            metadata: $data['metadata'],
            attachments: $data['attachments'],
            isMention: $data['isMention'],
        );
    }

    public static function withAuthor(string $id, string $text, Author $author): Message
    {
        return static::make($id, $text, ['author' => $author]);
    }

    public static function mention(string $id, string $text): Message
    {
        return static::make($id, $text, ['isMention' => true]);
    }

    public static function fromBot(string $id, string $text): Message
    {
        return static::make($id, $text, [
            'author' => new Author(
                userId: 'BOT',
                userName: 'testbot',
                fullName: 'Test Bot',
                isBot: true,
                isMe: true,
            ),
        ]);
    }
}
