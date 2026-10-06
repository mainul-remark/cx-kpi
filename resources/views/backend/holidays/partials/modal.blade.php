<div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="holidayForm" novalidate autocomplete="off">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="holidayModalLabel">Create Holiday</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="holiday_date">
                            Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="holiday_date" id="holiday_date" class="form-control">
                        <div class="invalid-feedback" data-error-for="holiday_date"></div>
                    </div>

                    <div>
                        <label class="form-label" for="holiday_title">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" id="holiday_title" class="form-control" maxlength="255" placeholder="e.g. Victory Day">
                        <div class="invalid-feedback" data-error-for="title"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="holidaySubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayImportModal" tabindex="-1" aria-labelledby="holidayImportModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="holidayImportForm" novalidate autocomplete="off">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="holidayImportModalLabel">Import Holidays</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">
                        Upload a csv or Excel file with a <strong>Date</strong> and a <strong>Title</strong> column, one holiday per row.
                        Download the sample file as
                        <a href="{{ route('holidays.sample') }}">xlsx</a> or
                        <a href="{{ route('holidays.sample', ['format' => 'csv']) }}">csv</a>.
                    </p>
                    <p class="text-muted mb-3">
                        Write dates like {{ today()->toDateString() }} or {{ today()->format('d/m/Y') }}. A date that is already a holiday gets the title from the file.
                        Nothing is imported if any row has an error.
                    </p>

                    <div>
                        <label class="form-label" for="holiday_import_file">
                            File <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="file" id="holiday_import_file" class="form-control" accept=".csv,.xlsx,.xls">
                        <div class="invalid-feedback" id="holidayImportFileError"></div>
                    </div>

                    <div class="alert alert-danger mt-3 mb-0 d-none" id="holidayImportRowErrorsWrap">
                        <div class="fw-semibold mb-2">Import errors</div>
                        <ul class="mb-0 ps-3 small" id="holidayImportRowErrors"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="holidayImportSubmitBtn">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
