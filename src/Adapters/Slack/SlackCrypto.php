<?php

namespace OpenCompany\Chatogrator\Adapters\Slack;

class SlackCrypto
{
    /**
     * Verify a Slack request signature (HMAC-SHA256).
     */
    public static function verifySignature(
        string $body,
        string $signature,
        string $timestamp,
        string $secret,
        int $maxAge = 300
    ): bool {
        if ($signature === '' || $timestamp === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $maxAge) {
            return false;
        }

        $sigBasestring = "v0:{$timestamp}:{$body}";
        $expected = 'v0='.hash_hmac('sha256', $sigBasestring, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Encrypt a token using AES-256-GCM.
     *
     * @return array<string, string>
     */
    public static function encryptToken(string $token, string $key): array
    {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $token,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        return [
            'iv' => base64_encode($iv),
            'data' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }

    /**
     * Decrypt a token encrypted with encryptToken.
     *
     * @param  array<string, string>  $encrypted
     */
    public static function decryptToken(array $encrypted, string $key): string
    {
        $iv = base64_decode($encrypted['iv']);
        $data = base64_decode($encrypted['data']);
        $tag = base64_decode($encrypted['tag']);

        $plaintext = openssl_decrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    /**
     * Check if a value looks like encrypted token data.
     */
    public static function isEncryptedTokenData(mixed $data): bool
    {
        return is_array($data)
            && isset($data['iv'], $data['data'], $data['tag']);
    }
}
