@extends('backend.master')
@section('title', 'KPI')

@push('styles')
    <style>
        .kpi-tile .kpi-tile-value { font-size: 1.75rem; font-weight: 600; line-height: 1.2; }
        .kpi-tile .kpi-tile-label { font-size: .8125rem; }
        #kpiContent.is-loading { opacity: .5; pointer-events: none; transition: opacity .15s; }
        .kpi-user-link { cursor: pointer; }
        .kpi-bar { width: 90px; height: 6px; }
        .kpi-status { display: inline-block; padding: 0 .4rem; border-radius: 3px; font-size: .75rem; font-weight: 600; }
        .kpi-status-worked { background: rgba(27, 175, 122, .16); color: #0f7a54; }
        .kpi-status-absent { background: rgba(227, 73, 72, .16); color: #b3302f; }
        .kpi-status-leave { background: rgba(59, 130, 246, .16); color: #1d5fc4; }
        .kpi-status-off, .kpi-status-not_joined { background: rgba(128, 128, 128, .12); color: #7a7a7a; }
        [data-theme-mode="dark"] .kpi-status-worked { color: #4fd3a3; }
        [data-theme-mode="dark"] .kpi-status-absent { color: #f08a89; }
        [data-theme-mode="dark"] .kpi-status-leave { color: #8ab4f8; }
        [data-theme-mode="dark"] .kpi-status-off, [data-theme-mode="dark"] .kpi-status-not_joined { color: #a8a8a8; }
    </style>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-4">
            <div class="my-auto">
                <h4 class="mb-1 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-chart-bar me-2"></i>KPI</h4>
                <p class="mb-0 text-muted" id="kpiRangeText">&nbsp;</p>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">KPI</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form id="kpiFilterForm" class="row g-2 align-items-end" autocomplete="off">
                    <div class="col-12 col-xl-auto">
                        <label class="form-label d-block">Period</label>
                        <div class="btn-group flex-wrap" role="group" aria-label="Period">
                            <button type="button" class="btn btn-outline-primary btn-sm kpi-preset" data-preset="today">Today</button>
                            <button type="button" class="btn btn-outline-primary btn-sm kpi-preset" data-preset="week">This Week</button>
                            <button type="button" class="btn btn-outline-primary btn-sm kpi-preset" data-preset="15days">Last 15 Days</button>
                            <button type="button" class="btn btn-outline-primary btn-sm kpi-preset" data-preset="month">This Month</button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="kpi_from">From</label>
                        <input type="date" id="kpi_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="kpi_to">To</label>
                        <input type="date" id="kpi_to" class="form-control form-control-sm">
                    </div>
                    @if($canViewAll)
                        <div class="col-12 col-md-3 col-xl-2">
                            <label class="form-label" for="kpi_user">Field User</label>
                            <select id="kpi_user" class="form-select form-select-sm">
                                <option value="">All field users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        <button type="button" class="btn btn-secondary btn-sm ms-1" id="kpiFilterReset">Reset</button>
                    </div>
                </form>
                <div class="text-danger mt-2 d-none" id="kpiFilterError" role="alert"></div>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="kpiError" role="alert">
            The KPI sheet could not be loaded. <a href="javascript:void(0)" id="kpiRetry" class="alert-link">Try again</a>
        </div>

        <div id="kpiContent" class="is-loading">
            <div class="row" id="kpiTiles"></div>

            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-view-list me-1"></i> {{ $canViewAll ? 'KPI Sheet' : 'My KPI' }}</h5>
                            @if($canViewAll)
                                @allowed('kpi.export')
                                    <a href="{{ route('kpi.export') }}" class="btn btn-sm btn-outline-primary" id="kpiExport" title="One tab per user, named after the employee id">
                                        <i class="mdi mdi-download me-1"></i> Export Excel
                                    </a>
                                @endallowed
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                The KPI is what was completed against the target, counted on the days and activities that had a target.
                                A day of official leave, an off day and a day without a target count for nothing either way, a working day without a report counts as zero.
                                The score is the KPI capped at {{ \App\Services\Kpi\EmployeeKpiService::MAX_SCORE }}.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered text-nowrap align-middle w-100 mb-0" id="kpiTable">
                                    <thead>
                                    <tr>
                                        <th>Sl</th>
                                        <th>User</th>
                                        @if($canViewAll)
                                            <th class="text-end">Target</th>
                                        @endif
                                        <th class="text-end">Completed</th>
                                        <th class="text-end">KPI</th>
                                        <th>Score</th>
                                        <th class="text-end">Days Worked</th>
                                        <th class="text-end">Absent</th>
                                        <th class="text-end">Leave</th>
                                        <th>Details</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="kpiPager">
                                <div class="text-muted kpi-pager-info"></div>
                                <nav aria-label="KPI sheet pages">
                                    <ul class="pagination pagination-sm mb-0"></ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @allowed('kpi.monthly')
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-lock-outline me-1"></i> Frozen Monthly Scores</h5>
                            <input type="month" id="kpi_month" class="form-control form-control-sm w-auto" max="{{ today()->subMonthNoOverflow()->format('Y-m') }}" value="{{ today()->subMonthNoOverflow()->format('Y-m') }}" aria-label="Month">
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                The score of a month is frozen once the month is over. A report, target or leave changed afterwards no longer changes it.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered text-nowrap align-middle w-100 mb-0" id="kpiMonthlyTable">
                                    <thead>
                                    <tr>
                                        <th>Sl</th>
                                        <th>User</th>
                                        @if($canViewAll)
                                            <th class="text-end">Target</th>
                                        @endif
                                        <th class="text-end">Completed</th>
                                        <th class="text-end">KPI</th>
                                        <th>Score</th>
                                        <th class="text-end">Days Worked</th>
                                        <th class="text-end">Absent</th>
                                        <th class="text-end">Leave</th>
                                        <th>Frozen On</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endallowed

    </div>
@endsection

@section('modal')
    <div class="modal fade" id="kpiDetailModal" tabindex="-1" aria-labelledby="kpiDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h1 class="modal-title fs-5" id="kpiDetailModalLabel">KPI Details</h1>
                        <div class="text-muted" id="kpiDetailRange"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="kpiDetailBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('backend.kpi.partials.script')
@endpush
