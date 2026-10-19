<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Incident extends Model
{
    protected $fillable = ['service_id', 'status', 'failure_reason', 'detected_at', 'restarted_at', 'recovered_at'];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'restarted_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(IncidentReport::class);
    }
}
