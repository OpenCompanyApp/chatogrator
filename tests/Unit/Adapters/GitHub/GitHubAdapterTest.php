<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GitHub;

use Illuminate\Http\Request;
use OpenCompany\Chatogrator\Adapters\GitHub\GitHubAdapter;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the GitHub adapter — construction, thread ID encoding/decoding,
 * webhook handling, and message operations.
 *
 * Ported from adapter-github/src/index.test.ts.
 *
 * @group github
 */
class GitHubAdapterTest extends TestCase
{
    // ── Factory / Construction ───────────────────────────────────────

    public function test_create_github_adapter_returns_instance(): void
    {
        $adapter = GitHubAdapter::fromConfig([
            'token' => 'test-token',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(GitHubAdapter::class, $adapter);
    }

    public function test_adapter_name_is_github(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertSame('github', $adapter->name());
    }

    public function test_default_user_name_is_github_bot(): void
    {
        $adapter = GitHubAdapter::fromConfig([
            'token' => 'test-token',
            'webhook_secret' => 'test-secret',
        ]);

        $this->assertSame('github-bot', $adapter->userName());
    }

    public function test_uses_provided_bot_name(): void
    {
        $adapter = GitHubAdapter::fromConfig([
            'token' => 'test-token',
            'webhook_secret' => 'test-secret',
            'bot_name' => 'my-github-app',
        ]);

        $this->assertSame('my-github-app', $adapter->userName());
    }

    // ── Thread ID Encoding ──────────────────────────────────────────

    public function test_encode_pr_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'owner' => 'acme',
            'repo' => 'app',
            'prNumber' => 123,
        ]);

        $this->assertSame('github:acme/app:123', $threadId);
    }

    public function test_encode_review_comment_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'owner' => 'acme',
            'repo' => 'app',
            'prNumber' => 123,
            'reviewCommentId' => 456789,
        ]);

        $this->assertSame('github:acme/app:123:rc:456789', $threadId);
    }

    public function test_encode_handles_special_characters_in_repo_names(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'owner' => 'my-org',
            'repo' => 'my-cool-app',
            'prNumber' => 42,
        ]);

        $this->assertSame('github:my-org/my-cool-app:42', $threadId);
    }

    // ── Thread ID Decoding ──────────────────────────────────────────

    public function test_decode_pr_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('github:acme/app:123');

        $this->assertSame('acme', $result['owner']);
        $this->assertSame('app', $result['repo']);
        $this->assertSame(123, $result['prNumber']);
    }

    public function test_decode_review_comment_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('github:acme/app:123:rc:456789');

        $this->assertSame('acme', $result['owner']);
        $this->assertSame('app', $result['repo']);
        $this->assertSame(123, $result['prNumber']);
        $this->assertSame(456789, $result['reviewCommentId']);
    }

    public function test_decode_throws_on_invalid_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid GitHub thread ID');
        $adapter->decodeThreadId('slack:C123:ts');
    }

    public function test_decode_throws_on_malformed_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid GitHub thread ID format');
        $adapter->decodeThreadId('github:invalid');
    }

    public function test_decode_handles_repo_names_with_hyphens(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('github:my-org/my-cool-app:42');

        $this->assertSame('my-org', $result['owner']);
        $this->assertSame('my-cool-app', $result['repo']);
        $this->assertSame(42, $result['prNumber']);
    }

    // ── Roundtrip ───────────────────────────────────────────────────

    public function test_roundtrip_pr_level_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'owner' => 'vercel',
            'repo' => 'next.js',
            'prNumber' => 99999,
        ];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['owner'], $decoded['owner']);
        $this->assertSame($original['repo'], $decoded['repo']);
        $this->assertSame($original['prNumber'], $decoded['prNumber']);
    }

    public function test_roundtrip_review_comment_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'owner' => 'vercel',
            'repo' => 'next.js',
            'prNumber' => 99999,
            'reviewCommentId' => 123456789,
        ];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['owner'], $decoded['owner']);
        $this->assertSame($original['repo'], $decoded['repo']);
        $this->assertSame($original['prNumber'], $decoded['prNumber']);
        $this->assertSame($original['reviewCommentId'], $decoded['reviewCommentId']);
    }

    // ── parseMessage ────────────────────────────────────────────────

    public function test_parses_pr_comment_message(): void
    {
        $adapter = $this->makeAdapter();

        $raw = [
            'comment' => [
                'id' => 123456,
                'body' => 'Looks good to me!',
                'user' => [
                    'login' => 'reviewer',
                    'id' => 789,
                ],
                'created_at' => '2025-01-29T12:00:00Z',
                'updated_at' => '2025-01-29T12:00:00Z',
            ],
            'pull_request' => [
                'number' => 42,
            ],
            'repository' => [
                'owner' => ['login' => 'acme'],
                'name' => 'app',
            ],
        ];

        $message = $adapter->parseMessage($raw);

        $this->assertSame('123456', (string) $message->id);
        $this->assertSame('Looks good to me!', $message->text);
        $this->assertSame('reviewer', $message->author->userName);
    }

    public function test_detects_edited_github_comment(): void
    {
        $adapter = $this->makeAdapter();

        $raw = [
            'comment' => [
                'id' => 123456,
                'body' => 'Updated comment',
                'user' => [
                    'login' => 'reviewer',
                    'id' => 789,
                ],
                'created_at' => '2025-01-29T12:00:00Z',
                'updated_at' => '2025-01-29T13:00:00Z',
            ],
            'pull_request' => [
                'number' => 42,
            ],
            'repository' => [
                'owner' => ['login' => 'acme'],
                'name' => 'app',
            ],
        ];

        $message = $adapter->parseMessage($raw);

        $this->assertTrue($message->metadata['edited']);
    }

    // ── renderFormatted ─────────────────────────────────────────────

    public function test_renders_simple_markdown_passthrough(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('Hello world');

        $this->assertStringContainsString('Hello world', $result);
    }

    public function test_renders_bold_markdown(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->renderFormatted('**bold**');

        $this->assertStringContainsString('**bold**', $result);
    }

    // ── Webhook Handling ────────────────────────────────────────────

    public function test_rejects_requests_without_signature_header(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'issue_comment',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['action' => 'created']));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_requests_with_invalid_signature(): void
    {
        $adapter = $this->makeAdapter();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            'HTTP_X_GITHUB_EVENT' => 'issue_comment',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['action' => 'created']));

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_accepts_requests_with_valid_hmac_signature(): void
    {
        $secret = 'test-webhook-secret';
        $adapter = GitHubAdapter::fromConfig([
            'token' => 'test-token',
            'webhook_secret' => $secret,
        ]);

        $body = json_encode([
            'action' => 'created',
            'comment' => [
                'id' => 1,
                'body' => 'test',
                'user' => ['login' => 'test', 'id' => 1],
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
            ],
            'issue' => ['number' => 1],
            'repository' => [
                'owner' => ['login' => 'acme'],
                'name' => 'app',
            ],
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'HTTP_X_GITHUB_EVENT' => 'issue_comment',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response = $adapter->handleWebhook($request, $this->makeChat());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeAdapter(): GitHubAdapter
    {
        return GitHubAdapter::fromConfig([
            'token' => 'test-token',
            'webhook_secret' => 'test-secret',
        ]);
    }

    private function makeChat(): \OpenCompany\Chatogrator\Chat
    {
        return $this->app->make(\OpenCompany\Chatogrator\Chat::class);
    }
}
