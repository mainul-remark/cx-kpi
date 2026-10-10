@extends('backend.master')
@php
    $pageTitle = $isEdit ? 'Edit Daily Report' : 'Daily Report';
    $totals = [
        'outbound_calls' => 'Outbound Calls',
        'order_processing' => 'Order Processing Calls',
        'inbound_calls' => 'Inbound Calls',
    ];
    $inboundColumns = [['field' => 'inbound_calls', 'label' => 'Outbound Calls', 'class' => '%s-inbound']];
    $commentColumns = [
        ['field' => 'comments', 'label' => 'Comments', 'class' => '%s-comments'],
        ['field' => 'message_replies', 'label' => 'Message Replies', 'class' => '%s-messages'],
    ];
    $withClass = fn (array $columns, string $group) => array_map(fn ($column) => ['class' => sprintf($column['class'], $group)] + $column, $columns);
@endphp
@section('title', $pageTitle)
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
    <style>
        /* the open tab reads white on its filled pill, whatever the theme sets for a link */
        #reportTabs .nav-link.active,
        #reportTabs .nav-link.active i {
            color: #fff !important;
        }
    </style>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>{{ $pageTitle }}</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        @allowed('daily-reports.index')
                            <li class="breadcrumb-item"><a href="{{route('daily-reports.index')}}">Report History</a></li>
                        @endallowed
                        <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                    </ol>
                </nav>

            </div>
        </div>

        <form id="dailyReportForm" novalidate autocomplete="off">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-calendar-check me-1"></i> Day End Data
                            </h5>
                            {{-- the day is picked by the system, so it is shown and not asked for --}}
                            <span class="badge text-bg-secondary fs-6">Report for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</span>
                        </div>
                        <div class="card-body">
                            @if(session('report_required'))
                                <div class="alert alert-warning" role="alert">
                                    You are checked in. Please submit today's daily report to continue using the system.
                                </div>
                            @endif

                            @if($report && !$isEdit)
                                <div class="alert alert-info" role="alert">
                                    You have already submitted a report for this date. Saving will update it.
                                </div>
                            @endif

                            <ul class="nav nav-pills justify-content-center mb-4" id="reportTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link active" id="calls-tab" data-bs-toggle="pill" data-bs-target="#calls-pane" role="tab" aria-controls="calls-pane" aria-selected="true">
                                        <i class="mdi mdi-phone me-1"></i> Calls
                                        <span class="badge text-bg-danger d-none" data-tab-errors="calls-pane"></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link" id="messages-tab" data-bs-toggle="pill" data-bs-target="#messages-pane" role="tab" aria-controls="messages-pane" aria-selected="false">
                                        <i class="mdi mdi-comment-multiple-outline me-1"></i> Comments + Message
                                        <span class="badge text-bg-danger d-none" data-tab-errors="messages-pane"></span>
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="calls-pane" role="tabpanel" aria-labelledby="calls-tab" tabindex="0">
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered align-middle w-100">
                                            <thead>
                                            <tr>
                                                <th style="width: 30%">Activity</th>
                                                <th style="width: 20%">Total <span class="text-danger">*</span></th>
                                                <th>Note</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($totals as $field => $label)
                                                <tr>
                                                    <td><label class="mb-0" for="{{ $field }}">{{ $label }}</label></td>
                                                    <td>
                                                        <input type="number" name="{{ $field }}" id="{{ $field }}" class="form-control" min="0" step="1" value="{{ $report?->{$field} ?? 0 }}">
                                                        <div class="invalid-feedback" data-error-for="{{ $field }}"></div>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="{{ $field }}_note" class="form-control" maxlength="5000" placeholder="Optional note" value="{{ $report?->{$field.'_note'} }}" aria-label="{{ $label }} note">
                                                        <div class="invalid-feedback" data-error-for="{{ $field }}_note"></div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="row">
                                        @if(count($projectRows) > 0)
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">Outbound Calls by Project</h6>
                                                    <span class="badge text-bg-primary">Total: <span data-sum-of="project-inbound">0</span></span>
                                                </div>
                                                <div class="mb-4">
                                                    @include('backend.daily-reports.partials.rows', [
                                                        'rows' => $projectRows,
                                                        'group' => 'projects',
                                                        'columns' => $withClass($inboundColumns, 'project'),
                                                        'withNote' => false,
                                                        'nameHeading' => 'Project',
                                                        'emptyText' => 'No project takes outbound calls.',
                                                    ])
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0">Outbound Calls by Social Platform</h6>
                                                <span class="badge text-bg-primary">Total: <span data-sum-of="platform-inbound">0</span></span>
                                            </div>
                                            @include('backend.daily-reports.partials.rows', [
                                                'rows' => $platformRows,
                                                'group' => 'platforms',
                                                'columns' => $withClass($inboundColumns, 'platform'),
                                                'withNote' => false,
                                                'nameHeading' => 'Social Platform',
                                                'emptyText' => 'No social platform takes outbound calls.',
                                            ])
                                        </div>
                                    </div>


                                </div>

                                <div class="tab-pane fade" id="messages-pane" role="tabpanel" aria-labelledby="messages-tab" tabindex="0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">By Project</h6>
                                        <span>
                                            <span class="badge text-bg-primary">Comments: <span data-sum-of="project-comments">0</span></span>
                                            <span class="badge text-bg-primary">Messages: <span data-sum-of="project-messages">0</span></span>
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        @include('backend.daily-reports.partials.rows', [
                                            'rows' => $projectRows,
                                            'group' => 'projects',
                                            'columns' => $withClass($commentColumns, 'project'),
                                            'withNote' => true,
                                            'nameHeading' => 'Project',
                                            'emptyText' => 'No project takes comments or messages.',
                                        ])
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">By Social Platform</h6>
                                        <span>
                                            <span class="badge text-bg-primary">Comments: <span data-sum-of="platform-comments">0</span></span>
                                            <span class="badge text-bg-primary">Messages: <span data-sum-of="platform-messages">0</span></span>
                                        </span>
                                    </div>
                                    @include('backend.daily-reports.partials.rows', [
                                        'rows' => $platformRows,
                                        'group' => 'platforms',
                                        'columns' => $withClass($commentColumns, 'platform'),
                                        'withNote' => true,
                                        'nameHeading' => 'Social Platform',
                                        'emptyText' => 'No social platform takes comments or messages.',
                                    ])
                                </div>
                            </div>

                            {{-- the id of each row is posted once, whichever tab its inputs are in --}}
                            @foreach(['projects' => ['project_id', $projectRows], 'platforms' => ['social_platform_id', $platformRows]] as $group => [$idField, $rows])
                                @foreach($rows as $index => $row)
                                    @if($row['inbound_calls'] || $row['comments'] || $row['message_replies'])
                                        <input type="hidden" name="{{ $group }}[{{ $index }}][{{ $idField }}]" value="{{ $row['id'] }}">
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <div class="card-footer text-end">
                            @allowed('daily-reports.index')
                                <a href="{{ route('daily-reports.index') }}" class="btn btn-secondary">Cancel</a>
                            @endallowed
                            <button type="submit" class="btn btn-primary" id="dailyReportSubmitBtn">{{ $report ? 'Update' : 'Save' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
@endsection

@push('scripts')
    @include('backend.includes.plugins.sweetalert2')
    @include('backend.includes.plugins.toastr')
    @include('backend.daily-reports.partials.form-script')
@endpush
