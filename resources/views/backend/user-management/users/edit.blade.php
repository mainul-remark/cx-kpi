@extends('backend.master')
@section('title','User')
@push('styles')
    <link rel="stylesheet" href="{{asset('backend/reza-custom/select2/select2.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/reza-custom/select2/select2-bootstrap-5-theme.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/reza-custom/dropify/dist/css/dropify.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>
    <style>
        .dropify-wrapper .dropify-message p {
            font-size: 14px !important;
        }
    </style>
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
                        <li class="breadcrumb-item"><a href="{{url('/admin/dashboard')}}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Users</li>
                    </ol>
                </nav>

            </div>
        </div>
        <div class="row">
            <div class="col-xl-12 mb-3">
                @include('backend.error.error')
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <a><i class="mdi mdi-plus-circle me-2"></i>Edit User Form </a>
                        <a class="btn btn-outline-secondary float-end btn-sm border-0" href="{{url('admin/users')}}"> <i class="fa fa-arrow-circle-left"></i> Back</a>
                    </div>
                    <div class="card-body">
                        <form action="{{route('users.update',$user)}}" method="POST" enctype="multipart/form-data">
                            @csrf @method('PUT')

{{--                            <div class="row mb-3">--}}
{{--                                <label class="col-lg-2 col-form-label" for="account_type"> Account Type <span class="text-danger">*</span></label>--}}
{{--                                <div class="col-lg-10">--}}
{{--                                    <select required id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror account_type select_account_type" data-placeholder="Select one">--}}
{{--                                       <option value="">Select One</option>--}}
{{--                                        <option value="frontend" {{old('account_type',$user->account_type) == 'frontend' ? 'selected': ''}}>Frontend</option>--}}
{{--                                        <option value="backend" {{old('account_type',$user->account_type) == 'backend' ? 'selected': ''}}>Backend</option>--}}
{{--                                    </select>--}}
{{--                                    @error('account_type')--}}
{{--                                        <span class="invalid-feedback" role="alert">--}}
{{--                                            <strong>{{ $message }}</strong>--}}
{{--                                        </span>--}}
{{--                                    @enderror--}}
{{--                                </div>--}}
{{--                            </div>--}}

                            <div class="row mb-3">
                                <label class="col-lg-2 col-form-label" for="name">Name <span class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input required id="name" type="text" name="name" value="{{old('name',$user->name??'')}}" class="form-control @error('name') is-invalid @enderror"  placeholder="Enter name" />
                                    @error('name')
                                    <span class="invalid-feedback" role="alert">
                                      <strong>{{$message}}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-lg-2 col-form-label" for="email">Email <span class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input  required  id="email" type="email" name="email" value="{{old('email',$user->email??'')}}" class="form-control @error('email') is-invalid @enderror"  placeholder="Enter email" />
                                    @error('email')
                                    <span class="invalid-feedback" role="alert">
                                      <strong>{{$message}}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                            @hasRole(['super-admin'])
                            <div class="row mb-3 position-relative">
                                <label class="col-lg-2 col-form-label" for="password">Password</label>
                                <div class="col-md-10">
                                    <input  id="password" type="password" name="password"  class="form-control @error('password') is-invalid @enderror" placeholder="Enter password" />
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
                          @endhasRole
                            <div class="row mb-3">
                                <label class="col-lg-2 col-form-label" for="employee_id">Employee Id <span class="text-danger">*</span></label>
                                <div class="col-md-10">
                                    <input  required  id="employee_id" type="text" name="employee_id" value="{{old('employee_id', $user->employee_id ?? '')}}" class="form-control @error('employee_id') is-invalid @enderror"  placeholder="Enter Employee ID" />
                                    @error('employee_id')
                                        <span class="invalid-feedback" role="alert">
                                          <strong>{{$message}}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

{{--                            <div class="row mb-3">--}}
{{--                                <label class="col-lg-2 col-form-label" for="mobile_no"> Mobile Number <span class="text-danger">*</span></label>--}}
{{--                                <div class="col-md-10">--}}
{{--                                    <input required id="mobile_no" type="text" name="mobile_no" value="{{old('mobile_no',$user->mobile_no??'')}}" class="form-control @error('mobile_no') is-invalid @enderror"  placeholder="Enter mobile number" />--}}
{{--                                    @error('mobile_no')--}}
{{--                                    <span class="invalid-feedback" role="alert">--}}
{{--                                       <strong>{{$message}}</strong>--}}
{{--                                    </span>--}}
{{--                                    @enderror--}}
{{--                                </div>--}}
{{--                            </div>--}}
                            <div class="row mb-3 roleField">
                                <label class="col-lg-2" for="role_id"> Select Role <span class="text-danger">*</span></label>
                                <div class="col-lg-10">
                                    <select multiple name="role_id[]" class="form-select @error('role_id') is-invalid @enderror role_id select_role role_select">
                                        @foreach($roles as $role)
                                            <option value="{{$role->role_id?? ''}}"  @if(!empty($user->getUserRoles)) @foreach($user->getUserRoles as $item) {{$item->role_id == $role->role_id ? 'selected' :''}} @endforeach @endif >{{ Str::of($role->name)->replace(['_', '-'], ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                    <span class="invalid-feedback" role="alert">
                                       <strong>{{ $message }}</strong>
                                     </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3 usages_sector">
                                <label class="col-lg-2" for="usages_sector"> Select Field <span class="text-danger">*</span></label>
                                <div class="col-lg-10">
                                    <select  name="usages_sector" class="form-select @error('usages_sector') is-invalid @enderror role_id select_role role_select">
                                        <option value="corporate" {{ old('usages_sector',$user->usages_sector??'') == 'corporate' ? 'selected' : '' }}>Corporate</option>
                                        <option value="field" {{ old('usages_sector',$user->usages_sector??'') == 'field' ? 'selected' : '' }}>Field</option>
                                    </select>
                                    @error('usages_sector')
                                    <span class="invalid-feedback" role="alert">
                                       <strong>{{ $message }}</strong>
                                     </span>
                                    @enderror
                                </div>
                            </div>



                            <div class="row mb-3">
                                <label class="col-lg-2 col-form-label" for="profile_image">Profile Image</label>
                                <div class="col-lg-10">
                                    <input type="file" id="profile_image" class="profile_image upload_file" name="profile_image"
                                           @if(!empty($user->profile_image))
                                               data-default-file="{{ asset($user->profile_image) }}"
                                           @endif
                                           accept=".jpg, .jpeg, .webp, .png" data-max-file-size="1M" data-height="100">
                                    <code>[Only upload file jpeg,jpg, png(max-size:1MB)]</code>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-lg-2" for="is_active">Status <span class="text-danger">*</span></label>
                                <div class="col-lg-10">
                                    <select required id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror select_status">
                                        <option value="1" {{ (string) old('is_active', (int) $user->is_active) === '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ (string) old('is_active', (int) $user->is_active) === '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-10 offset-lg-2">
                                    <button type="submit" class="btn btn-outline-primary">  <i class="fa fa-arrow-circle-up"></i> UPDATE </button>
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
    <script src="{{asset('backend/reza-custom/select2/js/select2.full.min.js')}}"></script>
    <script src="{{asset('backend/reza-custom/dropify/dist/js/dropify.min.js')}}"></script>
    @include('backend.user-management.partials.password.password-script')

    <script>
        $(document).ready(function (){
            const selectElements = [
                '.select_account_type',
                '.select_status'
            ];
            selectElements.forEach(selector=>{
                $(selector).select2({
                    theme: 'bootstrap-5',
                    allowClear: false
                });
            });
            $('.select_role').select2({
                theme: 'bootstrap-5',
                allowClear: false,
            });
            $('.select_store').select2({
                theme: 'bootstrap-5',
                allowClear: false,
            });


            function toggleStoreField() {
                if ($('select[name="usages_sector"]').val() === 'field') {
                    $('.storeIdsField').removeClass('d-none');
                } else {
                    $('.storeIdsField').addClass('d-none');
                    $('.select_store').val(null).trigger('change');
                }
            }
            toggleStoreField();
            $('select[name="usages_sector"]').on('change', toggleStoreField);

        });
    </script>

    <script>
        $(document).ready(function (){
            $('.upload_file').dropify();
        });

    </script>

@endpush
