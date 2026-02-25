<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Events\ModalSubmitEvent;
use OpenCompany\Chatogrator\Events\ReactionEvent;
use OpenCompany\Chatogrator\Events\SlashCommandEvent;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Tests\Helpers\FixtureLoader;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\TestCase;
use OpenCompany\Chatogrator\Threads\Thread;

/**
 * Base class for integration replay tests.
 *
 * Provides common infrastructure for replaying recorded webhook
 * sequences through the Chat instance and verifying end-to-end behavior.
 */
abstract class ReplayTestCase extends TestCase
{
    protected Chat $chat;

    protected MockAdapter $mockAdapter;

    protected MockStateAdapter $stateAdapter;

    /** @var array<string, int> Counts how many times each handler type was called */
    protected array $handlerCalls = [];

    /** @var array<string, list<array>> Captured handler arguments by handler type */
    protected array $capturedArgs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->handlerCalls = [];
        $this->capturedArgs = [];
        $this->stateAdapter = new MockStateAdapter;
    }

    protected function tearDown(): void
    {
        if (isset($this->mockAdapter)) {
            $this->mockAdapter->reset();
        }
        $this->stateAdapter->reset();

        parent::tearDown();
    }

    /**
     * Create a Chat instance with a MockAdapter for the given adapter name.
     */
    protected function createChat(string $adapterName, array $config = []): Chat
    {
        $this->mockAdapter = new MockAdapter($adapterName);

        $chat = Chat::make($config['botName'] ?? 'TestBot')
            ->adapter($adapterName, $this->mockAdapter)
            ->state($this->stateAdapter);

        $this->chat = $chat;

        return $chat;
    }

    /**
     * Load a fixture JSON file and replay each recorded webhook request
     * through the Chat instance.
     *
     * @return array{responses: Response[], events: array}
     */
    protected function replayFixture(string $fixturePath, string $adapterName): array
    {
        $fixture = FixtureLoader::load($fixturePath);
        $responses = [];
        $events = [];

        foreach ($fixture as $key => $payload) {
            if (in_array($key, ['botName', 'botUserId', 'appId', 'botId', 'serviceUrl', 'metadata'])) {
                continue;
            }

            $request = $this->createWebhookRequest($payload, $adapterName);
            $response = $this->mockAdapter->handleWebhook($request, $this->chat);
            $responses[] = $response;
            $events[$key] = $response;
        }

        return ['responses' => $responses, 'events' => $events];
    }

    /**
     * Send a single webhook payload through the Chat instance.
     */
    protected function sendWebhook(array $payload, string $adapterName): Response
    {
        $request = $this->createWebhookRequest($payload, $adapterName);

        return $this->mockAdapter->handleWebhook($request, $this->chat);
    }

    /**
     * Create a Laravel Request from a webhook payload.
     */
    protected function createWebhookRequest(array $payload, string $adapterName): Request
    {
        $request = Request::create(
            uri: "/webhooks/chat/{$adapterName}",
            method: 'POST',
            content: json_encode($payload),
        );
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    /**
     * Record a handler invocation.
     */
    protected function recordHandler(string $handlerName, array $args = []): void
    {
        $this->handlerCalls[$handlerName] = ($this->handlerCalls[$handlerName] ?? 0) + 1;
        $this->capturedArgs[$handlerName][] = $args;
    }

    /**
     * Assert a handler was called exactly N times.
     */
    protected function assertHandlerCalled(string $handlerName, int $times = 1): void
    {
        $actual = $this->handlerCalls[$handlerName] ?? 0;
        $this->assertEquals(
            $times,
            $actual,
            "Expected handler '{$handlerName}' to be called {$times} time(s), but was called {$actual} time(s)."
        );
    }

    /**
     * Assert a handler was never called.
     */
    protected function assertHandlerNotCalled(string $handlerName): void
    {
        $this->assertHandlerCalled($handlerName, 0);
    }

    /**
     * Get the captured arguments for a handler call by index.
     */
    protected function getCapturedArgs(string $handlerName, int $index = 0): ?array
    {
        return $this->capturedArgs[$handlerName][$index] ?? null;
    }

    /**
     * Get all captured arguments for a handler.
     */
    protected function getAllCapturedArgs(string $handlerName): array
    {
        return $this->capturedArgs[$handlerName] ?? [];
    }

    /**
     * Load a fixture from the replay directory.
     */
    protected function loadReplayFixture(string $filename): array
    {
        return FixtureLoader::load("replay/{$filename}");
    }

    /**
     * Assert that the mock adapter posted a message containing the given text.
     */
    protected function assertMessagePosted(string $textContains): void
    {
        $found = false;
        foreach ($this->mockAdapter->postedMessages as $posted) {
            $message = $posted['message'];
            $text = is_string($message) ? $message : ($message->getText() ?? '');
            if (str_contains($text, $textContains)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Expected a posted message containing '{$textContains}', but none was found."
        );
    }

    /**
     * Assert that the mock adapter edited a message containing the given text.
     */
    protected function assertMessageEdited(string $textContains): void
    {
        $found = false;
        foreach ($this->mockAdapter->editedMessages as $edited) {
            $message = $edited['message'];
            $text = is_string($message) ? $message : ($message->getText() ?? '');
            if (str_contains($text, $textContains)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Expected an edited message containing '{$textContains}', but none was found."
        );
    }

    /**
     * Assert the count of posted messages.
     */
    protected function assertPostedMessageCount(int $count): void
    {
        $this->assertCount($count, $this->mockAdapter->postedMessages);
    }

    /**
     * Assert the count of edited messages.
     */
    protected function assertEditedMessageCount(int $count): void
    {
        $this->assertCount($count, $this->mockAdapter->editedMessages);
    }

    /**
     * Assert a reaction was added.
     */
    protected function assertReactionAdded(string $emoji): void
    {
        $found = false;
        foreach ($this->mockAdapter->addedReactions as $reaction) {
            if ($reaction['emoji'] === $emoji) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected reaction '{$emoji}' to be added, but it was not.");
    }

    /**
     * Assert typing was started for a thread.
     */
    protected function assertTypingStarted(): void
    {
        $this->assertNotEmpty($this->mockAdapter->typingStarted, 'Expected typing to have been started.');
    }

    /**
     * Assert a modal was opened.
     */
    protected function assertModalOpened(): void
    {
        $this->assertNotEmpty($this->mockAdapter->modalsOpened, 'Expected a modal to have been opened.');
    }

    /**
     * Assert a stream was initiated.
     */
    protected function assertStreamStarted(): void
    {
        $this->assertNotEmpty($this->mockAdapter->streamedMessages, 'Expected a stream to have been started.');
    }

    /**
     * Assert an ephemeral message was sent.
     */
    protected function assertEphemeralSent(string $textContains = ''): void
    {
        if ($textContains === '') {
            $this->assertNotEmpty($this->mockAdapter->ephemeralMessages, 'Expected an ephemeral message.');

            return;
        }

        $found = false;
        foreach ($this->mockAdapter->ephemeralMessages as $msg) {
            $message = $msg['message'];
            $text = is_string($message) ? $message : ($message->getText() ?? '');
            if (str_contains($text, $textContains)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected an ephemeral message containing '{$textContains}'.");
    }

    /**
     * Assert a DM was opened.
     */
    protected function assertDMOpened(): void
    {
        $this->assertNotEmpty($this->mockAdapter->dmOpened, 'Expected a DM to have been opened.');
    }

    /**
     * Create a Thread object for test assertions.
     */
    protected function createThread(string $threadId, ?string $channelId = null, bool $isDM = false): Thread
    {
        return new Thread(
            id: $threadId,
            adapter: $this->mockAdapter,
            chat: $this->chat,
            channelId: $channelId,
            isDM: $isDM,
        );
    }
}
