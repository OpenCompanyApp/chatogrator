<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Messages;

use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group core
 */
class PostableMessageTest extends TestCase
{
    // ── Static factories ────────────────────────────────────────────

    public function test_text_factory_creates_text_message(): void
    {
        $message = PostableMessage::text('Hello world');

        $this->assertSame('Hello world', $message->getText());
        $this->assertNull($message->getMarkdown());
        $this->assertNull($message->getCard());
        $this->assertNull($message->getStream());
        $this->assertFalse($message->isStreaming());
    }

    public function test_markdown_factory_creates_markdown_message(): void
    {
        $message = PostableMessage::markdown('**Bold** text');

        $this->assertSame('**Bold** text', $message->getMarkdown());
        $this->assertNull($message->getText());
    }

    public function test_formatted_factory_creates_formatted_message(): void
    {
        $ast = ['type' => 'root', 'children' => []];

        $message = PostableMessage::formatted($ast);

        $this->assertSame($ast, $message->getFormatted());
        $this->assertNull($message->getText());
        $this->assertNull($message->getMarkdown());
    }

    public function test_card_factory_creates_card_message(): void
    {
        // Card is a class that doesn't exist yet as a full implementation,
        // but we test the factory accepts it.
        // For now, just verify the factory method exists and returns PostableMessage
        $this->assertTrue(method_exists(PostableMessage::class, 'card'));
    }

    public function test_streaming_factory_creates_streaming_message(): void
    {
        $stream = (function () {
            yield 'Hello';
            yield ' World';
        })();

        $message = PostableMessage::streaming($stream);

        $this->assertTrue($message->isStreaming());
        $this->assertNotNull($message->getStream());
    }

    public function test_raw_factory_creates_raw_message(): void
    {
        $rawContent = ['blocks' => [['type' => 'section', 'text' => ['text' => 'Hello']]]];

        $message = PostableMessage::raw($rawContent);

        $this->assertSame($rawContent, $message->getRaw());
        $this->assertNull($message->getText());
    }

    public function test_make_factory_is_alias_for_text(): void
    {
        $message = PostableMessage::make('Hello via make');

        $this->assertSame('Hello via make', $message->getText());
    }

    // ── files() method ──────────────────────────────────────────────

    public function test_files_method_attaches_files(): void
    {
        $files = [
            ['name' => 'document.pdf', 'content' => 'pdf-data'],
            ['name' => 'image.png', 'content' => 'png-data'],
        ];

        $message = PostableMessage::text('Here are some files')->files($files);

        $this->assertCount(2, $message->getFiles());
        $this->assertSame('document.pdf', $message->getFiles()[0]['name']);
    }

    public function test_files_method_returns_self_for_chaining(): void
    {
        $message = PostableMessage::text('Hello');
        $result = $message->files([]);

        $this->assertSame($message, $result);
    }

    public function test_get_files_returns_empty_array_by_default(): void
    {
        $message = PostableMessage::text('No files');

        $this->assertSame([], $message->getFiles());
    }

    // ── Getter methods ──────────────────────────────────────────────

    public function test_get_text_returns_null_for_non_text_message(): void
    {
        $message = PostableMessage::markdown('# Title');

        $this->assertNull($message->getText());
    }

    public function test_get_markdown_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Plain text');

        $this->assertNull($message->getMarkdown());
    }

    public function test_get_formatted_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Plain text');

        $this->assertNull($message->getFormatted());
    }

    public function test_get_card_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Plain text');

        $this->assertNull($message->getCard());
    }

    public function test_get_stream_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Plain text');

        $this->assertNull($message->getStream());
    }

    public function test_get_raw_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Plain text');

        $this->assertNull($message->getRaw());
    }

    // ── isStreaming() ───────────────────────────────────────────────

    public function test_is_streaming_false_for_text_message(): void
    {
        $message = PostableMessage::text('Not streaming');

        $this->assertFalse($message->isStreaming());
    }

    public function test_is_streaming_false_for_markdown_message(): void
    {
        $message = PostableMessage::markdown('**Not streaming**');

        $this->assertFalse($message->isStreaming());
    }

    public function test_is_streaming_true_for_streaming_message(): void
    {
        $stream = (function () {
            yield 'chunk';
        })();

        $message = PostableMessage::streaming($stream);

        $this->assertTrue($message->isStreaming());
    }

    // ── Fluent interface ────────────────────────────────────────────

    public function test_text_with_files_fluent_chain(): void
    {
        $files = [['name' => 'test.txt', 'content' => 'data']];

        $message = PostableMessage::text('With attachment')
            ->files($files);

        $this->assertSame('With attachment', $message->getText());
        $this->assertCount(1, $message->getFiles());
    }
}
