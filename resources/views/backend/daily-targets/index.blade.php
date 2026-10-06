@extends('backend.master')
@section('title','Daily Targets')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Daily Targets</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Daily Targets</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-view-list me-1"></i> Target List
                        </h5>
                        @allowed('daily-targets.create')
                            <a href="{{ route('daily-targets.create') }}" class="btn btn-sm btn-outline-primary">
                                <i class="mdi mdi-plus-circle me-1"></i> Set Target
                            </a>
                        @endallowed
                    </div>

                    <div class="card-body">
                        <form id="dailyTargetFilterForm" class="row g-2 align-items-end mb-3" autocomplete="off">
                            <div class="col-sm-6 col-md-3">
                                <label class="form-label" for="filter_from">From</label>
                                <input type="date" id="filter_from" class="form-control">
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label class="form-label" for="filter_to">To</label>
                                <input type="date" id="filter_to" class="form-control">
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <label class="form-label" for="filter_user">User</label>
                                <select id="filter_user" class="form-select">
                                    <option value="">All users</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <button type="button" class="btn btn-secondary ms-1" id="dailyTargetFilterReset">Reset</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100" id="dailyTargetDataTable">
                                <thead>
                                <tr>
                                    <th>Sl</th>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Outbound Calls</th>
                                    <th>Inbound Calls</th>
                                    <th>Message Replies</th>
                                    <th>Comment Replies</th>
                                    <th>Project Calls</th>
                                    <th>Set By</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    @include('backend.user-management.datatables.datatable-script')
    @include('backend.includes.plugins.sweetalert2')
    @include('backend.includes.plugins.toastr')
    @include('backend.daily-targets.partials.index-script')
@endpush
