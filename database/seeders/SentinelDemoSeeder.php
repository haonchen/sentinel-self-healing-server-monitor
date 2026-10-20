<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class SentinelDemoSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'slug' => 'edge-proxy',
                'name' => 'Edge proxy',
                'container_name' => 'sentinel-demo-web',
                'description' => 'Demo HTTP service powered by nginx.',
                'documentation_url' => 'https://nginx.org/en/docs/',
                'check_type' => 'http',
                'check_target' => 'http://demo-web/',
            ],
            [
                'slug' => 'sample-api',
                'name' => 'Sample API',
                'container_name' => 'sentinel-demo-api',
                'description' => 'Demo API endpoint powered by whoami.',
                'documentation_url' => 'https://github.com/traefik/whoami',
                'check_type' => 'http',
                'check_target' => 'http://demo-api/',
            ],
            [
                'slug' => 'testing-api',
                'name' => 'Testing API',
                'container_name' => 'testing-api-app-1',
                'description' => 'Products API running in the separate testing-api Docker project.',
                'documentation_url' => null,
                'check_type' => 'http',
                'check_target' => 'http://host.docker.internal:8080/up',
            ],
            [
                'slug' => 'cache',
                'name' => 'Cache',
                'container_name' => 'sentinel-demo-cache',
                'description' => 'Demo Redis service with a TCP probe.',
                'documentation_url' => 'https://redis.io/docs/latest/',
                'check_type' => 'tcp',
                'check_target' => 'demo-cache:6379',
            ],
        ];

        foreach ($services as $service) {
            $record = Service::query()->firstOrCreate(
                ['slug' => $service['slug']],
                [...$service, 'enabled' => true, 'status' => 'unknown'],
            );

            if (blank($record->documentation_url)) {
                $record->update(['documentation_url' => $service['documentation_url']]);
            }

            if ($record->check_target !== $service['check_target']) {
                $record->update(['check_target' => $service['check_target']]);
            }
        }
    }
}
