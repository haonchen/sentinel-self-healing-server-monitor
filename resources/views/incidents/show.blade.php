<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incident #{{ $incident->id }} | Sentinel</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar"><a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">S</span><span>sentinel<span class="brand-period">.</span></span></a><div class="workspace-label">WORKSPACE</div><div class="workspace-switch"><span class="workspace-dot"></span><span>Production</span><span class="chevron">⌄</span></div><div class="nav-label">MONITOR</div><a class="nav-link" href="{{ route('dashboard') }}"><span class="nav-glyph">▦</span>Overview</a><a class="nav-link" href="{{ route('dashboard') }}#services"><span class="nav-glyph">◉</span>Services</a><a class="nav-link active" href="{{ route('dashboard') }}#incidents"><span class="nav-glyph">⌁</span>Incidents</a><div class="sidebar-bottom"><div class="local-ai"><span class="ai-indicator"></span><div><strong>Local AI</strong><small>Ollama · checking</small></div><span class="ai-menu">···</span></div></div></aside>
        <main class="main-content incident-page"><header class="topbar"><div class="breadcrumbs"><a href="{{ route('dashboard') }}">Monitor</a><b>/</b><a href="{{ route('dashboard') }}#incidents">Incidents</a><b>/</b><strong>#{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }}</strong></div><a class="back-link" href="{{ route('dashboard') }}">← Back to overview</a></header>
            <section class="incident-title"><div class="incident-title-icon">!</div><div><div class="eyebrow">INCIDENT #{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }} <span class="eyebrow-dot">/</span> {{ strtoupper($incident->status) }}</div><h1>{{ $incident->service->name }} <span class="heading-period">failure</span></h1><p>{{ $incident->failure_reason ?? 'Container health check failed' }}</p></div><span class="incident-state {{ $incident->status }}">{{ $incident->status === 'open' ? 'Investigating' : 'Resolved' }}</span></section>
            <div class="incident-layout"><section class="timeline-panel"><div class="section-heading"><div><span class="section-kicker">WHAT HAPPENED</span><h2>Incident timeline</h2></div></div><div class="timeline">
                <div class="timeline-item"><span class="timeline-marker detected">!</span><div><strong>Failure detected</strong><p>{{ $incident->service->name }} reported an unhealthy check.</p><time>{{ $incident->detected_at->format('M j, Y · H:i:s') }}</time></div></div>
                @if($incident->restarted_at)<div class="timeline-item"><span class="timeline-marker restarted">↻</span><div><strong>Container restarted</strong><p>Sentinel requested a restart through the Docker Engine API.</p><time>{{ $incident->restarted_at->format('M j, Y · H:i:s') }}</time></div></div>@endif
                @if($incident->recovered_at)<div class="timeline-item"><span class="timeline-marker recovered">✓</span><div><strong>Service recovered</strong><p>Health checks are passing again.</p><time>{{ $incident->recovered_at->format('M j, Y · H:i:s') }}</time></div></div>@endif
            </div><div class="recovery-stat"><span>TIME TO RECOVERY</span><strong>{{ $incident->recovered_at ? number_format($incident->detected_at->diffInSeconds($incident->recovered_at), 1).' sec' : 'Still ongoing' }}</strong></div></section>
            <section class="analysis-panel"><div class="section-heading"><div><span class="section-kicker">PRIVATE · PROCESSED LOCALLY</span><h2>AI analysis</h2></div><span class="model-tag">{{ $incident->report?->model ?? 'OLLAMA' }}</span></div>
                @if($incident->report)<div class="severity-row"><span>SEVERITY</span><span class="severity-pill {{ strtolower($incident->report->severity ?? 'unknown') }}">{{ ucfirst($incident->report->severity ?? 'Unknown') }}</span></div><div class="analysis-block"><span class="analysis-label">LIKELY CAUSE</span><p>{{ $incident->report->likely_cause ?? 'Analysis not available.' }}</p></div><div class="analysis-block"><span class="analysis-label">SUGGESTED FIX</span><p>{{ $incident->report->suggested_fix ?? 'No remediation suggestion yet.' }}</p></div>@else<div class="analysis-pending"><span class="ai-orbit">✳</span><strong>Analysis is pending</strong><p>Sentinel will review recent container logs with your local Ollama model.</p><span>LOGS NEVER LEAVE THIS MACHINE</span></div>@endif
            </section></div>
            @if($incident->report?->log_excerpt)<section class="logs-panel"><div class="section-heading"><div><span class="section-kicker">CONTAINER OUTPUT</span><h2>Log excerpt</h2></div></div><pre>{{ $incident->report->log_excerpt }}</pre></section>@endif
        </main>
    </div>
</body>
</html>