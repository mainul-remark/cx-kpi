@extends('backend.master')
@php
    $pageTitle = $isEdit ? 'Edit Daily Report' : 'Daily Report';
    $totals = [
        'outbound_calls' => 'Outbound Calls',
        'inbound_calls' => 'Inbound Calls',
        'message_replies' => 'Message Replies',
    ];
@endphp
@section('title', $pageTitle)
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
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
                        </div>
                        <div class="card-body">
                            @if(session('owed_report_dates'))
                                <div class="alert alert-warning" role="alert">
                                    You checked in on {{ collect(session('owed_report_dates'))->map(fn ($day) => \Illuminate\Support\Carbon::parse($day)->format('d M Y'))->join(', ', ' and ') }}
                                    without submitting a daily report. Please file {{ count(session('owed_report_dates')) > 1 ? 'those reports' : 'that report' }} to continue.
                                </div>
                            @endif

                            @if($report && !$isEdit)
                                <div class="alert alert-info" role="alert">
                                    You have already submitted a report for this date. Saving will update it.
                                </div>
                            @endif

                            <div class="row mb-4">
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-label" for="report_date">
                                        Report Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="report_date" id="report_date" class="form-control" value="{{ $date }}" max="{{ today()->toDateString() }}">
                                    <div class="invalid-feedback" data-error-for="report_date"></div>
                                </div>
                            </div>

                            <div class="table-responsive">
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
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-comment-multiple-outline me-1"></i> Comment Replies by Social Platform
                            </h5>
                            <span class="badge text-bg-primary">Total: <span data-sum-of="platform-count">0</span></span>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-reports.partials.rows', [
                                'rows' => $platformRows,
                                'group' => 'platforms',
                                'idField' => 'social_platform_id',
                                'countField' => 'total_replies',
                                'countClass' => 'platform-count',
                                'nameHeading' => 'Social Platform',
                                'countHeading' => 'Comment Replies',
                                'emptyText' => 'No active social platform found.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-phone-outline me-1"></i> Calls by Project
                            </h5>
                            <span class="badge text-bg-primary">Total: <span data-sum-of="project-count">0</span></span>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-reports.partials.rows', [
                                'rows' => $projectRows,
                                'group' => 'projects',
                                'idField' => 'project_id',
                                'countField' => 'total_calls',
                                'countClass' => 'project-count',
                                'nameHeading' => 'Project',
                                'countHeading' => 'Total Calls',
                                'emptyText' => 'No active project found.',
                            ])
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
