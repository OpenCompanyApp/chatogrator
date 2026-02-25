<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\GoogleChat;

use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatWorkspaceEvents;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for Google Chat workspace/Pub/Sub event handling.
 *
 * Ported from adapter-gchat/src/workspace-events.test.ts (8 tests).
 * Covers: decoding base64 Pub/Sub message payloads, extracting CloudEvents
 * attributes, handling missing attributes, decoding reaction payloads,
 * verifying Pub/Sub request method/content-type.
 *
 * @group gchat
 */
class GoogleChatWorkspaceEventsTest extends TestCase
{
    // ── decodePubSubMessage ──────────────────────────────────────────

    public function test_decodes_base64_message_payload(): void
    {
        $push = $this->makePubSubMessage([
            'message' => ['text' => 'Hello world', 'name' => 'spaces/ABC/messages/123'],
        ]);

        $result = GoogleChatWorkspaceEvents::decodePubSubMessage($push);

        $this->assertSame('Hello world', $result['message']['text']);
        $this->assertSame(
            'projects/my-project/subscriptions/my-sub',
            $result['subscription']
        );
    }

    public function test_extracts_cloud_events_attributes(): void
    {
        $push = $this->makePubSubMessage(
            ['message' => ['text' => 'test']],
            [
                'ce-type' => 'google.workspace.chat.message.v1.created',
                'ce-subject' => '//chat.googleapis.com/spaces/ABC',
                'ce-time' => '2024-01-15T10:00:00Z',
            ]
        );

        $result = GoogleChatWorkspaceEvents::decodePubSubMessage($push);

        $this->assertSame('google.workspace.chat.message.v1.created', $result['eventType']);
        $this->assertSame('//chat.googleapis.com/spaces/ABC', $result['targetResource']);
        $this->assertSame('2024-01-15T10:00:00Z', $result['eventTime']);
    }

    public function test_handles_missing_attributes(): void
    {
        $push = $this->makePubSubMessage(['message' => ['text' => 'test']]);

        $result = GoogleChatWorkspaceEvents::decodePubSubMessage($push);

        $this->assertSame('', $result['eventType']);
        $this->assertSame('', $result['targetResource']);
        // Falls back to publishTime when ce-time is missing
        $this->assertSame('2024-01-15T10:00:00Z', $result['eventTime']);
    }

    public function test_decodes_reaction_payload(): void
    {
        $push = $this->makePubSubMessage(
            [
                'reaction' => [
                    'name' => 'spaces/ABC/messages/123/reactions/456',
                    'emoji' => ['unicode' => "\u{1F44D}"],
                ],
            ],
            ['ce-type' => 'google.workspace.chat.reaction.v1.created']
        );

        $result = GoogleChatWorkspaceEvents::decodePubSubMessage($push);

        $this->assertSame(
            'spaces/ABC/messages/123/reactions/456',
            $result['reaction']['name']
        );
        $this->assertSame("\u{1F44D}", $result['reaction']['emoji']['unicode']);
    }

    // ── verifyPubSubRequest ──────────────────────────────────────────

    public function test_rejects_non_post_requests(): void
    {
        $this->assertFalse(
            GoogleChatWorkspaceEvents::verifyPubSubRequest('GET', 'application/json')
        );
    }

    public function test_rejects_wrong_content_type(): void
    {
        $this->assertFalse(
            GoogleChatWorkspaceEvents::verifyPubSubRequest('POST', 'text/plain')
        );
    }

    public function test_accepts_valid_post_with_json_content_type(): void
    {
        $this->assertTrue(
            GoogleChatWorkspaceEvents::verifyPubSubRequest('POST', 'application/json')
        );
    }

    public function test_accepts_json_with_charset(): void
    {
        $this->assertTrue(
            GoogleChatWorkspaceEvents::verifyPubSubRequest('POST', 'application/json; charset=utf-8')
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Build a Pub/Sub push message structure.
     */
    private function makePubSubMessage(array $payload, ?array $attributes = null): array
    {
        $message = [
            'data' => base64_encode(json_encode($payload)),
            'messageId' => 'msg-123',
            'publishTime' => '2024-01-15T10:00:00Z',
        ];

        if ($attributes !== null) {
            $message['attributes'] = $attributes;
        }

        return [
            'message' => $message,
            'subscription' => 'projects/my-project/subscriptions/my-sub',
        ];
    }
}
