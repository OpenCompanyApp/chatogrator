<?php

namespace OpenCompany\Chatogrator\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenCompany\Chatogrator\Chat;

class ChatWebhookController
{
    public function __invoke(Request $request, string $adapter): Response
    {
        $chat = app(Chat::class);

        return $chat->handleWebhook($adapter, $request);
    }
}
