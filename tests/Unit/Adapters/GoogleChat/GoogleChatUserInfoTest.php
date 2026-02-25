<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatUserInfo;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for extracting and caching user info from Google Chat event payloads.
 *
 * Ported from adapter-gchat/src/user-info.test.ts (14 tests).
 * Covers: extracting sender name/email/avatar URL, user type detection
 * (human vs bot), in-memory caching with state persistence fallback,
 * display name resolution, and skipping invalid/unknown display names.
 *
 * @group gchat
 */
class GoogleChatUserInfoTest extends TestCase
{
    // ── set ──────────────────────────────────────────────────────────

    public function test_stores_user_info_in_memory_and_persists_to_state(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $cache->set('users/123', 'John Doe', 'john@example.com');

        $result = $cache->get('users/123');
        $this->assertSame('John Doe', $result['displayName']);
        $this->assertSame('john@example.com', $result['email']);
    }

    public function test_skips_empty_display_names(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $cache->set('users/123', '');

        $this->assertNull($cache->get('users/123'));
    }

    public function test_skips_unknown_display_name(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $cache->set('users/123', 'unknown');

        $this->assertNull($cache->get('users/123'));
    }

    public function test_works_without_state_adapter(): void
    {
        $cache = new GoogleChatUserInfo(null);

        $cache->set('users/123', 'John Doe');

        $result = $cache->get('users/123');
        $this->assertSame('John Doe', $result['displayName']);
        $this->assertNull($result['email'] ?? null);
    }

    // ── get ──────────────────────────────────────────────────────────

    public function test_returns_from_in_memory_cache_first(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $cache->set('users/123', 'John Doe');

        // Clear state to verify in-memory is used
        $state->cache = [];

        $result = $cache->get('users/123');
        $this->assertSame('John Doe', $result['displayName']);
    }

    public function test_falls_back_to_state_adapter(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        // Set directly in state to simulate cold cache
        $state->set('gchat:user:users/456', [
            'displayName' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $result = $cache->get('users/456');
        $this->assertSame('Jane', $result['displayName']);
        $this->assertSame('jane@example.com', $result['email']);
    }

    public function test_populates_in_memory_cache_on_state_hit(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $state->set('gchat:user:users/789', [
            'displayName' => 'Bob',
        ]);

        // First get populates in-memory
        $cache->get('users/789');

        // Clear state; second get should use in-memory
        $state->cache = [];
        $result = $cache->get('users/789');
        $this->assertSame('Bob', $result['displayName']);
    }

    public function test_returns_null_for_unknown_users(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $this->assertNull($cache->get('users/unknown'));
    }

    public function test_returns_null_without_state_adapter_for_uncached_user(): void
    {
        $cache = new GoogleChatUserInfo(null);

        $this->assertNull($cache->get('users/unknown'));
    }

    // ── resolveDisplayName ──────────────────────────────────────────

    public function test_uses_provided_display_name(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $name = $cache->resolveDisplayName('users/123', 'John Doe', 'users/bot', 'chatbot');

        $this->assertSame('John Doe', $name);
    }

    public function test_skips_unknown_provided_name_and_uses_cache(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        // Pre-cache a name
        $cache->set('users/123', 'Cached Name');

        $name = $cache->resolveDisplayName('users/123', 'unknown', 'users/bot', 'chatbot');

        $this->assertSame('Cached Name', $name);
    }

    public function test_returns_bot_name_for_bot_user_id(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $name = $cache->resolveDisplayName('users/bot', null, 'users/bot', 'chatbot');

        $this->assertSame('chatbot', $name);
    }

    public function test_uses_cache_for_unknown_display_name(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $cache->set('users/456', 'Cached User');

        $name = $cache->resolveDisplayName('users/456', null, 'users/bot', 'chatbot');

        $this->assertSame('Cached User', $name);
    }

    public function test_falls_back_to_formatted_user_id(): void
    {
        $state = new MockStateAdapter;
        $cache = new GoogleChatUserInfo($state);

        $name = $cache->resolveDisplayName('users/999', null, 'users/bot', 'chatbot');

        $this->assertSame('User 999', $name);
    }
}
