@extends('backend.master')
@php
    $pageTitle = $target ? 'Edit Daily Target' : 'Set Daily Target';
    $totals = [
        'outbound_calls' => ['label' => 'Outbound Calls', 'required' => true],
        'inbound_calls' => ['label' => 'Inbound Calls (approximate)', 'required' => false],
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
                        @allowed('daily-targets.index')
                            <li class="breadcrumb-item"><a href="{{route('daily-targets.index')}}">Daily Targets</a></li>
                        @endallowed
                        <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                    </ol>
                </nav>

            </div>
        </div>

        <form id="dailyTargetForm" novalidate autocomplete="off">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-account-multiple-outline me-1"></i> Users and Dates
                            </h5>
                            @allowed('daily-targets.index')
                                <a href="{{ route('daily-targets.index') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="mdi mdi-arrow-left me-1"></i> Back to Targets
                                </a>
                            @endallowed
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <label class="form-label" for="user_ids">
                                        Field Users <span class="text-danger">*</span>
                                    </label>
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

                            <div class="row">
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-label" for="from">
                                        From <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="from" id="from" class="form-control" value="{{ $from }}">
                                    <div class="invalid-feedback" data-error-for="from"></div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label class="form-label" for="to">
                                        To <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="to" id="to" class="form-control" value="{{ $to }}">
                                    <div class="invalid-feedback" data-error-for="to"></div>
                                </div>
                            </div>
                            <p class="text-muted mt-3 mb-0">
                                The target is set for each selected user on every day of the range. Fridays and holidays are skipped,
                                and a target already set for a day is replaced.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-target me-1"></i> Daily Target
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                The outbound call target is required, the KPI is worked out from it: the outbound and order processing calls
                                reported are added up and compared with it. Every other target is approximate and optional, it is shown for
                                reference and does not change the KPI. Leave it empty to set none.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle w-100">
                                    <thead>
                                    <tr>
                                        <th style="width: 50%">Activity</th>
                                        <th>Target per Day</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($totals as $field => $config)
                                        <tr>
                                            <td>
                                                <label class="mb-0" for="{{ $field }}">{{ $config['label'] }}</label>
                                                @if($config['required'])<span class="text-danger">*</span>@endif
                                            </td>
                                            <td>
                                                <input type="number" name="{{ $field }}" id="{{ $field }}" class="form-control" min="0" step="1" placeholder="{{ $config['required'] ? 'Required' : 'No target' }}" value="{{ $target?->{$field} }}">
                                                <div class="invalid-feedback" data-error-for="{{ $field }}"></div>
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
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-phone me-1"></i> Approximate Targets by Project
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-targets.partials.rows', [
                                'rows' => $projectRows,
                                'group' => 'projects',
                                'idField' => 'project_id',
                                'nameHeading' => 'Project',
                                'emptyText' => 'No active project takes calls, comments or messages.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0">
                                <i class="mdi mdi-comment-multiple-outline me-1"></i> Approximate Targets by Social Platform
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('backend.daily-targets.partials.rows', [
                                'rows' => $platformRows,
                                'group' => 'platforms',
                                'idField' => 'social_platform_id',
                                'nameHeading' => 'Social Platform',
                                'emptyText' => 'No active social platform takes calls, comments or messages.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 text-end mb-4">
                    @allowed('daily-targets.index')
                        <a href="{{ route('daily-targets.index') }}" class="btn btn-secondary">Cancel</a>
                    @endallowed
                    <button type="submit" class="btn btn-primary" id="dailyTargetSubmitBtn">Set Target</button>
                </div>
            </div>
        </form>

    </div>
@endsection

@push('scripts')
    @include('backend.includes.plugins.select2')
    @include('backend.includes.plugins.toastr')
    @include('backend.daily-targets.partials.form-script')
@endpush
