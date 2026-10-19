@forelse($events as $event)
    <div class="activity-item"><span class="feed-marker {{ $event->status === 'healthy' ? 'healthy' : 'down' }}"></span><div class="activity-copy"><strong>{{ $event->service?->name ?? 'Removed service' }} <span>{{ $event->status === 'healthy' ? 'is healthy' : 'check failed' }}</span></strong><small>{{ $event->message ?? 'Health check completed' }}</small></div><time>{{ $event->checked_at->diffForHumans(null, true) }}</time></div>
@empty
    <div class="activity-empty"><span class="empty-pulse"></span><strong>Quiet so far</strong><p>Health check events will appear here as your services report in.</p></div>
@endforelse
