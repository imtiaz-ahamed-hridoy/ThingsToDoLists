@extends('backend.app')

@section('title', 'Queue Monitor')

@push('styles')
    <!-- Load Bootstrap Icons specifically for this page since backend uses Feather by default -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
@endpush

@section('content')
{{-- PAGE-HEADER --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Queue Monitor</h1>
    </div>
    <div class="ms-auto pageheader-btn">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0);">System</a></li>
            <li class="breadcrumb-item active" aria-current="page">Queue Monitor</li>
        </ol>
    </div>
</div>
{{-- PAGE-HEADER --}}

{{-- Queue Controls Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-start border-primary border-3 shadow-sm">
            <div class="card-header bg-transparent border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <h3 class="card-title mb-0 fs-5">
                    <i class="bi bi-gear-wide-connected me-2 text-primary"></i>
                    Queue Controls
                </h3>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-info-circle me-1"></i>
                    Use with caution — these actions are irreversible
                </span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    {{-- Restart Workers --}}
                    <form action="{{ route('queue-monitor.restart-workers') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Send restart signal to all queue workers? They will gracefully stop and restart.');">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-clockwise me-1"></i>Restart Workers
                        </button>
                    </form>

                    {{-- Clear All Pending --}}
                    <form action="{{ route('queue-monitor.clear-all') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete ALL {{ $totalPending }} pending jobs across all queues? This cannot be undone.');">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash3 me-1"></i>Clear All Pending
                        </button>
                    </form>

                    {{-- Prune History --}}
                    <form action="{{ route('queue-monitor.prune-history') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete old job history records (older than 7 days)? This cannot be undone.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-clock-history me-1"></i>Prune History
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary Cards Row --}}
<div class="row">
    <div class="col-xl-3 col-lg-6 col-sm-6">
        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h3 class="mb-2 fw-semibold">{{ $totalPending }}</h3>
                        <p class="text-muted fs-13 mb-0">Total Pending Jobs</p>
                    </div>
                    <div class="col col-auto top-icn dash">
                        <div class="counter-icon bg-info dash ms-auto box-shadow-info">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="white">
                                <path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-sm-6">
        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h3 class="mb-2 fw-semibold">{{ $failedCount }}</h3>
                        <p class="text-muted fs-13 mb-0">Failed Jobs</p>
                    </div>
                    <div class="col col-auto top-icn dash">
                        <div class="counter-icon bg-danger dash ms-auto box-shadow-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="white">
                                <path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-sm-6">
        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h3 class="mb-2 fw-semibold">{{ $totalCompleted }}</h3>
                        <p class="text-muted fs-13 mb-0">Jobs Processed</p>
                    </div>
                    <div class="col col-auto top-icn dash">
                        <div class="counter-icon bg-success dash ms-auto box-shadow-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="white">
                                <path d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-sm-6">
        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h3 class="mb-2 fw-semibold">
                            <span class="badge bg-success" id="liveIndicator" style="font-size: 1rem;">&bull; Live</span>
                        </h3>
                        <p class="text-muted fs-13 mb-0">Auto-refreshing every 10s</p>
                    </div>
                    <div class="col col-auto top-icn dash">
                        <div class="counter-icon bg-primary dash ms-auto box-shadow-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="white">
                                <path d="M552 256L408 256C398.3 256 389.5 250.2 385.8 241.2C382.1 232.2 384.1 221.9 391 215L437.7 168.3C362.4 109.7 253.4 115 184.2 184.2C109.2 259.2 109.2 380.7 184.2 455.7C259.2 530.7 380.7 530.7 455.7 455.7C463.9 447.5 471.2 438.8 477.6 429.6C487.7 415.1 507.7 411.6 522.2 421.7C536.7 431.8 540.2 451.8 530.1 466.3C521.6 478.5 511.9 490.1 501 501C401 601 238.9 601 139 501C39.1 401 39 239 139 139C233.3 44.7 382.7 39.4 483.3 122.8L535 71C541.9 64.1 552.2 62.1 561.2 65.8C570.2 69.5 576 78.3 576 88L576 232C576 245.3 565.3 256 552 256z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Queue Health Cards (with per-queue Clear button) --}}
<div class="row">
    @foreach($queueNames as $queue)
        @php
            $pending = $queueStats[$queue] ?? 0;
            $health = $queueHealth[$queue];
            $progressPercent = min(100, ($pending / 500) * 100);
            $color = $health['color'] ?? 'success';
            if ($color === 'danger') {
                $progressColor = 'bg-danger';
            } elseif ($color === 'warning') {
                $progressColor = 'bg-warning';
            } elseif ($color === 'info') {
                $progressColor = 'bg-info';
            } else {
                $progressColor = 'bg-success';
            }
        @endphp
        <div class="col-xl col-md-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0 text-uppercase fw-bold" style="font-size: 0.9rem;">
                            <i class="bi bi-boxes me-1"></i>{{ $queue }}
                        </h5>
                        <span class="badge bg-{{ $color }} rounded-pill fs-12">
                            {{ $pending }} jobs
                        </span>
                    </div>

                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar {{ $progressColor }}" role="progressbar"
                             style="width: {{ $progressPercent }}%;"
                             aria-valuenow="{{ $pending }}" aria-valuemin="0" aria-valuemax="500">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span><i class="bi bi-{{ $color === 'success' ? 'check-circle-fill text-success' : ($color === 'danger' ? 'exclamation-circle-fill text-danger' : 'info-circle-fill text-info') }} me-1"></i>{{ $health['label'] }}</span>
                        <span>Max: 500</span>
                    </div>

                    {{-- Per-queue Clear Button --}}
                    @if($pending > 0)
                        <div class="mt-auto">
                            <form action="{{ route('queue-monitor.clear-queue', $queue) }}" method="POST" class="d-inline w-100"
                                  onsubmit="return confirm('Delete all {{ $pending }} pending jobs from the \'{{ $queue }}\' queue?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-trash me-1"></i>Clear {{ $queue }} Queue
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="mt-auto">
                            <button type="button" class="btn btn-sm btn-outline-success w-100" disabled>
                                <i class="bi bi-check-circle me-1"></i>No pending jobs
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Pending Jobs Table --}}
@if($totalPending > 0)
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <h3 class="card-title mb-0">
                    <i class="bi bi-hourglass-split me-2 text-info"></i>
                    Pending Jobs
                    <span class="badge bg-info ms-2">{{ $totalPending }} pending</span>
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="w-5">ID</th>
                                <th class="w-10">Queue</th>
                                <th class="w-45">Job</th>
                                <th class="w-15">Available At</th>
                                <th class="w-10">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingJobsTableBody">
                            {{-- Filled dynamically via JS on page load --}}
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-center text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Use the "Clear" buttons per queue above to remove all pending jobs, or <button type="button" class="btn btn-sm btn-link p-0 text-danger" onclick="cancelAllPending()">cancel all pending jobs</button>.
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Failed Jobs Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <h3 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle me-2 text-danger"></i>
                    Recent Failed Jobs
                    @if($failedCount > 0)
                        <span class="badge bg-danger ms-2">{{ $failedCount }} total</span>
                    @endif
                </h3>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if($failedCount > 0)
                        <button type="button" class="btn btn-sm btn-outline-warning" id="retrySelectedBtn" disabled
                                onclick="submitRetrySelected()">
                            <i class="bi bi-arrow-repeat me-1"></i>Retry Selected (<span id="selectedCount">0</span>)
                        </button>
                        <form action="{{ route('queue-monitor.retry-all') }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Retry ALL {{ $failedCount }} failed jobs?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-repeat me-1"></i>Retry All
                            </button>
                        </form>
                        <form action="{{ route('queue-monitor.flush-failed') }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Permanently delete ALL {{ $failedCount }} failed job records? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash me-1"></i>Flush All
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if($recentFailed->isEmpty())
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="mt-2 text-muted">No failed jobs &mdash; all queues are healthy!</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <form id="retrySelectedForm" method="POST" action="{{ route('queue-monitor.retry-selected') }}">
                            @csrf
                            <table class="table table-bordered table-hover text-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="w-5">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th class="w-5">ID</th>
                                        <th class="w-10">Queue</th>
                                        <th class="w-25">Job</th>
                                        <th class="w-30">Error</th>
                                        <th class="w-10">Failed At</th>
                                        <th class="w-10">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentFailed as $job)
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input job-checkbox" type="checkbox"
                                                           name="ids[]" value="{{ $job->id }}"
                                                           id="job_{{ $job->id }}">
                                                </div>
                                            </td>
                                            <td><code>#{{ $job->id }}</code></td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $job->queue }}</span>
                                            </td>
                                            <td class="text-truncate" style="max-width: 200px;">
                                                <small class="text-muted">{{ $job->job_class }}</small>
                                            </td>
                                            <td class="text-truncate" style="max-width: 250px;">
                                                <small class="text-danger">{{ $job->exception ?: 'Unknown error' }}</small>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</small>
                                            </td>
                                            <td>
                                                <form action="{{ route('queue-monitor.retry', $job->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Retry">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('queue-monitor.forget', $job->id) }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Delete this failed job record?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete record">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Completed Jobs Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <h3 class="card-title mb-0">
                    <i class="bi bi-check-circle me-2 text-success"></i>
                    Recent Completed Jobs
                    @if($totalCompleted > 0)
                        <span class="badge bg-success ms-2">{{ $totalCompleted }} total</span>
                    @endif
                </h3>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($queueNames as $q)
                        @php $cnt = $queueProcessedCounts[$q] ?? 0; @endphp
                        @if($cnt > 0)
                            <span class="badge bg-light text-dark border">{{ $q }}: {{ $cnt }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="card-body">
                @if($recentCompleted->isEmpty())
                    <div class="text-center py-4">
                        <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-2 text-muted">No jobs have been processed yet. Once a queue worker runs and completes a job, it will appear here.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="w-5">#</th>
                                    <th class="w-10">Queue</th>
                                    <th class="w-35">Job</th>
                                    <th class="w-10">Duration</th>
                                    <th class="w-15">Processed At</th>
                                    <th class="w-10">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentCompleted as $job)
                                    <tr>
                                        <td><code>#{{ $job->id }}</code></td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $job->queue }}</span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 350px;">
                                            <small class="text-muted">{{ $job->job_class }}</small>
                                        </td>
                                        <td>
                                            @if($job->duration_ms !== null)
                                                <small class="text-muted">
                                                    @if($job->duration_ms < 1000)
                                                        {{ $job->duration_ms }}ms
                                                    @else
                                                        {{ number_format($job->duration_ms / 1000, 2) }}s
                                                    @endif
                                                </small>
                                            @else
                                                <small class="text-muted">N/A</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ \Carbon\Carbon::parse($job->processed_at)->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Done
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($recentCompleted->count() >= 50)
                        <div class="mt-2 text-center text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Showing the 50 most recent completed jobs. Records older than 7 days are automatically pruned daily.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var refreshInterval = setInterval(function() {
            $('#liveIndicator').html('<span class=\\\"spinner-border spinner-border-sm me-1\\\"></span> Refreshing...');
            location.reload();
        }, 10000);

        $(document).on('click', 'button, a', function() {
            clearInterval(refreshInterval);
            refreshInterval = setInterval(function() {
                $('#liveIndicator').html('<span class=\\\"spinner-border spinner-border-sm me-1\\\"></span> Refreshing...');
                location.reload();
            }, 10000);
        });

        // ── Select All / Deselect All ──
        $('#selectAll').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('.job-checkbox').prop('checked', isChecked);
            updateRetrySelectedButton();
        });

        // ── Individual checkbox change ──
        $(document).on('change', '.job-checkbox', function() {
            updateRetrySelectedButton();
            // Uncheck 'Select All' if any individual checkbox is unchecked
            var total = $('.job-checkbox').length;
            var checked = $('.job-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked);
        });

        // ── Load pending jobs via AJAX ──
        loadPendingJobs();
    });

    /** Load pending jobs from the database jobs table */
    function loadPendingJobs() {
        $.ajax({
            url: '{{ route("queue-monitor.index") }}',
            method: 'GET',
            data: { pending_json: 1 },
            success: function(response) {
                if (response.pendingJobs && response.pendingJobs.length > 0) {
                    renderPendingJobs(response.pendingJobs);
                }
            },
            error: function() {
                // Silently fail — the section just won't show details
            }
        });
    }

    /** Render the pending jobs table rows */
    function renderPendingJobs(jobs) {
        var tbody = $('#pendingJobsTableBody');
        if (!tbody.length) return;

        var html = '';
        $.each(jobs, function(i, job) {
            var availableAt = job.available_at
                ? new Date(job.available_at * 1000).toLocaleString()
                : 'Immediately';
            html += '<tr>' +
                '<td><code>#' + job.id + '</code></td>' +
                '<td><span class="badge bg-secondary">' + escapeHtml(job.queue) + '</span></td>' +
                '<td class="text-truncate" style="max-width: 350px;"><small class="text-muted">' + escapeHtml(job.display_name || 'Unknown') + '</small></td>' +
                '<td><small class="text-muted">' + availableAt + '</small></td>' +
                '<td>' +
                '<form action="" method="POST" class="d-inline" onsubmit="return cancelPendingJob(' + job.id + ', this);">' +
                '@csrf' +
                '<button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel this job"><i class="bi bi-x-circle"></i></button>' +
                '</form>' +
                '</td>' +
                '</tr>';
        });
        tbody.html(html);
    }

    /** AJAX cancel a single pending job */
    function cancelPendingJob(id, form) {
        if (!confirm('Cancel pending job #' + id + '?')) return false;

        $.ajax({
            url: '{{ route("queue-monitor.cancel-pending") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: [id]
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Failed to cancel job #' + id);
            }
        });
        return false;
    }

    /** Cancel all pending jobs */
    function cancelAllPending() {
        if (!confirm('Cancel ALL pending jobs across all queues?')) return;
        $.ajax({
            url: '{{ route("queue-monitor.clear-all") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Failed to clear pending jobs');
            }
        });
    }

    /** Escape HTML to prevent XSS */
    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    /** Update the Retry Selected button state and selected count. */
    function updateRetrySelectedButton() {
        var checked = $('.job-checkbox:checked').length;
        $('#selectedCount').text(checked);
        if (checked > 0) {
            $('#retrySelectedBtn').prop('disabled', false);
        } else {
            $('#retrySelectedBtn').prop('disabled', true);
        }
    }

    /** Submit the retry selected form if at least one job is checked. */
    function submitRetrySelected() {
        var checked = $('.job-checkbox:checked').length;
        if (checked === 0) {
            return;
        }
        if (confirm('Retry ' + checked + ' selected failed job' + (checked > 1 ? 's' : '') + '?')) {
            var ids = [];
            $('.job-checkbox:checked').each(function() {
                ids.push($(this).val());
            });
            $('<form>', {
                'method': 'POST',
                'action': '{{ route("queue-monitor.retry-selected") }}',
                'style': 'display: none'
            }).append(
                $('<input>', {'name': '_token', 'value': '{{ csrf_token() }}', 'type': 'hidden'})
            ).append(
                ids.map(function(id) {
                    return $('<input>', {'name': 'ids[]', 'value': id, 'type': 'hidden'});
                })
            ).appendTo('body').submit();
        }
    }
</script>
@endpush
