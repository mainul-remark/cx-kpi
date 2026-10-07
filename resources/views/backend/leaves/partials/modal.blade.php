<div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="leaveForm" novalidate autocomplete="off">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="leaveModalLabel">Set Leave</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- a new leave covers users over a date range --}}
                    <div data-leave-mode="create">
                        <div class="mb-3">
                            <label class="form-label" for="leave_user_ids">
                                Field Users <span class="text-danger">*</span>
                            </label>
                            <select name="user_ids[]" id="leave_user_ids" class="form-select select-ele" multiple data-placeholder="Select field users" style="width: 100%">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" data-error-for="user_ids"></div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllLeaveUsersBtn">Select All</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-1" id="clearLeaveUsersBtn">Clear</button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="leave_from">
                                    From <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="from" id="leave_from" class="form-control">
                                <div class="invalid-feedback" data-error-for="from"></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label" for="leave_to">
                                    To <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="to" id="leave_to" class="form-control">
                                <div class="invalid-feedback" data-error-for="to"></div>
                            </div>
                        </div>
                        <p class="text-muted">
                            The leave is set for each selected user on every day of the range. Fridays and holidays are skipped,
                            and a leave already set for a day is replaced.
                        </p>
                    </div>

                    {{-- an existing leave is a single day of one user --}}
                    <div class="row" data-leave-mode="edit">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="leave_user_name">User</label>
                            <input type="text" id="leave_user_name" class="form-control" readonly>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="leave_date">
                                Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="leave_date" id="leave_date" class="form-control">
                            <div class="invalid-feedback" data-error-for="leave_date"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="leave_portion">
                                Duration <span class="text-danger">*</span>
                            </label>
                            <select name="portion" id="leave_portion" class="form-select">
                                @foreach($portions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" data-error-for="portion"></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="leave_type">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select name="type" id="leave_type" class="form-select">
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" data-error-for="type"></div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="leave_note">Note</label>
                        <textarea name="note" id="leave_note" class="form-control" rows="2" maxlength="5000" placeholder="Optional"></textarea>
                        <div class="invalid-feedback" data-error-for="note"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="leaveSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
