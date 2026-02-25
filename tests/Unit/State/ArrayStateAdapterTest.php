<?php

namespace OpenCompany\Chatogrator\Tests\Unit\State;

use OpenCompany\Chatogrator\State\ArrayStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group core
 */
class ArrayStateAdapterTest extends TestCase
{
    protected ArrayStateAdapter $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = new ArrayStateAdapter;
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

    public function test_set_with_ttl_does_not_error(): void
    {
        // TTL is accepted but not enforced in the array adapter
        $this->state->set('key', 'value', 60);

        $this->assertSame('value', $this->state->get('key'));
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

    // ── acquireLock / releaseLock ───────────────────────────────────

    public function test_acquire_lock_returns_lock_object(): void
    {
        $lock = $this->state->acquireLock('thread-1');

        $this->assertNotNull($lock);
        $this->assertSame('thread-1', $lock->threadId);
    }

    public function test_cannot_acquire_lock_twice_for_same_thread(): void
    {
        $lock1 = $this->state->acquireLock('thread-1');
        $lock2 = $this->state->acquireLock('thread-1');

        $this->assertNotNull($lock1);
        $this->assertNull($lock2, 'Second lock acquisition should fail');
    }

    public function test_can_acquire_locks_for_different_threads(): void
    {
        $lock1 = $this->state->acquireLock('thread-1');
        $lock2 = $this->state->acquireLock('thread-2');

        $this->assertNotNull($lock1);
        $this->assertNotNull($lock2);
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

    public function test_extend_lock_returns_false_for_released_lock(): void
    {
        $lock = $this->state->acquireLock('thread-1');
        $this->state->releaseLock($lock);

        $result = $this->state->extendLock($lock, 60);

        $this->assertFalse($result);
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

    public function test_subscribe_is_idempotent(): void
    {
        $this->state->subscribe('thread-1');
        $this->state->subscribe('thread-1');

        $this->assertTrue($this->state->isSubscribed('thread-1'));

        // Unsubscribe once should remove
        $this->state->unsubscribe('thread-1');
        $this->assertFalse($this->state->isSubscribed('thread-1'));
    }

    // ── connect / disconnect ────────────────────────────────────────

    public function test_connect_does_not_error(): void
    {
        $this->state->connect();

        // No exception means success — ArrayStateAdapter.connect() is a no-op
        $this->assertTrue(true);
    }

    public function test_disconnect_clears_all_state(): void
    {
        $this->state->set('key', 'value');
        $this->state->subscribe('thread-1');
        $this->state->acquireLock('thread-1');

        $this->state->disconnect();

        $this->assertNull($this->state->get('key'));
        $this->assertFalse($this->state->isSubscribed('thread-1'));
        // Lock should also be cleared, so we can reacquire
        $lock = $this->state->acquireLock('thread-1');
        $this->assertNotNull($lock);
    }

    // ── TTL acceptance ──────────────────────────────────────────────

    public function test_acquire_lock_accepts_custom_ttl(): void
    {
        $lock = $this->state->acquireLock('thread-1', 120);

        $this->assertNotNull($lock);
    }

    // ── Mixed operations ────────────────────────────────────────────

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
