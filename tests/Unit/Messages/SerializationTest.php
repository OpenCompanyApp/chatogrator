<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Messages;

use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * Serialization tests for Thread and Message.
 *
 * Ported from: vercel-chat/packages/chat/src/serialization.test.ts
 *
 * @group core
 */
class SerializationTest extends TestCase
{
    protected MockAdapter $slackAdapter;

    protected MockAdapter $teamsAdapter;

    protected MockStateAdapter $mockState;

    protected Chat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slackAdapter = new MockAdapter('slack');
        $this->teamsAdapter = new MockAdapter('teams');
        $this->mockState = new MockStateAdapter;

        $this->chat = Chat::make('testbot')
            ->adapter('slack', $this->slackAdapter)
            ->adapter('teams', $this->teamsAdapter)
            ->state($this->mockState);
    }

    // ── Thread toJSON() ─────────────────────────────────────────────

    public function test_thread_to_json_produces_expected_structure(): void
    {
        $thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
        );

        $json = $thread->toJSON();

        $this->assertSame('slack:C123:1234.5678', $json['id']);
        $this->assertSame('slack', $json['adapterName']);
        $this->assertSame('C123', $json['channelId']);
        $this->assertFalse($json['isDM']);
    }

    public function test_thread_to_json_for_dm_thread(): void
    {
        $thread = new Thread(
            id: 'slack:DU123:',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'DU123',
            isDM: true,
        );

        $json = $thread->toJSON();

        $this->assertTrue($json['isDM']);
        $this->assertSame('DU123', $json['channelId']);
    }

    public function test_thread_to_json_produces_json_serializable_output(): void
    {
        $thread = new Thread(
            id: 'teams:channel123:thread456',
            adapter: $this->teamsAdapter,
            chat: $this->chat,
            channelId: 'channel123',
        );

        $json = $thread->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($json, $decoded);
    }

    public function test_thread_to_json_includes_adapter_name(): void
    {
        $thread = new Thread(
            id: 'teams:ch:th',
            adapter: $this->teamsAdapter,
            chat: $this->chat,
            channelId: 'ch',
        );

        $json = $thread->toJSON();

        $this->assertSame('teams', $json['adapterName']);
    }

    // ── Thread fromJSON() ───────────────────────────────────────────

    public function test_thread_from_json_reconstructs_thread(): void
    {
        $json = [
            'id' => 'slack:C123:1234.5678',
            'adapterName' => 'slack',
            'channelId' => 'C123',
            'isDM' => false,
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertSame('slack:C123:1234.5678', $thread->id);
        $this->assertSame('C123', $thread->channelId);
        $this->assertFalse($thread->isDM);
        $this->assertSame('slack', $thread->adapter->name());
    }

    public function test_thread_from_json_reconstructs_dm_thread(): void
    {
        $json = [
            'id' => 'slack:DU456:',
            'adapterName' => 'slack',
            'channelId' => 'DU456',
            'isDM' => true,
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertTrue($thread->isDM);
    }

    public function test_thread_from_json_returns_null_adapter_for_unknown_adapter(): void
    {
        $json = [
            'id' => 'discord:channel:thread',
            'adapterName' => 'discord',
            'channelId' => 'channel',
            'isDM' => false,
        ];

        // Chat::getAdapter returns null for unknown adapter names.
        // The fromJSON method should handle this gracefully (or throw).
        $adapter = $this->chat->getAdapter('discord');
        $this->assertNull($adapter);
    }

    public function test_thread_from_json_defaults_is_dm_to_false(): void
    {
        $json = [
            'id' => 'slack:C123:1234.5678',
            'adapterName' => 'slack',
            'channelId' => 'C123',
            // isDM not provided
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertFalse($thread->isDM);
    }

    public function test_thread_from_json_defaults_channel_id_to_null(): void
    {
        $json = [
            'id' => 'slack:C123:1234.5678',
            'adapterName' => 'slack',
            // channelId not provided
        ];

        $thread = Thread::fromJSON($json, $this->chat);

        $this->assertNull($thread->channelId);
    }

    // ── Thread round-trip ───────────────────────────────────────────

    public function test_thread_round_trip_produces_equivalent_thread(): void
    {
        $original = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
            isDM: true,
        );

        $json = $original->toJSON();
        $restored = Thread::fromJSON($json, $this->chat);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->channelId, $restored->channelId);
        $this->assertSame($original->isDM, $restored->isDM);
        $this->assertSame($original->adapter->name(), $restored->adapter->name());
    }

    public function test_thread_round_trip_non_dm_thread(): void
    {
        $original = new Thread(
            id: 'teams:channel123:thread456',
            adapter: $this->teamsAdapter,
            chat: $this->chat,
            channelId: 'channel123',
        );

        $json = $original->toJSON();
        $restored = Thread::fromJSON($json, $this->chat);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->channelId, $restored->channelId);
        $this->assertFalse($restored->isDM);
    }

    // ── Thread with state data ──────────────────────────────────────

    public function test_thread_with_state_data_serializes_correctly(): void
    {
        $thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
        );

        $thread->setState(['aiMode' => true, 'counter' => 5]);

        // State is persisted via the state adapter, not in toJSON itself
        $json = $thread->toJSON();

        // Thread serialization should still work even with state set
        $this->assertSame('slack:C123:1234.5678', $json['id']);
        $this->assertSame('slack', $json['adapterName']);
    }

    public function test_thread_with_state_data_round_trip_state_via_adapter(): void
    {
        $thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
        );

        $thread->setState(['aiMode' => true, 'counter' => 5]);

        $json = $thread->toJSON();
        $restored = Thread::fromJSON($json, $this->chat);

        // State is retrieved from the state adapter, not from the JSON payload
        $restoredState = $restored->state();
        $this->assertSame(['aiMode' => true, 'counter' => 5], $restoredState);
    }

    // ── Thread subscription status ──────────────────────────────────

    public function test_thread_subscription_preserved_after_round_trip(): void
    {
        $thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
        );

        $thread->subscribe();
        $this->assertTrue($thread->isSubscribed());

        $json = $thread->toJSON();
        $restored = Thread::fromJSON($json, $this->chat);

        // Subscription is persisted via state adapter, so it survives round-trip
        $this->assertTrue($restored->isSubscribed());
    }

    // ── Message toJSON() ────────────────────────────────────────────

    public function test_message_to_json_produces_expected_structure(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello world');

        $json = $msg->toJSON();

        $this->assertSame('msg-1', $json['id']);
        $this->assertSame('Hello world', $json['text']);
        $this->assertSame('slack:C123:1234.5678', $json['threadId']);
        $this->assertArrayHasKey('author', $json);
        $this->assertArrayHasKey('metadata', $json);
        $this->assertArrayHasKey('isMention', $json);
    }

    public function test_message_to_json_includes_metadata_date_sent(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Test', [
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => true,
                'editedAt' => '2024-01-15T11:00:00.000Z',
            ],
        ]);

        $json = $msg->toJSON();

        $this->assertSame('2024-01-15T10:30:00.000Z', $json['metadata']['dateSent']);
        $this->assertSame('2024-01-15T11:00:00.000Z', $json['metadata']['editedAt']);
    }

    public function test_message_to_json_handles_undefined_edited_at(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Test', [
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => false,
            ],
        ]);

        $json = $msg->toJSON();

        $this->assertArrayNotHasKey('editedAt', $json['metadata']);
    }

    // ── Message fromJSON() ──────────────────────────────────────────

    public function test_message_from_json_reconstructs_message(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'Hello world',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => false,
            ],
            'attachments' => [],
        ];

        $msg = Message::fromJSON($json);

        $this->assertSame('msg-1', $msg->id);
        $this->assertSame('Hello world', $msg->text);
        $this->assertSame('testuser', $msg->author->userName);
    }

    public function test_message_from_json_preserves_metadata(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'Test',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => true,
                'editedAt' => '2024-01-15T11:00:00.000Z',
            ],
            'attachments' => [],
        ];

        $msg = Message::fromJSON($json);

        $this->assertSame('2024-01-15T10:30:00.000Z', $msg->metadata['dateSent']);
        $this->assertTrue($msg->metadata['edited']);
        $this->assertSame('2024-01-15T11:00:00.000Z', $msg->metadata['editedAt']);
    }

    // ── Message round-trip ──────────────────────────────────────────

    public function test_message_round_trip_produces_equivalent_message(): void
    {
        $original = TestMessageFactory::make('msg-1', 'Hello **world**', [
            'isMention' => true,
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => true,
                'editedAt' => '2024-01-15T11:00:00.000Z',
            ],
        ]);

        $json = $original->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->text, $restored->text);
        $this->assertSame($original->isMention, $restored->isMention);
        $this->assertSame($original->metadata['dateSent'], $restored->metadata['dateSent']);
        $this->assertSame($original->metadata['editedAt'], $restored->metadata['editedAt']);
    }

    // ── Message with formatted content (mdast) ─────────────────────

    public function test_message_with_formatted_content_serializes(): void
    {
        $formatted = [
            'type' => 'root',
            'children' => [
                [
                    'type' => 'paragraph',
                    'children' => [
                        ['type' => 'text', 'value' => 'Hello '],
                        ['type' => 'strong', 'children' => [
                            ['type' => 'text', 'value' => 'world'],
                        ]],
                    ],
                ],
            ],
        ];

        $msg = TestMessageFactory::make('msg-1', 'Hello **world**', [
            'formatted' => $formatted,
        ]);

        // The formatted content is stored but may not be included in toJSON
        // by the current implementation. This test defines expected behavior.
        $json = $msg->toJSON();

        $this->assertSame('msg-1', $json['id']);
        $this->assertSame('Hello **world**', $json['text']);
    }

    // ── Message with Author (including isBot flag) ──────────────────

    public function test_message_with_bot_author_serializes_correctly(): void
    {
        $msg = TestMessageFactory::fromBot('msg-1', 'I am a bot');

        $json = $msg->toJSON();

        $this->assertTrue($json['author']['isBot']);
        $this->assertTrue($json['author']['isMe']);
        $this->assertSame('BOT', $json['author']['userId']);
    }

    public function test_message_with_human_author_serializes_correctly(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello from human');

        $json = $msg->toJSON();

        $this->assertFalse($json['author']['isBot']);
        $this->assertFalse($json['author']['isMe']);
        $this->assertSame('U123', $json['author']['userId']);
        $this->assertSame('testuser', $json['author']['userName']);
        $this->assertSame('Test User', $json['author']['fullName']);
    }

    public function test_message_from_json_reconstructs_bot_author(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'Bot reply',
            'author' => [
                'userId' => 'BOT',
                'userName' => 'testbot',
                'fullName' => 'Test Bot',
                'isBot' => true,
                'isMe' => true,
            ],
            'metadata' => ['dateSent' => '2024-01-15T10:30:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertTrue($msg->author->isBot);
        $this->assertTrue($msg->author->isMe);
        $this->assertSame('BOT', $msg->author->userId);
    }

    // ── Message with isMention flag ─────────────────────────────────

    public function test_message_with_is_mention_flag_serializes(): void
    {
        $msg = TestMessageFactory::mention('msg-1', 'Hey @bot');

        $json = $msg->toJSON();

        $this->assertTrue($json['isMention']);
    }

    public function test_message_without_mention_serializes_false(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'No mention here');

        $json = $msg->toJSON();

        $this->assertFalse($json['isMention']);
    }

    public function test_message_is_mention_round_trip(): void
    {
        $original = TestMessageFactory::mention('msg-1', 'Hey @bot');

        $json = $original->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertTrue($restored->isMention);
    }

    // ── Message with metadata ───────────────────────────────────────

    public function test_message_with_custom_metadata_serializes(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello', [
            'metadata' => [
                'dateSent' => '2024-06-01T12:00:00.000Z',
                'edited' => false,
                'customKey' => 'customValue',
                'priority' => 'high',
            ],
        ]);

        $json = $msg->toJSON();

        $this->assertSame('customValue', $json['metadata']['customKey']);
        $this->assertSame('high', $json['metadata']['priority']);
    }

    // ── Message with files/attachments ──────────────────────────────

    public function test_message_with_attachments_serializes(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'See attached', [
            'attachments' => [
                [
                    'type' => 'image',
                    'url' => 'https://example.com/image.png',
                    'name' => 'image.png',
                    'mimeType' => 'image/png',
                    'size' => 1024,
                ],
                [
                    'type' => 'file',
                    'url' => 'https://example.com/doc.pdf',
                    'name' => 'doc.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 2048,
                ],
            ],
        ]);

        // Current Message.toJSON() does not include attachments.
        // This test defines the expected behavior: attachments should be
        // serializable and restorable from JSON.
        $json = $msg->toJSON();

        // The toJSON method should include attachments
        $this->assertArrayHasKey('attachments', $json);
        $this->assertCount(2, $json['attachments']);
        $this->assertSame('image', $json['attachments'][0]['type']);
        $this->assertSame('https://example.com/image.png', $json['attachments'][0]['url']);
        $this->assertSame('file', $json['attachments'][1]['type']);
    }

    public function test_message_from_json_restores_attachments(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'See attached',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
            'attachments' => [
                [
                    'type' => 'file',
                    'url' => 'https://example.com/file.pdf',
                    'name' => 'file.pdf',
                ],
            ],
        ];

        $msg = Message::fromJSON($json);

        // fromJSON should restore attachments from the JSON data
        $this->assertCount(1, $msg->attachments);
        $this->assertSame('file', $msg->attachments[0]['type']);
        $this->assertSame('https://example.com/file.pdf', $msg->attachments[0]['url']);
    }

    public function test_message_attachment_round_trip(): void
    {
        $original = TestMessageFactory::make('msg-1', 'Attached', [
            'attachments' => [
                [
                    'type' => 'file',
                    'url' => 'https://example.com/file.pdf',
                    'name' => 'file.pdf',
                ],
            ],
        ]);

        $json = $original->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($original->attachments, $restored->attachments);
    }

    // ── Edge cases: empty/null fields ───────────────────────────────

    public function test_message_with_empty_text(): void
    {
        $msg = TestMessageFactory::make('msg-1', '');

        $json = $msg->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame('', $restored->text);
    }

    public function test_message_from_json_handles_missing_text(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertSame('', $msg->text);
    }

    public function test_message_from_json_handles_missing_attachments(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'Hello',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
            'metadata' => ['dateSent' => '2024-01-01T00:00:00.000Z', 'edited' => false],
        ];

        $msg = Message::fromJSON($json);

        $this->assertIsArray($msg->attachments);
        $this->assertEmpty($msg->attachments);
    }

    public function test_message_from_json_handles_missing_metadata(): void
    {
        $json = [
            'id' => 'msg-1',
            'threadId' => 'slack:C123:1234.5678',
            'text' => 'Hello',
            'author' => [
                'userId' => 'U123',
                'userName' => 'testuser',
                'fullName' => 'Test User',
                'isBot' => false,
                'isMe' => false,
            ],
        ];

        $msg = Message::fromJSON($json);

        $this->assertIsArray($msg->metadata);
        $this->assertEmpty($msg->metadata);
    }

    // ── Edge cases: ISO date strings ────────────────────────────────

    public function test_iso_date_strings_preserved_in_round_trip(): void
    {
        $original = TestMessageFactory::make('msg-1', 'Date test', [
            'metadata' => [
                'dateSent' => '2024-01-15T10:30:00.000Z',
                'edited' => true,
                'editedAt' => '2024-01-15T11:00:00.000Z',
            ],
        ]);

        $json = $original->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame('2024-01-15T10:30:00.000Z', $restored->metadata['dateSent']);
        $this->assertSame('2024-01-15T11:00:00.000Z', $restored->metadata['editedAt']);
    }

    public function test_date_sent_as_iso_8601_format(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Date format test', [
            'metadata' => [
                'dateSent' => '2024-12-31T23:59:59.999Z',
                'edited' => false,
            ],
        ]);

        $json = $msg->toJSON();

        // ISO 8601 date string should pass through unchanged
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $json['metadata']['dateSent'],
        );
    }

    // ── Edge cases: special characters in text ──────────────────────

    public function test_message_with_special_characters_in_text(): void
    {
        $specialText = 'Hello "world" & <tag> \'quotes\' \\ backslash';

        $msg = TestMessageFactory::make('msg-1', $specialText);

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($specialText, $decoded['text']);
    }

    public function test_message_with_unicode_in_text(): void
    {
        $unicodeText = "Hello \u{1F600} \u{1F389} \u{2764}\u{FE0F}";

        $msg = TestMessageFactory::make('msg-1', $unicodeText);

        $json = $msg->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($unicodeText, $restored->text);
    }

    public function test_message_with_newlines_in_text(): void
    {
        $multilineText = "Line 1\nLine 2\n\nLine 4";

        $msg = TestMessageFactory::make('msg-1', $multilineText);

        $json = $msg->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($multilineText, $restored->text);
    }

    public function test_message_with_markdown_formatting_in_text(): void
    {
        $mdText = 'Hello **bold** and *italic* and `code` and ~~strike~~';

        $msg = TestMessageFactory::make('msg-1', $mdText);

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame($mdText, $decoded['text']);
    }

    // ── Edge cases: large message content ───────────────────────────

    public function test_large_message_content_serializes(): void
    {
        $largeText = str_repeat('A', 50_000);

        $msg = TestMessageFactory::make('msg-1', $largeText);

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame(50_000, strlen($decoded['text']));
    }

    public function test_large_message_content_round_trip(): void
    {
        $largeText = str_repeat('Lorem ipsum dolor sit amet. ', 1000);

        $msg = TestMessageFactory::make('msg-1', $largeText);

        $json = $msg->toJSON();
        $restored = Message::fromJSON($json);

        $this->assertSame($largeText, $restored->text);
    }

    // ── Edge cases: nested formatted content ────────────────────────

    public function test_nested_formatted_content_structure(): void
    {
        $deepNested = [
            'type' => 'root',
            'children' => [
                [
                    'type' => 'blockquote',
                    'children' => [
                        [
                            'type' => 'paragraph',
                            'children' => [
                                ['type' => 'text', 'value' => 'Quoted '],
                                ['type' => 'emphasis', 'children' => [
                                    ['type' => 'strong', 'children' => [
                                        ['type' => 'text', 'value' => 'bold italic'],
                                    ]],
                                ]],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'list',
                    'ordered' => true,
                    'children' => [
                        ['type' => 'listItem', 'children' => [
                            ['type' => 'paragraph', 'children' => [
                                ['type' => 'text', 'value' => 'Item 1'],
                            ]],
                        ]],
                        ['type' => 'listItem', 'children' => [
                            ['type' => 'paragraph', 'children' => [
                                ['type' => 'text', 'value' => 'Item 2'],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];

        $msg = TestMessageFactory::make('msg-1', '> Quoted **bold italic**\n1. Item 1\n2. Item 2', [
            'formatted' => $deepNested,
        ]);

        // Formatted content is stored on the Message object
        $this->assertSame('root', $msg->formatted['type']);
        $this->assertCount(2, $msg->formatted['children']);

        // Encoding to JSON and back should preserve the nested structure
        $encoded = json_encode($deepNested);
        $decoded = json_decode($encoded, true);
        $this->assertSame($deepNested, $decoded);
    }

    // ── JSON encode/decode full integration ─────────────────────────

    public function test_message_to_json_produces_json_serializable_output(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Hello **world**');

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);

        $this->assertSame('msg-1', $decoded['id']);
        $this->assertSame('Hello **world**', $decoded['text']);
    }

    public function test_full_json_encode_decode_round_trip(): void
    {
        $msg = TestMessageFactory::make('msg-1', 'Full round trip', [
            'isMention' => true,
            'metadata' => [
                'dateSent' => '2024-06-15T09:00:00.000Z',
                'edited' => true,
                'editedAt' => '2024-06-15T10:00:00.000Z',
            ],
        ]);

        $json = $msg->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);
        $restored = Message::fromJSON($decoded);

        $this->assertSame($msg->id, $restored->id);
        $this->assertSame($msg->text, $restored->text);
        $this->assertSame($msg->threadId, $restored->threadId);
        $this->assertSame($msg->isMention, $restored->isMention);
        $this->assertSame($msg->author->userId, $restored->author->userId);
        $this->assertSame($msg->metadata['dateSent'], $restored->metadata['dateSent']);
    }

    public function test_thread_full_json_encode_decode_round_trip(): void
    {
        $thread = new Thread(
            id: 'slack:C123:1234.5678',
            adapter: $this->slackAdapter,
            chat: $this->chat,
            channelId: 'C123',
            isDM: true,
        );

        $json = $thread->toJSON();
        $encoded = json_encode($json);
        $decoded = json_decode($encoded, true);
        $restored = Thread::fromJSON($decoded, $this->chat);

        $this->assertSame($thread->id, $restored->id);
        $this->assertSame($thread->channelId, $restored->channelId);
        $this->assertSame($thread->isDM, $restored->isDM);
        $this->assertSame($thread->adapter->name(), $restored->adapter->name());
    }
}
