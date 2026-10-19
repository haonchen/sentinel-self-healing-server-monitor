<?php

use App\Services\ServiceMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sentinel:monitor', function (ServiceMonitor $monitor) {
    $checked = $monitor->run();
    $this->info("Checked {$checked} monitored service(s).");
})->purpose('Run one Sentinel health-check and self-healing cycle');

Schedule::command('sentinel:monitor')->everyTenSeconds()->withoutOverlapping();
