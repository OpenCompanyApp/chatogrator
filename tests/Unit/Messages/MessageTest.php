<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Messages;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group core
 */
class MessageTest extends TestCase
{
    // ── Construction ────────────────────────────────────────────────

    public function test_constructor_assigns_all_properties(): void
    {
        $author = new Author(
            userId: 'U123',
            userName: 'testuser',
            fullName: 'Test User',
            isBot: false,
            isMe: false,
        );

        $msg = new Message(
            id: 'msg-1',
            threadId: 'slack:C123:1234.5678',
            text: 'Hello world',
            formatted: null,
            raw: ['platform' => 'test'],
            author: $author,
            metadata: [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => false,
            ],
            attachments: [],
            isMention: false,
        );

        $this->assertSame('msg-1', $msg->id);
        $this->assertSame('slack:C123:1234.5678', $msg->threadId);
        $this->assertSame('Hello world', $msg->text);
        $this->assertSame('testuser', $msg->author->userName);
        $this->assertSame('2024-01-15T10:30:00.000Z', $msg->metadata['dateSent']);
        $this->assertSame([], $msg->attachments);
    }

    public function test_is_mention_defaults_to_false(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello');

        $this->assertFalse($msg->isMention);
    }

    public function test_is_mention_when_set_to_true(): void
    {
        $msg = TestMessageFactory::mention('msg-1', 'Hey @bot');

        $this->assertTrue($msg->isMention);
    }

    // ── toJSON() ────────────────────────────────────────────────────

    public function test_to_json_includes_type_key(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello world');

        $json = $msg->toJSON();

        // The PHP Message.toJSON() should include an id and text
        $this->assertSame('msg-1', $json['id']);
        $this->assertSame('Hello world', $json['text']);
    }

    public function test_to_json_includes_author(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello');

        $json = $msg->toJSON();

        $this->assertArrayHasKey('author', $json);
        $this->assertSame('U123', $json['author']['userId']);
        $this->assertSame('testuser', $json['author']['userName']);
        $this->assertSame('Test User', $json['author']['fullName']);
        $this->assertFalse($json['author']['isBot']);
    }

    public function test_to_json_includes_metadata(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello', [
            'metadata' => [
                'dateSent' => '2024-06-01T12:00:00.000Z',
                'edited' => true,
                'editedAt' => '2024-06-01T13:00:00.000Z',
            ],
        ]);

        $json = $msg->toJSON();

        $this->assertSame('2024-06-01T12:00:00.000Z', $json['metadata']['dateSent']);
        $this->assertTrue($json['metadata']['edited']);
        $this->assertSame('2024-06-01T13:00:00.000Z', $json['metadata']['editedAt']);
    }

    public function test_to_json_includes_is_mention_flag(): void
    {
        $msg = TestMessageFactory::mention('msg-1', 'Hey @bot');

        $json = $msg->toJSON();

        $this->assertTrue($json['isMention']);
    }

    public function test_to_json_includes_thread_id(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello');

        $json = $msg->toJSON();

        $this->assertSame('slack:C123:1234.5678', $json['threadId']);
    }

    // ── fromJSON() ──────────────────────────────────────────────────

    public function test_from_json_restores_message(): void
    {
        $json = [
            'id' => 'msg-2',
            'threadId' => 'teams:ch:th',
            'text' => 'hi',
            'author' => [
                'userId' => 'U1',
                'userName' => 'u',
                'fullName' => 'U',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => [
                'dateSent' => '2024-03-01T00:00:00.000Z',
                'edited' => false,
            ],
        ];

        $msg = Message::fromJSON($json);

        $this->assertSame('msg-2', $msg->id);
        $this->assertSame('teams:ch:th', $msg->threadId);
        $this->assertSame('hi', $msg->text);
        $this->assertSame('U1', $msg->author->userId);
    }

    public function test_from_json_handles_missing_text(): void
    {
        $json = [
            'id' => 'msg-3',
            'threadId' => 't',
            'author' => [
                'userId' => 'U',
                'userName' => 'u',
                'fullName' => 'U',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertSame('', $msg->text);
    }

    public function test_from_json_restores_is_mention(): void
    {
        $json = [
            'id' => 'msg-4',
            'threadId' => 't',
            'text' => 'Hello',
            'isMention' => true,
            'author' => [
                'userId' => 'U',
                'userName' => 'u',
                'fullName' => 'U',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertTrue($msg->isMention);
    }

    public function test_from_json_defaults_is_mention_to_false(): void
    {
        $json = [
            'id' => 'msg-5',
            'threadId' => 't',
            'text' => 'Hello',
            'author' => [
                'userId' => 'U',
                'userName' => 'u',
                'fullName' => 'U',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertFalse($msg->isMention);
    }

    // ── Round-trip serialization ────────────────────────────────────

    public function test_round_trip_preserves_all_fields(): void
    {
        $original = TestMessageFactory::make('msg-1', 'Hello world', [
            'isMention' => true,
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => true,
                'editedAt' => '2024-01-15T11:00:00.000Z',
            ],
        ]);

        $json = $original->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->threadId, $restored->threadId);
        $this->assertSame($original->text, $restored->text);
        $this->assertSame($original->isMention, $restored->isMention);
        $this->assertSame($original->author->userId, $restored->author->userId);
    }

    public function test_to_json_produces_json_serializable_output(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello **world**');

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame('msg-1', $decoded['id']);
        $this->assertSame('Hello **world**', $decoded['text']);
    }

    // ── Bot message ─────────────────────────────────────────────────

    public function test_bot_message_has_correct_author_flags(): void
    {
        $msg = TestMessageFactory::fromBot('msg-1', 'I am bot');

        $this->assertTrue($msg->author->isBot);
        $this->assertTrue($msg->author->isMe);
        $this->assertSame('BOT', $msg->author->userId);
    }
}
