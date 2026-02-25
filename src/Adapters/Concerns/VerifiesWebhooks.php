<?php

namespace OpenCompany\Chatogrator\Adapters\Concerns;

trait VerifiesWebhooks
{
    protected function verifyHmacSha256(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    protected function verifyEd25519(string $signature, string $timestamp, string $body, string $publicKey): bool
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $message = $timestamp.$body;

        return sodium_crypto_sign_verify_detached(
            hex2bin($signature),
            $message,
            hex2bin($publicKey),
        );
    }
}
