<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HealthProbe
{
    public function __construct(private readonly DockerEngine $docker) {}

    public function check(Service $service): array
    {
        try {
            $running = $this->docker->isRunning($service->container_name);
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'message' => 'Docker Engine unavailable: '.$exception->getMessage(),
                'response_ms' => null,
                'http_status' => null,
                'docker_available' => false,
            ];
        }

        if ($running !== true) {
            return [
                'healthy' => false,
                'message' => $running === null ? 'Container was not found' : 'Container is not running',
                'response_ms' => null,
                'http_status' => null,
                'docker_available' => true,
            ];
        }

        return $service->check_type === 'tcp'
            ? $this->checkTcp($service->check_target)
            : $this->checkHttp($service->check_target);
    }

    private function checkHttp(string $target): array
    {
        $startedAt = hrtime(true);

        try {
            $response = Http::connectTimeout(2)->timeout(4)->get($target);
            $healthy = $response->successful();

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'HTTP '.$response->status() : 'HTTP '.$response->status(),
                'response_ms' => $this->elapsedMilliseconds($startedAt),
                'http_status' => $response->status(),
                'docker_available' => true,
            ];
        } catch (ConnectionException $exception) {
            return [
                'healthy' => false,
                'message' => $exception->getMessage() ?: 'HTTP health check timed out',
                'response_ms' => $this->elapsedMilliseconds($startedAt),
                'http_status' => null,
                'docker_available' => true,
            ];
        }
    }

    private function checkTcp(string $target): array
    {
        $startedAt = hrtime(true);
        $socket = @stream_socket_client('tcp://'.$target, $errorCode, $errorMessage, 2);

        if ($socket !== false) {
            fclose($socket);
        }

        return [
            'healthy' => $socket !== false,
            'message' => $socket !== false ? 'TCP connection accepted' : ($errorMessage ?: "TCP connection failed ({$errorCode})"),
            'response_ms' => $this->elapsedMilliseconds($startedAt),
            'http_status' => null,
            'docker_available' => true,
        ];
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) min(65535, round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
