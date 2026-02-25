<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Emoji;

use OpenCompany\Chatogrator\Emoji\Emoji;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group emoji
 */
class EmojiTest extends TestCase
{
    // =========================================================================
    // Emoji Constants
    // =========================================================================

    public function test_thumbs_up_constant(): void
    {
        $this->assertSame('thumbs_up', Emoji::thumbsUp);
    }

    public function test_thumbs_down_constant(): void
    {
        $this->assertSame('thumbs_down', Emoji::thumbsDown);
    }

    public function test_wave_constant(): void
    {
        $this->assertSame('wave', Emoji::wave);
    }

    public function test_check_constant(): void
    {
        $this->assertSame('check', Emoji::check);
    }

    public function test_x_constant(): void
    {
        $this->assertSame('x', Emoji::x);
    }

    public function test_rocket_constant(): void
    {
        $this->assertSame('rocket', Emoji::rocket);
    }

    public function test_heart_constant(): void
    {
        $this->assertSame('heart', Emoji::heart);
    }

    public function test_eyes_constant(): void
    {
        $this->assertSame('eyes', Emoji::eyes);
    }

    public function test_fire_constant(): void
    {
        $this->assertSame('fire', Emoji::fire);
    }

    public function test_star_constant(): void
    {
        $this->assertSame('star', Emoji::star);
    }

    public function test_warning_constant(): void
    {
        $this->assertSame('warning', Emoji::warning);
    }

    public function test_question_constant(): void
    {
        $this->assertSame('question', Emoji::question);
    }

    public function test_thinking_constant(): void
    {
        $this->assertSame('thinking', Emoji::thinking);
    }

    // =========================================================================
    // Platform Mapping: toSlack
    // =========================================================================

    public function test_to_slack_thumbs_up(): void
    {
        $this->assertSame('+1', Emoji::toSlack('thumbs_up'));
    }

    public function test_to_slack_thumbs_down(): void
    {
        $this->assertSame('-1', Emoji::toSlack('thumbs_down'));
    }

    public function test_to_slack_fire(): void
    {
        $this->assertSame('fire', Emoji::toSlack('fire'));
    }

    public function test_to_slack_heart(): void
    {
        $this->assertSame('heart', Emoji::toSlack('heart'));
    }

    public function test_to_slack_rocket(): void
    {
        $this->assertSame('rocket', Emoji::toSlack('rocket'));
    }

    public function test_to_slack_unknown_returns_original(): void
    {
        $this->assertSame('custom_emoji', Emoji::toSlack('custom_emoji'));
    }

    // =========================================================================
    // Platform Mapping: toDiscord
    // =========================================================================

    public function test_to_discord_thumbs_up(): void
    {
        $this->assertSame("\u{1F44D}", Emoji::toDiscord('thumbs_up'));
    }

    public function test_to_discord_fire(): void
    {
        $this->assertSame("\u{1F525}", Emoji::toDiscord('fire'));
    }

    public function test_to_discord_rocket(): void
    {
        $this->assertSame("\u{1F680}", Emoji::toDiscord('rocket'));
    }

    public function test_to_discord_unknown_returns_original(): void
    {
        $this->assertSame('custom_emoji', Emoji::toDiscord('custom_emoji'));
    }

    // =========================================================================
    // Platform Mapping: toUnicode
    // =========================================================================

    public function test_to_unicode_thumbs_up(): void
    {
        $this->assertSame("\u{1F44D}", Emoji::toUnicode('thumbs_up'));
    }

    public function test_to_unicode_fire(): void
    {
        $this->assertSame("\u{1F525}", Emoji::toUnicode('fire'));
    }

    public function test_to_unicode_heart(): void
    {
        $this->assertSame("\u{2764}", Emoji::toUnicode('heart'));
    }

    public function test_to_unicode_wave(): void
    {
        $this->assertSame("\u{1F44B}", Emoji::toUnicode('wave'));
    }

    public function test_to_unicode_check(): void
    {
        $this->assertSame("\u{2705}", Emoji::toUnicode('check'));
    }

    public function test_to_unicode_unknown_returns_original(): void
    {
        $this->assertSame('custom_emoji', Emoji::toUnicode('custom_emoji'));
    }

    // =========================================================================
    // Platform Mapping: fromSlack
    // =========================================================================

    public function test_from_slack_plus_one(): void
    {
        $this->assertSame('thumbs_up', Emoji::fromSlack('+1'));
    }

    public function test_from_slack_minus_one(): void
    {
        $this->assertSame('thumbs_down', Emoji::fromSlack('-1'));
    }

    public function test_from_slack_fire(): void
    {
        $this->assertSame('fire', Emoji::fromSlack('fire'));
    }

    public function test_from_slack_heart(): void
    {
        $this->assertSame('heart', Emoji::fromSlack('heart'));
    }

    public function test_from_slack_unknown_returns_null(): void
    {
        $this->assertNull(Emoji::fromSlack('nonexistent_emoji'));
    }

    // =========================================================================
    // Consistency Tests
    // =========================================================================

    public function test_all_mapped_emoji_have_slack_mapping(): void
    {
        $knownEmoji = [
            'thumbs_up', 'thumbs_down', 'wave', 'check', 'x',
            'rocket', 'heart', 'eyes', 'fire', 'star',
        ];

        foreach ($knownEmoji as $emojiName) {
            $this->assertTrue(Emoji::hasPlatformMapping($emojiName), "Emoji '{$emojiName}' should have a Slack platform mapping");
            $slack = Emoji::toSlack($emojiName);
            $this->assertNotEmpty($slack, "Emoji '{$emojiName}' Slack mapping should not be empty");
        }
    }

    public function test_all_mapped_emoji_have_unicode_mapping(): void
    {
        $knownEmoji = [
            'thumbs_up', 'thumbs_down', 'wave', 'check', 'x',
            'rocket', 'heart', 'eyes', 'fire', 'star',
        ];

        foreach ($knownEmoji as $emojiName) {
            $unicode = Emoji::toUnicode($emojiName);
            $this->assertNotSame($emojiName, $unicode, "Emoji '{$emojiName}' should have a Unicode mapping");
        }
    }

    public function test_all_mapped_emoji_have_discord_mapping(): void
    {
        $knownEmoji = [
            'thumbs_up', 'thumbs_down', 'wave', 'check', 'x',
            'rocket', 'heart', 'eyes', 'fire', 'star',
        ];

        foreach ($knownEmoji as $emojiName) {
            $discord = Emoji::toDiscord($emojiName);
            $this->assertNotSame($emojiName, $discord, "Emoji '{$emojiName}' should have a Discord mapping");
        }
    }

    public function test_from_slack_round_trips(): void
    {
        $slackCode = Emoji::toSlack('thumbs_up');
        $normalized = Emoji::fromSlack($slackCode);

        $this->assertSame('thumbs_up', $normalized);
    }

    public function test_fire_round_trip_via_slack(): void
    {
        $slack = Emoji::toSlack('fire');
        $back = Emoji::fromSlack($slack);

        $this->assertSame('fire', $back);
    }
}
