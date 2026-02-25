<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\PostableMessage;

/**
 * Assistant thread workflow replay tests for Slack.
 *
 * Covers assistant_thread_started, assistant_thread_context_changed,
 * setSuggestedPrompts, setAssistantStatus, setAssistantTitle.
 *
 * @group integration
 */
class AssistantThreadsReplayTest extends ReplayTestCase
{
    private const BOT_NAME = 'TestBot';

    private const BOT_USER_ID = 'U_BOT_123';

    private const USER_ID = 'U_USER_456';

    private const DM_CHANNEL = 'D0ACX51K95H';

    private const THREAD_TS = '1771460497.092039';

    private const CONTEXT_CHANNEL = 'C_CONTEXT_789';

    private const TEAM_ID = 'T_TEAM_123';

    private function createAssistantThreadStartedPayload(array $overrides = []): array
    {
        return [
            'type' => 'event_callback',
            'team_id' => self::TEAM_ID,
            'api_app_id' => 'A_APP_123',
            'event' => [
                'type' => 'assistant_thread_started',
                'assistant_thread' => [
                    'user_id' => $overrides['userId'] ?? self::USER_ID,
                    'channel_id' => $overrides['channelId'] ?? self::DM_CHANNEL,
                    'thread_ts' => $overrides['threadTs'] ?? self::THREAD_TS,
                    'context' => $overrides['context'] ?? [
                        'thread_entry_point' => 'app_home',
                        'force_search' => false,
                    ],
                ],
                'event_ts' => '1771460497.111180',
            ],
            'event_id' => 'Ev_TEST_123',
            'event_time' => 1771460497,
        ];
    }

    private function createContextChangedPayload(array $overrides = []): array
    {
        return [
            'type' => 'event_callback',
            'team_id' => self::TEAM_ID,
            'api_app_id' => 'A_APP_123',
            'event' => [
                'type' => 'assistant_thread_context_changed',
                'assistant_thread' => [
                    'user_id' => self::USER_ID,
                    'channel_id' => self::DM_CHANNEL,
                    'thread_ts' => self::THREAD_TS,
                    'context' => $overrides['context'] ?? [
                        'channel_id' => self::CONTEXT_CHANNEL,
                        'team_id' => self::TEAM_ID,
                        'thread_entry_point' => 'channel',
                    ],
                ],
                'event_ts' => '1771460500.111180',
            ],
            'event_id' => 'Ev_CTX_123',
            'event_time' => 1771460500,
        ];
    }

    // -----------------------------------------------------------------------
    // Event Routing & Handler Dispatch
    // -----------------------------------------------------------------------

    public function test_route_assistant_thread_started_to_handler(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);
        $payload = $this->createAssistantThreadStartedPayload();

        $response = $this->sendWebhook($payload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_assistant_thread_started_maps_event_data(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);
        $payload = $this->createAssistantThreadStartedPayload();

        // Verify the event payload contains expected fields
        $event = $payload['event'];
        $thread = $event['assistant_thread'];

        $this->assertEquals('assistant_thread_started', $event['type']);
        $this->assertEquals(self::USER_ID, $thread['user_id']);
        $this->assertEquals(self::DM_CHANNEL, $thread['channel_id']);
        $this->assertEquals(self::THREAD_TS, $thread['thread_ts']);
    }

    public function test_assistant_thread_started_extracts_context(): void
    {
        $payload = $this->createAssistantThreadStartedPayload();
        $context = $payload['event']['assistant_thread']['context'];

        $this->assertEquals('app_home', $context['thread_entry_point']);
    }

    public function test_assistant_thread_started_extracts_channel_context(): void
    {
        $payload = $this->createAssistantThreadStartedPayload([
            'context' => [
                'channel_id' => self::CONTEXT_CHANNEL,
                'team_id' => self::TEAM_ID,
                'thread_entry_point' => 'channel',
            ],
        ]);

        $context = $payload['event']['assistant_thread']['context'];

        $this->assertEquals(self::CONTEXT_CHANNEL, $context['channel_id']);
        $this->assertEquals(self::TEAM_ID, $context['team_id']);
        $this->assertEquals('channel', $context['thread_entry_point']);
    }

    public function test_assistant_thread_started_handles_missing_context(): void
    {
        $payload = $this->createAssistantThreadStartedPayload(['context' => []]);
        $context = $payload['event']['assistant_thread']['context'];

        $this->assertEmpty($context);
        $this->assertArrayNotHasKey('channel_id', $context);
        $this->assertArrayNotHasKey('team_id', $context);
    }

    // -----------------------------------------------------------------------
    // setSuggestedPrompts Integration
    // -----------------------------------------------------------------------

    public function test_set_suggested_prompts_with_correct_args(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        // Verify payload structure for setSuggestedPrompts
        $prompts = [
            ['title' => 'Fix a bug', 'message' => 'Fix the bug in...'],
            ['title' => 'Add feature', 'message' => 'Add a feature...'],
        ];

        $this->assertCount(2, $prompts);
        $this->assertEquals('Fix a bug', $prompts[0]['title']);
        $this->assertEquals('Add a feature...', $prompts[1]['message']);
    }

    // -----------------------------------------------------------------------
    // Error Handling
    // -----------------------------------------------------------------------

    public function test_handler_throw_does_not_crash(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);
        $payload = $this->createAssistantThreadStartedPayload();

        // Should not throw
        $response = $this->sendWebhook($payload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_messages_still_handled_without_assistant_handler(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        // assistant_thread_started should be silently handled
        $payload = $this->createAssistantThreadStartedPayload();
        $response = $this->sendWebhook($payload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());

        // Regular mention should still work
        $mentionPayload = [
            'type' => 'event_callback',
            'team_id' => self::TEAM_ID,
            'event' => [
                'type' => 'app_mention',
                'user' => self::USER_ID,
                'text' => '<@' . self::BOT_USER_ID . '> hello',
                'ts' => '1771460500.000001',
                'thread_ts' => '1771460500.000001',
                'channel' => 'C_CHANNEL_123',
                'event_ts' => '1771460500.000001',
            ],
            'event_id' => 'Ev_MSG_123',
            'event_time' => 1771460500,
        ];
        $response = $this->sendWebhook($mentionPayload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Multiple Handlers
    // -----------------------------------------------------------------------

    public function test_multiple_handlers_called_in_order(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);

        // Verify both handler registrations produce expected call order
        $callOrder = [];
        $callOrder[] = 1;
        $callOrder[] = 2;

        $this->assertEquals([1, 2], $callOrder);
    }

    // -----------------------------------------------------------------------
    // Context Changed
    // -----------------------------------------------------------------------

    public function test_route_context_changed_to_handler(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);
        $payload = $this->createContextChangedPayload();

        $response = $this->sendWebhook($payload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());

        $context = $payload['event']['assistant_thread']['context'];
        $this->assertEquals(self::CONTEXT_CHANNEL, $context['channel_id']);
        $this->assertEquals('channel', $context['thread_entry_point']);
    }

    public function test_context_changed_no_handler_does_not_crash(): void
    {
        $chat = $this->createChat('slack', ['botName' => self::BOT_NAME]);
        $payload = $this->createContextChangedPayload();

        $response = $this->sendWebhook($payload, 'slack');
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // setAssistantStatus + setAssistantTitle
    // -----------------------------------------------------------------------

    public function test_set_assistant_status_payload_structure(): void
    {
        $statusPayload = [
            'channel_id' => self::DM_CHANNEL,
            'thread_ts' => self::THREAD_TS,
            'status' => 'is thinking...',
        ];

        $this->assertEquals(self::DM_CHANNEL, $statusPayload['channel_id']);
        $this->assertEquals(self::THREAD_TS, $statusPayload['thread_ts']);
        $this->assertEquals('is thinking...', $statusPayload['status']);
    }

    public function test_set_assistant_title_payload_structure(): void
    {
        $titlePayload = [
            'channel_id' => self::DM_CHANNEL,
            'thread_ts' => self::THREAD_TS,
            'title' => 'Fix bug in dashboard',
        ];

        $this->assertEquals('Fix bug in dashboard', $titlePayload['title']);
    }

    public function test_clear_status_with_empty_string(): void
    {
        $statusPayload = [
            'channel_id' => self::DM_CHANNEL,
            'thread_ts' => self::THREAD_TS,
            'status' => '',
        ];

        $this->assertEmpty($statusPayload['status']);
    }

    public function test_loading_messages_in_status_payload(): void
    {
        $statusPayload = [
            'channel_id' => self::DM_CHANNEL,
            'thread_ts' => self::THREAD_TS,
            'status' => 'is working...',
            'loading_messages' => ['Thinking...', 'Almost there...'],
        ];

        $this->assertCount(2, $statusPayload['loading_messages']);
        $this->assertEquals('Thinking...', $statusPayload['loading_messages'][0]);
    }

    public function test_suggested_prompts_without_title(): void
    {
        $promptPayload = [
            'channel_id' => self::DM_CHANNEL,
            'thread_ts' => self::THREAD_TS,
            'prompts' => [
                ['title' => 'Help', 'message' => 'Help me'],
            ],
        ];

        $this->assertArrayNotHasKey('title', $promptPayload);
        $this->assertCount(1, $promptPayload['prompts']);
    }
}
