@extends('backend.master')

@section('title', 'Site Setting')

@section('body')
    <div class="container m-t-50">
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="card-title">Site Setting Management</div>
                    </div>
                    <div class="card-body">
                        <form id="siteSettingsForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" id="site_setting_id" value="{{ $siteSetting->id ?? '' }}">

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="title" class="form-label">Site Title</label>
                                    <input type="text" class="form-control" id="title" name="title" placeholder="Enter site title" value="{{ old('title', $siteSetting->title ?? '') }}">
                                    <div class="invalid-feedback" id="error-title"></div>
                                </div>
{{--                                <div class="col-lg-6 mb-3">--}}
{{--                                    <label for="site_color" class="form-label">Site Color</label>--}}
{{--                                    <div class="input-group">--}}
{{--                                        <input type="color" class="form-control form-control-color" id="site_color_picker" value="{{ old('site_color', $siteSetting->site_color ?? '#3b82f6') }}">--}}
{{--                                        <input type="text" class="form-control" id="site_color" name="site_color" placeholder="#3b82f6" value="{{ old('site_color', $siteSetting->site_color ?? '') }}">--}}
{{--                                    </div>--}}
{{--                                    <div class="invalid-feedback" id="error-site_color"></div>--}}
{{--                                </div>--}}
                            </div>

                            <div class="row">
                                <div class="col-lg-4 mb-3">
                                    <label for="favicon" class="form-label">Favicon</label>
                                    <input type="file" class="form-control" id="favicon" name="favicon" accept=".jpg,.jpeg,.png,.webp,.svg,.ico">
                                    <div class="invalid-feedback" id="error-favicon"></div>
                                    <img id="favicon-preview" class="image-preview mt-2 {{ empty($siteSetting?->favicon) ? 'd-none' : '' }}" src="{{ !empty($siteSetting?->favicon) ? asset($siteSetting->favicon) : '' }}" alt="Favicon preview">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label for="menu_logo" class="form-label">Menu Logo</label>
                                    <input type="file" class="form-control" id="menu_logo" name="menu_logo" accept=".jpg,.jpeg,.png,.webp,.svg">
                                    <div class="invalid-feedback" id="error-menu_logo"></div>
                                    <img id="menu_logo-preview" class="image-preview mt-2 {{ empty($siteSetting?->menu_logo) ? 'd-none' : '' }}" src="{{ !empty($siteSetting?->menu_logo) ? asset($siteSetting->menu_logo) : '' }}" alt="Menu logo preview">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label for="logo" class="form-label">Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg">
                                    <div class="invalid-feedback" id="error-logo"></div>
                                    <img id="logo-preview" class="image-preview mt-2 {{ empty($siteSetting?->logo) ? 'd-none' : '' }}" src="{{ !empty($siteSetting?->logo) ? asset($siteSetting->logo) : '' }}" alt="Logo preview">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-12 mb-3">
                                    <label for="banner" class="form-label">Banner Image</label>
                                    <input type="file" class="form-control" id="banner" name="banner" accept=".jpg,.jpeg,.png,.webp,.svg">
                                    <div class="invalid-feedback" id="error-banner"></div>
                                    <img id="banner-preview" class="image-preview image-preview-lg mt-2 {{ empty($siteSetting?->banner) ? 'd-none' : '' }}" src="{{ !empty($siteSetting?->banner) ? asset($siteSetting->banner) : '' }}" alt="Banner preview">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="meta_title" class="form-label">Meta Title</label>
                                    <textarea class="form-control" id="meta_title" name="meta_title" rows="2" placeholder="Enter meta title">{{ old('meta_title', $siteSetting->meta_title ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-meta_title"></div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="meta_description" class="form-label">Meta Description</label>
                                    <textarea class="form-control" id="meta_description" name="meta_description" rows="2" placeholder="Enter meta description">{{ old('meta_description', $siteSetting->meta_description ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-meta_description"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="meta_header" class="form-label">Meta Header</label>
                                    <textarea class="form-control" id="meta_header" name="meta_header" rows="4" placeholder="Header meta tags">{{ old('meta_header', $siteSetting->meta_header ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-meta_header"></div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="meta_footer" class="form-label">Meta Footer</label>
                                    <textarea class="form-control" id="meta_footer" name="meta_footer" rows="4" placeholder="Footer meta tags">{{ old('meta_footer', $siteSetting->meta_footer ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-meta_footer"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="header_custom_code" class="form-label">Header Custom Code</label>
                                    <textarea class="form-control" id="header_custom_code" name="header_custom_code" rows="4" placeholder="Custom scripts/styles for header">{{ old('header_custom_code', $siteSetting->header_custom_code ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-header_custom_code"></div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="footer_custom_code" class="form-label">Footer Custom Code</label>
                                    <textarea class="form-control" id="footer_custom_code" name="footer_custom_code" rows="4" placeholder="Custom scripts/styles for footer">{{ old('footer_custom_code', $siteSetting->footer_custom_code ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-footer_custom_code"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="site_info" class="form-label">Site Info</label>
                                    <textarea class="form-control" id="site_info" name="site_info" rows="3" placeholder="Short description or footer info">{{ old('site_info', $siteSetting->site_info ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-site_info"></div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="office_address" class="form-label">Office Address</label>
                                    <textarea class="form-control" id="office_address" name="office_address" rows="3" placeholder="Office address">{{ old('office_address', $siteSetting->office_address ?? '') }}</textarea>
                                    <div class="invalid-feedback" id="error-office_address"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="office_mobile" class="form-label">Office Mobile</label>
                                    <input type="text" class="form-control" id="office_mobile" name="office_mobile" placeholder="Enter office mobile" value="{{ old('office_mobile', $siteSetting->office_mobile ?? '') }}">
                                    <div class="invalid-feedback" id="error-office_mobile"></div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="office_email" class="form-label">Office Email</label>
                                    <input type="email" class="form-control" id="office_email" name="office_email" placeholder="Enter office email" value="{{ old('office_email', $siteSetting->office_email ?? '') }}">
                                    <div class="invalid-feedback" id="error-office_email"></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary" id="btn-save">
                                    <span class="btn-text">Save Settings</span>
                                    <span class="spinner-border spinner-border-sm d-none" id="btn-spinner"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')

@endsection

@push('styles')
<style>
    .image-preview {
        width: 100%;
        max-height: 120px;
        object-fit: contain;
        border-radius: 8px;
        border: 1px solid #e6e8f0;
        background: #fafbff;
        padding: 6px;
    }
    .image-preview-lg {
        max-height: 220px;
    }
</style>

@endpush

@push('scripts')
    <script>
        $(document).ready(function () {
            bindPreview('favicon', 'favicon-preview');
            bindPreview('menu_logo', 'menu_logo-preview');
            bindPreview('logo', 'logo-preview');
            bindPreview('banner', 'banner-preview');

            $('#siteSettingsForm').on('submit', function (e) {
                e.preventDefault();
                clearErrors();

                const id = $('#site_setting_id').val();
                const url = id ? base_url + 'site-settings/' + id : base_url + 'site-settings';
                const formData = new FormData(this);
                if (id) formData.append('_method', 'PUT');

                $('#btn-save').prop('disabled', true);
                $('#btn-spinner').removeClass('d-none');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        showToast(res.message, 'success');
                        setTimeout(() => location.reload(), 800);
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            const errors = xhr.responseJSON.errors;
                            $.each(errors, function (field, messages) {
                                $('#' + field).addClass('is-invalid');
                                $('#error-' + field).text(messages[0]);
                            });
                        } else {
                            showToast('Something went wrong.', 'danger');
                        }
                    },
                    complete: function () {
                        $('#btn-save').prop('disabled', false);
                        $('#btn-spinner').addClass('d-none');
                    }
                });
            });

            function bindPreview(inputId, previewId) {
                $('#' + inputId).on('change', function () {
                    const file = this.files && this.files[0];
                    if (!file) {
                        $('#' + previewId).addClass('d-none').attr('src', '');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        $('#' + previewId).attr('src', e.target.result).removeClass('d-none');
                    };
                    reader.readAsDataURL(file);
                });
            }

            function clearErrors() {
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').text('');
            }

            function showToast(message, type) {
                const toast = $(`
                    <div class="toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `).appendTo('body');
                setTimeout(() => toast.remove(), 3000);
            }
        });
    </script>

@endpush
