<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Errors\ValidationError;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Slack thread ID encoding, decoding, channel extraction,
 * and DM thread ID patterns.
 *
 * @group slack
 */
class SlackThreadsTest extends TestCase
{
    // ========================================================================
    // Thread ID Encoding
    // ========================================================================

    public function test_encode_thread_id_with_channel_and_ts(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C12345',
            'threadTs' => '1234567890.123456',
        ]);

        $this->assertSame('slack:C12345:1234567890.123456', $threadId);
    }

    public function test_encode_thread_id_uses_slack_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'CABC',
            'threadTs' => '999.000',
        ]);

        $this->assertStringStartsWith('slack:', $threadId);
    }

    public function test_encode_thread_id_with_dm_channel(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'D_DM_CHAN',
            'threadTs' => '1234567890.111111',
        ]);

        $this->assertSame('slack:D_DM_CHAN:1234567890.111111', $threadId);
    }

    public function test_encode_thread_id_with_private_channel(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'G_PRIVATE',
            'threadTs' => '1234567890.999999',
        ]);

        $this->assertSame('slack:G_PRIVATE:1234567890.999999', $threadId);
    }

    public function test_encode_thread_id_with_empty_thread_ts(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C12345',
            'threadTs' => '',
        ]);

        $this->assertSame('slack:C12345:', $threadId);
    }

    // ========================================================================
    // Thread ID Decoding
    // ========================================================================

    public function test_decode_full_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345:1234567890.123456');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('1234567890.123456', $result['threadTs']);
    }

    public function test_decode_thread_id_with_empty_ts(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345:');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    public function test_decode_channel_only_without_colon(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C12345');

        $this->assertSame('C12345', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    public function test_decode_dm_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:D_DM_CHAN:1234567890.111111');

        $this->assertSame('D_DM_CHAN', $result['channel']);
        $this->assertSame('1234567890.111111', $result['threadTs']);
    }

    public function test_decode_private_channel_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:G_PRIVATE:1234567890.999999');

        $this->assertSame('G_PRIVATE', $result['channel']);
        $this->assertSame('1234567890.999999', $result['threadTs']);
    }

    public function test_decode_throws_for_invalid_format(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('invalid');
    }

    public function test_decode_throws_for_slack_prefix_only(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack');
    }

    public function test_decode_throws_for_wrong_adapter_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('teams:C12345:123');
    }

    public function test_decode_throws_for_discord_prefix(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('discord:12345:67890');
    }

    public function test_decode_throws_for_too_many_segments(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('slack:A:B:C:D');
    }

    public function test_decode_throws_for_empty_string(): void
    {
        $adapter = $this->makeAdapter();

        $this->expectException(ValidationError::class);
        $adapter->decodeThreadId('');
    }

    // ========================================================================
    // Encode/Decode Roundtrip
    // ========================================================================

    public function test_encode_decode_roundtrip(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'channel' => 'C_TEST_ROUND',
            'threadTs' => '1609459200.000000',
        ];

        $threadId = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($threadId);

        $this->assertSame($original['channel'], $decoded['channel']);
        $this->assertSame($original['threadTs'], $decoded['threadTs']);
    }

    public function test_encode_decode_roundtrip_with_empty_ts(): void
    {
        $adapter = $this->makeAdapter();

        $original = [
            'channel' => 'D_DM_ROUND',
            'threadTs' => '',
        ];

        $threadId = $adapter->encodeThreadId($original);
        $decoded = $adapter->decodeThreadId($threadId);

        $this->assertSame($original['channel'], $decoded['channel']);
        $this->assertSame($original['threadTs'], $decoded['threadTs']);
    }

    // ========================================================================
    // Channel-Only Thread IDs
    // ========================================================================

    public function test_channel_only_thread_id_decodes_with_empty_ts(): void
    {
        $adapter = $this->makeAdapter();

        // A channel-only thread ID has no trailing colon or ts
        $result = $adapter->decodeThreadId('slack:C_CHANNEL');

        $this->assertSame('C_CHANNEL', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    public function test_channel_only_thread_id_with_trailing_colon(): void
    {
        $adapter = $this->makeAdapter();

        $result = $adapter->decodeThreadId('slack:C_CHANNEL:');

        $this->assertSame('C_CHANNEL', $result['channel']);
        $this->assertSame('', $result['threadTs']);
    }

    // ========================================================================
    // DM Thread ID Patterns
    // ========================================================================

    public function test_is_dm_for_d_prefix_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('slack:D12345:1234567890.123456'));
    }

    public function test_is_dm_for_d_prefix_channel_empty_ts(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('slack:D12345:'));
    }

    public function test_is_not_dm_for_c_prefix_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('slack:C12345:1234567890.123456'));
    }

    public function test_is_not_dm_for_g_prefix_channel(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertFalse($adapter->isDM('slack:G12345:1234567890.123456'));
    }

    public function test_is_dm_for_d_prefix_channel_only(): void
    {
        $adapter = $this->makeAdapter();

        $this->assertTrue($adapter->isDM('slack:D_USER_CHANNEL'));
    }

    // ========================================================================
    // Channel ID from Thread ID
    // ========================================================================

    public function test_channel_id_from_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $channelId = $adapter->channelIdFromThreadId('slack:C12345:1234567890.123456');

        $this->assertSame('C12345', $channelId);
    }

    public function test_channel_id_from_dm_thread_id(): void
    {
        $adapter = $this->makeAdapter();

        $channelId = $adapter->channelIdFromThreadId('slack:D12345:1234567890.123456');

        $this->assertSame('D12345', $channelId);
    }

    public function test_channel_id_from_thread_id_with_empty_ts(): void
    {
        $adapter = $this->makeAdapter();

        $channelId = $adapter->channelIdFromThreadId('slack:C12345:');

        $this->assertSame('C12345', $channelId);
    }

    // ========================================================================
    // Thread ID Format Validation
    // ========================================================================

    public function test_thread_id_format_is_slack_channel_ts(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C0123ABC',
            'threadTs' => '1609459200.000001',
        ]);

        // Expected format: slack:{channel}:{threadTs}
        $this->assertMatchesRegularExpression('/^slack:[A-Z0-9_]+:\d+\.\d+$/', $threadId);
    }

    public function test_thread_id_segments_count(): void
    {
        $adapter = $this->makeAdapter();

        $threadId = $adapter->encodeThreadId([
            'channel' => 'C12345',
            'threadTs' => '1234567890.123456',
        ]);

        $segments = explode(':', $threadId);
        $this->assertCount(3, $segments);
        $this->assertSame('slack', $segments[0]);
        $this->assertSame('C12345', $segments[1]);
        $this->assertSame('1234567890.123456', $segments[2]);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function makeAdapter(): SlackAdapter
    {
        return SlackAdapter::fromConfig([
            'bot_token' => 'xoxb-test-token',
            'signing_secret' => 'test-signing-secret',
        ]);
    }
}
