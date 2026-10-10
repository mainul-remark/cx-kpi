<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="projectForm" novalidate autocomplete="off">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="projectModalLabel">Create Project</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="project_name">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="project_name" class="form-control" maxlength="255" placeholder="Enter project name">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>

                    <div class="mb-3 d-none" id="projectSlugGroup">
                        <label class="form-label" for="project_slug">Slug</label>
                        <input type="text" id="project_slug" class="form-control" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="project_notes">Notes</label>
                        <textarea name="notes" id="project_notes" class="form-control" rows="4" maxlength="5000" placeholder="Enter project notes"></textarea>
                        <div class="invalid-feedback" data-error-for="notes"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Tracking</label>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="has_outbound_calls" id="project_has_outbound_calls" value="1" class="form-check-input" checked>
                            <label class="form-check-label" for="project_has_outbound_calls">Outbound Calls</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="has_comments" id="project_has_comments" value="1" class="form-check-input" checked>
                            <label class="form-check-label" for="project_has_comments">Comments</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="has_message_replies" id="project_has_message_replies" value="1" class="form-check-input" checked>
                            <label class="form-check-label" for="project_has_message_replies">Message Replies</label>
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input type="checkbox" name="active" id="project_active" value="1" class="form-check-input" role="switch" checked>
                        <label class="form-check-label" for="project_active">Active</label>
                        <div class="invalid-feedback" data-error-for="active"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="projectSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
