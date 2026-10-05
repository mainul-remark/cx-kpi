@extends('backend.master')

@section('title', 'Activity Logs')

@section('body')
    @php
        $eventBadgeClasses = [
            'created' => 'bg-success-subtle text-success',
            'updated' => 'bg-warning-subtle text-warning',
            'deleted' => 'bg-danger-subtle text-danger',
        ];

        $formatValue = function ($value) {
            if (is_null($value) || $value === '') {
                return '-';
            }

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value) || is_object($value)) {
                $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                return $json !== false ? $json : '-';
            }

            return (string) $value;
        };

        $resolveModelLabel = function ($model, ?string $fallbackType, $fallbackId) {
            if (! $model) {
                return class_basename((string) $fallbackType) . ($fallbackId ? ' #' . $fallbackId : '');
            }

            foreach (['name', 'title', 'code', 'asset_code', 'unique_code', 'email'] as $attribute) {
                if (! empty($model->{$attribute})) {
                    return $model->{$attribute};
                }
            }

            return class_basename($model::class) . ' #' . $model->getKey();
        };
    @endphp

    <div class="container m-t-50">
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card activity-summary-card h-100">
                    <div class="card-body">
                        <span class="summary-label">Total Logs</span>
                        <h3 class="summary-value">{{ number_format($summary['total'] ?? 0) }}</h3>
                        <p class="summary-meta mb-0">Matching the current filter set.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card activity-summary-card h-100">
                    <div class="card-body">
                        <span class="summary-label">Today</span>
                        <h3 class="summary-value">{{ number_format($summary['today'] ?? 0) }}</h3>
                        <p class="summary-meta mb-0">Entries created today.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card activity-summary-card h-100">
                    <div class="card-body">
                        <span class="summary-label">Auth Logs</span>
                        <h3 class="summary-value">{{ number_format($summary['auth'] ?? 0) }}</h3>
                        <p class="summary-meta mb-0">Login, logout, lockout, and failed login events.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card activity-summary-card h-100">
                    <div class="card-body">
                        <span class="summary-label">Data Logs</span>
                        <h3 class="summary-value">{{ number_format($summary['data'] ?? 0) }}</h3>
                        <p class="summary-meta mb-0">Create, update, and delete model events.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card activity-summary-card h-100">
                    <div class="card-body">
                        <span class="summary-label">Workflow Logs</span>
                        <h3 class="summary-value">{{ number_format($summary['workflow'] ?? 0) }}</h3>
                        <p class="summary-meta mb-0">Assignments, proof uploads, and status transitions.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="card-title mb-1">Activity Logs</div>
                            <p class="text-muted mb-0">Audit trail powered by `spatie/laravel-activitylog`.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.activity-logs') }}" class="activity-filter-panel mb-4">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <label for="search" class="form-label">Search</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="search"
                                        name="search"
                                        value="{{ $filters['search'] ?? '' }}"
                                        placeholder="Description, event, JSON payload"
                                    >
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="event" class="form-label">Event</label>
                                    <select name="event" id="event" class="form-select">
                                        <option value="">All Events</option>
                                        @foreach($eventOptions as $eventOption)
                                            <option value="{{ $eventOption }}" @selected(($filters['event'] ?? '') === $eventOption)>
                                                {{ ucfirst($eventOption) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="log_name" class="form-label">Log Name</label>
                                    <select name="log_name" id="log_name" class="form-select">
                                        <option value="">All Logs</option>
                                        @foreach($logNameOptions as $logNameOption)
                                            <option value="{{ $logNameOption }}" @selected(($filters['log_name'] ?? '') === $logNameOption)>
                                                {{ $logNameOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="subject_type" class="form-label">Subject Type</label>
                                    <select name="subject_type" id="subject_type" class="form-select">
                                        <option value="">All Subjects</option>
                                        @foreach($subjectTypeOptions as $subjectTypeOption)
                                            <option value="{{ $subjectTypeOption['value'] }}" @selected(($filters['subject_type'] ?? '') === $subjectTypeOption['value'])>
                                                {{ $subjectTypeOption['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="causer_id" class="form-label">Actor</label>
                                    <select name="causer_id" id="causer_id" class="form-select">
                                        <option value="">All Actors</option>
                                        @foreach($causerOptions as $causerOption)
                                            <option value="{{ $causerOption->id }}" @selected((string) ($filters['causer_id'] ?? '') === (string) $causerOption->id)>
                                                {{ $causerOption->name }} ({{ $causerOption->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-1 col-md-6">
                                    <label for="date_from" class="form-label">From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                </div>
                                <div class="col-lg-1 col-md-6">
                                    <label for="date_to" class="form-label">To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                </div>
                                <div class="col-lg-1 col-md-12 d-flex align-items-end">
                                    <div class="d-grid w-100 gap-2">
                                        <button type="submit" class="btn btn-primary btn-wave">
                                            <i class="ri-search-line me-1"></i> Apply
                                        </button>
                                        <a href="{{ route('admin.activity-logs') }}" class="btn btn-light btn-wave">
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0 activity-log-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">#</th>
                                        <th style="min-width: 160px;">When</th>
                                        <th style="min-width: 170px;">Actor</th>
                                        <th style="min-width: 130px;">Action</th>
                                        <th style="min-width: 190px;">Subject</th>
                                        <th>Description</th>
                                        <th style="width: 120px;">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($activityLogs as $activity)
                                        @php
                                            $changes = $activity->changes;
                                            $attributes = collect($changes->get('attributes', []));
                                            $oldValues = collect($changes->get('old', []));
                                            $fieldNames = $attributes->keys()->merge($oldValues->keys())->unique()->values();
                                            $event = strtolower((string) ($activity->event ?? $activity->description));
                                            $eventBadgeClass = $eventBadgeClasses[$event] ?? 'bg-info-subtle text-info';
                                            $causerLabel = $resolveModelLabel($activity->causer, $activity->causer_type, $activity->causer_id);
                                            $subjectLabel = $resolveModelLabel($activity->subject, $activity->subject_type, $activity->subject_id);
                                            $rowNumber = $activityLogs->firstItem() + $loop->index;
                                        @endphp
                                        <tr>
                                            <td>{{ $rowNumber }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ optional($activity->created_at)->format('d M Y, h:i A') }}</div>
                                                <div class="text-muted fs-12">{{ optional($activity->created_at)->diffForHumans() }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $causerLabel ?: 'System' }}</div>
                                                <div class="text-muted fs-12">
                                                    {{ class_basename((string) $activity->causer_type) ?: 'System' }}
                                                    @if($activity->causer_id)
                                                        #{{ $activity->causer_id }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $eventBadgeClass }} activity-event-badge">
                                                    {{ ucfirst($event ?: 'activity') }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $subjectLabel }}</div>
                                                <div class="text-muted fs-12">
                                                    {{ class_basename((string) $activity->subject_type) }}
                                                    @if($activity->subject_id)
                                                        #{{ $activity->subject_id }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ ucfirst((string) $activity->description) }}</div>
                                                <div class="text-muted fs-12">
                                                    Log: {{ $activity->log_name ?: 'default' }}
                                                    @if($fieldNames->isNotEmpty())
                                                        | {{ $fieldNames->count() }} field(s) captured
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-outline-primary"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#activity-log-{{ $activity->id }}"
                                                    aria-expanded="false"
                                                    aria-controls="activity-log-{{ $activity->id }}"
                                                >
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="activity-log-detail-row">
                                            <td colspan="7" class="p-0 border-top-0">
                                                <div class="collapse" id="activity-log-{{ $activity->id }}">
                                                    <div class="activity-log-detail-panel">
                                                        <div class="row g-3">
                                                            <div class="col-xl-4">
                                                                <div class="detail-card">
                                                                    <h6 class="detail-title">Metadata</h6>
                                                                    <dl class="activity-meta-list mb-0">
                                                                        <dt>Log Name</dt>
                                                                        <dd>{{ $activity->log_name ?: 'default' }}</dd>

                                                                        <dt>Event</dt>
                                                                        <dd>{{ $activity->event ?: $activity->description }}</dd>

                                                                        <dt>Batch UUID</dt>
                                                                        <dd>{{ $activity->batch_uuid ?: '-' }}</dd>

                                                                        <dt>Subject</dt>
                                                                        <dd>{{ class_basename((string) $activity->subject_type) }} @if($activity->subject_id)#{{ $activity->subject_id }}@endif</dd>

                                                                        <dt>Causer</dt>
                                                                        <dd>{{ class_basename((string) $activity->causer_type) ?: 'System' }} @if($activity->causer_id)#{{ $activity->causer_id }}@endif</dd>
                                                                    </dl>
                                                                </div>
                                                            </div>
                                                            <div class="col-xl-8">
                                                                <div class="detail-card">
                                                                    <h6 class="detail-title">Change Summary</h6>

                                                                    @if($fieldNames->isNotEmpty())
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm align-middle mb-0 change-table">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th style="width: 26%;">Field</th>
                                                                                        <th style="width: 37%;">Old Value</th>
                                                                                        <th style="width: 37%;">New Value</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($fieldNames as $fieldName)
                                                                                        @php
                                                                                            $oldValue = $oldValues->get($fieldName);
                                                                                            $newValue = $attributes->get($fieldName);
                                                                                        @endphp
                                                                                        <tr>
                                                                                            <td><code>{{ $fieldName }}</code></td>
                                                                                            <td>
                                                                                                <pre class="change-value old-value">{{ $formatValue($oldValue) }}</pre>
                                                                                            </td>
                                                                                            <td>
                                                                                                <pre class="change-value new-value">{{ $formatValue($newValue) }}</pre>
                                                                                            </td>
                                                                                        </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    @else
                                                                        <pre class="change-value mb-0">{{ $formatValue($activity->properties?->toArray() ?? []) }}</pre>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                No activity logs found for the selected filters.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($activityLogs->hasPages())
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                                <div class="text-muted fs-13">
                                    Showing {{ $activityLogs->firstItem() }} to {{ $activityLogs->lastItem() }} of {{ $activityLogs->total() }} logs
                                </div>
                                {{ $activityLogs->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .activity-summary-card {
            border: 1px solid rgba(110, 118, 137, 0.14);
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.05);
        }

        .summary-label {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }

        .summary-value {
            margin-bottom: 0.35rem;
            font-size: 1.9rem;
            font-weight: 700;
            color: #111827;
        }

        .summary-meta {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .activity-filter-panel {
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.85rem;
            background: #f8fafc;
        }

        .activity-log-table thead th {
            white-space: nowrap;
            background: #f8fafc;
        }

        .activity-event-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.45rem 0.7rem;
            text-transform: uppercase;
        }

        .activity-log-detail-panel {
            padding: 1.25rem;
            background: #fbfdff;
            border-top: 1px solid #edf2f7;
        }

        .detail-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.85rem;
            background: #fff;
        }

        .detail-title {
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .activity-meta-list {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 0.65rem 0.9rem;
        }

        .activity-meta-list dt {
            margin: 0;
            font-weight: 600;
            color: #475569;
        }

        .activity-meta-list dd {
            margin: 0;
            color: #0f172a;
            word-break: break-word;
        }

        .change-table th {
            background: #f8fafc;
            white-space: nowrap;
        }

        .change-value {
            margin: 0;
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.8rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .old-value {
            border: 1px solid rgba(239, 68, 68, 0.16);
        }

        .new-value {
            border: 1px solid rgba(34, 197, 94, 0.16);
        }

        @media (max-width: 767.98px) {
            .activity-meta-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('scripts')

@endpush
