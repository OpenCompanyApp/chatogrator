<?php

namespace OpenCompany\Chatogrator\Contracts;

interface StateAdapter
{
    public function connect(): void;

    public function disconnect(): void;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;

    public function delete(string $key): void;

    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?object;

    public function extendLock(object $lock, int $ttlSeconds): bool;

    public function releaseLock(object $lock): void;

    public function subscribe(string $threadId): void;

    public function unsubscribe(string $threadId): void;

    public function isSubscribed(string $threadId): bool;
}
