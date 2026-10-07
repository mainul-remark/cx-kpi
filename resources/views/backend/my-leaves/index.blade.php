@extends('backend.master')
@section('title','My Leaves')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>My Leaves</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">My Leaves</li>
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
                        @if($canApply)
                            @allowed('my-leaves.store')
                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary" id="requestLeaveBtn">
                                    <i class="mdi mdi-plus-circle me-1"></i> Request Leave
                                </a>
                            @endallowed
                        @endif
                    </div>

                    <div class="card-body">
                        <p class="text-muted">A leave you ask for waits as pending until it is approved. Only an approved leave counts: no report is due on a full day of it, a half day still takes one.</p>
                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100" id="myLeaveDataTable">
                                <thead>
                                <tr>
                                    <th>Sl</th>
                                    <th>Date</th>
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
    <div class="modal fade" id="myLeaveModal" tabindex="-1" aria-labelledby="myLeaveModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="myLeaveForm" novalidate autocomplete="off">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="myLeaveModalLabel">Request Leave</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="my_leave_from">
                                    From <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="from" id="my_leave_from" class="form-control">
                                <div class="invalid-feedback" data-error-for="from"></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="my_leave_to">
                                    To <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="to" id="my_leave_to" class="form-control">
                                <div class="invalid-feedback" data-error-for="to"></div>
                            </div>
                        </div>
                        <p class="text-muted">The leave is asked for on every day of the range. Fridays and holidays are skipped.</p>

                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="my_leave_portion">
                                    Duration <span class="text-danger">*</span>
                                </label>
                                <select name="portion" id="my_leave_portion" class="form-select">
                                    @foreach($portions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="portion"></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="my_leave_type">
                                    Type <span class="text-danger">*</span>
                                </label>
                                <select name="type" id="my_leave_type" class="form-select">
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="type"></div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="my_leave_note">Reason</label>
                            <textarea name="note" id="my_leave_note" class="form-control" rows="2" maxlength="5000" placeholder="Optional"></textarea>
                            <div class="invalid-feedback" data-error-for="note"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="myLeaveSubmitBtn">Send Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('backend.user-management.datatables.datatable-script')
    @include('backend.includes.plugins.sweetalert2')
    @include('backend.includes.plugins.toastr')
    @include('backend.my-leaves.partials.script')
@endpush
