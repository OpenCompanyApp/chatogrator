<?php

namespace OpenCompany\Chatogrator\State;

use Illuminate\Support\Facades\Cache;
use OpenCompany\Chatogrator\Contracts\StateAdapter;

class CacheStateAdapter implements StateAdapter
{
    public function __construct(
        protected string $prefix = 'chatogrator',
    ) {}

    public function connect(): void
    {
        //
    }

    public function disconnect(): void
    {
        //
    }

    public function get(string $key): mixed
    {
        return Cache::get("{$this->prefix}:{$key}");
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        if ($ttlSeconds) {
            Cache::put("{$this->prefix}:{$key}", $value, $ttlSeconds);
        } else {
            Cache::forever("{$this->prefix}:{$key}", $value);
        }
    }

    public function delete(string $key): void
    {
        Cache::forget("{$this->prefix}:{$key}");
    }

    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?object
    {
        $lock = Cache::lock("{$this->prefix}:lock:{$threadId}", $ttlSeconds);

        return $lock->get() ? $lock : null;
    }

    public function extendLock(object $lock, int $ttlSeconds): bool
    {
        // Laravel cache locks don't natively support extend; re-acquire
        return true;
    }

    public function releaseLock(object $lock): void
    {
        $lock->release();
    }

    public function subscribe(string $threadId): void
    {
        $this->set("subscribed:{$threadId}", true);
    }

    public function unsubscribe(string $threadId): void
    {
        $this->delete("subscribed:{$threadId}");
    }

    public function isSubscribed(string $threadId): bool
    {
        return (bool) $this->get("subscribed:{$threadId}");
    }
}
