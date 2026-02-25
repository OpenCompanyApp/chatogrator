<?php

namespace OpenCompany\Chatogrator\Adapters\Discord;

class DiscordCrypto
{
    /**
     * Check if a signature string has valid Ed25519 format.
     *
     * A valid Ed25519 signature is 64 bytes, represented as 128 hex characters.
     */
    public static function hasValidFormat(string $signature): bool
    {
        return strlen($signature) === 128 && ctype_xdigit($signature);
    }

    /**
     * Verify a Discord interaction signature using Ed25519.
     *
     * @param  string  $body  Raw request body
     * @param  string  $signature  Hex-encoded Ed25519 signature from X-Signature-Ed25519 header
     * @param  string  $timestamp  Timestamp from X-Signature-Timestamp header
     * @param  string  $publicKey  Hex-encoded 32-byte Ed25519 public key
     */
    public static function verifySignature(
        string $body,
        string $signature,
        string $timestamp,
        string $publicKey,
    ): bool {
        if ($signature === '' || $timestamp === '' || $publicKey === '') {
            return false;
        }

        if (! static::hasValidFormat($signature)) {
            return false;
        }

        // Public key must be 64 hex chars (32 bytes)
        if (strlen($publicKey) !== 64 || ! ctype_xdigit($publicKey)) {
            return false;
        }

        try {
            $signatureBytes = sodium_hex2bin($signature);
            $publicKeyBytes = sodium_hex2bin($publicKey);
            $message = $timestamp . $body;

            return sodium_crypto_sign_verify_detached($signatureBytes, $message, $publicKeyBytes);
        } catch (\SodiumException) {
            return false;
        }
    }
}
