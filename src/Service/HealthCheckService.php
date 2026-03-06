<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class HealthCheckService
{
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private Connection $connection,
        private string $redisUrl,
        private string $messengerTransportDsn,
        private string $jwtSecretKey,
        private string $jwtPublicKey,
    ) {
    }

    /** @return array<string, array{status: string, response_time_ms: float, error?: string}> */
    public function check(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'rabbitmq' => $this->checkRabbitMq(),
            'jwt_keys' => $this->checkJwtKeys(),
        ];
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            $this->connection->executeQuery('SELECT 1');

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            $parsed = parse_url($this->redisUrl);
            $redis = new \Redis();
            $redis->connect(
                $parsed['host'] ?? 'redis',
                $parsed['port'] ?? 6379,
                self::TIMEOUT_SECONDS,
            );
            if (!empty($parsed['pass'])) {
                $redis->auth($parsed['pass']);
            }
            $redis->ping();
            $redis->close();

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRabbitMq(): array
    {
        $start = microtime(true);
        try {
            $parsed = parse_url($this->messengerTransportDsn);
            $host = $parsed['host'] ?? 'rabbitmq';
            $port = $parsed['port'] ?? 5672;

            $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_SECONDS);
            if ($socket === false) {
                return [
                    'status' => 'down',
                    'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                    'error' => "Connection failed: $errstr ($errno)",
                ];
            }
            fclose($socket);

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkJwtKeys(): array
    {
        $start = microtime(true);
        $privateExists = file_exists($this->jwtSecretKey);
        $publicExists = file_exists($this->jwtPublicKey);

        if ($privateExists && $publicExists) {
            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        $missing = [];
        if (!$privateExists) {
            $missing[] = 'private key';
        }
        if (!$publicExists) {
            $missing[] = 'public key';
        }

        return [
            'status' => 'down',
            'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'error' => 'Missing: ' . implode(', ', $missing),
        ];
    }
}
