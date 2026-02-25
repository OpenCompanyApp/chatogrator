<?php

namespace OpenCompany\Chatogrator\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;

interface Adapter
{
    public function name(): string;

    public function userName(): string;

    public function botUserId(): ?string;

    public function initialize(Chat $chat): void;

    public function handleWebhook(Request $request, Chat $chat): Response;

    public function parseMessage(mixed $raw): Message;

    public function postMessage(string $threadId, PostableMessage $message): SentMessage;

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage;

    public function deleteMessage(string $threadId, string $messageId): void;

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult;

    public function fetchMessage(string $threadId, string $messageId): ?Message;

    public function fetchThread(string $threadId): ThreadInfo;

    /** @param array<string, mixed> $data */
    public function encodeThreadId(array $data): string;

    /** @return array<string, mixed> */
    public function decodeThreadId(string $threadId): array;

    public function addReaction(string $threadId, string $messageId, string $emoji): void;

    public function removeReaction(string $threadId, string $messageId, string $emoji): void;

    public function startTyping(string $threadId): void;

    public function renderFormatted(string $markdown): string;

    public function openDM(string $userId): ?string;

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage;

    /** @return array<string, mixed>|null */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array;

    /**
     * @param  iterable<string>  $textStream
     * @param  array<string, mixed>  $options
     */
    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage;

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage;

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult;

    public function fetchChannelInfo(string $channelId): ?ChannelInfo;

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult;

    public function channelIdFromThreadId(string $threadId): ?string;

    public function isDM(string $threadId): bool;

    public function onThreadSubscribe(string $threadId): void;
}
