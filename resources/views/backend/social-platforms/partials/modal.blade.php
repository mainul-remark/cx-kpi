<div class="modal fade" id="socialPlatformModal" tabindex="-1" aria-labelledby="socialPlatformModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="socialPlatformForm" novalidate autocomplete="off">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="socialPlatformModalLabel">Create Social Platform</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="social_platform_name">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="social_platform_name" class="form-control" maxlength="255" placeholder="Enter social platform name">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>

                    <div class="mb-3 d-none" id="socialPlatformSlugGroup">
                        <label class="form-label" for="social_platform_slug">Slug</label>
                        <input type="text" id="social_platform_slug" class="form-control" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="social_platform_notes">Notes</label>
                        <textarea name="notes" id="social_platform_notes" class="form-control" rows="4" maxlength="5000" placeholder="Enter social platform notes"></textarea>
                        <div class="invalid-feedback" data-error-for="notes"></div>
                    </div>

                    <div class="form-check form-switch">
                        <input type="checkbox" name="active" id="social_platform_active" value="1" class="form-check-input" role="switch" checked>
                        <label class="form-check-label" for="social_platform_active">Active</label>
                        <div class="invalid-feedback" data-error-for="active"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="socialPlatformSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
