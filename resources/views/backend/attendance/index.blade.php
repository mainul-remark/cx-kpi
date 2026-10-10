@extends('backend.master')
@section('title', 'Attendance')

@push('styles')
    <style>
        .att-kpi .att-kpi-value { font-size: 1.75rem; font-weight: 600; line-height: 1.2; }
        .att-kpi .att-kpi-label { font-size: .8125rem; }
        #attendanceContent.is-loading { opacity: .5; pointer-events: none; transition: opacity .15s; }
        .att-user-link { cursor: pointer; }
        #attendanceTable .att-name { position: sticky; left: 0; z-index: 1; background: var(--custom-white, #fff); min-width: 160px; }
        #attendanceTable .att-day { text-align: center; padding: .35rem .3rem; min-width: 38px; font-weight: 500; }
        #attendanceTable .att-day small { display: block; font-size: .65rem; font-weight: 400; opacity: .7; }
        #attendanceTable .att-cell { text-align: center; padding: .35rem .3rem; font-size: .75rem; font-weight: 600; }
        .att-P { background: rgba(27, 175, 122, .16); color: #0f7a54; }
        .att-A { background: rgba(227, 73, 72, .16); color: #b3302f; }
        .att-O, .att-H { background: rgba(128, 128, 128, .12); color: #7a7a7a; }
        .att-L { background: rgba(59, 130, 246, .16); color: #1d5fc4; }
        [data-theme-mode="dark"] .att-L { color: #8ab4f8; }
        [data-theme-mode="dark"] .att-P { color: #4fd3a3; }
        [data-theme-mode="dark"] .att-A { color: #f08a89; }
        [data-theme-mode="dark"] .att-O, [data-theme-mode="dark"] .att-H { color: #a8a8a8; }
        .att-key { display: inline-block; min-width: 22px; padding: 0 .3rem; margin-left: .75rem; border-radius: 3px; text-align: center; font-size: .75rem; font-weight: 600; }
    </style>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-4">
            <div class="my-auto">
                <h4 class="mb-1 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-calendar-check-outline me-2"></i>Attendance</h4>
                <p class="mb-0 text-muted" id="attendanceRangeText">&nbsp;</p>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Attendance</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form id="attendanceFilterForm" class="row g-2 align-items-end" autocomplete="off">
                    <div class="col-12 col-xl-auto">
                        <label class="form-label d-block">Period</label>
                        <div class="btn-group flex-wrap" role="group" aria-label="Period">
                            <button type="button" class="btn btn-outline-primary btn-sm attendance-preset" data-preset="today">Today</button>
                            <button type="button" class="btn btn-outline-primary btn-sm attendance-preset" data-preset="week">This Week</button>
                            <button type="button" class="btn btn-outline-primary btn-sm attendance-preset" data-preset="15days">Last 15 Days</button>
                            <button type="button" class="btn btn-outline-primary btn-sm attendance-preset" data-preset="month">This Month</button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="attendance_from">From</label>
                        <input type="date" id="attendance_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label" for="attendance_to">To</label>
                        <input type="date" id="attendance_to" class="form-control form-control-sm">
                    </div>
                    @if($canViewAll)
                        <div class="col-12 col-md-3 col-xl-2">
                            <label class="form-label" for="attendance_user">Field User</label>
                            <select id="attendance_user" class="form-select form-select-sm">
                                <option value="">All field users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        <button type="button" class="btn btn-secondary btn-sm ms-1" id="attendanceFilterReset">Reset</button>
                    </div>
                </form>
                <div class="text-danger mt-2 d-none" id="attendanceFilterError" role="alert"></div>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="attendanceError" role="alert">
            The attendance sheet could not be loaded. <a href="javascript:void(0)" id="attendanceRetry" class="alert-link">Try again</a>
        </div>

        <div id="attendanceContent" class="is-loading">
            <div class="row" id="attendanceKpis"></div>

            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-view-list me-1"></i> {{ $canViewAll ? 'Attendance Sheet' : 'My Attendance' }}</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="attendanceExport">
                                <i class="mdi mdi-download me-1"></i> Export CSV
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                A user is present on a day they submitted a daily report for or checked in on. A day of official leave counts as neither present nor absent.
                                <span class="att-key att-P">P</span> Present
                                <span class="att-key att-A">A</span> Absent
                                <span class="att-key att-L">L</span> On leave
                                <span class="att-key att-O">O</span> Off day
                                <span class="att-key att-H">H</span> Holiday
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered text-nowrap align-middle mb-0" id="attendanceTable">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-none" id="attendancePager">
                                <div class="text-muted att-pager-info"></div>
                                <nav aria-label="Attendance sheet pages">
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
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-clock-check-outline me-1"></i> Check-in Log</h5>
                        </div>
                        <div class="card-body">
                            @if($canViewAll)
                                <div class="mb-3" id="checkInsNow"></div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-bordered text-nowrap align-middle mb-0" id="checkInsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            @if($canViewAll)<th>Field User</th>@endif
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Hours</th>
                                            <th>Status</th>
                                            @if($canViewAll)<th>Location</th><th></th>@endif
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <p class="text-muted small mt-2 mb-0 d-none" id="checkInsTruncated">Only the latest check-ins of this range are listed. Narrow the range or pick a user to see the rest.</p>
                            <p class="text-muted small mt-2 mb-0">A daily report or a check-in makes a user present, so a day with a report and no check-in is still present. A user who checked in on a day without a report must file that report at their next login.</p>
                            <p class="text-muted small mt-1 mb-0">An incomplete session was not ended by the user and was closed automatically at midnight, so its hours are unknown.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@if($canViewAll)
    @section('modal')
        <div class="modal fade" id="adjustModal" tabindex="-1" aria-labelledby="adjustModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="adjustForm" autocomplete="off">
                    <div class="modal-header">
                        <h6 class="modal-title" id="adjustModalLabel">Correct Check Out</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" id="adjustSummary"></p>
                        <div class="mb-3">
                            <label class="form-label" for="adjust_checked_out_at">Check Out Time</label>
                            <input type="datetime-local" class="form-control" id="adjust_checked_out_at" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label" for="adjust_note">Reason</label>
                            <textarea class="form-control" id="adjust_note" rows="2" maxlength="500" required></textarea>
                        </div>
                        <div class="text-danger mt-2 d-none" id="adjustError" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="adjustSave">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endsection
@endif

@push('scripts')
    @include('backend.attendance.partials.check-ins-script')
    @include('backend.attendance.partials.script')
@endpush
