@extends('backend.master')
@section('title','Role')
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
                @include('backend.error.error')
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom">
                        <a><i class="mdi mdi-plus-circle me-2"></i>Create Role Form </a>
                        <a class="btn btn-outline-secondary float-end btn-sm border-0" href="{{url('admin/roles')}}"> <i class="fa fa-arrow-circle-left"></i> Back</a>
                    </div>
                    <div class="card-body">
                        <form action="{{route('roles.store')}}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3 align-items-center">
                                <label class="col-md-3 col-form-label" for="name">
                                    Name <span class="text-danger">*</span>
                                </label>
                                <div class="col-md-9">
                                    <input type="text" name="name" value="{{ old('name') }}"
                                           class="form-control @error('name') is-invalid @enderror"
                                           required placeholder="Enter role name">

                                    @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input border-primary" id="select-all">
                                        <label class="form-check-label" for="select-all">Select All</label>
                                    </div>
                                </div>
                            </div>

                            @foreach($data as $key => $actions)
                                <div class="border mb-3 p-3 rounded">
                                    <div class="row mb-2">
                                        <label class="col-md-3 fw-bold">{{ $key ?? '' }} :</label>
                                        <div class="col-md-9 resource-group">

                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input border-primary select-sub-options" data-key="{{ $key ?? '' }}" id="select-sub-options-{{ $key }}">
                                                        <label class="form-check-label" for="select-sub-options-{{ $key }}">Select All</label>
                                                    </div>
                                                </div>
                                            </div>

                                            @foreach($actions as $act)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input resource-checkbox border-primary child-{{ $key }}"
                                                           type="checkbox"
                                                           name="resource[]"
                                                           value="{{ $act['id'] ?? '' }}"
                                                           id="chk{{ $act['id'] }}"
                                                        {{ in_array($act['id'], old('resource', [])) ? 'checked' : '' }}>

                                                    <label class="form-check-label ms-1" for="chk{{ $act['id'] }}">
                                                        {{ $act['name'] ?? '' }}
                                                    </label>
                                                </div>
                                            @endforeach

                                            @error('resource')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror

                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="fa fa-arrow-circle-up"></i> CREATE
                                    </button>
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
    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.resource-checkbox').forEach(cb => cb.checked = checked);
            document.querySelectorAll('.select-sub-options').forEach(cb => cb.checked = checked);
        });

        $(document).on('change', '.select-sub-options', function () {
            let key = $(this).data('key');
            let isChecked = $(this).is(':checked');

            $('.child-' + key).prop('checked', isChecked);
        });

        $(document).on('change', '.resource-checkbox', function () {
            let classList = $(this).attr('class').split(/\s+/);
            let childClass = classList.find(function (cls) {
                return cls.startsWith('child-');
            });

            if (!childClass) return;

            let total = $('.' + childClass).length;
            let checked = $('.' + childClass + ':checked').length;

            $('.select-sub-options').each(function () {
                if ('child-' + $(this).data('key') === childClass) {
                    $(this).prop('checked', total > 0 && total === checked);
                }
            });
        });
    </script>

@endpush
