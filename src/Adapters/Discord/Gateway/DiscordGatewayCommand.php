<?php

namespace OpenCompany\Chatogrator\Adapters\Discord\Gateway;

use Illuminate\Console\Command;

class DiscordGatewayCommand extends Command
{
    protected $signature = 'chat:discord-gateway
        {--max-memory=128 : Maximum memory usage in MB before restart}';

    protected $description = 'Run the Discord Gateway WebSocket bridge';

    public function handle(): int
    {
        if (! class_exists(\Ratchet\Client\Connector::class)) {
            $this->error('ratchet/pawl is required for Discord Gateway.');
            $this->error('Install it with: composer require ratchet/pawl');

            return self::FAILURE;
        }

        $config = config('chatogrator.adapters.discord', []);
        $token = $config['bot_token'] ?? '';

        if (empty($token)) {
            $this->error('Discord bot_token is not configured.');

            return self::FAILURE;
        }

        $gatewaySecret = $config['gateway_secret'] ?? '';
        if (empty($gatewaySecret)) {
            $this->error('Discord gateway_secret is not configured.');
            $this->error('Set chatogrator.adapters.discord.gateway_secret in your config.');

            return self::FAILURE;
        }

        // Default intents: GUILD_MESSAGES (1<<9), GUILD_MESSAGE_REACTIONS (1<<10), MESSAGE_CONTENT (1<<15)
        $intents = $config['gateway_intents'] ?? ((1 << 9) | (1 << 10) | (1 << 15));
        $routePrefix = config('chatogrator.route_prefix', 'webhooks/chat');
        $webhookUrl = url("{$routePrefix}/discord");

        $loop = \React\EventLoop\Loop::get();

        $forwarder = new GatewayEventForwarder(
            webhookUrl: $webhookUrl,
            gatewaySecret: $gatewaySecret,
            logger: $this->createLogger(),
        );

        $connection = new GatewayConnection(
            token: $token,
            intents: $intents,
            loop: $loop,
            onEvent: fn (string $event, array $data) => $forwarder->forward($event, $data),
            logger: $this->createLogger(),
        );

        // Graceful shutdown
        if (function_exists('pcntl_signal')) {
            $loop->addSignal(SIGTERM, fn () => $connection->disconnect());
            $loop->addSignal(SIGINT, fn () => $connection->disconnect());
        }

        // Memory limit check
        $maxMemory = (int) $this->option('max-memory');
        $loop->addPeriodicTimer(15, function () use ($maxMemory, $connection) {
            $usageMB = memory_get_usage(true) / 1024 / 1024;
            if ($usageMB > $maxMemory) {
                $this->warn("Memory limit ({$maxMemory}MB) exceeded ({$usageMB}MB), restarting...");
                $connection->disconnect();
            }
        });

        $this->info('Discord Gateway bridge starting...');
        $this->info("Forwarding events to: {$webhookUrl}");

        $connection->connect();
        $loop->run();

        $this->info('Discord Gateway bridge stopped.');

        return self::SUCCESS;
    }

    protected function createLogger(): \Psr\Log\LoggerInterface
    {
        return new class($this) implements \Psr\Log\LoggerInterface
        {
            use \Psr\Log\LoggerTrait;

            public function __construct(protected Command $command) {}

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                match ($level) {
                    'error', 'critical', 'emergency', 'alert' => $this->command->error((string) $message),
                    'warning' => $this->command->warn((string) $message),
                    default => $this->command->info((string) $message),
                };
            }
        };
    }
}
