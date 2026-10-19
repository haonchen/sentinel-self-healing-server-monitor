<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'container_name', 'description', 'documentation_url', 'check_type', 'check_target',
        'status', 'enabled', 'consecutive_failures', 'cooldown_seconds',
        'last_checked_at', 'last_healthy_at', 'last_restart_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_healthy_at' => 'datetime',
            'last_restart_at' => 'datetime',
        ];
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
