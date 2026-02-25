<?php

namespace OpenCompany\Chatogrator\Tests\Helpers;

use OpenCompany\Chatogrator\Contracts\StateAdapter;

class MockStateAdapter implements StateAdapter
{
    /** @var array<string, mixed> */
    public array $cache = [];

    /** @var array<string, bool> */
    public array $subscriptions = [];

    /** @var array<string, object> */
    public array $locks = [];

    public bool $connected = false;

    /** @var array<int, array{method: string, args: array}> */
    public array $calls = [];

    public function connect(): void
    {
        $this->connected = true;
        $this->recordCall('connect');
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->recordCall('disconnect');
    }

    public function get(string $key): mixed
    {
        $this->recordCall('get', [$key]);

        return $this->cache[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->recordCall('set', [$key, $value, $ttlSeconds]);
        $this->cache[$key] = $value;
    }

    public function delete(string $key): void
    {
        $this->recordCall('delete', [$key]);
        unset($this->cache[$key]);
    }

    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?object
    {
        $this->recordCall('acquireLock', [$threadId, $ttlSeconds]);

        if (isset($this->locks[$threadId])) {
            return null;
        }

        $lock = (object) [
            'threadId' => $threadId,
            'token' => 'test-token-' . uniqid(),
            'expiresAt' => time() + $ttlSeconds,
        ];

        $this->locks[$threadId] = $lock;

        return $lock;
    }

    public function extendLock(object $lock, int $ttlSeconds): bool
    {
        $this->recordCall('extendLock', [$lock, $ttlSeconds]);

        if (! isset($this->locks[$lock->threadId])) {
            return false;
        }

        $this->locks[$lock->threadId]->expiresAt = time() + $ttlSeconds;

        return true;
    }

    public function releaseLock(object $lock): void
    {
        $this->recordCall('releaseLock', [$lock]);
        unset($this->locks[$lock->threadId]);
    }

    public function subscribe(string $threadId): void
    {
        $this->recordCall('subscribe', [$threadId]);
        $this->subscriptions[$threadId] = true;
    }

    public function unsubscribe(string $threadId): void
    {
        $this->recordCall('unsubscribe', [$threadId]);
        unset($this->subscriptions[$threadId]);
    }

    public function isSubscribed(string $threadId): bool
    {
        $this->recordCall('isSubscribed', [$threadId]);

        return $this->subscriptions[$threadId] ?? false;
    }

    /**
     * Check if a specific method was called.
     */
    public function wasCalledWith(string $method): array
    {
        return array_values(
            array_filter($this->calls, fn ($call) => $call['method'] === $method)
        );
    }

    /**
     * Count how many times a method was called.
     */
    public function callCount(string $method): int
    {
        return count($this->wasCalledWith($method));
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->subscriptions = [];
        $this->locks = [];
        $this->connected = false;
        $this->calls = [];
    }

    private function recordCall(string $method, array $args = []): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];
    }
}
