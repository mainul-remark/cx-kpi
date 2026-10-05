@extends('backend.master')
@section('title','Profile')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/dropify/dist/css/dropify.min.css')}}"/>

@endpush
@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-5">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Profile</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('/admin/dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Profile</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                @include('backend.error.error')
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <a><i class="fas fa-plus-circle me-2"></i>Edit Profile Info</a>
                        <a class="btn btn-outline-primary float-end btn-sm border-0" href="{{url('admin/users')}}"> <i class="fa fa-arrow-circle-left"></i> Back </a>
                    </div>
                    <div class="card-body">
                        <form action="{{url('admin/update-profile')}}" method="POST" enctype="multipart/form-data">
                            @csrf
                           <table class="table table-bordered">
                               <thead>
                               <tr>
                                   <td colspan="2" class="text-center">
                                       @if(!empty($user->profile_image) && file_exists(public_path($user->profile_image)))
                                           <img src="{{ asset($user->profile_image)}}" alt="user_profile" class="rounded-circle" height="150" width="150">
                                       @else
                                           <img src="{{asset('backend/remark-logo.png')}}" alt="user" class="rounded-circle" height="150" width="150">
                                       @endif
                                   </td>
                               </tr>
                               <tr>
                                   <th>Name</th>
                                   <td>
                                       <input type="text" name="name" value="{{$user->name??''}}" class="form-control" placeholder="Enter name" readonly/>
                                   </td>
                               </tr>
                               <tr>
                                   <th>Email</th>
                                   <td>
                                       <input type="text" name="email" value="{{$user->email??''}}" class="form-control" placeholder="Enter email" readonly/>
                                   </td>
                               </tr>
                               <tr>
                                   <th> Mobile No. <span class="text-danger"> *</span></th>
                                   <td>
                                       <input type="text" name="mobile_no" class="form-control @error('mobile_no')is-invalid @enderror" value="{{old('mobile_no',$user->mobile_no??'')}}" placeholder="Enter mobile number" required/>
                                       @error('mobile_no')
                                       <span class="invalid-feedback" role="alert">
                                            <strong>{{$message}}</strong>
                                         </span>
                                       @enderror
                                   </td>
                               </tr>
                               <tr>
                                   <th style="vertical-align: top"> Profile Image </th>
                                   <td>
                                       <input type="file" name="profile_image" class="form-control-file @error('profile_image') is-invalid @enderror upload_file" data-default-file="{{asset($user->profile_image??'')}}" accept=".jpg, .jpeg, .png" data-max-file-size="1M" data-height="100"/>
                                       @error('profile_image')
                                       <span class="invalid-feedback" role="alert">
                                            <strong>{{$message}}</strong>
                                        </span>
                                       @enderror
                                       <code>[Only upload file jpeg,jpg,png(max-size:1MB)]</code>
                                   </td>
                               </tr>
                               </thead>
                           </table>
                            <div class="form-group mt-4">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-outline-primary"> <i class="fa fa-arrow-circle-up"></i> UPDATE </button>
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
    <script src="{{asset('backend/reza-custom/dropify/dist/js/dropify.min.js')}}"></script>
    <script>
        $(document).ready(function (){
            $('.upload_file').dropify();
        });
    </script>
@endpush
