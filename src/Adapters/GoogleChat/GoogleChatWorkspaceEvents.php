<?php

namespace OpenCompany\Chatogrator\Adapters\GoogleChat;

class GoogleChatWorkspaceEvents
{
    /**
     * Decode a Pub/Sub push message into a normalized event payload.
     *
     * Extracts the base64-encoded data, CloudEvents attributes, and subscription info.
     *
     * @param  array<string, mixed>  $push
     * @return array<string, mixed>
     */
    public static function decodePubSubMessage(array $push): array
    {
        $message = $push['message'] ?? [];
        $subscription = $push['subscription'] ?? '';

        // Decode the base64 data
        $data = json_decode(base64_decode($message['data'] ?? ''), true) ?? [];

        // Extract CloudEvents attributes
        $attributes = $message['attributes'] ?? [];

        $eventType = $attributes['ce-type'] ?? '';
        $targetResource = $attributes['ce-subject'] ?? '';
        $eventTime = $attributes['ce-time'] ?? ($message['publishTime'] ?? '');

        return array_merge($data, [
            'subscription' => $subscription,
            'eventType' => $eventType,
            'targetResource' => $targetResource,
            'eventTime' => $eventTime,
        ]);
    }

    /**
     * Verify that a Pub/Sub push request has valid method and content type.
     */
    public static function verifyPubSubRequest(string $method, string $contentType): bool
    {
        if (strtoupper($method) !== 'POST') {
            return false;
        }

        // Accept "application/json" or "application/json; charset=utf-8"
        if (! str_starts_with($contentType, 'application/json')) {
            return false;
        }

        return true;
    }
}
