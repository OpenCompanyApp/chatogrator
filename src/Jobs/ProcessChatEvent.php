<?php

namespace OpenCompany\Chatogrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;

class ProcessChatEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $eventType,
        public readonly string $adapterName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $chat = app(Chat::class);
        $adapter = $chat->getAdapter($this->adapterName);

        if ($adapter === null) {
            return;
        }

        match ($this->eventType) {
            'message' => $chat->handleIncomingMessage(
                $adapter,
                $this->payload['threadId'],
                Message::fromJSON($this->payload['message']),
            ),
            'action' => $chat->processAction(
                array_merge($this->payload, ['adapter' => $adapter])
            ),
            'reaction' => $chat->processReaction(
                array_merge($this->payload, ['adapter' => $adapter])
            ),
            'slash_command' => $chat->processSlashCommand(
                array_merge($this->payload, ['adapter' => $adapter])
            ),
            'message_edited' => $chat->processMessageEdited(
                array_merge($this->payload, ['adapter' => $adapter])
            ),
            'message_deleted' => $chat->processMessageDeleted(
                array_merge($this->payload, ['adapter' => $adapter])
            ),
            default => null,
        };
    }
}
