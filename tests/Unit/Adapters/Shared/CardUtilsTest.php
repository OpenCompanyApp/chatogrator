<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Adapters\Shared;

use OpenCompany\Chatogrator\Adapters\CardUtils;
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\Actions;
use OpenCompany\Chatogrator\Cards\Elements\Divider;
use OpenCompany\Chatogrator\Cards\Elements\Fields;
use OpenCompany\Chatogrator\Cards\Elements\Text;
use OpenCompany\Chatogrator\Cards\Interactive\Button;
use OpenCompany\Chatogrator\Emoji\EmojiResolver;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * Tests for shared card utility functions.
 *
 * Ported from adapter-shared/src/card-utils.test.ts (28 tests).
 * Covers: emoji conversion across platforms (Slack/Discord/Teams/GChat),
 * button style mapping (primary/danger/default), generating card fallback
 * text from Card objects, and handling empty/null cards.
 *
 * @group core
 */
class CardUtilsTest extends TestCase
{
    // ── createEmojiConverter / EmojiResolver ─────────────────────────

    public function test_slack_emoji_converter_uses_colon_format(): void
    {
        $result = EmojiResolver::resolve('{{emoji:wave}} Hello', 'slack');

        $this->assertSame(':wave: Hello', $result);
    }

    public function test_slack_emoji_converter_resolves_fire(): void
    {
        $result = EmojiResolver::resolve('{{emoji:fire}}', 'slack');

        $this->assertSame(':fire:', $result);
    }

    public function test_discord_emoji_converter_uses_unicode(): void
    {
        $result = EmojiResolver::resolve('{{emoji:wave}} Hello', 'discord');

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('{{emoji:', $result);
    }

    public function test_gchat_emoji_converter_uses_unicode(): void
    {
        $result = EmojiResolver::resolve('{{emoji:wave}} Hello', 'gchat');

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('{{emoji:', $result);
    }

    public function test_teams_emoji_converter_uses_unicode(): void
    {
        $result = EmojiResolver::resolve('{{emoji:wave}} Hello', 'teams');

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('{{emoji:', $result);
    }

    public function test_text_unchanged_when_no_emoji_placeholders(): void
    {
        $result = EmojiResolver::resolve('Hello world', 'slack');

        $this->assertSame('Hello world', $result);
    }

    public function test_resolves_multiple_emoji_placeholders(): void
    {
        $result = EmojiResolver::resolve('{{emoji:fire}} hot {{emoji:star}} star', 'slack');

        $this->assertSame(':fire: hot :star: star', $result);
    }

    // ── mapButtonStyle ──────────────────────────────────────────────

    public function test_slack_maps_primary_to_primary(): void
    {
        $this->assertSame('primary', CardUtils::mapButtonStyle('primary', 'slack'));
    }

    public function test_slack_maps_danger_to_danger(): void
    {
        $this->assertSame('danger', CardUtils::mapButtonStyle('danger', 'slack'));
    }

    public function test_slack_returns_null_for_undefined_style(): void
    {
        $this->assertNull(CardUtils::mapButtonStyle(null, 'slack'));
    }

    public function test_teams_maps_primary_to_positive(): void
    {
        $this->assertSame('positive', CardUtils::mapButtonStyle('primary', 'teams'));
    }

    public function test_teams_maps_danger_to_destructive(): void
    {
        $this->assertSame('destructive', CardUtils::mapButtonStyle('danger', 'teams'));
    }

    public function test_teams_returns_null_for_undefined_style(): void
    {
        $this->assertNull(CardUtils::mapButtonStyle(null, 'teams'));
    }

    public function test_gchat_maps_primary_to_primary(): void
    {
        $this->assertSame('primary', CardUtils::mapButtonStyle('primary', 'gchat'));
    }

    public function test_gchat_maps_danger_to_danger(): void
    {
        $this->assertSame('danger', CardUtils::mapButtonStyle('danger', 'gchat'));
    }

    // ── BUTTON_STYLE_MAPPINGS ───────────────────────────────────────

    public function test_button_style_mappings_exist_for_all_platforms(): void
    {
        $mappings = CardUtils::buttonStyleMappings();

        $this->assertArrayHasKey('slack', $mappings);
        $this->assertArrayHasKey('teams', $mappings);
        $this->assertArrayHasKey('gchat', $mappings);
    }

    public function test_button_style_mappings_have_primary_and_danger(): void
    {
        $mappings = CardUtils::buttonStyleMappings();

        foreach (['slack', 'teams', 'gchat'] as $platform) {
            $this->assertArrayHasKey('primary', $mappings[$platform]);
            $this->assertArrayHasKey('danger', $mappings[$platform]);
        }
    }

    // ── cardToFallbackText ──────────────────────────────────────────

    public function test_formats_title_with_bold(): void
    {
        $card = Card::make('Test Title');

        $this->assertSame('*Test Title*', CardUtils::cardToFallbackText($card));
    }

    public function test_formats_title_and_subtitle(): void
    {
        $card = Card::make('Title')->subtitle('Subtitle');

        $this->assertSame("*Title*\nSubtitle", CardUtils::cardToFallbackText($card));
    }

    public function test_uses_double_asterisks_for_markdown_bold_format(): void
    {
        $card = Card::make('Title');

        $result = CardUtils::cardToFallbackText($card, boldFormat: '**');

        $this->assertSame('**Title**', $result);
    }

    public function test_uses_double_line_breaks_when_specified(): void
    {
        $card = Card::make('Title')->subtitle('Subtitle');

        $result = CardUtils::cardToFallbackText($card, lineBreak: "\n\n");

        $this->assertSame("*Title*\n\nSubtitle", $result);
    }

    public function test_formats_text_children(): void
    {
        $card = Card::make('Card')
            ->section(Text::make('Some content'));

        $result = CardUtils::cardToFallbackText($card);

        $this->assertStringContainsString('Card', $result);
        $this->assertStringContainsString('Some content', $result);
    }

    public function test_formats_fields(): void
    {
        $card = Card::make('')
            ->fields(['Name' => 'John', 'Age' => '30']);

        $result = CardUtils::cardToFallbackText($card);

        $this->assertStringContainsString('Name: John', $result);
        $this->assertStringContainsString('Age: 30', $result);
    }

    public function test_excludes_actions_from_fallback_text(): void
    {
        $card = Card::make('')
            ->actions(
                Button::make('ok', 'OK'),
                Button::make('cancel', 'Cancel'),
            );

        $result = CardUtils::cardToFallbackText($card);

        // Actions are excluded because they are interactive-only
        $this->assertStringNotContainsString('OK', $result);
        $this->assertStringNotContainsString('Cancel', $result);
    }

    public function test_formats_dividers_as_horizontal_rules(): void
    {
        $card = Card::make('Title')
            ->divider()
            ->section(Text::make('After divider'));

        $result = CardUtils::cardToFallbackText($card);

        $this->assertStringContainsString('---', $result);
        $this->assertStringContainsString('After divider', $result);
    }

    public function test_converts_emoji_placeholders_when_platform_specified(): void
    {
        $card = Card::make('{{emoji:wave}} Welcome')
            ->section(Text::make('{{emoji:fire}} Hot stuff'));

        $result = CardUtils::cardToFallbackText($card, platform: 'slack');

        $this->assertStringContainsString(':wave:', $result);
        $this->assertStringContainsString(':fire:', $result);
    }

    public function test_leaves_emoji_placeholders_when_no_platform_specified(): void
    {
        $card = Card::make('{{emoji:wave}} Welcome');

        $result = CardUtils::cardToFallbackText($card);

        $this->assertStringContainsString('{{emoji:wave}}', $result);
    }

    public function test_handles_empty_card(): void
    {
        $card = Card::make('');

        $result = CardUtils::cardToFallbackText($card);

        $this->assertSame('', $result);
    }
}
