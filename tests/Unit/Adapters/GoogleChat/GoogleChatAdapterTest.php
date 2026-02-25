<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the Google Chat adapter — construction, thread ID encoding/decoding,
 * webhook handling, user info caching, and message handling.
 *
 * Ported from adapter-gchat/src/index.test.ts (12 tests).
 *
 * @group gchat
 */
class GoogleChatAdapterTest extends TestCase
{
    private const TEST_CREDENTIALS = [
        'client_email' => 'test@test.iam.gserviceaccount.com',
        'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
    ];

    // ── Factory / Construction ───────────────────────────────────────

    public function test_create_google_chat_adapter_factory_exists(): void
    {
        $adapter = GoogleChatAdapter::fromConfig([
            'credentials' => self::TEST_CREDENTIALS,
        ]);

        $this->assertInstanceOf(GoogleChatAdapter::class, $adapter);
    }

    public function test_adapter_name_is_gchat(): void
    {
        $adapter = GoogleChatAdapter::fromConfig([
            'credentials' => self::TEST_CREDENTIALS,
        ]);

        $this->assertSame('gchat', $adapter->name());
    }

    // ── Thread ID Encoding/Decoding ─────────────────────────────────

    public function test_encodes_and_decodes_thread_ids_without_thread_name(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'spaceName' => 'spaces/ABC123',
        ];

        $encoded = $adapter->encodeThreadId($original);
        $this->assertMatchesRegularExpression('/^gchat:/', $encoded);

        $decoded = $adapter->decodeThreadId($encoded);
        $this->assertSame($original['spaceName'], $decoded['spaceName']);
    }

    public function test_encodes_and_decodes_thread_ids_with_thread_name(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'spaceName' => 'spaces/ABC123',
            'threadName' => 'spaces/ABC123/threads/XYZ789',
        ];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['spaceName'], $decoded['spaceName']);
        $this->assertSame($original['threadName'], $decoded['threadName']);
    }

    // ── User Info Caching ───────────────────────────────────────────

    public function test_caches_user_info_from_direct_webhook_messages(): void
    {
        $adapter = $this->makeAdapter();
        $state = new MockStateAdapter;

        // Initialize adapter with state so caching works
        $adapter->setState($state);

        $event = [
            'chat' => [
                'messagePayload' => [
                    'space' => ['name' => 'spaces/ABC123', 'type' => 'ROOM'],
                    'message' => [
                        'name' => 'spaces/ABC123/messages/msg1',
                        'sender' => [
                            'name' => 'users/123456789',
                            'displayName' => 'John Doe',
                            'type' => 'HUMAN',
                            'email' => 'john@example.com',
                        ],
                        'text' => 'Hello',
                        'createTime' => now()->toISOString(),
                    ],
                ],
            ],
        ];

        $adapter->parseMessage($event);

        // Verify user info was cached
        $cached = $state->get('gchat:user:users/123456789');
        $this->assertNotNull($cached);
        $this->assertSame('John Doe', $cached['displayName']);
        $this->assertSame('john@example.com', $cached['email']);
    }

    public function test_does_not_cache_user_info_when_display_name_is_unknown(): void
    {
        $adapter = $this->makeAdapter();
        $state = new MockStateAdapter;
        $adapter->setState($state);

        $event = [
            'chat' => [
                'messagePayload' => [
                    'space' => ['name' => 'spaces/ABC123', 'type' => 'ROOM'],
                    'message' => [
                        'name' => 'spaces/ABC123/messages/msg1',
                        'sender' => [
                            'name' => 'users/123456789',
                            'displayName' => 'unknown',
                            'type' => 'HUMAN',
                        ],
                        'text' => 'Hello',
                        'createTime' => now()->toISOString(),
                    ],
                ],
            ],
        ];

        $adapter->parseMessage($event);

        // Verify user info was NOT cached
        $cached = $state->get('gchat:user:users/123456789');
        $this->assertNull($cached);
    }

    public function test_resolves_user_display_name_from_cache_for_pubsub_messages(): void
    {
        $adapter = $this->makeAdapter();
        $state = new MockStateAdapter;
        $adapter->setState($state);

        // Pre-populate cache
        $state->set('gchat:user:users/123456789', [
            'displayName' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $notification = [
            'eventType' => 'google.workspace.chat.message.v1.created',
            'targetResource' => '//chat.googleapis.com/spaces/ABC123',
            'message' => [
                'name' => 'spaces/ABC123/messages/msg1',
                'sender' => [
                    'name' => 'users/123456789',
                    'type' => 'HUMAN',
                    // Note: displayName is missing in Pub/Sub messages
                ],
                'text' => 'Hello from Pub/Sub',
                'createTime' => now()->toISOString(),
            ],
        ];

        $message = $adapter->parsePubSubMessage($notification, 'gchat:spaces/ABC123');

        $this->assertSame('Jane Smith', $message->author->fullName);
        $this->assertSame('Jane Smith', $message->author->userName);
    }

    public function test_falls_back_to_user_id_when_cache_miss(): void
    {
        $adapter = $this->makeAdapter();
        $state = new MockStateAdapter;
        $adapter->setState($state);

        $notification = [
            'eventType' => 'google.workspace.chat.message.v1.created',
            'targetResource' => '//chat.googleapis.com/spaces/ABC123',
            'message' => [
                'name' => 'spaces/ABC123/messages/msg1',
                'sender' => [
                    'name' => 'users/987654321',
                    'type' => 'HUMAN',
                ],
                'text' => 'Hello from unknown user',
                'createTime' => now()->toISOString(),
            ],
        ];

        $message = $adapter->parsePubSubMessage($notification, 'gchat:spaces/ABC123');

        // Should fall back to "User {numeric_id}"
        $this->assertSame('User 987654321', $message->author->fullName);
        $this->assertSame('User 987654321', $message->author->userName);
    }

    public function test_uses_provided_display_name_and_caches_it(): void
    {
        $adapter = $this->makeAdapter();
        $state = new MockStateAdapter;
        $adapter->setState($state);

        $notification = [
            'eventType' => 'google.workspace.chat.message.v1.created',
            'targetResource' => '//chat.googleapis.com/spaces/ABC123',
            'message' => [
                'name' => 'spaces/ABC123/messages/msg1',
                'sender' => [
                    'name' => 'users/111222333',
                    'displayName' => 'Bob Wilson',
                    'type' => 'HUMAN',
                ],
                'text' => 'Hello with displayName',
                'createTime' => now()->toISOString(),
            ],
        ];

        $message = $adapter->parsePubSubMessage($notification, 'gchat:spaces/ABC123');

        $this->assertSame('Bob Wilson', $message->author->fullName);

        // Should also cache the displayName for future use
        $cached = $state->get('gchat:user:users/111222333');
        $this->assertNotNull($cached);
        $this->assertSame('Bob Wilson', $cached['displayName']);
    }

    // ── isDM ────────────────────────────────────────────────────────

    public function test_is_dm_returns_true_for_dm_thread_ids(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'spaceName' => 'spaces/DM123',
            'isDM' => true,
        ]);

        $this->assertTrue($adapter->isDM($threadId));
    }

    public function test_is_dm_returns_false_for_room_thread_ids(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'spaceName' => 'spaces/ABC123',
        ]);

        $this->assertFalse($adapter->isDM($threadId));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeAdapter(): GoogleChatAdapter
    {
        return GoogleChatAdapter::fromConfig([
            'credentials' => self::TEST_CREDENTIALS,
        ]);
    }
}
