<?php

namespace OpenCompany\Chatogrator\State;

use OpenCompany\Chatogrator\Contracts\StateAdapter;

class ArrayStateAdapter implements StateAdapter
{
    /** @var array<string, mixed> */
    protected array $store = [];

    /** @var array<string, bool> */
    protected array $locks = [];

    /** @var array<string, bool> */
    protected array $subscriptions = [];

    public function connect(): void
    {
        //
    }

    public function disconnect(): void
    {
        $this->store = [];
        $this->locks = [];
        $this->subscriptions = [];
    }

    public function get(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->store[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?object
    {
        if (isset($this->locks[$threadId])) {
            return null;
        }

        $this->locks[$threadId] = true;

        return (object) ['threadId' => $threadId];
    }

    public function extendLock(object $lock, int $ttlSeconds): bool
    {
        return isset($this->locks[$lock->threadId]);
    }

    public function releaseLock(object $lock): void
    {
        unset($this->locks[$lock->threadId]);
    }

    public function subscribe(string $threadId): void
    {
        $this->subscriptions[$threadId] = true;
    }

    public function unsubscribe(string $threadId): void
    {
        unset($this->subscriptions[$threadId]);
    }

    public function isSubscribed(string $threadId): bool
    {
        return $this->subscriptions[$threadId] ?? false;
    }
}
