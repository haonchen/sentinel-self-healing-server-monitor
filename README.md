# Sentinel

Sentinel monitors Docker containers, records health history and incidents in MariaDB, attempts a cooldown-limited restart, and asks a local Ollama model to explain failures from recent container logs.

## Run the demo

Requirements: Docker Engine with Compose, and a Linux host with access to `/var/run/docker.sock`.

1. Make sure `.env` has an application key. Generate one with `php artisan key:generate` if needed.
2. Start the stack with `docker compose up --build -d`.
3. Open [http://localhost:8000](http://localhost:8000). The stack binds only to loopback and seeds three demo services.
4. Download the local model once with `docker compose --profile setup-ai run --rm ollama-pull`. The default is `llama3.1:8b` and requires several gigabytes of disk space.
5. Use the stop icon on a service row to run a chaos test. Sentinel will detect the stopped container and attempt to restart it on its next check.

The scheduler checks enabled services every ten seconds. The queue worker runs incident analysis asynchronously. To inspect either process, use `docker compose logs -f scheduler worker`.

## Configure a service

Create a `Service` record with a unique `slug`, its exact Docker `container_name`, `check_type` (`http` or `tcp`), and a `check_target` reachable from the app container. Set `cooldown_seconds` to control the minimum interval between automatic restarts. Services are enabled by default.

The HTTP probe considers 2xx responses healthy. TCP targets use `host:port`. A failed check creates one open incident; later failures update service status without duplicating the incident. A healthy check resolves the open incident and records its recovery time. Each incident queues one report job with up to 80 recent log lines.

## Local development

Install PHP dependencies and frontend assets, configure a MariaDB database and local Ollama endpoint in `.env`, then run migrations and seed demo records:

```sh
composer install
npm install
npm run build
php artisan migrate
php artisan db:seed --class=SentinelDemoSeeder
```

Run `php artisan schedule:work` and `php artisan queue:work` in separate terminals alongside `php artisan serve`. Configure `DOCKER_SOCKET`, `OLLAMA_URL`, and `OLLAMA_MODEL` as needed. Chaos actions are disabled unless `SENTINEL_CHAOS_ENABLED=true`.

## Security notes

This demo dashboard does not include user authentication. Do not expose it to an untrusted network without adding authentication and authorization. The Compose port is bound to `127.0.0.1`. Granting access to the Docker socket is effectively granting host-root control to the app, scheduler, and worker; only run this stack on a host and network you trust. Container logs and Ollama prompts stay on the local Docker network.
