<?php

namespace OpenCompany\Chatogrator\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Contracts\StateAdapter;

class GoogleChatUserInfo
{
    /** @var array<string, array{displayName: string, email?: string|null}> */
    protected array $memoryCache = [];

    public function __construct(
        protected ?StateAdapter $state,
    ) {}

    /**
     * Store user info in memory and optionally persist to state adapter.
     */
    public function set(string $userId, string $displayName, ?string $email = null): void
    {
        // Skip empty or "unknown" display names
        if ($displayName === '' || strtolower($displayName) === 'unknown') {
            return;
        }

        $data = ['displayName' => $displayName];
        if ($email !== null) {
            $data['email'] = $email;
        }

        // Store in memory
        $this->memoryCache[$userId] = $data;

        // Persist to state adapter
        $this->state?->set($this->stateKey($userId), $data);
    }

    /**
     * Get user info from memory cache, falling back to state adapter.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $userId): ?array
    {
        // Check in-memory cache first
        if (isset($this->memoryCache[$userId])) {
            return $this->memoryCache[$userId];
        }

        // Fall back to state adapter
        if ($this->state !== null) {
            $data = $this->state->get($this->stateKey($userId));
            if ($data !== null) {
                // Populate in-memory cache
                $this->memoryCache[$userId] = $data;

                return $data;
            }
        }

        return null;
    }

    /**
     * Resolve a display name for a user, checking provided name, cache, and fallbacks.
     */
    public function resolveDisplayName(
        string $userId,
        ?string $providedName,
        string $botUserId,
        string $botName,
    ): string {
        // If this is the bot itself, return bot name
        if ($userId === $botUserId) {
            return $botName;
        }

        // Use provided name if valid (not null, not empty, not "unknown")
        if ($providedName !== null && $providedName !== '' && strtolower($providedName) !== 'unknown') {
            return $providedName;
        }

        // Try cache
        $cached = $this->get($userId);
        if ($cached !== null) {
            return $cached['displayName'];
        }

        // Fall back to formatted user ID
        $numericId = str_replace('users/', '', $userId);

        return "User {$numericId}";
    }

    protected function stateKey(string $userId): string
    {
        return "gchat:user:{$userId}";
    }
}
