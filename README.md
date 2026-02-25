# Chatogrator

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012-FF2D20.svg)](https://laravel.com)

A unified PHP/Laravel SDK for building multi-platform chat bots. Write your bot logic once, deploy to Slack, Discord, Microsoft Teams, Google Chat, Telegram, GitHub, and Linear.

Inspired by the [Vercel Chat SDK](https://github.com/vercel/chat/) — ported to idiomatic PHP with Laravel-native patterns: fluent builders, queued jobs, cache-backed state, and Artisan commands.

**Full documentation:** [opencompany.app/docs/open-source/chatogrator](https://opencompany.app/docs/open-source/chatogrator)

## Installation

```bash
composer require opencompany/chatogrator
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=chatogrator-config
```

**Optional dependencies:**

```bash
# Required for Discord Gateway WebSocket bridge
composer require ratchet/pawl

# Required for Discord adapter (Ed25519 signature verification)
# Usually available by default in PHP 8.2+
php -m | grep sodium
```

## Quick Start

```php
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\State\CacheStateAdapter;
use OpenCompany\Chatogrator\Threads\Thread;
use OpenCompany\Chatogrator\Messages\Message;

$chat = Chat::make('my-bot')
    ->adapter('slack', SlackAdapter::fromConfig([
        'bot_token' => config('services.slack.bot_token'),
        'signing_secret' => config('services.slack.signing_secret'),
    ]))
    ->state(new CacheStateAdapter())

    ->onNewMention(function (Thread $thread, Message $message) {
        $thread->subscribe();
        $thread->post("Hello! I'm watching this thread now.");
    })

    ->onSubscribedMessage(function (Thread $thread, Message $message) {
        $thread->post("You said: {$message->text}");
    });
```

Register the `Chat` instance in your `AppServiceProvider`:

```php
use OpenCompany\Chatogrator\Chat;

public function register(): void
{
    $this->app->singleton(Chat::class, function () {
        return Chat::make('my-bot')
            ->adapter('slack', SlackAdapter::fromConfig([...]))
            ->state(new CacheStateAdapter());
    });
}
```

Webhooks are automatically routed to `POST /webhooks/chat/{adapter}` via the package's service provider. Point your platform's webhook URL to:

```
https://your-app.com/webhooks/chat/slack
https://your-app.com/webhooks/chat/discord
https://your-app.com/webhooks/chat/teams
```

## Supported Platforms

| Feature | Slack | Discord | Teams | Google Chat | Telegram | GitHub | Linear |
|---------|-------|---------|-------|-------------|----------|--------|--------|
| Messages | Yes | Yes | Yes | Yes | Yes | PR comments | Issue comments |
| Reactions | Yes | Yes (add + remove) | No | Yes | Yes | Limited | Emoji |
| Threading | Native | Thread channels | Reply chain | Native | Forum topics | N/A | Comment threads |
| Cards | Block Kit | Embeds + Components | Adaptive Cards | Google Cards | Inline Keyboard | Markdown | Markdown |
| Modals | Full | Partial | Partial | No | No | No | No |
| Ephemeral | Native | DM fallback | DM fallback | Native | DM fallback | No | No |
| Streaming | Native | Fallback | Fallback | Fallback | Fallback | No | No |
| File uploads | Yes | Yes | Yes | Images only | Yes | N/A | Attachments |
| Slash commands | Yes | Yes | No | No | Yes | No | No |
| DMs | Yes | Yes | Yes | Yes | Yes | No | No |
| Typing indicator | Yes | Yes | Yes | No | Yes | No | No |
| Message history | Yes | Yes | Yes | Yes | No | Yes | Yes |
| Webhook delivery | Yes | Interactions only* | Yes | Yes | Yes | Yes | Yes |

\* Discord requires the Gateway bridge for MESSAGE_CREATE. See [Discord Gateway](#discord-gateway).

## Core Concepts

### Chat Orchestrator

The `Chat` class is the central entry point. It coordinates adapters, state, and handler registration via a fluent API.

```php
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\State\RedisStateAdapter;
use OpenCompany\Chatogrator\Threads\Thread;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Events\ReactionEvent;
use OpenCompany\Chatogrator\Events\SlashCommandEvent;
use OpenCompany\Chatogrator\Events\ModalSubmitEvent;
use OpenCompany\Chatogrator\Events\ModalResponse;
use OpenCompany\Chatogrator\Emoji\Emoji;

$chat = Chat::make('my-bot')
    ->adapter('slack', SlackAdapter::fromConfig([
        'bot_token' => config('services.slack.bot_token'),
        'signing_secret' => config('services.slack.signing_secret'),
    ]))
    ->adapter('discord', DiscordAdapter::fromConfig([
        'bot_token' => config('services.discord.bot_token'),
        'public_key' => config('services.discord.public_key'),
        'application_id' => config('services.discord.application_id'),
    ]))
    ->state(new RedisStateAdapter())
    ->logger('debug')

    // New @mentions in threads the bot is NOT yet subscribed to
    ->onNewMention(function (Thread $thread, Message $message) {
        $thread->subscribe();
        $thread->post("Hello! I'm watching this thread now.");
    })

    // Messages in threads the bot IS subscribed to (excl. bot's own)
    ->onSubscribedMessage(function (Thread $thread, Message $message) {
        $thread->post("You said: {$message->text}");
    })

    // Pattern-matched messages (regex, any thread)
    ->onNewMessage('/deploy/', function (Thread $thread, Message $message) {
        $thread->post('Starting deployment...');
    })

    // Button/select clicks — filtered by action ID or catch-all
    ->onAction('approve', function (ActionEvent $event) {
        $event->thread->post('Approved!');
    })
    ->onAction(function (ActionEvent $event) {
        // Catch-all for unhandled actions
    })

    // Slash commands
    ->onSlashCommand('/help', function (SlashCommandEvent $event) {
        $event->respond('Available commands: /help, /status');
    })

    // Reactions — emoji filter or catch-all
    ->onReaction([Emoji::thumbsUp], function (ReactionEvent $event) {
        $event->thread->post('Thanks!');
    })

    // Modal submit — can return validation errors, update, push, or close
    ->onModalSubmit('feedback_form', function (ModalSubmitEvent $event) {
        if (empty($event->values['message'])) {
            return ModalResponse::errors(['message' => 'Required']);
        }
        $event->relatedThread?->post('Thanks for the feedback!');
        return ModalResponse::close();
    })

    // Modal close — user dismissed without submitting
    ->onModalClose('feedback_form', function (ModalCloseEvent $event) {
        // Optional cleanup
    });
```

**All handler types support both filtered and catch-all overloads.** All matching handlers execute sequentially.

| Method | Filter | Trigger |
|--------|--------|---------|
| `onNewMention(fn)` | — | @mention in unsubscribed thread |
| `onSubscribedMessage(fn)` | — | Message in subscribed thread |
| `onNewMessage(regex, fn)` | Regex pattern | Message matching pattern |
| `onAction(id?, fn)` | Action ID or catch-all | Button/select click |
| `onReaction(emojis?, fn)` | Emoji list or catch-all | Reaction add/remove |
| `onSlashCommand(cmd?, fn)` | Command name or catch-all | Slash command |
| `onModalSubmit(callbackId?, fn)` | Callback ID or catch-all | Modal form submission |
| `onModalClose(callbackId?, fn)` | Callback ID or catch-all | Modal dismissed |

**Handler routing flow:**

1. Webhook arrives at `handleWebhook()`
2. Adapter parses payload and verifies signature
3. Dedup via state adapter (`dedupe:{adapter}:{messageId}`, 60s TTL)
4. Acquire distributed lock on thread (prevents concurrent processing)
5. Route to matching handlers (all execute sequentially)
6. Lock released after completion

### Thread

Full conversation context with persistent state, message access, and posting.

```php
// Posting — accepts string, Card, FileUpload, or iterable (streaming)
$thread->post('Hello!');
$thread->post(Card::make('Order #123')->section(Text::make('Total: $50')));
$thread->post($aiStream);  // Generator for streaming
$thread->post(PostableMessage::make('text')->files([
    FileUpload::make($buffer, 'report.pdf', 'application/pdf'),
]));

// Ephemeral messages (only visible to specific user)
$thread->postEphemeral($userId, 'Only you can see this', fallbackToDM: true);

// Subscription (controls which handlers fire)
$thread->subscribe();
$thread->unsubscribe();
$thread->isSubscribed();  // bool

// Persistent state (30d TTL, merged by default)
$state = $thread->state();
$thread->setState(['mode' => 'ai']);
$thread->setState(['mode' => 'ai'], replace: true);

// Message access
$result = $thread->messages();          // FetchResult with pagination
$all = $thread->allMessages();          // Paginate through all messages
$recent = $thread->recentMessages(10);  // Fetch recent N messages
$thread->refresh();                     // Clear cached state

// Typing indicator
$thread->startTyping();

// User mentions (platform-specific syntax)
$mention = $thread->mentionUser($userId);  // "<@U123>" for Slack, etc.

// Metadata
$thread->id;         // Full thread ID: "slack:C123:ts123"
$thread->channelId;  // Channel portion
$thread->isDM;       // bool
$thread->adapter;    // Adapter instance

// Channel access
$channel = $thread->channel;  // Parent Channel object

// Serialization (for queued jobs, workflow engines)
$json = $thread->toJSON();
$thread = Thread::fromJSON($json, $chat);
```

### Channel

Container for threads with its own state, message access, and posting.

```php
// Channel-level posting (top-level message, not in a thread)
$channel->post('Announcement!');
$channel->postEphemeral($userId, 'Only you see this');

// Channel state (separate from thread state)
$state = $channel->state();
$channel->setState(['notifications' => true]);

// List threads in channel
$result = $channel->threads();
foreach ($result->threads as $threadSummary) {
    echo $threadSummary->id;
    echo $threadSummary->lastActivity;
}

// Channel-level messages (top-level only, not thread replies)
$result = $channel->messages();

// Metadata
$info = $channel->fetchMetadata();  // ChannelInfo: name, topic, memberCount
$channel->isDM;

// Typing
$channel->startTyping();

// Serialization
$json = $channel->toJSON();
$channel = Channel::fromJSON($json, $chat);
```

### Message

The canonical message format uses a Markdown AST (mdast) for rich content, with plain text as a convenience accessor.

```php
$message->id;           // Platform message ID
$message->threadId;     // Full thread ID
$message->text;         // Plain text (all formatting stripped)
$message->formatted;    // Markdown AST (mdast Root node)
$message->raw;          // Platform-specific raw payload (escape hatch)
$message->author;       // Author { userId, userName, fullName, isBot, isMe }
$message->metadata;     // { dateSent, edited, editedAt }
$message->attachments;  // Attachment[] with fetchData() callback
$message->isMention;    // Whether this message @-mentions the bot

// Author properties
$message->author->userId;    // Platform user ID
$message->author->userName;  // Username/handle
$message->author->fullName;  // Display name
$message->author->isBot;     // bool|'unknown'
$message->author->isMe;      // bool — is this the bot's own message?
```

**SentMessage** extends Message with mutation methods:

```php
$sent = $thread->post('Hello');
$sent->edit('Hello, updated!');
$sent->delete();
$sent->addReaction(Emoji::thumbsUp);
$sent->removeReaction(Emoji::thumbsUp);
```

**Serialization** for queued jobs:

```php
$json = $message->toJSON();
$message = Message::fromJSON($json);
```

## PostableMessage

All variants of outbound messages:

```php
// Simple string
$thread->post('Hello!');

// Markdown (parsed to AST internally)
$thread->post(PostableMessage::markdown('**Bold** text'));

// AST content directly
$thread->post(PostableMessage::formatted($ast));

// Card
$thread->post($card);

// With files attached
$thread->post(PostableMessage::make('See attached')->files([
    FileUpload::make($buffer, 'chart.png', 'image/png'),
]));

// Streaming (generator)
$thread->post($generator);

// Raw platform-specific content (escape hatch)
$thread->post(PostableMessage::raw($slackBlockKitJson));
```

## Cards & Interactive Elements

A fluent card builder that renders to each platform's native format.

```php
use OpenCompany\Chatogrator\Cards\Card;
use OpenCompany\Chatogrator\Cards\Elements\{Text, Image, Divider, Section, Fields};
use OpenCompany\Chatogrator\Cards\Interactive\{Button, LinkButton, Select, SelectOption};

$card = Card::make('Welcome!')
    ->subtitle('You have a new notification')
    ->imageUrl('https://example.com/banner.png')
    ->section(
        Text::bold('Order #1234'),
        Text::muted('Placed 5 minutes ago'),
    )
    ->divider()
    ->fields([
        'Status' => 'Pending',
        'Total' => '$42.00',
        'Items' => '3',
    ])
    ->actions(
        Button::make('approve', 'Approve')->primary(),
        Button::make('reject', 'Reject')->danger(),
        Button::make('details', 'Details'),
        LinkButton::make('https://example.com', 'View Online'),
        Select::make('assign', 'Assign to')->options([
            SelectOption::make('alice', 'Alice'),
            SelectOption::make('bob', 'Bob'),
        ]),
    );

$thread->post($card);

// Fallback text (for notifications, logging, unsupported platforms)
$fallback = $card->toFallbackText();  // "Welcome! — Order #1234 ..."
```

**Button styles:** `primary`, `danger`, `default` (no modifier = default).

Each adapter renders cards to its native format:

| Platform | Format |
|----------|--------|
| Slack | Block Kit JSON |
| Discord | Embeds + Action Row Components |
| Teams | Adaptive Cards |
| Google Chat | Google Chat Cards v2 |
| GitHub | Markdown fallback |
| Linear | Markdown fallback |

## Modals

Form dialogs with inputs, validation, and context persistence.

### Building a modal

```php
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Cards\Interactive\{TextInput, Select, SelectOption, RadioSelect};

$modal = Modal::make('feedback_form', 'Send Feedback')
    ->submitLabel('Send')
    ->closeLabel('Cancel')
    ->notifyOnClose()
    ->privateMetadata(['context' => 'value'])
    ->input(TextInput::make('message', 'Your Feedback')->multiline()->maxLength(500))
    ->input(Select::make('rating', 'Rating')->options([
        SelectOption::make('5', 'Excellent'),
        SelectOption::make('3', 'Average'),
        SelectOption::make('1', 'Poor'),
    ]))
    ->input(RadioSelect::make('priority', 'Priority')->options([
        SelectOption::make('low', 'Low'),
        SelectOption::make('high', 'High'),
    ])->optional());
```

### Opening from an action handler

```php
$chat->onAction('open_feedback', function (ActionEvent $event) {
    $event->openModal($modal);
});
```

**Context persistence:** When a modal is opened from an action handler, the originating `thread`, `message`, and `channel` are stored server-side (via state adapter, 24h TTL). On submit/close, these are restored as `$event->relatedThread`, `$event->relatedMessage`, `$event->relatedChannel`.

### Validation responses

```php
$chat->onModalSubmit('feedback_form', function (ModalSubmitEvent $event) {
    if (empty($event->values['message'])) {
        return ModalResponse::errors(['message' => 'Message is required']);
    }

    // Or update the modal content
    return ModalResponse::update($updatedModal);

    // Or push a new modal on top
    return ModalResponse::push($nextStepModal);

    // Or close (default if handler returns void)
    return ModalResponse::close();
});
```

**Supported by:** Slack (full), Discord (partial — via interactions), Teams (partial). Other platforms silently skip modal operations.

## Emoji System

Cross-platform emoji with ~125 normalized constants and per-platform mapping.

```php
use OpenCompany\Chatogrator\Emoji\Emoji;
use OpenCompany\Chatogrator\Emoji\EmojiResolver;

// Normalized constants
Emoji::thumbsUp;
Emoji::thumbsDown;
Emoji::wave;
Emoji::check;
Emoji::x;
Emoji::rocket;
Emoji::heart;
Emoji::eyes;
Emoji::fire;
// ... ~125 total

// In reaction handlers
$chat->onReaction([Emoji::thumbsUp, Emoji::heart], function (ReactionEvent $event) {
    if ($event->emoji === Emoji::thumbsUp) { ... }
});

// Platform-specific resolution
Emoji::fromSlack('+1');              // Emoji::thumbsUp
Emoji::toSlack(Emoji::thumbsUp);    // '+1'
Emoji::toDiscord(Emoji::thumbsUp);  // '👍'

// Placeholder system for message text
$text = "Great job! {{emoji:thumbs_up}}";
$resolved = EmojiResolver::resolve($text, 'slack');   // "Great job! :+1:"
$resolved = EmojiResolver::resolve($text, 'discord'); // "Great job! 👍"
```

## Markdown AST

Messages use mdast (Markdown Abstract Syntax Tree) as the canonical format for rich content. This preserves structure across platforms.

```php
use OpenCompany\Chatogrator\Markdown\{Markdown, AstBuilder, AstWalker, TypeGuards};

// Parse markdown string to AST
$ast = Markdown::parse('**Hello** world');

// Stringify AST back to markdown
$md = Markdown::stringify($ast);

// Convert AST to plain text (strip formatting)
$text = Markdown::toPlainText($ast);

// Build AST programmatically
$ast = AstBuilder::root([
    AstBuilder::paragraph([
        AstBuilder::strong([AstBuilder::text('Hello')]),
        AstBuilder::text(' world'),
    ]),
]);

// Type guards for AST nodes
TypeGuards::isTextNode($node);
TypeGuards::isParagraphNode($node);
TypeGuards::isStrongNode($node);
TypeGuards::isLinkNode($node);
TypeGuards::isCodeNode($node);

// Walk/traverse AST
AstWalker::walk($ast, function ($node) {
    if (TypeGuards::isLinkNode($node)) {
        // Process all links
    }
});
```

## Streaming

PHP generators are the equivalent of TypeScript's `AsyncIterable`:

```php
$stream = function () use ($prompt) {
    foreach ($aiClient->stream($prompt) as $chunk) {
        yield $chunk;
    }
};

$thread->post($stream());
```

**`Thread::post()` detects iterables and:**

1. If the adapter supports native streaming (Slack) — delegates to `$adapter->stream()`
2. Otherwise — posts an initial "..." message, then edits with accumulated content every 500ms
3. Final edit after stream completes
4. Only edits when content actually changed (avoids API chatter)

Configurable via `streamingUpdateIntervalMs` option (default 500ms).

## Queue Dispatch

For fast webhook responses, enable queue dispatch to process handlers in the background:

```php
$chat = Chat::make('my-bot')
    ->adapter('slack', $slackAdapter)
    ->state(new CacheStateAdapter())
    ->queued(queue: 'chat')  // Dispatch handler execution to Laravel queue
    ->onSubscribedMessage(function (Thread $thread, Message $message) {
        // This runs in a queued job, not during the webhook request
        $thread->post("Processing...");
    });
```

The adapter returns `200 OK` immediately, and a `ProcessChatEvent` job handles the rest. This is the PHP equivalent of the Vercel Chat SDK's `waitUntil` pattern.

**Modal submit/close handlers always run synchronously** — they need to return HTTP responses (validation errors, modal updates) directly.

## Adapters

### Adapter Configuration

Each adapter is created via `fromConfig()`:

```php
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\Adapters\Teams\TeamsAdapter;
use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatAdapter;
use OpenCompany\Chatogrator\Adapters\Telegram\TelegramAdapter;
use OpenCompany\Chatogrator\Adapters\GitHub\GitHubAdapter;
use OpenCompany\Chatogrator\Adapters\Linear\LinearAdapter;

// Slack
SlackAdapter::fromConfig([
    'bot_token' => env('SLACK_BOT_TOKEN'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),
]);

// Discord
DiscordAdapter::fromConfig([
    'bot_token' => env('DISCORD_BOT_TOKEN'),
    'public_key' => env('DISCORD_PUBLIC_KEY'),
    'application_id' => env('DISCORD_APPLICATION_ID'),
    'gateway_secret' => env('DISCORD_GATEWAY_SECRET'),  // For Gateway bridge
]);

// Microsoft Teams
TeamsAdapter::fromConfig([
    'app_id' => env('TEAMS_APP_ID'),
    'app_password' => env('TEAMS_APP_PASSWORD'),
    'app_tenant_id' => env('TEAMS_APP_TENANT_ID'),
]);

// Google Chat
GoogleChatAdapter::fromConfig([
    'credentials' => env('GOOGLE_CHAT_CREDENTIALS'),  // Service account JSON
]);

// Telegram
TelegramAdapter::fromConfig([
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),         // From @BotFather
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'), // For webhook verification
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),    // e.g., 'MyBot'
]);

// GitHub
GitHubAdapter::fromConfig([
    'token' => env('GITHUB_TOKEN'),
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
]);

// Linear
LinearAdapter::fromConfig([
    'api_key' => env('LINEAR_API_KEY'),
    'webhook_secret' => env('LINEAR_WEBHOOK_SECRET'),
]);
```

### Authentication

| Adapter | Webhook Verification | API Authentication |
|---------|---------------------|--------------------|
| Slack | HMAC-SHA256 (`hash_hmac`) | `Bearer {bot_token}` |
| Discord | Ed25519 (`sodium_crypto_sign_verify_detached`) | `Bot {token}` |
| Teams | Bot Framework JWT validation | App ID + Password |
| Google Chat | Bearer token / Pub/Sub push | Service Account JWT |
| Telegram | Secret token header (`X-Telegram-Bot-Api-Secret-Token`) | Bot token in URL |
| GitHub | HMAC-SHA256 | `Bearer {token}` or GitHub App |
| Linear | HMAC-SHA256 | `Bearer {api_key}` |

All verification uses PHP built-ins (`hash_hmac`, `hash_equals`, `sodium_*`). No external packages needed.

### Discord Gateway

Discord does **not** provide HTTP webhooks for regular messages. Their Interactions Endpoint only covers slash commands, button clicks, and modals. Receiving MESSAGE_CREATE events requires a persistent WebSocket connection to the Gateway.

The package ships an Artisan command that acts as a lightweight bridge:

```bash
php artisan chat:discord-gateway
```

**Features:**

- Connects to Discord Gateway WebSocket via `ratchet/pawl` (ReactPHP)
- Forwards MESSAGE_CREATE, MESSAGE_UPDATE, MESSAGE_DELETE, MESSAGE_REACTION_ADD, MESSAGE_REACTION_REMOVE as HTTP POSTs to the webhook route
- Authenticates forwarded events via `gateway_secret`
- Full Gateway v10 protocol: HELLO, IDENTIFY, HEARTBEAT (with jitter), RESUME, RECONNECT, INVALID_SESSION
- Exponential backoff reconnection
- Graceful shutdown on SIGTERM/SIGINT
- `--max-memory=128` option for auto-restart (like `queue:work`)

**Running in production (Supervisor):**

```ini
[program:discord-gateway]
command=php /path/to/artisan chat:discord-gateway
autostart=true
autorestart=true
startretries=10
stopwaitsecs=30
stopsignal=SIGTERM
```

Users who don't use Discord never need to run this command. The package works fine without it.

## State Adapters

State adapters handle key-value storage, distributed locking, and thread subscriptions.

```php
use OpenCompany\Chatogrator\State\CacheStateAdapter;
use OpenCompany\Chatogrator\State\RedisStateAdapter;
use OpenCompany\Chatogrator\State\ArrayStateAdapter;

// Laravel Cache (any driver — Redis, Memcached, file, database)
$chat->state(new CacheStateAdapter());

// Direct Redis (with optional key prefix for multi-app deployments)
$chat->state(new RedisStateAdapter());

// In-memory (for testing)
$chat->state(new ArrayStateAdapter());
```

**Key prefixes used internally:**

| Key Pattern | TTL | Purpose |
|-------------|-----|---------|
| `dedupe:{adapter}:{messageId}` | 60s | Prevents duplicate processing |
| `thread-state:{threadId}` | 30d | Persistent thread state |
| `channel-state:{channelId}` | 30d | Persistent channel state |
| `modal-context:{adapter}:{contextId}` | 24h | Modal thread/message/channel context |
| `subscription:{threadId}` | — | Thread subscription tracking |

## Webhook Routing

Routes are registered automatically by the package's ServiceProvider:

```
POST /webhooks/chat/{adapter}  →  ChatWebhookController@handle
```

Configuration (`config/chatogrator.php`):

```php
return [
    'route_prefix' => 'webhooks/chat',
    'middleware' => [],  // Webhooks are verified by each adapter's signature
];
```

## Thread ID Format

Each adapter encodes thread IDs with a prefix for unambiguous routing:

| Adapter | Format | Example |
|---------|--------|---------|
| Slack | `slack:{channel}:{threadTs}` | `slack:C123:1234567.890` |
| Discord | `discord:{guild}:{channel}:{thread?}` | `discord:123:456:789` |
| Teams | `teams:{b64(conversationId)}:{b64(serviceUrl)}` | `teams:abc123:def456` |
| Google Chat | `gchat:{space}:{b64(thread)}` | `gchat:spaces/ABC:xyz` |
| GitHub | `github/{owner}/{repo}:{pr}` | `github/acme/app:42` |
| Linear | `linear:{issueId}` | `linear:ABC-123` |

## Error Handling

Two error categories:

**Core errors** (thrown by the Chat orchestrator):

| Error | Description |
|-------|-------------|
| `ChatError` | Base error |
| `RateLimitError` | Rate limit hit, includes `retryAfter` seconds |
| `LockError` | Thread lock acquisition failed |
| `NotImplementedError` | Adapter doesn't support the requested capability |

**Adapter errors** (thrown by individual adapters):

| Error | Description |
|-------|-------------|
| `AdapterError` | Base adapter error with error code and metadata |
| `AuthenticationError` | Invalid tokens/credentials |
| `PermissionError` | Missing bot permissions |
| `ResourceNotFoundError` | Channel/thread/message not found |
| `ValidationError` | Invalid input |
| `NetworkError` | Connection timeouts, DNS failures |

## Type Objects

Typed value objects for pagination and metadata:

```php
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\ThreadSummary;

// Fetch messages with typed options
$result = $adapter->fetchMessages($threadId, new FetchOptions(
    cursor: $nextCursor,
    limit: 25,
    direction: 'backward',
));
// $result->messages, $result->nextCursor, $result->hasMore

// Thread info
$info = $adapter->fetchThread($threadId);
// $info->id, $info->channelId, $info->isDM, $info->title

// Channel info
$channel = $adapter->fetchChannelInfo($channelId);
// $channel->id, $channel->name, $channel->topic, $channel->memberCount, $channel->isDM

// List threads with pagination
$result = $adapter->listThreads($channelId, new ListThreadsOptions(limit: 20));
// $result->threads (ThreadSummary[]), $result->nextCursor, $result->hasMore
```

## Package Structure

```
src/
├── Chat.php                           # Orchestrator
├── ChatServiceProvider.php            # Laravel auto-discovery
├── Contracts/                         # Interfaces
│   ├── Adapter.php                    # Platform adapter contract (29 methods)
│   ├── StateAdapter.php               # Persistence (cache, locks, subscriptions)
│   └── FormatConverter.php            # Platform ↔ markdown conversion
├── Messages/                          # Domain models
│   ├── Message.php                    # Normalized incoming message
│   ├── SentMessage.php                # Message with edit/delete/react
│   ├── Author.php                     # User identity
│   ├── Attachment.php                 # File/image attachment
│   ├── FileUpload.php                 # Outbound file
│   └── PostableMessage.php            # Outbound message builder
├── Threads/
│   ├── Thread.php                     # Conversation context
│   └── Channel.php                    # Thread container
├── Cards/
│   ├── Card.php                       # Fluent card builder
│   ├── Modal.php                      # Modal form builder
│   ├── Elements/                      # Text, Image, Divider, Section, Fields, Actions
│   └── Interactive/                   # Button, LinkButton, Select, RadioSelect, TextInput
├── Events/                            # Handler event objects
│   ├── ActionEvent.php
│   ├── ReactionEvent.php
│   ├── SlashCommandEvent.php
│   ├── ModalSubmitEvent.php
│   ├── ModalCloseEvent.php
│   └── ModalResponse.php
├── Adapters/
│   ├── Slack/                         # SlackAdapter, FormatConverter, CardRenderer
│   ├── Discord/                       # DiscordAdapter + Gateway/
│   ├── Teams/                         # TeamsAdapter
│   ├── GoogleChat/                    # GoogleChatAdapter
│   ├── GitHub/                        # GitHubAdapter
│   ├── Linear/                        # LinearAdapter
│   ├── Telegram/                      # TelegramAdapter, FormatConverter, CardRenderer
│   ├── Concerns/                      # VerifiesWebhooks, HandlesRateLimits, ConvertsMarkdown
│   └── BaseFormatConverter.php
├── State/                             # CacheStateAdapter, RedisStateAdapter, ArrayStateAdapter
├── Markdown/                          # Markdown, AstBuilder, AstWalker, TypeGuards
├── Emoji/                             # Emoji constants + EmojiResolver
├── Errors/                            # ChatError, AdapterError, and subclasses
├── Types/                             # FetchOptions, FetchResult, ThreadInfo, etc.
├── Jobs/                              # ProcessChatEvent (queue dispatch)
├── Facades/                           # Chat facade
└── Http/
    └── ChatWebhookController.php      # Universal webhook router
```

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- `ext-sodium` (for Discord Ed25519 verification)
- `ratchet/pawl` (optional, for Discord Gateway)

## Testing

```bash
composer test
```

The package includes 1,380+ tests covering unit tests, integration replay tests, and adapter-specific tests.

## License

MIT
