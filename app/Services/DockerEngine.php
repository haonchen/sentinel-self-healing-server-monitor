<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DockerEngine
{
    public function isRunning(string $container): ?bool
    {
        $response = $this->request()->get($this->path("/containers/{$this->containerId($container)}/json"));

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return (bool) $response->json('State.Running');
    }

    public function restart(string $container): void
    {
        $response = $this->request()->post($this->path("/containers/{$this->containerId($container)}/restart").'?t=10');

        if (! $response->successful() && $response->status() !== 304) {
            $response->throw();
        }
    }

    public function stop(string $container): void
    {
        $response = $this->request()->post($this->path("/containers/{$this->containerId($container)}/stop").'?t=10');

        if (! $response->successful() && $response->status() !== 304) {
            $response->throw();
        }
    }

    public function logs(string $container, int $lines = 80): string
    {
        $response = $this->request()->get($this->path("/containers/{$this->containerId($container)}/logs"), [
            'stdout' => 'true',
            'stderr' => 'true',
            'timestamps' => 'true',
            'tail' => min(max($lines, 1), 100),
        ]);
        $response->throw();

        return $this->decodeLogStream($response->body());
    }

    private function request(): PendingRequest
    {
        $socket = config('services.sentinel.docker_socket');

        return Http::baseUrl('http://localhost')->withOptions([
            'curl' => [CURLOPT_UNIX_SOCKET_PATH => $socket],
            'connect_timeout' => 3,
            'timeout' => 10,
        ]);
    }

    private function path(string $path): string
    {
        $version = config('services.sentinel.docker_api_version');

        return ($version ? '/'.$version : '').$path;
    }

    private function containerId(string $container): string
    {
        return rawurlencode($container);
    }

    private function decodeLogStream(string $stream): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($stream);

        while ($offset + 8 <= $length) {
            $frameLength = unpack('Nlength', substr($stream, $offset + 4, 4))['length'];

            if ($frameLength < 0 || $offset + 8 + $frameLength > $length) {
                return trim($stream);
            }

            $output .= substr($stream, $offset + 8, $frameLength);
            $offset += 8 + $frameLength;
        }

        return trim($output !== '' ? $output : $stream);
    }
}
