@extends('backend.master')
@section('title', 'Monthly Targets')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-calendar-month-outline me-2"></i>Monthly Targets</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monthly Targets</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if(config('kpi.target_mode') !== 'period')
            <div class="alert alert-warning">
                The KPI is currently worked out from the <strong>daily</strong> targets. The monthly targets set here are used
                once <code>target_mode</code> in <code>config/kpi.php</code> is set to <code>period</code>.
            </div>
        @endif

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0"><i class="mdi mdi-view-list me-1"></i> Targets of {{ $month->format('F Y') }}</h5>
                        <form method="GET" action="{{ route('period-targets.index') }}" class="d-flex align-items-center">
                            <input type="month" name="month" class="form-control form-control-sm" value="{{ $month->format('Y-m') }}">
                            <button type="submit" class="btn btn-sm btn-primary ms-2">Show</button>
                        </form>
                    </div>
                    <div class="card-body">
                        @if($list->isEmpty())
                            <p class="text-muted mb-0">No monthly target is set for {{ $month->format('F Y') }}.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100">
                                    <thead>
                                    <tr><th style="width: 22%">User</th><th>Targets for the month</th><th style="width: 12%">Action</th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach($list as $row)
                                        <tr>
                                            <td>{{ $row['name'] }}</td>
                                            <td>{{ implode(' · ', $row['targets']) }}</td>
                                            <td class="text-nowrap">
                                                <a href="{{ route('period-targets.index', ['month' => $month->format('Y-m'), 'user' => $row['id']]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <button type="button" class="btn btn-sm btn-outline-danger period-target-delete" data-user="{{ $row['id'] }}">Delete</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <form id="periodTargetForm" novalidate autocomplete="off">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-account-multiple-outline me-1"></i> Set the Target of {{ $month->format('F Y') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <label class="form-label" for="user_ids">Field Users <span class="text-danger">*</span></label>
                                    <select name="user_ids[]" id="user_ids" class="form-select select-ele" multiple data-placeholder="Select field users">
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" @selected(in_array($user->id, $selectedUserIds))>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback" data-error-for="user_ids"></div>
                                </div>
                                <div class="col-md-3 d-flex align-items-start" style="padding-top: 1.9rem">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllUsersBtn">Select All</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" id="clearUsersBtn">Clear</button>
                                </div>
                            </div>
                            <p class="text-muted mb-3">
                                One target for the whole month per user. A user's target is brought down to the days they are asked for in the
                                range being scored (days off, on leave and before they joined are left out), so a month in progress is
                                scored on the days gone so far. A monthly target already set for the user is replaced as a whole.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100">
                                    <thead><tr><th style="width: 50%">Activity</th><th>Target for the Month</th></tr></thead>
                                    <tbody>
                                    <tr>
                                        <td><label class="mb-0" for="outbound_calls">Outbound Calls</label> <span class="text-danger">*</span></td>
                                        <td>
                                            <input type="number" name="outbound_calls" id="outbound_calls" class="form-control" min="0" step="1" placeholder="Required" value="{{ $values->get('outbound_calls') }}">
                                            <div class="invalid-feedback" data-error-for="outbound_calls"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label class="mb-0" for="inbound_calls">Inbound Calls</label></td>
                                        <td>
                                            <input type="number" name="inbound_calls" id="inbound_calls" class="form-control" min="0" step="1" placeholder="No target" value="{{ $values->get('inbound_calls') }}">
                                            <div class="invalid-feedback" data-error-for="inbound_calls"></div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-phone me-1"></i> Targets by Project</h5>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-targets.partials.rows', [
                                'rows' => $projectRows,
                                'group' => 'projects',
                                'idField' => 'project_id',
                                'nameHeading' => 'Project',
                                'per' => 'per Month',
                                'emptyText' => 'No active project takes calls, comments or messages.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0"><i class="mdi mdi-comment-multiple-outline me-1"></i> Targets by Social Platform</h5>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-targets.partials.rows', [
                                'rows' => $platformRows,
                                'group' => 'platforms',
                                'idField' => 'social_platform_id',
                                'nameHeading' => 'Social Platform',
                                'per' => 'per Month',
                                'emptyText' => 'No active social platform takes calls, comments or messages.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 text-end mb-4">
                    <button type="submit" class="btn btn-primary" id="periodTargetSubmitBtn">Set Target</button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('backend.includes.plugins.select2')
    @include('backend.includes.plugins.toastr')
    @include('backend.period-targets.partials.script')
@endpush
