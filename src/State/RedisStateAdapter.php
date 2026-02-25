<?php

namespace OpenCompany\Chatogrator\State;

use Illuminate\Support\Facades\Redis;
use OpenCompany\Chatogrator\Contracts\StateAdapter;

class RedisStateAdapter implements StateAdapter
{
    protected string $prefix;

    protected string $connection;

    public function __construct(string $prefix = 'chatogrator', string $connection = 'default')
    {
        $this->prefix = $prefix;
        $this->connection = $connection;
    }

    public function connect(): void
    {
        //
    }

    public function disconnect(): void
    {
        //
    }

    protected function redis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection($this->connection);
    }

    protected function key(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }

    public function get(string $key): mixed
    {
        $value = $this->redis()->get($this->key($key));

        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return $decoded === null && $value !== 'null' ? $value : $decoded;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $encoded = json_encode($value);

        if ($ttlSeconds) {
            $this->redis()->setex($this->key($key), $ttlSeconds, $encoded);
        } else {
            $this->redis()->set($this->key($key), $encoded);
        }
    }

    public function delete(string $key): void
    {
        $this->redis()->del($this->key($key));
    }

    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?object
    {
        $lockKey = $this->key("lock:{$threadId}");
        $lockValue = bin2hex(random_bytes(16));

        // SET key value NX EX ttl — atomic lock acquisition
        $acquired = $this->redis()->set($lockKey, $lockValue, 'EX', $ttlSeconds, 'NX');

        if (! $acquired) {
            return null;
        }

        return (object) ['key' => $lockKey, 'value' => $lockValue];
    }

    public function extendLock(object $lock, int $ttlSeconds): bool
    {
        // Extend only if we still own the lock (compare-and-extend via Lua)
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("expire", KEYS[1], ARGV[2])
else
    return 0
end
LUA;

        return (bool) $this->redis()->eval($script, 1, $lock->key, $lock->value, $ttlSeconds);
    }

    public function releaseLock(object $lock): void
    {
        // Release only if we still own the lock (compare-and-delete via Lua)
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        $this->redis()->eval($script, 1, $lock->key, $lock->value);
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
