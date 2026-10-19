@if($incidents->isEmpty())
    <div class="incident-empty"><span class="resolved-mark">✓</span><span><strong>No incidents to report</strong><small>When something needs attention, it will show up here.</small></span><span class="all-clear">ALL CLEAR</span></div>
@else
    <div class="incident-table"><div class="table-head"><span>INCIDENT</span><span>STATUS</span><span>DETECTED</span><span>RECOVERY</span><span>ANALYSIS</span></div>
    @foreach($incidents as $incident)
        <a class="incident-row" href="{{ route('incidents.show', $incident) }}"><span class="incident-name"><i class="incident-marker {{ $incident->status }}"></i><span><strong>{{ $incident->service->name }}</strong><small>{{ $incident->failure_reason ?? 'Container health check failed' }}</small></span></span><span><span class="incident-state {{ $incident->status }}">{{ $incident->status === 'open' ? 'Investigating' : 'Resolved' }}</span></span><span class="check-cell">{{ $incident->detected_at->diffForHumans() }}</span><span class="uptime-cell">{{ $incident->recovered_at ? $incident->detected_at->diffInSeconds($incident->recovered_at).'s' : '—' }}</span><span class="analysis-cell">{{ $incident->report?->severity ? ucfirst($incident->report->severity) : 'Pending' }} <span>↗</span></span></a>
    @endforeach
    </div>
@endif
