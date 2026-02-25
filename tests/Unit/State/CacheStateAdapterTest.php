<?php

namespace OpenCompany\Chatogrator\Tests\Unit\State;

use Illuminate\Support\Facades\Cache;
use OpenCompany\Chatogrator\State\CacheStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the Laravel Cache-backed state adapter.
 *
 * Verifies get/set with TTL, set without TTL (forever), delete, lock
 * acquisition/release/extension, subscribe/unsubscribe/isSubscribed,
 * connect/disconnect no-ops, and key prefixing with "chatogrator:" prefix.
 *
 * @group core
 */
class CacheStateAdapterTest extends TestCase
{
    protected CacheStateAdapter $state;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the array cache driver for isolated testing
        $this->app['config']->set('cache.default', 'array');

        $this->state = new CacheStateAdapter;
    }

    // ── get / set / delete ──────────────────────────────────────────

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->state->get('nonexistent'));
    }

    public function test_set_and_get_string_value(): void
    {
        $this->state->set('key', 'value');

        $this->assertSame('value', $this->state->get('key'));
    }

    public function test_set_and_get_array_value(): void
    {
        $this->state->set('config', ['debug' => true, 'level' => 5]);

        $this->assertSame(['debug' => true, 'level' => 5], $this->state->get('config'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        $this->state->set('key', 'first');
        $this->state->set('key', 'second');

        $this->assertSame('second', $this->state->get('key'));
    }

    public function test_set_with_ttl_stores_value(): void
    {
        $this->state->set('key', 'value', 60);

        $this->assertSame('value', $this->state->get('key'));
    }

    public function test_set_without_ttl_stores_forever(): void
    {
        $this->state->set('key', 'permanent');

        // Value should persist without a TTL (stored via Cache::forever)
        $this->assertSame('permanent', $this->state->get('key'));
    }

    public function test_delete_removes_key(): void
    {
        $this->state->set('key', 'value');
        $this->state->delete('key');

        $this->assertNull($this->state->get('key'));
    }

    public function test_delete_nonexistent_key_does_not_error(): void
    {
        $this->state->delete('nonexistent');

        $this->assertNull($this->state->get('nonexistent'));
    }

    // ── Key prefixing ───────────────────────────────────────────────

    public function test_keys_are_prefixed_with_chatogrator(): void
    {
        $this->state->set('mykey', 'myvalue');

        // The CacheStateAdapter uses "chatogrator:" prefix, so the actual
        // cache key is "chatogrator:mykey"
        $this->assertSame('myvalue', Cache::get('chatogrator:mykey'));
    }

    public function test_custom_prefix_is_applied(): void
    {
        $state = new CacheStateAdapter('custom');
        $state->set('key', 'value');

        $this->assertSame('value', Cache::get('custom:key'));
        $this->assertNull(Cache::get('chatogrator:key'));
    }

    // ── acquireLock / releaseLock ───────────────────────────────────

    public function test_acquire_lock_returns_lock_object(): void
    {
        $lock = $this->state->acquireLock('thread-1');

        $this->assertNotNull($lock);
    }

    public function test_cannot_acquire_lock_twice_for_same_thread(): void
    {
        $lock1 = $this->state->acquireLock('thread-1');
        $lock2 = $this->state->acquireLock('thread-1');

        $this->assertNotNull($lock1);
        $this->assertNull($lock2, 'Second lock acquisition should fail');
    }

    public function test_release_lock_allows_reacquisition(): void
    {
        $lock1 = $this->state->acquireLock('thread-1');
        $this->state->releaseLock($lock1);

        $lock2 = $this->state->acquireLock('thread-1');

        $this->assertNotNull($lock2, 'Should be able to reacquire lock after release');
    }

    // ── extendLock ──────────────────────────────────────────────────

    public function test_extend_lock_returns_true_for_held_lock(): void
    {
        $lock = $this->state->acquireLock('thread-1');

        $result = $this->state->extendLock($lock, 60);

        $this->assertTrue($result);
    }

    // ── subscribe / unsubscribe / isSubscribed ──────────────────────

    public function test_is_subscribed_returns_false_initially(): void
    {
        $this->assertFalse($this->state->isSubscribed('thread-1'));
    }

    public function test_subscribe_makes_thread_subscribed(): void
    {
        $this->state->subscribe('thread-1');

        $this->assertTrue($this->state->isSubscribed('thread-1'));
    }

    public function test_unsubscribe_removes_subscription(): void
    {
        $this->state->subscribe('thread-1');
        $this->state->unsubscribe('thread-1');

        $this->assertFalse($this->state->isSubscribed('thread-1'));
    }

    public function test_unsubscribe_nonexistent_does_not_error(): void
    {
        $this->state->unsubscribe('thread-never-subscribed');

        $this->assertFalse($this->state->isSubscribed('thread-never-subscribed'));
    }

    public function test_multiple_threads_have_independent_subscriptions(): void
    {
        $this->state->subscribe('thread-1');
        $this->state->subscribe('thread-2');

        $this->assertTrue($this->state->isSubscribed('thread-1'));
        $this->assertTrue($this->state->isSubscribed('thread-2'));
        $this->assertFalse($this->state->isSubscribed('thread-3'));
    }

    // ── connect / disconnect (no-ops) ───────────────────────────────

    public function test_connect_does_not_error(): void
    {
        $this->state->connect();

        // No exception means success — CacheStateAdapter.connect() is a no-op
        $this->assertTrue(true);
    }

    public function test_disconnect_does_not_error(): void
    {
        $this->state->set('key', 'value');

        $this->state->disconnect();

        // Disconnect is a no-op; cache state is managed by Laravel
        $this->assertTrue(true);
    }

    // ── Subscription uses cache keys ────────────────────────────────

    public function test_subscription_uses_prefixed_cache_key(): void
    {
        $this->state->subscribe('thread-1');

        // Subscriptions are stored under "chatogrator:subscribed:{threadId}"
        $this->assertTrue((bool) Cache::get('chatogrator:subscribed:thread-1'));
    }

    public function test_state_and_subscriptions_are_independent(): void
    {
        $this->state->set('thread-1', 'some-state');
        $this->state->subscribe('thread-1');

        $this->assertSame('some-state', $this->state->get('thread-1'));
        $this->assertTrue($this->state->isSubscribed('thread-1'));

        $this->state->delete('thread-1');

        // Deleting the state key should not affect subscription
        $this->assertNull($this->state->get('thread-1'));
        $this->assertTrue($this->state->isSubscribed('thread-1'));
    }
}
