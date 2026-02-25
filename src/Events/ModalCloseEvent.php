<?php

namespace OpenCompany\Chatogrator\Events;

use OpenCompany\Chatogrator\Messages\Message;
use OpenCompany\Chatogrator\Threads\Channel;
use OpenCompany\Chatogrator\Threads\Thread;

class ModalCloseEvent
{
    public function __construct(
        public readonly string $callbackId,
        public readonly ?Thread $relatedThread = null,
        public readonly ?Message $relatedMessage = null,
        public readonly ?Channel $relatedChannel = null,
    ) {}
}
