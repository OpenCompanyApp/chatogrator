<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Shared;

use OpenCompany\Chatogrator\Adapters\AdapterUtils;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for shared adapter utility functions.
 *
 * Ported from adapter-shared/src/adapter-utils.test.ts (26 tests).
 * Covers: extracting cards from PostableMessage, extracting files from
 * PostableMessage, handling nested PostableMessage structures, and
 * null/empty handling across all message types.
 *
 * @group core
 */
class AdapterUtilsTest extends TestCase
{
    // ── extractCard — with Card object ──────────────────────────────

    public function test_extracts_card_from_card_message(): void
    {
        $card = Card::make('Test Card');
        $message = PostableMessage::card($card);

        $result = AdapterUtils::extractCard($message);

        $this->assertSame($card, $result);
    }

    public function test_extracts_card_with_all_properties(): void
    {
        $card = Card::make('Order #123')
            ->subtitle('Processing')
            ->imageUrl('https://example.com/img.png');

        $message = PostableMessage::card($card);

        $result = AdapterUtils::extractCard($message);

        $this->assertNotNull($result);
        $this->assertSame('Order #123', $result->getTitle());
        $this->assertSame('Processing', $result->getSubtitle());
    }

    public function test_extracts_card_from_card_message_with_files(): void
    {
        $card = Card::make('With Files');
        $message = PostableMessage::card($card)->files([
            ['data' => 'test', 'filename' => 'test.txt'],
        ]);

        $result = AdapterUtils::extractCard($message);

        $this->assertSame($card, $result);
    }

    // ── extractCard — with non-card messages ────────────────────────

    public function test_returns_null_for_text_message(): void
    {
        $message = PostableMessage::text('Hello world');

        $this->assertNull(AdapterUtils::extractCard($message));
    }

    public function test_returns_null_for_raw_message(): void
    {
        $message = PostableMessage::raw('Raw text');

        $this->assertNull(AdapterUtils::extractCard($message));
    }

    public function test_returns_null_for_markdown_message(): void
    {
        $message = PostableMessage::markdown('**Bold** text');

        $this->assertNull(AdapterUtils::extractCard($message));
    }

    public function test_returns_null_for_formatted_message(): void
    {
        $message = PostableMessage::formatted(['type' => 'root', 'children' => []]);

        $this->assertNull(AdapterUtils::extractCard($message));
    }

    public function test_extract_card_returns_null_for_null_input(): void
    {
        $this->assertNull(AdapterUtils::extractCard(null));
    }

    public function test_extract_card_returns_null_for_empty_postable_message(): void
    {
        $message = new PostableMessage;

        $this->assertNull(AdapterUtils::extractCard($message));
    }

    // ── extractFiles — with files present ───────────────────────────

    public function test_extracts_files_from_raw_message(): void
    {
        $files = [
            ['data' => 'content1', 'filename' => 'file1.txt'],
            ['data' => 'content2', 'filename' => 'file2.txt'],
        ];
        $message = PostableMessage::raw('Text')->files($files);

        $result = AdapterUtils::extractFiles($message);

        $this->assertCount(2, $result);
    }

    public function test_extracts_files_from_markdown_message(): void
    {
        $files = [
            ['data' => 'image', 'filename' => 'image.png', 'mimeType' => 'image/png'],
        ];
        $message = PostableMessage::markdown('**Text**')->files($files);

        $result = AdapterUtils::extractFiles($message);

        $this->assertCount(1, $result);
        $this->assertSame('image/png', $result[0]['mimeType']);
    }

    public function test_extracts_files_from_card_message(): void
    {
        $card = Card::make('Test');
        $files = [
            ['data' => 'doc', 'filename' => 'doc.pdf'],
        ];
        $message = PostableMessage::card($card)->files($files);

        $result = AdapterUtils::extractFiles($message);

        $this->assertCount(1, $result);
    }

    public function test_extracts_files_with_mime_type(): void
    {
        $files = [
            ['data' => 'content', 'filename' => 'file.txt', 'mimeType' => 'text/plain'],
        ];
        $message = PostableMessage::text('Test')->files($files);

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame('text/plain', $result[0]['mimeType']);
    }

    // ── extractFiles — with empty or missing files ──────────────────

    public function test_returns_empty_array_when_files_is_empty(): void
    {
        $message = PostableMessage::raw('Text')->files([]);

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_for_raw_without_files(): void
    {
        $message = PostableMessage::raw('Just text');

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_for_markdown_without_files(): void
    {
        $message = PostableMessage::markdown('**Bold**');

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_for_text_without_files(): void
    {
        $message = PostableMessage::text('Hello');

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame([], $result);
    }

    // ── extractFiles — with non-object messages ─────────────────────

    public function test_returns_empty_array_for_card_without_files(): void
    {
        $card = Card::make('Test');
        $message = PostableMessage::card($card);

        $result = AdapterUtils::extractFiles($message);

        $this->assertSame([], $result);
    }

    public function test_extract_files_returns_empty_for_null_input(): void
    {
        $result = AdapterUtils::extractFiles(null);

        $this->assertSame([], $result);
    }

    // ── extractFallbackText ─────────────────────────────────────────

    public function test_extracts_fallback_text_from_text_message(): void
    {
        $message = PostableMessage::text('Hello world');

        $result = AdapterUtils::extractFallbackText($message);

        $this->assertSame('Hello world', $result);
    }

    public function test_extracts_fallback_text_from_markdown_message(): void
    {
        $message = PostableMessage::markdown('**Bold** text');

        $result = AdapterUtils::extractFallbackText($message);

        $this->assertStringContainsString('Bold', $result);
    }

    public function test_extracts_fallback_text_from_card_message(): void
    {
        $card = Card::make('Test Card')->subtitle('Subtitle');
        $message = PostableMessage::card($card);

        $result = AdapterUtils::extractFallbackText($message);

        $this->assertStringContainsString('Test Card', $result);
    }

    public function test_returns_empty_string_for_null_input(): void
    {
        $result = AdapterUtils::extractFallbackText(null);

        $this->assertSame('', $result);
    }

    public function test_returns_empty_string_for_empty_message(): void
    {
        $message = new PostableMessage;

        $result = AdapterUtils::extractFallbackText($message);

        $this->assertSame('', $result);
    }

    public function test_extracts_fallback_text_from_raw_message(): void
    {
        $message = PostableMessage::raw('Raw content');

        $result = AdapterUtils::extractFallbackText($message);

        $this->assertStringContainsString('Raw content', $result);
    }

    // ── extractFiles — multiple files with mixed types ──────────────

    public function test_extracts_multiple_files_with_different_mime_types(): void
    {
        $files = [
            ['data' => 'img', 'filename' => 'photo.jpg', 'mimeType' => 'image/jpeg'],
            ['data' => 'doc', 'filename' => 'report.pdf', 'mimeType' => 'application/pdf'],
            ['data' => 'txt', 'filename' => 'notes.txt', 'mimeType' => 'text/plain'],
        ];
        $message = PostableMessage::text('Files attached')->files($files);

        $result = AdapterUtils::extractFiles($message);

        $this->assertCount(3, $result);
        $this->assertSame('image/jpeg', $result[0]['mimeType']);
        $this->assertSame('application/pdf', $result[1]['mimeType']);
        $this->assertSame('text/plain', $result[2]['mimeType']);
    }
}
