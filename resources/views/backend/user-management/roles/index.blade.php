@extends('backend.master')
@section('title','Role')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Roles</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('/admin/dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Roles</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-view-list me-1"></i> Roles List
                        </h5>
                        @allowed('roles.create')
                            <a href="{{ route('roles.create') }}" class="btn btn-sm btn-outline-primary">
                                <i class="mdi mdi-plus-circle me-1"></i> Create
                            </a>
                        @endallowed
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100" id="roleDataTable">
                                <thead>
                                <tr>
                                    <th>Sl</th>
                                    <th>Name</th>
                                    <th>Permission</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $key=>$row)
                                    <tr>
                                        <td>{{$key+1}}</td>
                                        <td>{{ Str::of($row->name)->replace(['_', '-'], ' ')->title() }}</td>
                                        <td>
                                            @if(!empty($row->getPermissions->count())>0)
                                                <span class="badge bg-secondary px-3 py-2">{{$row->getPermissions->count()??''}}</span>
                                            @else
                                                <span class="badge bg-danger px-3 py-2">No Permission</span>
                                            @endif
                                        </td>
                                        <td>
                                            @allowed('roles.edit')
                                                <a href="{{route('roles.edit',$row)}}" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fa fa-pencil-alt"></i></a>
                                            @endallowed

                                            @hasRole(['super-admin'])
                                                <form action="{{route('roles.destroy',$row)}}" method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm ms-2 delete-data" title="Delete"><i class="fa fa-trash-alt"></i></button>
                                                </form>
                                            @endhasRole
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
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
    <script>
        $(document).ready(function () {
            $('#roleDataTable').DataTable();
        });
    </script>
@endpush
