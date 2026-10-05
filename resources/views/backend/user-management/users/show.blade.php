@extends('backend.master')
@section('title','View')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/dropify/dist/css/dropify.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Users</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Users</li>
                    </ol>
                </nav>

            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-id-card me-2"></i>  View User Info
                        </h5>
                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary border-0">
                            <i class="fa fa-arrow-circle-left"></i>  Back
                        </a>
                    </div>
                    <div class="card-body">
                        <table  class="table table-bordered text-nowrap w-100">
                            <thead>
                            <tr>
                                <td colspan="2" class="text-center">
                                    @if(!empty($user->profile_image) && file_exists(public_path($user->profile_image)))
                                        <img src="{{ asset($user->profile_image)}}" alt="user_profile" class="rounded-circle" height="150" width="150">
                                    @else
                                        <img src="{{ asset('backend/remark-logo.png') }}" alt="Image" class="rounded-circle" height="150" width="150">
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td>{{$user->name??''}}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{$user->email??''}}</td>
                            </tr>
{{--                            <tr>--}}
{{--                                <th>Mobile Number </th>--}}
{{--                                <td>{{$user->mobile_no??''}}</td>--}}
{{--                            </tr>--}}
{{--                            <tr>--}}
{{--                                <th>Type</th>--}}
{{--                                <td>--}}
{{--                                    <span class="badge bg-outline-light px-2">{{$user->account_type??''}}</span>--}}
{{--                                </td>--}}
{{--                            </tr>--}}
                            <tr>
                                <th>Joined At</th>
                                <td>
                                    <span class="badge bg-outline-primary px-2">{{$user->created_at->format('Y-m-d  H:i:s')}}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Updated At</th>
                                <td>
                                    <span class="badge bg-outline-secondary px-2">{{$user->updated_at->format('Y-m-d H:i:s')}}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td>
                                    @if($user->getUserRoles && $user->getUserRoles->isNotEmpty())
                                        @foreach($user->getUserRoles as $tmp_role)
                                            <span class="badge bg-outline-primary px-2">
                                                {{ ucfirst(str_replace('_', ' ', $tmp_role->role->name ?? '')) }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="badge bg-outline-warning px-2">User</span>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($user->is_active == 1)
                                        <span class="badge bg-outline-success px-2">Active</span>
                                    @else
                                        <span class="badge bg-outline-danger px-2">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{asset('backend/reza-custom/dropify/dist/js/dropify.min.js')}}"></script>
@endpush
