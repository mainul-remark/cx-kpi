@extends('backend.master')
@section('title','Holidays')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Holidays</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Holidays</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-view-list me-1"></i> Holiday List
                        </h5>
                        <div>
                            @allowed('holidays.import')
                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary me-1" id="importHolidayBtn">
                                    <i class="ri-import-line me-1"></i> Import
                                </a>
                            @endallowed
                            @allowed('holidays.store')
                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary" id="createHolidayBtn">
                                    <i class="mdi mdi-plus-circle me-1"></i> Create
                                </a>
                            @endallowed
                        </div>
                    </div>

                    <div class="card-body">
                        <p class="text-muted">Every Friday is an off day already. Add the other days the office is closed here, they are skipped when a target is set. Targets already set on a day are removed when it becomes a holiday.</p>
                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100" id="holidayDataTable">
                                <thead>
                                <tr>
                                    <th>Sl</th>
                                    <th>Date</th>
                                    <th>Title</th>
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
    @include('backend.holidays.partials.modal')
@endsection

@push('scripts')
    @include('backend.user-management.datatables.datatable-script')
    @include('backend.includes.plugins.sweetalert2')
    @include('backend.includes.plugins.toastr')
    @include('backend.holidays.partials.script')
@endpush
