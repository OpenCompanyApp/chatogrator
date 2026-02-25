<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Messages;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group core
 */
class SentMessageTest extends TestCase
{
    protected SentMessage $sentMessage;

    protected MockAdapter $mockAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAdapter = new MockAdapter('slack');

        $this->sentMessage = new SentMessage(
            id: 'msg-1',
            threadId: 'slack:C123:1234.5678',
            text: 'Hello world',
            formatted: null,
            raw: [],
            author: new Author(
                userId: 'BOT_SLACK',
                userName: 'slack-bot',
                fullName: 'Slack Bot',
                isBot: true,
                isMe: true,
            ),
            metadata: ['dateSent' => '2024-01-15T10:30:00.000Z'],
            attachments: [],
            isMention: false,
        );

        $this->sentMessage->setAdapter($this->mockAdapter);
    }

    // ── edit() ──────────────────────────────────────────────────────

    public function test_edit_with_string_delegates_to_adapter(): void
    {
        $this->sentMessage->edit('Updated text');

        $this->assertNotEmpty($this->mockAdapter->editedMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->editedMessages[0]['threadId']);
        $this->assertSame('msg-1', $this->mockAdapter->editedMessages[0]['messageId']);
    }

    public function test_edit_with_postable_message_delegates_to_adapter(): void
    {
        $message = PostableMessage::markdown('**Updated**');

        $this->sentMessage->edit($message);

        $this->assertNotEmpty($this->mockAdapter->editedMessages);
        $this->assertSame('msg-1', $this->mockAdapter->editedMessages[0]['messageId']);
    }

    public function test_edit_returns_self_for_chaining(): void
    {
        $result = $this->sentMessage->edit('Updated');

        $this->assertSame($this->sentMessage, $result);
    }

    // ── delete() ────────────────────────────────────────────────────

    public function test_delete_delegates_to_adapter(): void
    {
        $this->sentMessage->delete();

        $this->assertNotEmpty($this->mockAdapter->deletedMessages);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->deletedMessages[0]['threadId']);
        $this->assertSame('msg-1', $this->mockAdapter->deletedMessages[0]['messageId']);
    }

    // ── addReaction() ───────────────────────────────────────────────

    public function test_add_reaction_delegates_to_adapter(): void
    {
        $this->sentMessage->addReaction('thumbs_up');

        $this->assertNotEmpty($this->mockAdapter->addedReactions);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->addedReactions[0]['threadId']);
        $this->assertSame('msg-1', $this->mockAdapter->addedReactions[0]['messageId']);
        $this->assertSame('thumbs_up', $this->mockAdapter->addedReactions[0]['emoji']);
    }

    // ── removeReaction() ────────────────────────────────────────────

    public function test_remove_reaction_delegates_to_adapter(): void
    {
        $this->sentMessage->removeReaction('thumbs_up');

        $this->assertNotEmpty($this->mockAdapter->removedReactions);
        $this->assertSame('slack:C123:1234.5678', $this->mockAdapter->removedReactions[0]['threadId']);
        $this->assertSame('msg-1', $this->mockAdapter->removedReactions[0]['messageId']);
        $this->assertSame('thumbs_up', $this->mockAdapter->removedReactions[0]['emoji']);
    }

    // ── Multiple operations ─────────────────────────────────────────

    public function test_multiple_reactions_are_tracked_independently(): void
    {
        $this->sentMessage->addReaction('thumbs_up');
        $this->sentMessage->addReaction('heart');
        $this->sentMessage->removeReaction('thumbs_up');

        $this->assertCount(2, $this->mockAdapter->addedReactions);
        $this->assertCount(1, $this->mockAdapter->removedReactions);

        $this->assertSame('thumbs_up', $this->mockAdapter->addedReactions[0]['emoji']);
        $this->assertSame('heart', $this->mockAdapter->addedReactions[1]['emoji']);
        $this->assertSame('thumbs_up', $this->mockAdapter->removedReactions[0]['emoji']);
    }

    // ── SentMessage inherits Message properties ─────────────────────

    public function test_inherits_message_properties(): void
    {
        $this->assertSame('msg-1', $this->sentMessage->id);
        $this->assertSame('slack:C123:1234.5678', $this->sentMessage->threadId);
        $this->assertSame('Hello world', $this->sentMessage->text);
        $this->assertTrue($this->sentMessage->author->isBot);
    }

    public function test_to_json_works_on_sent_message(): void
    {
        $json = $this->sentMessage->toJSON();

        $this->assertSame('msg-1', $json['id']);
        $this->assertSame('Hello world', $json['text']);
        $this->assertArrayHasKey('author', $json);
    }
}
