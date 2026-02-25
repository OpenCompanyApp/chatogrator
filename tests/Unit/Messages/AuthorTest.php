<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Messages;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group core
 */
class AuthorTest extends TestCase
{
    // ── Construction with all fields ────────────────────────────────

    public function test_constructor_assigns_all_fields(): void
    {
        $author = new Author(
            userId: 'U123',
            userName: 'testuser',
            fullName: 'Test User',
            isBot: false,
            isMe: false,
        );

        $this->assertSame('U123', $author->userId);
        $this->assertSame('testuser', $author->userName);
        $this->assertSame('Test User', $author->fullName);
        $this->assertFalse($author->isBot);
        $this->assertFalse($author->isMe);
    }

    public function test_constructor_with_bot_author(): void
    {
        $author = new Author(
            userId: 'BOT',
            userName: 'mybot',
            fullName: 'My Bot',
            isBot: true,
            isMe: true,
        );

        $this->assertSame('BOT', $author->userId);
        $this->assertTrue($author->isBot);
        $this->assertTrue($author->isMe);
    }

    // ── isBot as bool ───────────────────────────────────────────────

    public function test_is_bot_as_true(): void
    {
        $author = new Author('U1', 'bot', 'Bot', true, false);

        $this->assertTrue($author->isBot);
    }

    public function test_is_bot_as_false(): void
    {
        $author = new Author('U1', 'human', 'Human', false, false);

        $this->assertFalse($author->isBot);
    }

    // ── isBot as 'unknown' string ───────────────────────────────────

    public function test_is_bot_as_unknown_string(): void
    {
        $author = new Author(
            userId: 'U1',
            userName: 'mystery',
            fullName: 'Mystery User',
            isBot: 'unknown',
            isMe: false,
        );

        $this->assertSame('unknown', $author->isBot);
    }

    // ── toJSON() ────────────────────────────────────────────────────

    public function test_to_json_returns_correct_structure(): void
    {
        $author = new Author(
            userId: 'U123',
            userName: 'testuser',
            fullName: 'Test User',
            isBot: false,
            isMe: false,
        );

        $json = $author->toJSON();

        $this->assertSame([
            'userId' => 'U123',
            'userName' => 'testuser',
            'fullName' => 'Test User',
            'isBot' => false,
            'isMe' => false,
        ], $json);
    }

    public function test_to_json_with_bot_author(): void
    {
        $author = new Author(
            userId: 'BOT',
            userName: 'mybot',
            fullName: 'My Bot',
            isBot: true,
            isMe: true,
        );

        $json = $author->toJSON();

        $this->assertTrue($json['isBot']);
        $this->assertTrue($json['isMe']);
    }

    public function test_to_json_with_unknown_is_bot(): void
    {
        $author = new Author('U1', 'user', 'User', 'unknown', false);

        $json = $author->toJSON();

        $this->assertSame('unknown', $json['isBot']);
    }

    public function test_to_json_produces_json_serializable_output(): void
    {
        $author = new Author('U123', 'testuser', 'Test User', false, false);

        $json = $author->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($json, $decoded);
    }

    // ── fromJSON() ──────────────────────────────────────────────────

    public function test_from_json_restores_author(): void
    {
        $json = [
            'userId' => 'U123',
            'userName' => 'testuser',
            'fullName' => 'Test User',
            'isBot' => false,
            'isMe' => false,
        ];

        $author = Author::fromJSON($json);

        $this->assertSame('U123', $author->userId);
        $this->assertSame('testuser', $author->userName);
        $this->assertSame('Test User', $author->fullName);
        $this->assertFalse($author->isBot);
        $this->assertFalse($author->isMe);
    }

    public function test_from_json_with_bot_flags(): void
    {
        $json = [
            'userId' => 'BOT',
            'userName' => 'bot',
            'fullName' => 'Bot',
            'isBot' => true,
            'isMe' => true,
        ];

        $author = Author::fromJSON($json);

        $this->assertTrue($author->isBot);
        $this->assertTrue($author->isMe);
    }

    public function test_from_json_defaults_missing_user_name(): void
    {
        $json = [
            'userId' => 'U123',
            'fullName' => 'Test',
            'isBot' => false,
            'isMe' => false,
        ];

        $author = Author::fromJSON($json);

        $this->assertSame('', $author->userName);
    }

    public function test_from_json_defaults_missing_full_name(): void
    {
        $json = [
            'userId' => 'U123',
            'userName' => 'user',
            'isBot' => false,
            'isMe' => false,
        ];

        $author = Author::fromJSON($json);

        $this->assertSame('', $author->fullName);
    }

    public function test_from_json_defaults_missing_is_bot_to_unknown(): void
    {
        $json = [
            'userId' => 'U123',
            'userName' => 'user',
            'fullName' => 'User',
            'isMe' => false,
        ];

        $author = Author::fromJSON($json);

        $this->assertSame('unknown', $author->isBot);
    }

    public function test_from_json_defaults_missing_is_me_to_false(): void
    {
        $json = [
            'userId' => 'U123',
            'userName' => 'user',
            'fullName' => 'User',
            'isBot' => false,
        ];

        $author = Author::fromJSON($json);

        $this->assertFalse($author->isMe);
    }

    // ── Round-trip ──────────────────────────────────────────────────

    public function test_round_trip_preserves_all_fields(): void
    {
        $original = new Author(
            userId: 'U123',
            userName: 'testuser',
            fullName: 'Test User',
            isBot: false,
            isMe: false,
        );

        $json = $original->toJSON();
        $restored = Author::fromJSON($json);

        $this->assertSame($original->userId, $restored->userId);
        $this->assertSame($original->userName, $restored->userName);
        $this->assertSame($original->fullName, $restored->fullName);
        $this->assertSame($original->isBot, $restored->isBot);
        $this->assertSame($original->isMe, $restored->isMe);
    }

    public function test_round_trip_preserves_bot_author(): void
    {
        $original = new Author('BOT', 'bot', 'Bot', true, true);

        $json = $original->toJSON();
        $restored = Author::fromJSON($json);

        $this->assertTrue($restored->isBot);
        $this->assertTrue($restored->isMe);
    }

    public function test_round_trip_preserves_unknown_is_bot(): void
    {
        $original = new Author('U1', 'user', 'User', 'unknown', false);

        $json = $original->toJSON();
        $restored = Author::fromJSON($json);

        $this->assertSame('unknown', $restored->isBot);
    }
}
