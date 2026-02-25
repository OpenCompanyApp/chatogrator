<?php

namespace OpenCompany\Chatogrator\Adapters\Discord\Gateway;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;

class GatewayConnection
{
    protected ?WebSocket $conn = null;

    protected ?int $heartbeatInterval = null;

    protected ?\React\EventLoop\TimerInterface $heartbeatTimer = null;

    protected ?int $sequence = null;

    protected ?string $sessionId = null;

    protected ?string $resumeGatewayUrl = null;

    protected bool $shouldResume = false;

    protected bool $heartbeatAcked = true;

    protected bool $running = true;

    protected int $reconnectAttempts = 0;

    /**
     * Gateway opcodes.
     */
    private const OP_DISPATCH = 0;

    private const OP_HEARTBEAT = 1;

    private const OP_IDENTIFY = 2;

    private const OP_RESUME = 6;

    private const OP_RECONNECT = 7;

    private const OP_INVALID_SESSION = 9;

    private const OP_HELLO = 10;

    private const OP_HEARTBEAT_ACK = 11;

    private const GATEWAY_URL = 'wss://gateway.discord.gg/?v=10&encoding=json';

    public function __construct(
        protected string $token,
        protected int $intents,
        protected LoopInterface $loop,
        /** @var callable(string, array): void */
        protected $onEvent,
        protected LoggerInterface $logger = new NullLogger,
    ) {}

    public function connect(): void
    {
        $url = $this->shouldResume && $this->resumeGatewayUrl
            ? $this->resumeGatewayUrl.'/?v=10&encoding=json'
            : self::GATEWAY_URL;

        $this->logger->info("Connecting to {$url}");

        $connector = new \Ratchet\Client\Connector($this->loop);

        $connector($url)->then(
            function (WebSocket $conn) {
                $this->conn = $conn;
                $this->reconnectAttempts = 0;

                $conn->on('message', function ($msg) {
                    $this->onMessage((string) $msg);
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->logger->warning("WebSocket closed: {$code} {$reason}");
                    $this->stopHeartbeat();

                    if ($this->running) {
                        $this->shouldResume = $this->sessionId !== null;
                        $this->scheduleReconnect();
                    }
                });

                $conn->on('error', function (\Exception $e) {
                    $this->logger->error("WebSocket error: {$e->getMessage()}");
                });
            },
            function (\Exception $e) {
                $this->logger->error("Connection failed: {$e->getMessage()}");

                if ($this->running) {
                    $this->scheduleReconnect();
                }
            }
        );
    }

    public function disconnect(): void
    {
        $this->running = false;
        $this->stopHeartbeat();

        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }

        $this->loop->stop();
        $this->logger->info('Gateway disconnected');
    }

    protected function onMessage(string $raw): void
    {
        $data = json_decode($raw, true);
        if ($data === null) {
            return;
        }

        $op = $data['op'] ?? null;
        $d = $data['d'] ?? null;
        $s = $data['s'] ?? null;
        $t = $data['t'] ?? null;

        if ($s !== null) {
            $this->sequence = $s;
        }

        match ($op) {
            self::OP_DISPATCH => $this->handleDispatch($t, $d ?? []),
            self::OP_HEARTBEAT => $this->sendHeartbeat(),
            self::OP_RECONNECT => $this->handleReconnect(),
            self::OP_INVALID_SESSION => $this->handleInvalidSession($d ?? false),
            self::OP_HELLO => $this->handleHello($d ?? []),
            self::OP_HEARTBEAT_ACK => $this->heartbeatAcked = true,
            default => null,
        };
    }

    protected function handleHello(array $data): void
    {
        $this->heartbeatInterval = $data['heartbeat_interval'] ?? 41250;
        $this->logger->info("Received HELLO, heartbeat interval: {$this->heartbeatInterval}ms");

        $this->startHeartbeat($this->heartbeatInterval);

        if ($this->shouldResume && $this->sessionId) {
            $this->sendResume();
        } else {
            $this->sendIdentify();
        }
    }

    protected function sendIdentify(): void
    {
        $this->logger->info('Sending IDENTIFY');

        $this->send([
            'op' => self::OP_IDENTIFY,
            'd' => [
                'token' => $this->token,
                'intents' => $this->intents,
                'properties' => [
                    'os' => PHP_OS_FAMILY,
                    'browser' => 'chatogrator',
                    'device' => 'chatogrator',
                ],
            ],
        ]);
    }

    protected function sendResume(): void
    {
        $this->logger->info('Sending RESUME');

        $this->send([
            'op' => self::OP_RESUME,
            'd' => [
                'token' => $this->token,
                'session_id' => $this->sessionId,
                'seq' => $this->sequence,
            ],
        ]);
    }

    protected function startHeartbeat(int $intervalMs): void
    {
        $this->stopHeartbeat();

        // First heartbeat with jitter
        $jitter = $intervalMs * (mt_rand() / mt_getrandmax()) / 1000;
        $this->loop->addTimer($jitter, function () use ($intervalMs) {
            $this->sendHeartbeat();

            $this->heartbeatTimer = $this->loop->addPeriodicTimer(
                $intervalMs / 1000,
                fn () => $this->sendHeartbeat()
            );
        });
    }

    protected function stopHeartbeat(): void
    {
        if ($this->heartbeatTimer) {
            $this->loop->cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
    }

    protected function sendHeartbeat(): void
    {
        if (! $this->heartbeatAcked) {
            $this->logger->warning('Heartbeat not ACKed, reconnecting...');
            $this->shouldResume = true;
            $this->conn?->close();

            return;
        }

        $this->heartbeatAcked = false;
        $this->send([
            'op' => self::OP_HEARTBEAT,
            'd' => $this->sequence,
        ]);
    }

    protected function handleDispatch(string $event, array $data): void
    {
        if ($event === 'READY') {
            $this->sessionId = $data['session_id'] ?? null;
            $this->resumeGatewayUrl = $data['resume_gateway_url'] ?? null;
            $this->shouldResume = false;
            $this->logger->info("READY — session: {$this->sessionId}");

            return;
        }

        if ($event === 'RESUMED') {
            $this->shouldResume = false;
            $this->logger->info('Session resumed');

            return;
        }

        // Forward relevant events
        $forwardedEvents = [
            'MESSAGE_CREATE',
            'MESSAGE_UPDATE',
            'MESSAGE_DELETE',
            'MESSAGE_REACTION_ADD',
            'MESSAGE_REACTION_REMOVE',
        ];

        if (in_array($event, $forwardedEvents, true)) {
            ($this->onEvent)($event, $data);
        }
    }

    protected function handleReconnect(): void
    {
        $this->logger->info('Received RECONNECT');
        $this->shouldResume = true;
        $this->conn?->close();
    }

    protected function handleInvalidSession(bool $resumable): void
    {
        $this->logger->warning('INVALID_SESSION, resumable: '.($resumable ? 'yes' : 'no'));

        if (! $resumable) {
            $this->sessionId = null;
            $this->sequence = null;
            $this->shouldResume = false;
        } else {
            $this->shouldResume = true;
        }

        // Wait 1-5 seconds before reconnecting (per Discord docs)
        $delay = mt_rand(1, 5);
        $this->loop->addTimer($delay, fn () => $this->connect());
    }

    protected function scheduleReconnect(): void
    {
        $this->reconnectAttempts++;
        $delay = min(pow(2, $this->reconnectAttempts), 60);
        $this->logger->info("Reconnecting in {$delay}s (attempt {$this->reconnectAttempts})");

        $this->loop->addTimer($delay, fn () => $this->connect());
    }

    protected function send(array $data): void
    {
        $this->conn?->send(json_encode($data));
    }
}
