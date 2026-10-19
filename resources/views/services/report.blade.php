<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $service->name }} service report | Sentinel</title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202722;background:#f4f5f1}*{box-sizing:border-box}body{margin:0;padding:32px}.report{max-width:900px;margin:auto;padding:42px;background:#fff;border:1px solid #e1e6de}.toolbar{max-width:900px;margin:0 auto 16px;display:flex;justify-content:space-between;align-items:center}.toolbar a,.toolbar button{font:inherit;font-size:14px;color:#526b4c}.toolbar button{padding:9px 13px;border:0;border-radius:4px;background:#344b3c;color:white;cursor:pointer}.eyebrow{font-size:11px;letter-spacing:.12em;color:#768273;text-transform:uppercase}h1{margin:8px 0;font-size:30px}h2{margin:30px 0 12px;font-size:17px}.muted{color:#6f786f;font-size:13px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:25px 0}.metric{padding:14px;background:#f2f5ef;border:1px solid #e5eae1}.metric small{display:block;color:#768273;font-size:10px;text-transform:uppercase;letter-spacing:.08em}.metric strong{display:block;margin-top:8px;font-size:16px}.details{display:grid;grid-template-columns:150px 1fr;gap:9px;font-size:13px}.details dt{color:#768273}.details dd{margin:0;overflow-wrap:anywhere}a{color:#3b7755;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px 8px;border-bottom:1px solid #e7eae5;text-align:left;vertical-align:top}th{font-size:10px;text-transform:uppercase;color:#768273}.status{font-weight:600;text-transform:capitalize}.empty{padding:14px;background:#f7f8f6;color:#758075;font-size:13px}.incident{padding:13px 0;border-bottom:1px solid #e7eae5;font-size:13px}.incident strong{display:flex;justify-content:space-between;gap:12px}.incident p{margin:7px 0;color:#596159}.incident small{color:#758075}.doc-form{margin-top:12px;display:flex;gap:8px}.doc-form input{flex:1;min-width:0;padding:8px;border:1px solid #dfe4dc;border-radius:3px}.doc-form button{padding:8px 12px;border:0;border-radius:3px;background:#344b3c;color:white;cursor:pointer}.flash{margin:0 auto 12px;max-width:900px;padding:10px 14px;background:#e9eee2;color:#344b3c;font-size:13px}@media print{@page{margin:16mm}body{padding:0;background:white}.report{max-width:none;padding:0;border:0}.toolbar,.doc-form,.flash{display:none}a{color:inherit;text-decoration:none}.summary,.metric{break-inside:avoid}h2{break-after:avoid}tr,.incident{break-inside:avoid}}@media(max-width:600px){body{padding:12px}.report{padding:20px}.summary{grid-template-columns:repeat(2,1fr)}.details{grid-template-columns:110px 1fr}table{font-size:10px}th,td{padding:7px 4px}}
    </style>
</head>
<body>
    <nav class="toolbar"><a href="{{ route('dashboard') }}">← Back to Sentinel</a><span><a href="{{ route('services.check', $service) }}" target="_blank" rel="noopener noreferrer">Test health route</a> <button type="button" onclick="window.print()">Print / Save as PDF</button></span></nav>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <main class="report">
        <div class="eyebrow">Sentinel · Service report</div>
        <h1>{{ $service->name }}</h1>
        <p class="muted">Generated {{ $generatedAt->format('F j, Y \a\t H:i T') }}</p>

        <section class="summary" aria-label="Service summary">
            <div class="metric"><small>Current status</small><strong>{{ ucfirst($service->status) }}</strong></div>
            <div class="metric"><small>Recent uptime</small><strong>{{ $uptime === null ? 'No checks' : number_format($uptime, 2).'%' }}</strong></div>
            <div class="metric"><small>Checks included</small><strong>{{ $checks->count() }}</strong></div>
            <div class="metric"><small>Open incidents</small><strong>{{ $incidents->where('status', 'open')->count() }}</strong></div>
        </section>

        <h2>Configuration</h2>
        <dl class="details">
            <dt>Container</dt><dd>{{ $service->container_name }}</dd>
            <dt>Description</dt><dd>{{ $service->description ?: 'Not provided' }}</dd>
            <dt>Health check</dt><dd>{{ strtoupper($service->check_type) }} · {{ $service->check_target }}</dd>
            <dt>Last checked</dt><dd>{{ $service->last_checked_at?->format('F j, Y H:i:s T') ?? 'Not checked yet' }}</dd>
            <dt>Last healthy</dt><dd>{{ $service->last_healthy_at?->format('F j, Y H:i:s T') ?? 'No successful check yet' }}</dd>
            <dt>Documentation</dt><dd>@if($service->documentation_url)<a href="{{ $service->documentation_url }}" target="_blank" rel="noopener noreferrer">Open / download documentation</a><div class="muted">{{ $service->documentation_url }}</div>@else Not provided yet.@endif
                <form class="doc-form" method="post" action="{{ route('services.documentation.update', $service) }}">@csrf<label class="visually-hidden" for="documentation-url">Documentation URL</label><input id="documentation-url" name="documentation_url" type="url" value="{{ old('documentation_url', $service->documentation_url) }}" placeholder="https://docs.example.com/service"><button type="submit">Save link</button></form>
                @error('documentation_url')<div class="muted">{{ $message }}</div>@enderror
            </dd>
        </dl>

        <h2>Recent health checks</h2>
        @if($checks->isEmpty())
            <div class="empty">No health checks have been recorded for this service.</div>
        @else
            <table><thead><tr><th>Time</th><th>Status</th><th>Response</th><th>Details</th></tr></thead><tbody>
                @foreach($checks as $check)
                    <tr><td>{{ $check->checked_at?->format('M j, Y H:i:s T') }}</td><td class="status">{{ $check->status }}</td><td>{{ $check->response_ms !== null ? $check->response_ms.' ms' : '—' }}{{ $check->http_status ? ' · HTTP '.$check->http_status : '' }}</td><td>{{ $check->message ?: '—' }}</td></tr>
                @endforeach
            </tbody></table>
        @endif

        <h2>Recent incidents</h2>
        @forelse($incidents as $incident)
            <article class="incident"><strong><span>{{ ucfirst($incident->status) }} · {{ $incident->detected_at->format('M j, Y H:i:s T') }}</span><span>{{ $incident->report?->severity ? ucfirst($incident->report->severity).' severity' : '' }}</span></strong><p>{{ $incident->failure_reason ?: 'Health check failed.' }}</p>@if($incident->report?->likely_cause)<p><b>Likely cause:</b> {{ $incident->report->likely_cause }}</p>@endif @if($incident->report?->suggested_fix)<p><b>Suggested fix:</b> {{ $incident->report->suggested_fix }}</p>@endif @if($incident->recovered_at)<small>Recovered {{ $incident->recovered_at->format('M j, Y H:i:s T') }}</small>@endif</article>
        @empty
            <div class="empty">No incidents have been recorded for this service.</div>
        @endforelse
    </main>
</body>
</html>
