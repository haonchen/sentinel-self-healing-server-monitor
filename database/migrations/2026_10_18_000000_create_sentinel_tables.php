<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('container_name');
            $table->text('description')->nullable();
            $table->string('check_type')->default('http');
            $table->string('check_target');
            $table->string('status')->default('unknown');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('cooldown_seconds')->default(300);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_healthy_at')->nullable();
            $table->timestamp('last_restart_at')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'status']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->text('failure_reason')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('restarted_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'detected_at']);
        });

        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedSmallInteger('response_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->index(['service_id', 'checked_at']);
        });

        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('severity')->nullable();
            $table->text('likely_cause')->nullable();
            $table->text('suggested_fix')->nullable();
            $table->longText('log_excerpt')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
        Schema::dropIfExists('health_checks');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('services');
    }
};
