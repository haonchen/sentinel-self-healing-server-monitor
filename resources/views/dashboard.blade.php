<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sentinel | Service health</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">S</span><span>sentinel<span class="brand-period">.</span></span></a>
            <div class="workspace-label">WORKSPACE</div>
            <div class="workspace-switch"><span class="workspace-dot"></span><span>Production</span><span class="chevron">⌄</span></div>
            <div class="nav-label">MONITOR</div>
            <a class="nav-link active" href="{{ route('dashboard') }}"><span class="nav-glyph">▦</span>Overview<span class="nav-count">{{ $services->count() }}</span></a>
            <a class="nav-link" href="#services"><span class="nav-glyph">◉</span>Services</a>
            <a class="nav-link" href="#incidents"><span class="nav-glyph">⌁</span>Incidents
                <span class="nav-count alert-count" id="open-incident-nav" @if(!$openIncidents) hidden @endif>{{ $openIncidents }}</span>
            </a>
            <div class="sidebar-bottom">
                <div class="local-ai"><span class="ai-indicator"></span><div><strong>Local AI</strong><small>Ollama · {{ $ollamaModel }}</small></div><span class="ai-menu">···</span></div>
                <div class="user-block"><span class="avatar">S</span><div><strong>Sentinel operator</strong><small>Local workspace</small></div><span class="ai-menu">⌄</span></div>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar"><div class="breadcrumbs"><span>Monitor</span><b>/</b><strong>Overview</strong></div><div class="topbar-right"><span class="live-label"><i></i> LIVE</span><span class="topbar-divider"></span><span class="clock-label" id="last-refresh">{{ $lastCheck ? \Illuminate\Support\Carbon::parse($lastCheck)->diffForHumans() : 'Waiting for first check' }}</span><button class="icon-button" id="refresh-button" type="button" title="Refresh dashboard" aria-label="Refresh dashboard">↻</button></div></header>

            @if(session('status'))<div class="flash-message">{{ session('status') }}</div>@endif
            @if($errors->has('chaos'))<div class="flash-message error-message">{{ $errors->first('chaos') }}</div>@endif
            <section class="page-heading"><div><div class="eyebrow">{{ strtoupper(now()->format('l, F j, Y')) }} <span class="eyebrow-dot">/</span> SYSTEM OVERVIEW</div><h1><span id="time-greeting">{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}</span><span class="heading-period">.</span></h1><p class="subheading">A clear view of everything running in your environment.</p></div><div class="heading-actions"><span class="cluster-tag"><span></span> LOCAL CLUSTER</span></div></section>

            <section class="summary-strip" aria-label="Environment summary">
                <div class="summary-main"><span class="summary-icon">✳</span><div><strong id="overall-status">{{ $overallStatus }}</strong><small>{{ $services->count() }} monitored services <span>·</span> checks every 10 seconds</small></div></div>
                <div class="summary-metrics"><div><span class="metric-value good" id="healthy-count">{{ $healthy }}</span><span class="metric-label">HEALTHY</span></div><div><span class="metric-value warn" id="degraded-count">{{ $degraded }}</span><span class="metric-label">DEGRADED</span></div><div><span class="metric-value bad" id="down-count">{{ $down }}</span><span class="metric-label">DOWN</span></div></div>
            </section>

            <div class="content-grid">
                <section class="services-section" id="services">
                    <div class="section-heading"><div><span class="section-kicker">YOUR INFRASTRUCTURE</span><h2>Services <span class="count-pill">{{ $services->count() }}</span></h2></div><button class="primary-button" type="button" data-open-service-dialog><span class="button-plus">+</span>Add service</button></div>
                    @if($services->isEmpty())
                        <div class="empty-state"><div class="empty-mark">＋</div><div><strong>No services connected</strong><p>Register containers to start monitoring their health here.</p></div><button class="text-link" type="button" data-open-service-dialog>Add a service <span>→</span></button></div>
                    @else
                        <div class="service-table"><div class="table-head"><span>SERVICE</span><span>STATUS</span><span>UPTIME</span><span>LAST CHECK</span><span></span></div>
                        @foreach($services as $service)
                            <div class="service-row" data-service-id="{{ $service->id }}" style="--row-index: {{ $loop->index }}">
                                <span class="service-name"><span class="service-icon">{{ strtoupper(substr($service->name, 0, 1)) }}</span><span><strong>{{ $service->name }}</strong><small>{{ $service->container_name }}</small></span></span>
                                <span class="status-cell"><i class="status-dot {{ $service->status }}"></i><span class="status-text">{{ ucfirst($service->status) }}</span></span>
                                <span class="uptime-cell">{{ $service->checks_last_24h_count ? number_format($service->healthy_checks_last_24h_count / $service->checks_last_24h_count * 100, 2).'%' : '—' }}</span>
                                <span class="check-cell">{{ $service->last_checked_at?->diffForHumans() ?? 'Not checked yet' }}</span>
                                <details class="service-actions">
                                    <summary class="service-actions-button" aria-label="Manage {{ $service->name }}">Manage <span aria-hidden="true">⌄</span></summary>
                                    <div class="service-action-menu">
                                        <a class="service-menu-item service-report-link" href="{{ route('services.report', $service) }}">View report</a>
                                        <button
                                            class="service-menu-item"
                                            type="button"
                                            data-configure-service
                                            data-service-id="{{ $service->id }}"
                                            data-update-url="{{ route('services.update', $service) }}"
                                            data-name="{{ $service->name }}"
                                            data-container-name="{{ $service->container_name }}"
                                            data-check-type="{{ $service->check_type }}"
                                            data-check-target="{{ $service->check_target }}"
                                            data-cooldown-seconds="{{ $service->cooldown_seconds }}"
                                            data-description="{{ $service->description }}"
                                            data-documentation-url="{{ $service->documentation_url }}"
                                        >Configure</button>
                                        @if($chaosEnabled)
                                            <form method="post" action="{{ route('services.chaos', $service) }}" class="chaos-form">
                                                @csrf
                                                <button class="service-menu-item chaos-menu-item" type="submit">Chaos test</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('services.destroy', $service) }}" class="remove-service-form" data-service-name="{{ $service->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="service-menu-item remove-menu-item" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        @endforeach
                        </div>
                    @endif
                </section>

                <aside class="activity-section">
                    <div class="section-heading"><div><span class="section-kicker">LAST 24 HOURS</span><h2>Activity</h2></div><button class="more-button" type="button" aria-label="Activity options">···</button></div>
                    <div class="activity-summary"><span class="activity-spark">⌁</span><div><strong id="activity-count">{{ $events->count() }} recent checks</strong><small>Latest monitor activity</small></div><span class="activity-period">24H</span></div>
                    <div class="activity-feed" id="activity-feed">@include('dashboard.partials.activity-feed', ['events' => $events])</div>
                    <a class="activity-footer" href="#incidents">View event history <span>→</span></a>
                </aside>
            </div>

            <section class="incidents-section" id="incidents"><div class="section-heading"><div><span class="section-kicker">REQUIRES ATTENTION</span><h2>Recent incidents <span class="count-pill" id="incident-open-count">{{ $openIncidents }} open</span></h2></div><a class="text-link" href="#incidents">All incidents <span>→</span></a></div>
                <div id="incident-content">@include('dashboard.partials.incident-list', ['incidents' => $incidents, 'openIncidents' => $openIncidents])</div>
            </section>
            <footer class="page-footer"><span>Sentinel <span class="footer-dot">·</span> Self-hosted monitoring</span><span>Data stays on this machine <span class="privacy-mark">◈</span></span></footer>
        </main>
    </div>

    <dialog class="service-dialog" id="service-dialog" aria-labelledby="service-dialog-title" @if($errors->hasAny(['name', 'container_name', 'check_type', 'check_target', 'cooldown_seconds', 'description', 'documentation_url'])) open @endif>
        <form
            method="post"
            action="{{ $editingService ? route('services.update', $editingService) : route('services.store') }}"
            id="service-form"
            data-store-action="{{ route('services.store') }}"
        >
            @csrf
            @if($editingService)
                @method('PUT')
            @endif
            <input type="hidden" name="service_id" value="{{ old('service_id', $editingService?->id) }}">
            <header class="dialog-header"><div><span class="section-kicker">SERVICE CONFIGURATION</span><h2 id="service-dialog-title">{{ $editingService ? 'Configure service' : 'Add a service' }}</h2><p id="service-dialog-description">{{ $editingService ? 'Update the container and how Sentinel checks it.' : 'Connect a container and choose how Sentinel checks it.' }}</p></div><button class="dialog-close" type="button" data-close-service-dialog aria-label="Close dialog">×</button></header>
            <div class="dialog-fields">
                <label class="form-field"><span>Service name</span><input name="name" value="{{ old('name', $editingService?->name) }}" placeholder="Payments API" maxlength="120" required autocomplete="off">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Docker container</span><input name="container_name" value="{{ old('container_name', $editingService?->container_name) }}" placeholder="payments-api" maxlength="255" required autocomplete="off">@error('container_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                <fieldset class="form-field probe-field"><legend>Health check type</legend><div class="mode-switch"><label><input type="radio" name="check_type" value="http" @checked(old('check_type', $editingService?->check_type ?? 'http') === 'http')><span>HTTP</span></label><label><input type="radio" name="check_type" value="tcp" @checked(old('check_type', $editingService?->check_type) === 'tcp')><span>TCP</span></label></div>@error('check_type')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
                <label class="form-field"><span id="target-label">Health-check URL</span><input id="check-target" name="check_target" type="text" value="{{ old('check_target', $editingService?->check_target) }}" placeholder="https://payments.internal/health" maxlength="255" required autocomplete="off" aria-describedby="target-help">@error('check_target')<small class="field-error">{{ $message }}</small>@enderror<small class="field-hint" id="target-help">Use an HTTP or HTTPS endpoint reachable from the monitor.</small></label>
                <label class="form-field"><span>Restart cooldown</span><div class="number-field"><input name="cooldown_seconds" type="number" value="{{ old('cooldown_seconds', $editingService?->cooldown_seconds ?? 300) }}" min="30" max="3600" step="30" required><span>seconds</span></div>@error('cooldown_seconds')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Description <small>OPTIONAL</small></span><input name="description" value="{{ old('description', $editingService?->description) }}" placeholder="What this service does" maxlength="500">@error('description')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Documentation URL <small>OPTIONAL</small></span><input name="documentation_url" type="url" value="{{ old('documentation_url', $editingService?->documentation_url) }}" placeholder="https://docs.example.com/service" maxlength="2048">@error('documentation_url')<small class="field-error">{{ $message }}</small>@enderror<small class="field-hint">Link to this service’s official docs or a downloadable document.</small></label>
            </div>
            <footer class="dialog-actions"><button class="secondary-button" type="button" data-close-service-dialog>Cancel</button><button class="primary-button" type="submit"><span class="button-plus">+</span><span class="submit-label">{{ $editingService ? 'Save changes' : 'Add service' }}</span></button></footer>
        </form>
    </dialog>

    <button class="chat-launcher" id="open-chat" type="button" aria-haspopup="dialog" aria-controls="chat-dialog"><span class="chat-launch-icon">✳</span><span>Ask Sentinel</span></button>
    <dialog class="chat-dialog" id="chat-dialog" aria-labelledby="chat-title">
        <header class="chat-header"><div class="chat-avatar">✳</div><div class="chat-heading"><span>LOCAL MODEL · {{ $ollamaModel }}</span><h2 id="chat-title">Sentinel assistant</h2></div><button class="chat-close" id="close-chat" type="button" aria-label="Close chat">×</button></header>
        <div class="chat-transcript" id="chat-transcript" aria-live="polite" aria-relevant="additions text"><div class="chat-message assistant-message"><span class="message-avatar">S</span><p>Hello. What would you like to know about your services or incidents?</p></div></div>
        <div class="chat-suggestions" id="chat-suggestions"><button type="button" data-chat-prompt="Summarize the current service status.">Service status</button><button type="button" data-chat-prompt="What should I investigate first?">What to investigate</button></div>
        <form class="chat-composer" id="chat-form"><label class="visually-hidden" for="chat-input">Message Sentinel</label><textarea id="chat-input" name="message" rows="1" maxlength="4000" placeholder="Ask about your services…" required></textarea><button class="chat-send" type="submit" aria-label="Send message" title="Send message"><span>↑</span></button></form>
        <div class="chat-footnote"><span class="local-indicator"></span>Processed by your local Ollama instance</div>
    </dialog>
</body>
</html>
