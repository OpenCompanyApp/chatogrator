<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Linear;

use Illuminate\Http\Request;
use OpenCompany\Chatogrator\Adapters\Linear\LinearAdapter;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the Linear adapter — construction, thread ID encoding/decoding,
 * webhook handling, and message operations.
 *
 * Ported from adapter-linear/src/index.test.ts.
 *
 * @group linear
 */
class LinearAdapterTest extends TestCase
{
    // ── Factory / Construction ───────────────────────────────────────

    public function test_create_linear_adapter_returns_instance(): void
    {
        $adapter = LinearAdapter::fromConfig([
            'api_key' => 'test-api-key',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(LinearAdapter::class, $adapter);
    }

    public function test_adapter_name_is_linear(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertSame('linear', $adapter->name());
    }

    public function test_default_user_name_is_linear_bot(): void
    {
        $adapter = LinearAdapter::fromConfig([
            'api_key' => 'test-api-key',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertSame('linear-bot', $adapter->userName());
    }

    public function test_uses_provided_bot_name(): void
    {
        $adapter = LinearAdapter::fromConfig([
            'api_key' => 'test-api-key',
            'webhook_secret' => 'test-secret',
            'bot_name' => 'my-linear-app',
        ]);

        $this->assertSame('my-linear-app', $adapter->userName());
    }

    // ── Thread ID Encoding ──────────────────────────────────────────

    public function test_encode_issue_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'issueId' => 'abc123-def456-789',
        ]);

        $this->assertSame('linear:abc123-def456-789', $threadId);
    }

    public function test_encode_uuid_issue_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'issueId' => '2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9',
        ]);

        $this->assertSame('linear:2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9', $threadId);
    }

    public function test_encode_comment_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'issueId' => 'issue-123',
            'commentId' => 'comment-456',
        ]);

        $this->assertSame('linear:issue-123:c:comment-456', $threadId);
    }

    public function test_encode_comment_level_thread_with_uuids(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'issueId' => '2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9',
            'commentId' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $this->assertSame(
            'linear:2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9:c:a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            $threadId
        );
    }

    // ── Thread ID Decoding ──────────────────────────────────────────

    public function test_decode_issue_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('linear:abc123-def456-789');

        $this->assertSame('abc123-def456-789', $result['issueId']);
        $this->assertArrayNotHasKey('commentId', $result);
    }

    public function test_decode_uuid_issue_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('linear:2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9');

        $this->assertSame('2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9', $result['issueId']);
    }

    public function test_decode_comment_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('linear:issue-123:c:comment-456');

        $this->assertSame('issue-123', $result['issueId']);
        $this->assertSame('comment-456', $result['commentId']);
    }

    public function test_decode_comment_level_thread_with_uuids(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId(
            'linear:2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9:c:a1b2c3d4-e5f6-7890-abcd-ef1234567890'
        );

        $this->assertSame('2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9', $result['issueId']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $result['commentId']);
    }

    public function test_decode_throws_on_invalid_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid Linear thread ID');
        $adapter->decodeThreadId('slack:C123:ts123');
    }

    public function test_decode_throws_on_empty_issue_id(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid Linear thread ID format');
        $adapter->decodeThreadId('linear:');
    }

    public function test_decode_throws_on_completely_wrong_format(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid Linear thread ID');
        $adapter->decodeThreadId('nonsense');
    }

    // ── Roundtrip ───────────────────────────────────────────────────

    public function test_roundtrip_issue_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $original = ['issueId' => '2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9'];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['issueId'], $decoded['issueId']);
    }

    public function test_roundtrip_comment_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'issueId' => '2174add1-f7c8-44e3-bbf3-2d60b5ea8bc9',
            'commentId' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['issueId'], $decoded['issueId']);
        $this->assertSame($original['commentId'], $decoded['commentId']);
    }

    // ── renderFormatted ─────────────────────────────────────────────

    public function test_renders_markdown_passthrough(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('Hello world');

        $this->assertStringContainsString('Hello world', $result);
    }

    // ── parseMessage ────────────────────────────────────────────────

    public function test_parses_raw_linear_comment(): void
    {
        $adapter = $this->makeAdapter();

        $raw = [
            'comment' => [
                'id' => 'comment-abc123',
                'body' => 'Hello from Linear!',
                'issueId' => 'issue-123',
                'userId' => 'user-456',
                'createdAt' => '2025-01-29T12:00:00.000Z',
                'updatedAt' => '2025-01-29T12:00:00.000Z',
            ],
        ];

        $message = $adapter->parseMessage($raw);

        $this->assertSame('comment-abc123', $message->id);
        $this->assertSame('Hello from Linear!', $message->text);
        $this->assertSame('user-456', $message->author->userId);
    }

    public function test_detects_edited_linear_comment(): void
    {
        $adapter = $this->makeAdapter();

        $raw = [
            'comment' => [
                'id' => 'comment-abc123',
                'body' => 'Edited message',
                'issueId' => 'issue-123',
                'userId' => 'user-456',
                'createdAt' => '2025-01-29T12:00:00.000Z',
                'updatedAt' => '2025-01-29T13:00:00.000Z',
            ],
        ];

        $message = $adapter->parseMessage($raw);

        $this->assertTrue($message->metadata['edited']);
    }

    // ── Webhook Handling ────────────────────────────────────────────

    public function test_rejects_requests_without_signature(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['action' => 'create', 'type' => 'Comment']));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_requests_with_invalid_signature(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_LINEAR_SIGNATURE' => 'invalid-signature',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['action' => 'create', 'type' => 'Comment']));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeAdapter(): LinearAdapter
    {
        return LinearAdapter::fromConfig([
            'api_key' => 'test-api-key',
            'webhook_secret' => 'test-secret',
        ]);
    }

    private function makeChat(): \OpenCompany\Chatogrator\Chat
    {
        return $this->app->make(\OpenCompany\Chatogrator\Chat::class);
    }
}
