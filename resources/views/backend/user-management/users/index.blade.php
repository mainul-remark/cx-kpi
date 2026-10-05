@extends('backend.master')
@section('title','User')
@push('styles')
    @include('backend.user-management.datatables.datatable-style')
    <link rel="modulepreload" href="{{asset('backend/build/assets/date_time_pickers-CfSDcSmz.js')}}" />
    <link rel="stylesheet" href="{{asset('backend/reza-custom/css/custom.css')}}"/>

@endpush

@section('body')
    <div class="container-fluid pt-3">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-3">
            <div class="my-auto">
                <h4 class="mb-sm-0 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Users</h4>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Users</li>
                    </ol>
                </nav>

            </div>
        </div>
        <div class="row mb-4">
            <div class="col-xl-7 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <form id="filter_form" class="form-inline justify-content-center">
                            <div class="row">

                                <div class="col-md-4">
                                    <div class="input-group ">
                                        <span class="input-group-text text-muted"><i class="ri-group-line"></i></span>
                                        <select name="role_id" id="selectRole" class="form-control ">
                                            <option value="">All Roles</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->role_id }}" >{{ $role->name ?? '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="btn-group" role="group" aria-label="Filter actions">
                                        <button type="submit" class="btn btn-outline-primary " id="filterBtn" title="Filter">
                                            <i class="ri-search-line"></i> Filter
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary  ajax_reload" id="resetBtn" title="Refresh">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-view-list me-1"></i> User List
                        </h5>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary ms-auto me-2" data-bs-toggle="modal" data-bs-target="#importUserModal">
                            <i class="ri-import-line me-1"></i> Import
                        </a>
                        <a href="{{ route('users.create') }}" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-plus-circle me-1"></i> Create
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table  class="table table-bordered text-nowrap w-100 mb-3" id="userDataTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="importUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-2">Import User</h6>
                    <p class="text-muted mb-3">Download sample file from <a href="{{ asset('import-samples/import-user.xlsx') }}">here</a>.</p>
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        Default password for imported users: <strong>remarkhb</strong>
                    </div>
                    <form action="{{ route('users.import') }}" method="post" enctype="multipart/form-data" id="importUserForm">
                        @csrf
                        <div class="mb-3">
                            <label for="import_user_file" class="form-label">Upload File</label>
                            <input
                                type="file"
                                id="import_user_file"
                                name="file"
                                class="filepond-user-import"
                                accept=".xlsx,.xls,.csv"
                            >
                            <div class="invalid-feedback d-block" id="import-user-file-error"></div>
                        </div>
                        <div class="d-none" id="import-user-row-errors-wrap">
                            <div class="alert alert-danger">
                                <div class="fw-semibold mb-2">Import errors</div>
                                <ul class="mb-0 ps-3 small" id="import-user-row-errors"></ul>
                            </div>
                        </div>
                        <div class="mt-2 text-end">
                            <button type="submit" class="btn btn-sm btn-success ms-auto" id="btn-import-user-submit">
                                <span class="btn-text">Upload</span>
                                <span class="spinner-border spinner-border-sm d-none" id="import-user-spinner"></span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('backend/build/assets/libs/filepond/filepond.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/build/assets/libs/filepond-plugin-image-preview/filepond-plugin-image-preview.min.css') }}">
@endpush

@push('scripts')
    @include('backend.includes.plugins.select2')
    <script src="{{asset('backend/build/assets/libs/flatpickr/flatpickr.min.js')}}"></script>
    @include('backend.user-management.datatables.datatable-script')
    @include('backend.user-management.toasts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @include('backend.user-management.partials.user.user-index-script')
    <script src="{{ asset('backend/build/assets/libs/filepond/filepond.min.js') }}"></script>
    <script src="{{ asset('backend/build/assets/libs/filepond-plugin-image-preview/filepond-plugin-image-preview.min.js') }}"></script>
    <script src="{{ asset('backend/build/assets/libs/filepond-plugin-image-exif-orientation/filepond-plugin-image-exif-orientation.min.js') }}"></script>
    <script src="{{ asset('backend/build/assets/libs/filepond-plugin-file-validate-type/filepond-plugin-file-validate-type.min.js') }}"></script>
    <script src="{{ asset('backend/build/assets/libs/filepond-plugin-file-validate-size/filepond-plugin-file-validate-size.min.js') }}"></script>

    <script>
        $(function () {
            const importUserModalEl = document.getElementById('importUserModal');
            const importUserModal = new bootstrap.Modal(importUserModalEl);

            FilePond.registerPlugin(
                FilePondPluginFileValidateType,
                FilePondPluginFileValidateSize
            );

            const importUserPond = FilePond.create(document.querySelector('.filepond-user-import'), {
                labelIdle: '<i class="ri-upload-cloud-2-line" style="font-size:1.45rem;color:var(--text-muted)"></i><br><span class="text-muted fs-13">Drag & drop Excel/CSV file or <span class="filepond--label-action">browse</span></span>',
                acceptedFileTypes: [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv'
                ],
                maxFileSize: '5MB',
                credits: false,
            });

            function resetImportUserState() {
                $('#import-user-file-error').text('');
                $('#import-user-row-errors').empty();
                $('#import-user-row-errors-wrap').addClass('d-none');
                $('#btn-import-user-submit').prop('disabled', false);
                $('#import-user-spinner').addClass('d-none');
                importUserPond.removeFiles();
            }

            $('#importUserForm').on('submit', function (event) {
                event.preventDefault();

                $('#import-user-file-error').text('');
                $('#import-user-row-errors').empty();
                $('#import-user-row-errors-wrap').addClass('d-none');

                const uploadedFile = importUserPond.getFile();

                if (!uploadedFile) {
                    $('#import-user-file-error').text('Please select an import file.');
                    return;
                }

                const formData = new FormData(this);
                formData.set('file', uploadedFile.file);

                $('#btn-import-user-submit').prop('disabled', true);
                $('#import-user-spinner').removeClass('d-none');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        showAjaxToast('success', response.message || 'Users imported successfully.');
                        importUserModal.hide();
                        $('#userDataTable').DataTable().ajax.reload(null, false);
                        resetImportUserState();
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            if (xhr.responseJSON?.errors?.file?.[0]) {
                                $('#import-user-file-error').text(xhr.responseJSON.errors.file[0]);
                                return;
                            }

                            if (Array.isArray(xhr.responseJSON?.errors) && xhr.responseJSON.errors.length) {
                                const rowsHtml = xhr.responseJSON.errors
                                    .map(function (item) {
                                        const rowErrors = Array.isArray(item.errors) ? item.errors.join(', ') : 'Unknown error';
                                        return `<li><strong>Row ${item.row}:</strong> ${rowErrors}</li>`;
                                    })
                                    .join('');

                                $('#import-user-row-errors').html(rowsHtml);
                                $('#import-user-row-errors-wrap').removeClass('d-none');
                                showAjaxToast('error', xhr.responseJSON?.message || 'Import failed. Please fix the listed rows.');
                                return;
                            }
                        }

                        showAjaxToast('error', xhr.responseJSON?.message || 'User import failed.');
                    },
                    complete: function () {
                        $('#btn-import-user-submit').prop('disabled', false);
                        $('#import-user-spinner').addClass('d-none');
                    }
                });
            });

            importUserModalEl.addEventListener('hidden.bs.modal', function () {
                resetImportUserState();
            });
        });
    </script>
@endpush
