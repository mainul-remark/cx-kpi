@extends('backend.master')
@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('backend/build/assets/libs/apexcharts/apexcharts.css') }}">
    <style>
        .db-kpi .db-kpi-value { font-size: 1.75rem; font-weight: 600; line-height: 1.2; }
        .db-kpi .db-kpi-label { font-size: .8125rem; }
        .db-kpi .db-kpi-meta { font-size: .75rem; }
        .db-kpi .progress { height: 6px; }
        .db-chart { min-height: 320px; }
        .db-empty { min-height: 200px; display: flex; align-items: center; justify-content: center; }
        #dashboardContent.is-loading { opacity: .5; pointer-events: none; transition: opacity .15s; }
        .db-user-link { cursor: pointer; }
        #dashboardUserTable .progress { height: 6px; min-width: 80px; }
        #dashboardAttendanceTable .db-att-name { position: sticky; left: 0; z-index: 1; background: var(--custom-white, #fff); min-width: 160px; }
        #dashboardAttendanceTable .db-att-day { text-align: center; padding: .35rem .3rem; min-width: 38px; font-weight: 500; }
        #dashboardAttendanceTable .db-att-day small { display: block; font-size: .65rem; font-weight: 400; opacity: .7; }
        #dashboardAttendanceTable .db-att-cell { text-align: center; padding: .35rem .3rem; font-size: .75rem; font-weight: 600; }
        .db-att-P { background: rgba(27, 175, 122, .16); color: #0f7a54; }
        .db-att-A { background: rgba(227, 73, 72, .16); color: #b3302f; }
        .db-att-O, .db-att-H { background: rgba(128, 128, 128, .12); color: #7a7a7a; }
        .db-att-L { background: rgba(59, 130, 246, .16); color: #1d5fc4; }
        [data-theme-mode="dark"] .db-att-L { color: #8ab4f8; }
        [data-theme-mode="dark"] .db-att-P { color: #4fd3a3; }
        [data-theme-mode="dark"] .db-att-A { color: #f08a89; }
        [data-theme-mode="dark"] .db-att-O, [data-theme-mode="dark"] .db-att-H { color: #a8a8a8; }
        .db-att-key { display: inline-block; min-width: 22px; padding: 0 .3rem; margin-left: .75rem; border-radius: 3px; text-align: center; font-size: .75rem; font-weight: 600; }
    </style>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-4">
            <div class="my-auto">
                <h4 class="mb-1 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-view-dashboard-outline me-2"></i>Dashboard</h4>
                <p class="mb-0 text-muted" id="dashboardRangeText">&nbsp;</p>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
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

        <div class="alert alert-danger d-none" id="dashboardError" role="alert">
            The dashboard could not be loaded. <a href="javascript:void(0)" id="dashboardRetry" class="alert-link">Try again</a>
        </div>

        <div id="dashboardContent" class="is-loading">
            <div class="row" id="dashboardKpis"></div>

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

            @if($canViewAll)
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="card-title mb-0"><i class="mdi mdi-alert-circle-outline me-1"></i> Missing Reports</h5>
                                <span class="badge text-bg-danger"><span id="dashboardMissingCount">0</span> users</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle w-100 mb-0" id="dashboardMissingTable">
                                        <thead>
                                        <tr>
                                            <th style="width: 60px">Sl</th>
                                            <th style="width: 25%">User</th>
                                            <th class="text-end" style="width: 140px">Days Missed</th>
                                            <th>Dates Without a Report</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="dashboardMissingPager">
                                    <div class="text-muted db-pager-info"></div>
                                    <nav aria-label="Missing report pages">
                                        <ul class="pagination pagination-sm mb-0"></ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="card-title mb-0"><i class="mdi mdi-account-group-outline me-1"></i> Field User Performance</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="dashboardUserExport">
                                    <i class="mdi mdi-download me-1"></i> Export CSV
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered text-nowrap align-middle w-100 mb-0" id="dashboardUserTable">
                                        <thead>
                                        <tr>
                                            <th>Sl</th>
                                            <th>User</th>
                                            <th class="text-end">Reports</th>
                                            <th class="text-end">Outbound Calls</th>
                                            <th class="text-end">Inbound Calls</th>
                                            <th class="text-end">Message Replies</th>
                                            <th class="text-end">Comment Replies</th>
                                            <th class="text-end">Project Calls</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Target</th>
                                            <th>Achievement</th>
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

                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="card-title mb-0"><i class="mdi mdi-calendar-check-outline me-1"></i> Attendance Sheet</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="dashboardAttendanceExport">
                                    <i class="mdi mdi-download me-1"></i> Export CSV
                                </button>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    A user is present on a day they submitted a daily report for. A day of official leave counts as neither present nor absent.
                                    <span class="db-att-key db-att-P">P</span> Present
                                    <span class="db-att-key db-att-A">A</span> Absent
                                    <span class="db-att-key db-att-L">L</span> On leave
                                    <span class="db-att-key db-att-O">O</span> Off day
                                    <span class="db-att-key db-att-H">H</span> Holiday
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-nowrap align-middle mb-0" id="dashboardAttendanceTable">
                                        <thead></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="dashboardAttendancePager">
                                    <div class="text-muted db-pager-info"></div>
                                    <nav aria-label="Attendance sheet pages">
                                        <ul class="pagination pagination-sm mb-0"></ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection

@push('scripts')
    <script src="{{ asset('backend/build/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
    @include('backend.common-pages.dashboard.partials.script')
@endpush
