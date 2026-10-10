@extends('backend.master')
@section('title','Daily Report')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Daily Report</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        @allowed('daily-reports.index')
                            <li class="breadcrumb-item"><a href="{{route('daily-reports.index')}}">Report History</a></li>
                        @endallowed
                        <li class="breadcrumb-item active" aria-current="page">Daily Report</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-calendar-check me-1"></i>
                            {{ $report->report_date->format('d M Y') }} &mdash; {{ $report->user?->name }}
                        </h5>
                        @if($isOwner)
                            @allowed('daily-reports.edit')
                                <a href="{{ route('daily-reports.edit', $report) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-pencil-alt me-1"></i> Edit
                                </a>
                            @endallowed
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle w-100">
                                <thead>
                                <tr>
                                    <th style="width: 30%">Activity</th>
                                    <th style="width: 20%">Total</th>
                                    <th>Note</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>Outbound Calls</td>
                                    <td>{{ $report->outbound_calls }}</td>
                                    <td class="text-wrap">{{ $report->outbound_calls_note }}</td>
                                </tr>
                                <tr>
                                    <td>Order Processing Calls</td>
                                    <td>{{ $report->order_processing }}</td>
                                    <td class="text-wrap">{{ $report->order_processing_note }}</td>
                                </tr>
                                <tr>
                                    <td>Inbound Calls</td>
                                    <td>{{ $report->inbound_calls }}</td>
                                    <td class="text-wrap">{{ $report->inbound_calls_note }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-phone me-1"></i> By Project
                        </h5>
                        <span>
                            <span class="badge text-bg-primary">Outbound: {{ $report->projectCalls->sum('inbound_calls') }}</span>
                            <span class="badge text-bg-primary">Comments: {{ $report->projectCalls->sum('comments') }}</span>
                            <span class="badge text-bg-primary">Messages: {{ $report->projectCalls->sum('message_replies') }}</span>
                        </span>
                    </div>
                    <div class="card-body">
                        @if($report->projectCalls->isEmpty())
                            <p class="text-muted mb-0">No project data on this report.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100">
                                    <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Outbound Calls</th>
                                        <th>Comments</th>
                                        <th>Message Replies</th>
                                        <th>Note</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($report->projectCalls->sortBy('project.name') as $row)
                                        <tr>
                                            <td>{{ $row->project?->name }}</td>
                                            <td>{{ $row->inbound_calls }}</td>
                                            <td>{{ $row->comments }}</td>
                                            <td>{{ $row->message_replies }}</td>
                                            <td class="text-wrap">{{ $row->note }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-comment-multiple-outline me-1"></i> By Social Platform
                        </h5>
                        <span>
                            <span class="badge text-bg-primary">Outbound: {{ $report->platformReplies->sum('inbound_calls') }}</span>
                            <span class="badge text-bg-primary">Comments: {{ $report->platformReplies->sum('comments') }}</span>
                            <span class="badge text-bg-primary">Messages: {{ $report->platformReplies->sum('message_replies') }}</span>
                        </span>
                    </div>
                    <div class="card-body">
                        @if($report->platformReplies->isEmpty())
                            <p class="text-muted mb-0">No platform data on this report.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100">
                                    <thead>
                                    <tr>
                                        <th>Social Platform</th>
                                        <th>Outbound Calls</th>
                                        <th>Comments</th>
                                        <th>Message Replies</th>
                                        <th>Note</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($report->platformReplies->sortBy('socialPlatform.name') as $row)
                                        <tr>
                                            <td>{{ $row->socialPlatform?->name }}</td>
                                            <td>{{ $row->inbound_calls }}</td>
                                            <td>{{ $row->comments }}</td>
                                            <td>{{ $row->message_replies }}</td>
                                            <td class="text-wrap">{{ $row->note }}</td>
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

    </div>
@endsection
