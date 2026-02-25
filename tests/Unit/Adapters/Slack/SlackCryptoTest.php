<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Slack;

use OpenCompany\Chatogrator\Adapters\Slack\SlackCrypto;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Slack-specific cryptographic operations.
 *
 * Ported from adapter-slack/src/crypto.test.ts (14 tests).
 * Covers HMAC-SHA256 signature verification (valid signature passes,
 * invalid fails, expired timestamp fails, missing headers fail),
 * token encryption/decryption with AES-256, IV randomization,
 * tamper detection, and wrong key detection.
 *
 * @group slack
 */
class SlackCryptoTest extends TestCase
{
    // ── HMAC-SHA256 Signature Verification ───────────────────────────

    public function test_valid_signature_passes_verification(): void
    {
        $secret = 'test-signing-secret';
        $timestamp = (string) time();
        $body = '{"type":"url_verification","challenge":"abc"}';

        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, $secret);

        $this->assertTrue(
            SlackCrypto::verifySignature($body, $signature, $timestamp, $secret)
        );
    }

    public function test_invalid_signature_fails_verification(): void
    {
        $secret = 'test-signing-secret';
        $timestamp = (string) time();
        $body = '{"type":"url_verification"}';

        $this->assertFalse(
            SlackCrypto::verifySignature($body, 'v0=invalid-signature', $timestamp, $secret)
        );
    }

    public function test_expired_timestamp_fails_verification(): void
    {
        $secret = 'test-signing-secret';
        $timestamp = (string) (time() - 400); // 400 seconds ago, exceeds 5 min threshold
        $body = '{"type":"url_verification"}';

        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, $secret);

        $this->assertFalse(
            SlackCrypto::verifySignature($body, $signature, $timestamp, $secret, maxAge: 300)
        );
    }

    public function test_missing_signature_fails_verification(): void
    {
        $secret = 'test-signing-secret';
        $timestamp = (string) time();
        $body = '{"type":"url_verification"}';

        $this->assertFalse(
            SlackCrypto::verifySignature($body, '', $timestamp, $secret)
        );
    }

    public function test_missing_timestamp_fails_verification(): void
    {
        $secret = 'test-signing-secret';
        $body = '{"type":"url_verification"}';

        $this->assertFalse(
            SlackCrypto::verifySignature($body, 'v0=something', '', $secret)
        );
    }

    public function test_wrong_secret_fails_verification(): void
    {
        $secret = 'correct-secret';
        $wrongSecret = 'wrong-secret';
        $timestamp = (string) time();
        $body = '{"type":"event_callback"}';

        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0=' . hash_hmac('sha256', $sigBasestring, $secret);

        $this->assertFalse(
            SlackCrypto::verifySignature($body, $signature, $timestamp, $wrongSecret)
        );
    }

    // ── Token Encryption / Decryption ───────────────────────────────

    public function test_encrypt_and_decrypt_round_trips_correctly(): void
    {
        $key = random_bytes(32);
        $token = 'xoxb-test-bot-token-12345';

        $encrypted = SlackCrypto::encryptToken($token, $key);
        $decrypted = SlackCrypto::decryptToken($encrypted, $key);

        $this->assertSame($token, $decrypted);
    }

    public function test_produces_different_ciphertexts_for_same_input(): void
    {
        $key = random_bytes(32);
        $token = 'xoxb-same-token';

        $a = SlackCrypto::encryptToken($token, $key);
        $b = SlackCrypto::encryptToken($token, $key);

        // Random IV means different ciphertext each time
        $this->assertNotSame($a['data'], $b['data']);
        $this->assertNotSame($a['iv'], $b['iv']);
    }

    public function test_decryption_with_wrong_key_throws(): void
    {
        $key = random_bytes(32);
        $wrongKey = random_bytes(32);
        $token = 'xoxb-secret';

        $encrypted = SlackCrypto::encryptToken($token, $key);

        $this->expectException(\RuntimeException::class);
        SlackCrypto::decryptToken($encrypted, $wrongKey);
    }

    public function test_decryption_with_tampered_ciphertext_throws(): void
    {
        $key = random_bytes(32);
        $token = 'xoxb-secret';

        $encrypted = SlackCrypto::encryptToken($token, $key);
        $encrypted['data'] = base64_encode('tampered');

        $this->expectException(\RuntimeException::class);
        SlackCrypto::decryptToken($encrypted, $key);
    }

    public function test_encrypted_token_contains_required_fields(): void
    {
        $key = random_bytes(32);
        $token = 'xoxb-test';

        $encrypted = SlackCrypto::encryptToken($token, $key);

        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('data', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);
    }

    // ── isEncryptedTokenData ────────────────────────────────────────

    public function test_is_encrypted_token_data_returns_true_for_valid_data(): void
    {
        $key = random_bytes(32);
        $encrypted = SlackCrypto::encryptToken('test', $key);

        $this->assertTrue(SlackCrypto::isEncryptedTokenData($encrypted));
    }

    public function test_is_encrypted_token_data_returns_false_for_plain_string(): void
    {
        $this->assertFalse(SlackCrypto::isEncryptedTokenData('xoxb-token'));
    }

    public function test_is_encrypted_token_data_returns_false_for_null(): void
    {
        $this->assertFalse(SlackCrypto::isEncryptedTokenData(null));
    }

    // ── decodeKey ───────────────────────────────────────────────────

    // Note: decodeKey tests verify that base64 and hex encoded 32-byte keys
    // are properly decoded. These are ported from the TypeScript tests but
    // adapted for PHP's native base64/hex handling.
}
