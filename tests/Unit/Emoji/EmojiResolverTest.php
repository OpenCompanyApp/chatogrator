<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Emoji;

use OpenCompany\Chatogrator\Emoji\Emoji;
use OpenCompany\Chatogrator\Emoji\EmojiResolver;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group emoji
 */
class EmojiResolverTest extends TestCase
{
    // =========================================================================
    // resolve() — Slack platform
    // =========================================================================

    public function test_resolve_converts_placeholders_to_slack_format(): void
    {
        $text = 'Thanks! {{emoji:thumbs_up}} Great work! {{emoji:fire}}';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame('Thanks! :+1: Great work! :fire:', $result);
    }

    public function test_resolve_wraps_slack_emoji_in_colons(): void
    {
        $text = '{{emoji:heart}}';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame(':heart:', $result);
    }

    public function test_resolve_slack_handles_unknown_emoji(): void
    {
        $text = 'Check this {{emoji:unknown_emoji}}!';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame('Check this :unknown_emoji:!', $result);
    }

    // =========================================================================
    // resolve() — Discord platform
    // =========================================================================

    public function test_resolve_converts_placeholders_to_discord_format(): void
    {
        $text = '{{emoji:thumbs_up}} Nice!';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("\u{1F44D} Nice!", $result);
    }

    public function test_resolve_discord_fire(): void
    {
        $text = '{{emoji:fire}}';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("\u{1F525}", $result);
    }

    // =========================================================================
    // resolve() — Unicode / default platform
    // =========================================================================

    public function test_resolve_converts_placeholders_to_unicode_by_default(): void
    {
        $text = '{{emoji:thumbs_up}} Hello!';
        $result = EmojiResolver::resolve($text, 'unicode');

        $this->assertSame("\u{1F44D} Hello!", $result);
    }

    public function test_resolve_uses_unicode_for_unknown_platform(): void
    {
        $text = '{{emoji:rocket}}';
        $result = EmojiResolver::resolve($text, 'teams');

        $this->assertSame("\u{1F680}", $result);
    }

    // =========================================================================
    // resolve() — Multiple placeholders
    // =========================================================================

    public function test_resolve_handles_multiple_emoji_in_one_message(): void
    {
        $text = '{{emoji:wave}} Hello! {{emoji:heart}} How are you? {{emoji:thumbs_up}}';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("\u{1F44B} Hello! \u{2764} How are you? \u{1F44D}", $result);
    }

    public function test_resolve_handles_text_with_no_emoji(): void
    {
        $text = 'Just a regular message';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame('Just a regular message', $result);
    }

    public function test_resolve_handles_empty_string(): void
    {
        $result = EmojiResolver::resolve('', 'slack');

        $this->assertSame('', $result);
    }

    // =========================================================================
    // resolve() — Edge cases
    // =========================================================================

    public function test_resolve_handles_adjacent_placeholders(): void
    {
        $text = '{{emoji:fire}}{{emoji:rocket}}';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("\u{1F525}\u{1F680}", $result);
    }

    public function test_resolve_preserves_surrounding_text(): void
    {
        $text = 'Before {{emoji:star}} Middle {{emoji:heart}} After';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("Before \u{2B50} Middle \u{2764} After", $result);
    }

    public function test_resolve_slack_thumbs_up_mapping(): void
    {
        $text = '{{emoji:thumbs_up}}';
        $result = EmojiResolver::resolve($text, 'slack');

        // Slack uses :+1: for thumbs_up
        $this->assertSame(':+1:', $result);
    }

    public function test_resolve_slack_thumbs_down_mapping(): void
    {
        $text = '{{emoji:thumbs_down}}';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame(':-1:', $result);
    }

    // =========================================================================
    // Integration with Emoji class
    // =========================================================================

    public function test_resolve_works_with_emoji_constants(): void
    {
        $text = '{{emoji:' . Emoji::thumbsUp . '}}';
        $result = EmojiResolver::resolve($text, 'slack');

        $this->assertSame(':+1:', $result);
    }

    public function test_resolve_works_with_emoji_fire_constant(): void
    {
        $text = '{{emoji:' . Emoji::fire . '}}';
        $result = EmojiResolver::resolve($text, 'discord');

        $this->assertSame("\u{1F525}", $result);
    }

    public function test_resolve_all_known_emoji_for_slack(): void
    {
        $knownEmoji = [
            'thumbs_up' => '+1',
            'thumbs_down' => '-1',
            'fire' => 'fire',
            'heart' => 'heart',
            'rocket' => 'rocket',
            'wave' => 'wave',
            'eyes' => 'eyes',
            'star' => 'star',
        ];

        foreach ($knownEmoji as $name => $slackCode) {
            $text = "{{emoji:{$name}}}";
            $result = EmojiResolver::resolve($text, 'slack');
            $this->assertSame(":{$slackCode}:", $result, "Failed for emoji: {$name}");
        }
    }

    public function test_resolve_all_known_emoji_for_unicode(): void
    {
        $knownEmoji = [
            'thumbs_up' => "\u{1F44D}",
            'thumbs_down' => "\u{1F44E}",
            'fire' => "\u{1F525}",
            'heart' => "\u{2764}",
            'rocket' => "\u{1F680}",
            'wave' => "\u{1F44B}",
            'eyes' => "\u{1F440}",
            'star' => "\u{2B50}",
            'check' => "\u{2705}",
            'x' => "\u{274C}",
        ];

        foreach ($knownEmoji as $name => $unicode) {
            $text = "{{emoji:{$name}}}";
            $result = EmojiResolver::resolve($text, 'unicode');
            $this->assertSame($unicode, $result, "Failed for emoji: {$name}");
        }
    }
}
