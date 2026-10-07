@extends('backend.master')
@section('title','Leaves')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Leaves</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Leaves</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-view-list me-1"></i> Leave List
                        </h5>
                        @allowed('leaves.store')
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary" id="createLeaveBtn">
                                <i class="mdi mdi-plus-circle me-1"></i> Set Leave
                            </a>
                        @endallowed
                    </div>

                    <div class="card-body">
                        <p class="text-muted">Record the official leave of the field users here, one row per user per day. A leave set here is approved at once, one a user asked for waits as pending until it is approved or rejected. Only an approved leave counts: no report can be submitted for a full day of it, a half day still takes one. Targets set on a leave day are kept.</p>
                        <form id="leaveFilterForm" class="row g-2 align-items-end mb-3" autocomplete="off">
                            <div class="col-sm-6 col-md-2">
                                <label class="form-label" for="filter_from">From</label>
                                <input type="date" id="filter_from" class="form-control">
                            </div>
                            <div class="col-sm-6 col-md-2">
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
                            <div class="col-sm-6 col-md-2">
                                <label class="form-label" for="filter_status">Status</label>
                                <select id="filter_status" class="form-select">
                                    <option value="">All</option>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <button type="button" class="btn btn-secondary ms-1" id="leaveFilterReset">Reset</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100" id="leaveDataTable">
                                <thead>
                                <tr>
                                    <th>Sl</th>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Duration</th>
                                    <th>Type</th>
                                    <th>Note</th>
                                    <th>Status</th>
                                    <th>Decided By</th>
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

@section('modal')
    @include('backend.leaves.partials.modal')
@endsection

@push('scripts')
    @include('backend.user-management.datatables.datatable-script')
    @include('backend.includes.plugins.select2')
    @include('backend.includes.plugins.sweetalert2')
    @include('backend.includes.plugins.toastr')
    @include('backend.leaves.partials.script')
@endpush
