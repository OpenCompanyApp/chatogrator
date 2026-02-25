<?php

namespace OpenCompany\Chatogrator\Emoji;

class Emoji
{
    // ── Hands & Gestures ─────────────────────────────────────────────
    public const thumbsUp = 'thumbs_up';

    public const thumbsDown = 'thumbs_down';

    public const wave = 'wave';

    public const clap = 'clap';

    public const raisedHands = 'raised_hands';

    public const pray = 'pray';

    public const handshake = 'handshake';

    public const pointUp = 'point_up';

    public const pointDown = 'point_down';

    public const pointLeft = 'point_left';

    public const pointRight = 'point_right';

    public const ok = 'ok';

    public const peace = 'peace';

    public const muscle = 'muscle';

    // ── Faces & Emotions ─────────────────────────────────────────────
    public const smile = 'smile';

    public const grin = 'grin';

    public const joy = 'joy';

    public const rofl = 'rofl';

    public const wink = 'wink';

    public const blush = 'blush';

    public const heartEyes = 'heart_eyes';

    public const kissing = 'kissing';

    public const thinking = 'thinking';

    public const neutral = 'neutral';

    public const expressionless = 'expressionless';

    public const unamused = 'unamused';

    public const sweat = 'sweat';

    public const confused = 'confused';

    public const disappointed = 'disappointed';

    public const cry = 'cry';

    public const sob = 'sob';

    public const angry = 'angry';

    public const rage = 'rage';

    public const scream = 'scream';

    public const flushed = 'flushed';

    public const sleepy = 'sleepy';

    public const sunglasses = 'sunglasses';

    public const nerd = 'nerd';

    public const monocle = 'monocle';

    public const hug = 'hug';

    public const shush = 'shush';

    public const mindBlown = 'mind_blown';

    public const partyFace = 'party_face';

    public const eyeRoll = 'eye_roll';

    public const zany = 'zany';

    public const skull = 'skull';

    public const ghost = 'ghost';

    public const robot = 'robot';

    // ── Symbols & Marks ──────────────────────────────────────────────
    public const check = 'check';

    public const x = 'x';

    public const question = 'question';

    public const exclamation = 'exclamation';

    public const warning = 'warning';

    public const noEntry = 'no_entry';

    public const prohibited = 'prohibited';

    public const hundred = 'hundred';

    public const infinity = 'infinity';

    // ── Objects & Activities ─────────────────────────────────────────
    public const heart = 'heart';

    public const brokenHeart = 'broken_heart';

    public const fire = 'fire';

    public const star = 'star';

    public const sparkles = 'sparkles';

    public const lightning = 'lightning';

    public const rainbow = 'rainbow';

    public const sun = 'sun';

    public const moon = 'moon';

    public const cloud = 'cloud';

    public const rain = 'rain';

    public const snow = 'snow';

    public const eyes = 'eyes';

    public const brain = 'brain';

    public const bulb = 'bulb';

    public const megaphone = 'megaphone';

    public const bell = 'bell';

    public const bookmark = 'bookmark';

    public const pin = 'pin';

    public const link = 'link';

    public const lock = 'lock';

    public const unlock = 'unlock';

    public const key = 'key';

    public const gear = 'gear';

    public const wrench = 'wrench';

    public const hammer = 'hammer';

    public const shield = 'shield';

    public const trophy = 'trophy';

    public const medal = 'medal';

    public const crown = 'crown';

    public const gem = 'gem';

    public const money = 'money';

    public const chart = 'chart';

    public const clock = 'clock';

    public const hourglass = 'hourglass';

    public const calendar = 'calendar';

    public const envelope = 'envelope';

    public const inbox = 'inbox';

    public const paperclip = 'paperclip';

    public const scissors = 'scissors';

    public const pencil = 'pencil';

    public const paintbrush = 'paintbrush';

    // ── Communication & Tech ─────────────────────────────────────────
    public const speech = 'speech';

    public const thought = 'thought';

    public const wave2 = 'wave2';

    // ── Celebration ──────────────────────────────────────────────────
    public const tada = 'tada';

    public const confetti = 'confetti';

    public const balloon = 'balloon';

    public const gift = 'gift';

    public const cake = 'cake';

    // ── Transport & Travel ───────────────────────────────────────────
    public const rocket = 'rocket';

    public const airplane = 'airplane';

    public const car = 'car';

    public const bike = 'bike';

    public const ship = 'ship';

    // ── Nature & Animals ─────────────────────────────────────────────
    public const dog = 'dog';

    public const cat = 'cat';

    public const bug = 'bug';

    public const bee = 'bee';

    public const turtle = 'turtle';

    public const snake = 'snake';

    public const tree = 'tree';

    public const flower = 'flower';

    public const cactus = 'cactus';

    // ── Food ─────────────────────────────────────────────────────────
    public const coffee = 'coffee';

    public const beer = 'beer';

    public const pizza = 'pizza';

    public const taco = 'taco';

    /** @var array<string, array<string, string>> Platform-specific mappings */
    protected static array $platformMap = [
        // Hands & Gestures
        'thumbs_up' => ['slack' => '+1', 'discord' => "\u{1F44D}", 'unicode' => "\u{1F44D}"],
        'thumbs_down' => ['slack' => '-1', 'discord' => "\u{1F44E}", 'unicode' => "\u{1F44E}"],
        'wave' => ['slack' => 'wave', 'discord' => "\u{1F44B}", 'unicode' => "\u{1F44B}"],
        'clap' => ['slack' => 'clap', 'discord' => "\u{1F44F}", 'unicode' => "\u{1F44F}"],
        'raised_hands' => ['slack' => 'raised_hands', 'discord' => "\u{1F64C}", 'unicode' => "\u{1F64C}"],
        'pray' => ['slack' => 'pray', 'discord' => "\u{1F64F}", 'unicode' => "\u{1F64F}"],
        'handshake' => ['slack' => 'handshake', 'discord' => "\u{1F91D}", 'unicode' => "\u{1F91D}"],
        'point_up' => ['slack' => 'point_up', 'discord' => "\u{261D}", 'unicode' => "\u{261D}"],
        'point_down' => ['slack' => 'point_down', 'discord' => "\u{1F447}", 'unicode' => "\u{1F447}"],
        'point_left' => ['slack' => 'point_left', 'discord' => "\u{1F448}", 'unicode' => "\u{1F448}"],
        'point_right' => ['slack' => 'point_right', 'discord' => "\u{1F449}", 'unicode' => "\u{1F449}"],
        'ok' => ['slack' => 'ok_hand', 'discord' => "\u{1F44C}", 'unicode' => "\u{1F44C}"],
        'peace' => ['slack' => 'v', 'discord' => "\u{270C}", 'unicode' => "\u{270C}"],
        'muscle' => ['slack' => 'muscle', 'discord' => "\u{1F4AA}", 'unicode' => "\u{1F4AA}"],

        // Faces & Emotions
        'smile' => ['slack' => 'slightly_smiling_face', 'discord' => "\u{1F642}", 'unicode' => "\u{1F642}"],
        'grin' => ['slack' => 'grinning', 'discord' => "\u{1F600}", 'unicode' => "\u{1F600}"],
        'joy' => ['slack' => 'joy', 'discord' => "\u{1F602}", 'unicode' => "\u{1F602}"],
        'rofl' => ['slack' => 'rofl', 'discord' => "\u{1F923}", 'unicode' => "\u{1F923}"],
        'wink' => ['slack' => 'wink', 'discord' => "\u{1F609}", 'unicode' => "\u{1F609}"],
        'blush' => ['slack' => 'blush', 'discord' => "\u{1F60A}", 'unicode' => "\u{1F60A}"],
        'heart_eyes' => ['slack' => 'heart_eyes', 'discord' => "\u{1F60D}", 'unicode' => "\u{1F60D}"],
        'kissing' => ['slack' => 'kissing_heart', 'discord' => "\u{1F618}", 'unicode' => "\u{1F618}"],
        'thinking' => ['slack' => 'thinking_face', 'discord' => "\u{1F914}", 'unicode' => "\u{1F914}"],
        'neutral' => ['slack' => 'neutral_face', 'discord' => "\u{1F610}", 'unicode' => "\u{1F610}"],
        'expressionless' => ['slack' => 'expressionless', 'discord' => "\u{1F611}", 'unicode' => "\u{1F611}"],
        'unamused' => ['slack' => 'unamused', 'discord' => "\u{1F612}", 'unicode' => "\u{1F612}"],
        'sweat' => ['slack' => 'sweat', 'discord' => "\u{1F613}", 'unicode' => "\u{1F613}"],
        'confused' => ['slack' => 'confused', 'discord' => "\u{1F615}", 'unicode' => "\u{1F615}"],
        'disappointed' => ['slack' => 'disappointed', 'discord' => "\u{1F61E}", 'unicode' => "\u{1F61E}"],
        'cry' => ['slack' => 'cry', 'discord' => "\u{1F622}", 'unicode' => "\u{1F622}"],
        'sob' => ['slack' => 'sob', 'discord' => "\u{1F62D}", 'unicode' => "\u{1F62D}"],
        'angry' => ['slack' => 'angry', 'discord' => "\u{1F620}", 'unicode' => "\u{1F620}"],
        'rage' => ['slack' => 'rage', 'discord' => "\u{1F621}", 'unicode' => "\u{1F621}"],
        'scream' => ['slack' => 'scream', 'discord' => "\u{1F631}", 'unicode' => "\u{1F631}"],
        'flushed' => ['slack' => 'flushed', 'discord' => "\u{1F633}", 'unicode' => "\u{1F633}"],
        'sleepy' => ['slack' => 'sleepy', 'discord' => "\u{1F62A}", 'unicode' => "\u{1F62A}"],
        'sunglasses' => ['slack' => 'sunglasses', 'discord' => "\u{1F60E}", 'unicode' => "\u{1F60E}"],
        'nerd' => ['slack' => 'nerd_face', 'discord' => "\u{1F913}", 'unicode' => "\u{1F913}"],
        'monocle' => ['slack' => 'face_with_monocle', 'discord' => "\u{1F9D0}", 'unicode' => "\u{1F9D0}"],
        'hug' => ['slack' => 'hugging_face', 'discord' => "\u{1F917}", 'unicode' => "\u{1F917}"],
        'shush' => ['slack' => 'shushing_face', 'discord' => "\u{1F92B}", 'unicode' => "\u{1F92B}"],
        'mind_blown' => ['slack' => 'exploding_head', 'discord' => "\u{1F92F}", 'unicode' => "\u{1F92F}"],
        'party_face' => ['slack' => 'partying_face', 'discord' => "\u{1F973}", 'unicode' => "\u{1F973}"],
        'eye_roll' => ['slack' => 'face_with_rolling_eyes', 'discord' => "\u{1F644}", 'unicode' => "\u{1F644}"],
        'zany' => ['slack' => 'zany_face', 'discord' => "\u{1F92A}", 'unicode' => "\u{1F92A}"],
        'skull' => ['slack' => 'skull', 'discord' => "\u{1F480}", 'unicode' => "\u{1F480}"],
        'ghost' => ['slack' => 'ghost', 'discord' => "\u{1F47B}", 'unicode' => "\u{1F47B}"],
        'robot' => ['slack' => 'robot_face', 'discord' => "\u{1F916}", 'unicode' => "\u{1F916}"],

        // Symbols & Marks
        'check' => ['slack' => 'white_check_mark', 'discord' => "\u{2705}", 'unicode' => "\u{2705}"],
        'x' => ['slack' => 'x', 'discord' => "\u{274C}", 'unicode' => "\u{274C}"],
        'question' => ['slack' => 'question', 'discord' => "\u{2753}", 'unicode' => "\u{2753}"],
        'exclamation' => ['slack' => 'exclamation', 'discord' => "\u{2757}", 'unicode' => "\u{2757}"],
        'warning' => ['slack' => 'warning', 'discord' => "\u{26A0}", 'unicode' => "\u{26A0}"],
        'no_entry' => ['slack' => 'no_entry_sign', 'discord' => "\u{1F6AB}", 'unicode' => "\u{1F6AB}"],
        'prohibited' => ['slack' => 'no_entry', 'discord' => "\u{26D4}", 'unicode' => "\u{26D4}"],
        'hundred' => ['slack' => '100', 'discord' => "\u{1F4AF}", 'unicode' => "\u{1F4AF}"],
        'infinity' => ['slack' => 'infinity', 'discord' => "\u{267E}", 'unicode' => "\u{267E}"],

        // Objects & Activities
        'heart' => ['slack' => 'heart', 'discord' => "\u{2764}", 'unicode' => "\u{2764}"],
        'broken_heart' => ['slack' => 'broken_heart', 'discord' => "\u{1F494}", 'unicode' => "\u{1F494}"],
        'fire' => ['slack' => 'fire', 'discord' => "\u{1F525}", 'unicode' => "\u{1F525}"],
        'star' => ['slack' => 'star', 'discord' => "\u{2B50}", 'unicode' => "\u{2B50}"],
        'sparkles' => ['slack' => 'sparkles', 'discord' => "\u{2728}", 'unicode' => "\u{2728}"],
        'lightning' => ['slack' => 'zap', 'discord' => "\u{26A1}", 'unicode' => "\u{26A1}"],
        'rainbow' => ['slack' => 'rainbow', 'discord' => "\u{1F308}", 'unicode' => "\u{1F308}"],
        'sun' => ['slack' => 'sunny', 'discord' => "\u{2600}", 'unicode' => "\u{2600}"],
        'moon' => ['slack' => 'crescent_moon', 'discord' => "\u{1F319}", 'unicode' => "\u{1F319}"],
        'cloud' => ['slack' => 'cloud', 'discord' => "\u{2601}", 'unicode' => "\u{2601}"],
        'rain' => ['slack' => 'rain_cloud', 'discord' => "\u{1F327}", 'unicode' => "\u{1F327}"],
        'snow' => ['slack' => 'snowflake', 'discord' => "\u{2744}", 'unicode' => "\u{2744}"],
        'eyes' => ['slack' => 'eyes', 'discord' => "\u{1F440}", 'unicode' => "\u{1F440}"],
        'brain' => ['slack' => 'brain', 'discord' => "\u{1F9E0}", 'unicode' => "\u{1F9E0}"],
        'bulb' => ['slack' => 'bulb', 'discord' => "\u{1F4A1}", 'unicode' => "\u{1F4A1}"],
        'megaphone' => ['slack' => 'mega', 'discord' => "\u{1F4E3}", 'unicode' => "\u{1F4E3}"],
        'bell' => ['slack' => 'bell', 'discord' => "\u{1F514}", 'unicode' => "\u{1F514}"],
        'bookmark' => ['slack' => 'bookmark', 'discord' => "\u{1F516}", 'unicode' => "\u{1F516}"],
        'pin' => ['slack' => 'pushpin', 'discord' => "\u{1F4CC}", 'unicode' => "\u{1F4CC}"],
        'link' => ['slack' => 'link', 'discord' => "\u{1F517}", 'unicode' => "\u{1F517}"],
        'lock' => ['slack' => 'lock', 'discord' => "\u{1F512}", 'unicode' => "\u{1F512}"],
        'unlock' => ['slack' => 'unlock', 'discord' => "\u{1F513}", 'unicode' => "\u{1F513}"],
        'key' => ['slack' => 'key', 'discord' => "\u{1F511}", 'unicode' => "\u{1F511}"],
        'gear' => ['slack' => 'gear', 'discord' => "\u{2699}", 'unicode' => "\u{2699}"],
        'wrench' => ['slack' => 'wrench', 'discord' => "\u{1F527}", 'unicode' => "\u{1F527}"],
        'hammer' => ['slack' => 'hammer', 'discord' => "\u{1F528}", 'unicode' => "\u{1F528}"],
        'shield' => ['slack' => 'shield', 'discord' => "\u{1F6E1}", 'unicode' => "\u{1F6E1}"],
        'trophy' => ['slack' => 'trophy', 'discord' => "\u{1F3C6}", 'unicode' => "\u{1F3C6}"],
        'medal' => ['slack' => 'medal', 'discord' => "\u{1F3C5}", 'unicode' => "\u{1F3C5}"],
        'crown' => ['slack' => 'crown', 'discord' => "\u{1F451}", 'unicode' => "\u{1F451}"],
        'gem' => ['slack' => 'gem', 'discord' => "\u{1F48E}", 'unicode' => "\u{1F48E}"],
        'money' => ['slack' => 'moneybag', 'discord' => "\u{1F4B0}", 'unicode' => "\u{1F4B0}"],
        'chart' => ['slack' => 'chart_with_upwards_trend', 'discord' => "\u{1F4C8}", 'unicode' => "\u{1F4C8}"],
        'clock' => ['slack' => 'clock3', 'discord' => "\u{1F552}", 'unicode' => "\u{1F552}"],
        'hourglass' => ['slack' => 'hourglass', 'discord' => "\u{231B}", 'unicode' => "\u{231B}"],
        'calendar' => ['slack' => 'calendar', 'discord' => "\u{1F4C5}", 'unicode' => "\u{1F4C5}"],
        'envelope' => ['slack' => 'envelope', 'discord' => "\u{2709}", 'unicode' => "\u{2709}"],
        'inbox' => ['slack' => 'inbox_tray', 'discord' => "\u{1F4E5}", 'unicode' => "\u{1F4E5}"],
        'paperclip' => ['slack' => 'paperclip', 'discord' => "\u{1F4CE}", 'unicode' => "\u{1F4CE}"],
        'scissors' => ['slack' => 'scissors', 'discord' => "\u{2702}", 'unicode' => "\u{2702}"],
        'pencil' => ['slack' => 'pencil2', 'discord' => "\u{270F}", 'unicode' => "\u{270F}"],
        'paintbrush' => ['slack' => 'paintbrush', 'discord' => "\u{1F58C}", 'unicode' => "\u{1F58C}"],

        // Communication
        'speech' => ['slack' => 'speech_balloon', 'discord' => "\u{1F4AC}", 'unicode' => "\u{1F4AC}"],
        'thought' => ['slack' => 'thought_balloon', 'discord' => "\u{1F4AD}", 'unicode' => "\u{1F4AD}"],

        // Celebration
        'tada' => ['slack' => 'tada', 'discord' => "\u{1F389}", 'unicode' => "\u{1F389}"],
        'confetti' => ['slack' => 'confetti_ball', 'discord' => "\u{1F38A}", 'unicode' => "\u{1F38A}"],
        'balloon' => ['slack' => 'balloon', 'discord' => "\u{1F388}", 'unicode' => "\u{1F388}"],
        'gift' => ['slack' => 'gift', 'discord' => "\u{1F381}", 'unicode' => "\u{1F381}"],
        'cake' => ['slack' => 'cake', 'discord' => "\u{1F370}", 'unicode' => "\u{1F370}"],

        // Transport
        'rocket' => ['slack' => 'rocket', 'discord' => "\u{1F680}", 'unicode' => "\u{1F680}"],
        'airplane' => ['slack' => 'airplane', 'discord' => "\u{2708}", 'unicode' => "\u{2708}"],
        'car' => ['slack' => 'car', 'discord' => "\u{1F697}", 'unicode' => "\u{1F697}"],
        'bike' => ['slack' => 'bike', 'discord' => "\u{1F6B2}", 'unicode' => "\u{1F6B2}"],
        'ship' => ['slack' => 'ship', 'discord' => "\u{1F6A2}", 'unicode' => "\u{1F6A2}"],

        // Nature & Animals
        'dog' => ['slack' => 'dog', 'discord' => "\u{1F436}", 'unicode' => "\u{1F436}"],
        'cat' => ['slack' => 'cat', 'discord' => "\u{1F431}", 'unicode' => "\u{1F431}"],
        'bug' => ['slack' => 'bug', 'discord' => "\u{1F41B}", 'unicode' => "\u{1F41B}"],
        'bee' => ['slack' => 'bee', 'discord' => "\u{1F41D}", 'unicode' => "\u{1F41D}"],
        'turtle' => ['slack' => 'turtle', 'discord' => "\u{1F422}", 'unicode' => "\u{1F422}"],
        'snake' => ['slack' => 'snake', 'discord' => "\u{1F40D}", 'unicode' => "\u{1F40D}"],
        'tree' => ['slack' => 'evergreen_tree', 'discord' => "\u{1F332}", 'unicode' => "\u{1F332}"],
        'flower' => ['slack' => 'cherry_blossom', 'discord' => "\u{1F338}", 'unicode' => "\u{1F338}"],
        'cactus' => ['slack' => 'cactus', 'discord' => "\u{1F335}", 'unicode' => "\u{1F335}"],

        // Food
        'coffee' => ['slack' => 'coffee', 'discord' => "\u{2615}", 'unicode' => "\u{2615}"],
        'beer' => ['slack' => 'beer', 'discord' => "\u{1F37A}", 'unicode' => "\u{1F37A}"],
        'pizza' => ['slack' => 'pizza', 'discord' => "\u{1F355}", 'unicode' => "\u{1F355}"],
        'taco' => ['slack' => 'taco', 'discord' => "\u{1F32E}", 'unicode' => "\u{1F32E}"],
    ];

    public static function toSlack(string $emoji): string
    {
        return static::$platformMap[$emoji]['slack'] ?? $emoji;
    }

    public static function toDiscord(string $emoji): string
    {
        return static::$platformMap[$emoji]['discord'] ?? $emoji;
    }

    public static function toUnicode(string $emoji): string
    {
        return static::$platformMap[$emoji]['unicode'] ?? $emoji;
    }

    public static function toTelegram(string $emoji): string
    {
        return static::toUnicode($emoji);
    }

    public static function hasPlatformMapping(string $emoji): bool
    {
        return isset(static::$platformMap[$emoji]);
    }

    public static function fromSlack(string $slackEmoji): ?string
    {
        foreach (static::$platformMap as $name => $mappings) {
            if (($mappings['slack'] ?? null) === $slackEmoji) {
                return $name;
            }
        }

        return null;
    }
}
