<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatAdapter;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Google Chat thread ID encoding/decoding utilities,
 * round-trip verification, and DM detection.
 *
 * Ported from adapter-gchat/src/thread-utils.test.ts (16 tests).
 *
 * @group gchat
 */
class GoogleChatThreadsTest extends TestCase
{
    private GoogleChatAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = GoogleChatAdapter::fromConfig([
            'credentials' => [
                'client_email' => 'test@test.iam.gserviceaccount.com',
                'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
            ],
        ]);
    }

    // ── encodeThreadId ──────────────────────────────────────────────

    public function test_encode_space_name_only(): void
    {
        $id = $this->adapter->encodeThreadId(['spaceName' => 'spaces/ABC123']);

        $this->assertSame('gchat:spaces/ABC123', $id);
    }

    public function test_encode_space_name_with_thread_name(): void
    {
        $id = $this->adapter->encodeThreadId([
            'spaceName' => 'spaces/ABC123',
            'threadName' => 'spaces/ABC123/threads/xyz',
        ]);

        $this->assertStringStartsWith('gchat:spaces/ABC123:', $id);
        // Should contain base64url encoded thread name
        $parts = explode(':', $id);
        $this->assertGreaterThanOrEqual(3, count($parts));
    }

    public function test_encode_adds_dm_suffix_for_dm_threads(): void
    {
        $id = $this->adapter->encodeThreadId([
            'spaceName' => 'spaces/DM123',
            'isDM' => true,
        ]);

        $this->assertSame('gchat:spaces/DM123:dm', $id);
    }

    public function test_encode_adds_dm_suffix_with_thread_name(): void
    {
        $id = $this->adapter->encodeThreadId([
            'spaceName' => 'spaces/DM123',
            'threadName' => 'spaces/DM123/threads/t1',
            'isDM' => true,
        ]);

        $this->assertStringEndsWith(':dm', $id);
    }

    // ── decodeThreadId ──────────────────────────────────────────────

    public function test_decode_space_only_thread_id(): void
    {
        $result = $this->adapter->decodeThreadId('gchat:spaces/ABC123');

        $this->assertSame('spaces/ABC123', $result['spaceName']);
        $this->assertArrayNotHasKey('threadName', $result);
        $this->assertFalse($result['isDM'] ?? false);
    }

    public function test_decode_dm_thread_id(): void
    {
        $result = $this->adapter->decodeThreadId('gchat:spaces/DM123:dm');

        $this->assertSame('spaces/DM123', $result['spaceName']);
        $this->assertTrue($result['isDM']);
    }

    public function test_decode_throws_on_invalid_format(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid Google Chat thread ID');

        $this->adapter->decodeThreadId('invalid');
    }

    public function test_decode_throws_on_wrong_prefix(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid Google Chat thread ID');

        $this->adapter->decodeThreadId('slack:C123:1234');
    }

    // ── Round-trip ──────────────────────────────────────────────────

    public function test_round_trip_space_only(): void
    {
        $original = ['spaceName' => 'spaces/ABC'];
        $encoded = $this->adapter->encodeThreadId($original);
        $decoded = $this->adapter->decodeThreadId($encoded);

        $this->assertSame($original['spaceName'], $decoded['spaceName']);
    }

    public function test_round_trip_with_thread_name(): void
    {
        $original = [
            'spaceName' => 'spaces/ABC',
            'threadName' => 'spaces/ABC/threads/xyz',
        ];

        $encoded = $this->adapter->encodeThreadId($original);
        $decoded = $this->adapter->decodeThreadId($encoded);

        $this->assertSame($original['spaceName'], $decoded['spaceName']);
        $this->assertSame($original['threadName'], $decoded['threadName']);
    }

    public function test_round_trip_dm(): void
    {
        $original = ['spaceName' => 'spaces/DM1', 'isDM' => true];

        $encoded = $this->adapter->encodeThreadId($original);
        $decoded = $this->adapter->decodeThreadId($encoded);

        $this->assertSame($original['spaceName'], $decoded['spaceName']);
        $this->assertTrue($decoded['isDM']);
    }

    // ── isDM ────────────────────────────────────────────────────────

    public function test_is_dm_returns_true_for_dm_thread_ids(): void
    {
        $this->assertTrue($this->adapter->isDM('gchat:spaces/DM123:dm'));
    }

    public function test_is_dm_returns_false_for_non_dm_thread_ids(): void
    {
        $this->assertFalse($this->adapter->isDM('gchat:spaces/ABC123'));
    }

    public function test_is_dm_returns_false_for_dm_in_middle(): void
    {
        // ":dm" must be at the end, not in the middle
        $this->assertFalse($this->adapter->isDM('gchat:dm:spaces/ABC'));
    }

    public function test_is_dm_returns_false_for_thread_with_encoded_content(): void
    {
        // Encode a non-DM thread with thread name — should not be DM
        $encoded = $this->adapter->encodeThreadId([
            'spaceName' => 'spaces/ABC123',
            'threadName' => 'spaces/ABC123/threads/xyz',
        ]);

        $this->assertFalse($this->adapter->isDM($encoded));
    }
}
