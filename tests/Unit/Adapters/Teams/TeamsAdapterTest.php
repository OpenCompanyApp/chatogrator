<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Teams;

use OpenCompany\Chatogrator\Adapters\Teams\TeamsAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for the Teams adapter — construction, thread ID encoding/decoding,
 * and basic message handling.
 *
 * Ported from adapter-teams/src/index.test.ts (5 tests).
 *
 * @group teams
 */
class TeamsAdapterTest extends TestCase
{
    // ── Factory / Construction ───────────────────────────────────────

    public function test_create_teams_adapter_factory_exists(): void
    {
        $adapter = TeamsAdapter::fromConfig([
            'app_id' => 'test-app-id',
            'app_password' => 'test-password',
        ]);

        $this->assertInstanceOf(TeamsAdapter::class, $adapter);
    }

    public function test_adapter_name_is_teams(): void
    {
        $adapter = TeamsAdapter::fromConfig([
            'app_id' => 'test-app-id',
            'app_password' => 'test-password',
        ]);

        $this->assertSame('teams', $adapter->name());
    }

    // ── Thread ID Encoding/Decoding ─────────────────────────────────

    public function test_encodes_and_decodes_thread_ids(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'conversationId' => '19:abc123@thread.tacv2',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
        ];

        $encoded = $adapter->encodeThreadId($original);

        $this->assertMatchesRegularExpression('/^teams:/', $encoded);

        $decoded = $adapter->decodeThreadId($encoded);

        $this->assertSame($original['conversationId'], $decoded['conversationId']);
        $this->assertSame($original['serviceUrl'], $decoded['serviceUrl']);
    }

    public function test_preserves_messageid_in_channel_thread_context(): void
    {
        $adapter = $this->makeAdapter();

        // Teams channel threads include ;messageid=XXX in the conversation ID
        // This thread context is needed to reply in the correct thread
        $original = [
            'conversationId' => '19:d441d38c655c47a085215b2726e76927@thread.tacv2;messageid=1767297849909',
            'serviceUrl' => 'https://smba.trafficmanager.net/amer/',
        ];

        $encoded = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($encoded);

        // The full conversation ID including messageid must be preserved
        $this->assertSame($original['conversationId'], $decoded['conversationId']);
        $this->assertStringContainsString(';messageid=', $decoded['conversationId']);
    }

    public function test_thread_id_uses_base64_encoding(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'conversationId' => '19:abc123@thread.tacv2',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
        ];

        $encoded = $adapter->encodeThreadId($original);

        // Format should be: teams:{b64(conversationId)}:{b64(serviceUrl)}
        $parts = explode(':', $encoded, 3);
        $this->assertSame('teams', $parts[0]);
        $this->assertCount(3, $parts);

        // Verify the base64-encoded parts can decode back
        $decodedConversation = base64_decode($parts[1]);
        $decodedService = base64_decode($parts[2]);

        $this->assertSame($original['conversationId'], $decodedConversation);
        $this->assertSame($original['serviceUrl'], $decodedService);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function makeAdapter(): TeamsAdapter
    {
        return TeamsAdapter::fromConfig([
            'app_id' => 'test',
            'app_password' => 'test',
        ]);
    }
}
