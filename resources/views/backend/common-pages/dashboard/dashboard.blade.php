@extends('backend.master')
@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('backend/build/assets/libs/apexcharts/apexcharts.css') }}">
    <style>
        .db-kpi .db-kpi-value { font-size: 1.75rem; font-weight: 600; line-height: 1.2; }
        .db-kpi .db-kpi-label { font-size: .8125rem; }
        .db-kpi .db-kpi-meta { font-size: .75rem; }
        .db-kpi .progress { height: 6px; }
        .db-kpi.db-kpi-link { transition: box-shadow .15s; }
        .db-kpi.db-kpi-link:hover { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .08); }
        .db-chart { min-height: 320px; }
        .db-empty { min-height: 200px; display: flex; align-items: center; justify-content: center; }
        .db-section.is-loading { opacity: .5; pointer-events: none; transition: opacity .15s; }
        .db-skeleton { display: inline-block; width: 4.5rem; height: 1.75rem; border-radius: .25rem; background: rgba(128, 128, 128, .18); animation: db-pulse 1.2s ease-in-out infinite; }
        @keyframes db-pulse { 50% { opacity: .45; } }
        .db-user-link { cursor: pointer; }
        .db-sort { cursor: pointer; user-select: none; white-space: nowrap; }
        .db-sort .mdi { opacity: .35; }
        .db-sort.is-sorted .mdi { opacity: 1; }
        #dashboardUserTable .progress { height: 6px; min-width: 80px; }
        .db-rank { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid rgba(128, 128, 128, .15); }
        .db-rank:last-child { border-bottom: 0; }
        .db-rank-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-4">
            <div class="my-auto">
                <h4 class="mb-1 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-view-dashboard-outline me-2"></i>Dashboard</h4>
                <p class="mb-0 text-muted" id="dashboardRangeText">&nbsp;</p>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center gap-2">
                <span class="text-muted small d-none d-md-inline" id="dashboardUpdated"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dashboardRefresh" title="Work the figures out again">
                    <i class="mdi mdi-refresh me-1"></i> Refresh
                </button>
                <span class="badge {{ $canViewAll ? 'text-bg-primary' : 'text-bg-secondary' }}">
                    {{ $canViewAll ? 'All field users' : 'My activity' }}
                </span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form id="dashboardFilterForm" class="row g-2 align-items-end" autocomplete="off">
                    <div class="col-12 col-xl-auto">
                        <label class="form-label d-block">Period</label>
                        <div class="btn-group flex-wrap" role="group" aria-label="Period">
                            <button type="button" class="btn btn-outline-primary btn-sm dashboard-preset" data-preset="today">Today</button>
                            <button type="button" class="btn btn-outline-primary btn-sm dashboard-preset" data-preset="week">This Week</button>
                            <button type="button" class="btn btn-outline-primary btn-sm dashboard-preset" data-preset="15days">Last 15 Days</button>
                            <button type="button" class="btn btn-outline-primary btn-sm dashboard-preset" data-preset="month">This Month</button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="dashboard_from">From</label>
                        <input type="date" id="dashboard_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="dashboard_to">To</label>
                        <input type="date" id="dashboard_to" class="form-control form-control-sm">
                    </div>
                    @if($canViewAll)
                        <div class="col-12 col-md-3 col-xl-2">
                            <label class="form-label" for="dashboard_user">Field User</label>
                            <select id="dashboard_user" class="form-select form-select-sm">
                                <option value="">All field users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        <button type="button" class="btn btn-secondary btn-sm ms-1" id="dashboardFilterReset">Reset</button>
                    </div>
                </form>
                <div class="text-danger mt-2 d-none" id="dashboardFilterError" role="alert"></div>
            </div>
        </div>

        @if($canViewAll)
            {{-- where the team stands right now, whatever period is chosen below --}}
            <div class="row" id="dashboardLive" data-section="live"></div>
        @endif

        <div class="alert alert-danger d-none" id="dashboardError" role="alert">
            The dashboard could not be loaded. <a href="javascript:void(0)" id="dashboardRetry" class="alert-link">Try again</a>
        </div>

        <div class="row db-section is-loading" id="dashboardKpis" data-section="overview"></div>

        <div class="db-section is-loading" data-section="overview">
            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-chart-line me-1"></i> Daily Activity</h5>
                        </div>
                        <div class="card-body">
                            <div id="dashboardTrendChart" class="db-chart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-target me-1"></i> {{ $canViewAll ? 'Actual vs Target' : 'Activity Totals' }}</h5>
                        </div>
                        <div class="card-body">
                            <div id="dashboardActivityChart" class="db-chart"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="{{ $canViewAll ? 'col-xl-8' : 'col-xl-12' }}">
                <div class="card db-section is-loading" data-section="attendance">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0"><i class="mdi mdi-calendar-check-outline me-1"></i> Daily Presence</h5>
                        <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-primary">Attendance sheet <i class="mdi mdi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="card-body">
                        <div id="dashboardPresenceChart" class="db-chart"></div>
                    </div>
                </div>
            </div>
            @if($canViewAll)
                <div class="col-xl-4">
                    <div class="card db-section is-loading" data-section="people">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-chart-donut me-1"></i> KPI Score Spread</h5>
                        </div>
                        <div class="card-body">
                            <div id="dashboardScoreChart" class="db-chart"></div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="db-section is-loading" data-section="overview">
            <div class="row">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-phone-outline me-1"></i> Calls by Project</h5>
                        </div>
                        <div class="card-body">
                            <div id="dashboardProjectChart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-comment-multiple-outline me-1"></i> Comment Replies by Social Platform</h5>
                        </div>
                        <div class="card-body">
                            <div id="dashboardPlatformChart"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($canViewAll)
            <div class="row">
                <div class="col-xl-4">
                    <div class="card db-section is-loading" data-section="people">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-trophy-outline me-1"></i> Top Performers</h5>
                        </div>
                        <div class="card-body" id="dashboardTopList"></div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card db-section is-loading" data-section="people">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-trending-down me-1"></i> Needs Attention</h5>
                        </div>
                        <div class="card-body" id="dashboardAttentionList"></div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card db-section is-loading" data-section="attendance">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-alert-circle-outline me-1"></i> Missing Reports</h5>
                            <span class="badge text-bg-danger"><span id="dashboardMissingCount">0</span> users</span>
                        </div>
                        <div class="card-body" id="dashboardMissingList"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-12">
                    <div class="card db-section is-loading" data-section="people">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-account-group-outline me-1"></i> Field User Performance</h5>
                            <div class="d-flex align-items-center gap-2">
                                <input type="search" id="dashboardUserSearch" class="form-control form-control-sm" placeholder="Search name or employee ID" maxlength="100" style="min-width: 220px">
                                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="dashboardUserExport">
                                    <i class="mdi mdi-download me-1"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered text-nowrap align-middle w-100 mb-0" id="dashboardUserTable">
                                    <thead>
                                    <tr>
                                        <th>Sl</th>
                                        <th class="db-sort" data-sort="name">User <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="reports">Reports <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="outbound_calls">Outbound Calls <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="inbound_calls">Inbound Calls <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="message_replies">Message Replies <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="comments">Comment Replies <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="solved_issues">Solved Issues <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="total">Total <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="text-end">Target</th>
                                        <th class="db-sort" data-sort="achievement_pct">Achievement <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                        <th class="db-sort text-end" data-sort="score">KPI Score <i class="mdi mdi-unfold-more-horizontal"></i></th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="dashboardUserPager">
                                <div class="text-muted db-pager-info"></div>
                                <nav aria-label="Field user performance pages">
                                    <ul class="pagination pagination-sm mb-0"></ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('backend/build/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
    @include('backend.common-pages.dashboard.partials.script')
@endpush
