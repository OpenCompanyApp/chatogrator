<?php

namespace OpenCompany\Chatogrator\Tests\Helpers;

use Illuminate\Http\Request;
use RuntimeException;

class FixtureLoader
{
    /**
     * Load a JSON fixture file and decode it.
     */
    public static function load(string $path): array
    {
        $fullPath = static::resolvePath($path);

        if (! file_exists($fullPath)) {
            throw new RuntimeException("Fixture not found: {$fullPath}");
        }

        $contents = file_get_contents($fullPath);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Load a JSON fixture and create a Laravel Request from it.
     */
    public static function loadWebhookRequest(string $path, array $headers = []): Request
    {
        $data = static::load($path);

        $body = $data['body'] ?? $data;
        $method = $data['method'] ?? 'POST';
        $requestHeaders = array_merge($data['headers'] ?? [], $headers);

        $request = Request::create(
            uri: '/webhooks/chat/test',
            method: $method,
            content: is_string($body) ? $body : json_encode($body),
        );

        foreach ($requestHeaders as $key => $value) {
            $request->headers->set($key, $value);
        }

        if (! is_string($body)) {
            $request->headers->set('Content-Type', 'application/json');
        }

        return $request;
    }

    /**
     * Check if a fixture file exists.
     */
    public static function exists(string $path): bool
    {
        return file_exists(static::resolvePath($path));
    }

    private static function resolvePath(string $path): string
    {
        // If it's already an absolute path, use it
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Otherwise, resolve relative to the tests/fixtures directory
        return dirname(__DIR__) . '/fixtures/' . ltrim($path, '/');
    }
}
