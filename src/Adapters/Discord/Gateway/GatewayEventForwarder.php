<?php

namespace OpenCompany\Chatogrator\Adapters\Discord\Gateway;

use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GatewayEventForwarder
{
    public function __construct(
        protected string $webhookUrl,
        protected string $gatewaySecret,
        protected LoggerInterface $logger = new NullLogger,
    ) {}

    public function forward(string $eventName, array $data): void
    {
        try {
            Http::withHeaders([
                'X-Gateway-Source' => 'discord-gateway',
                'X-Gateway-Secret' => $this->gatewaySecret,
                'Content-Type' => 'application/json',
            ])->post($this->webhookUrl, [
                'type' => 'gateway_event',
                'event' => $eventName,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to forward {$eventName}: {$e->getMessage()}");
        }
    }
}
