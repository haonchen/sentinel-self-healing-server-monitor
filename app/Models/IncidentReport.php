<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    protected $fillable = ['incident_id', 'severity', 'likely_cause', 'suggested_fix', 'log_excerpt', 'model'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
