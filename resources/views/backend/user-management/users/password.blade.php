@extends('backend.master')
@section('title','Password')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
@endpush
@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Password</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('/admin/dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Password</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-7 mx-auto">
                @include('backend.error.error')
            </div>
        </div>
        <div class="row">
            <div class="col-xl-7 mx-auto">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <a><i class="fas fa-plus-circle me-2"></i>Change Password Form</a>
                        <a class="btn btn-outline-primary float-end btn-sm border-0" href="{{url('admin/users')}}"> <i class="fa fa-arrow-circle-left"></i> Back </a>
                    </div>
                    <div class="card-body">
                        <form action="{{url('admin/update-password')}}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group row">
                                <label class="col-md-3 control-label">Old Password<span class="text-danger"> * </span></label>
                                <div class="col-md-9">
                                    <input type="password" class="form-control @error('old_password') is-invalid @enderror" name="old_password" value="{{ old('old_password') }}" placeholder="Enter old password"/>
                                    @error('old_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{$message}}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group row position-relative">
                                <label class="col-md-3 control-label">New Password <span class="text-danger"> * </span></label>
                                <div class="col-md-9">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" value="{{ old('password') }}" placeholder="Enter new password"/>
                                    @error('password')
                                    <span class="invalid-feedback" role="alert">
                                       <strong>{{$message}}</strong>
                                     </span>
                                    @enderror
                                    <span class="password-toggle-icon" onclick="togglePassword()">
                                        <i id="toggleIcon" class="bi bi-eye-slash"></i>
                                    </span>
                                    <small id="passwordHelp" class="text-danger d-none"></small>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-md-3 control-label">Confirm Password  <span class="text-danger"> * </span></label>
                                <div class="col-md-9">
                                    <input id="confirmPassword" type="password" class="form-control @error('password_confirmation') is-invalid @enderror" name="password_confirmation" value="{{old('password_confirmation')}}" placeholder="Enter confirm password"/>
                                    @error('password_confirmation')
                                    <span class="invalid-feedback" role="alert">
                                       <strong>{{$message}}</strong>
                                     </span>
                                    @enderror
                                    <small id="confirmHelp" class="text-danger d-none"></small>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-9 offset-3">
                                    <button type="submit" class="btn btn-outline-primary"> <i class="fa fa-arrow-circle-up"></i> CHANGE</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
   @include('backend.user-management.partials.password.password-script')
@endpush
