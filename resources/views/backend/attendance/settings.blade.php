@extends('backend.master')
@section('title', 'Shifts & Offices')

@section('body')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-header-breadcrumb mb-4">
            <div class="my-auto">
                <h4 class="mb-1 text-uppercase" style="font-family: 'Bell MT';font-size: 16px"><i class="mdi mdi-clock-outline me-2"></i>Shifts &amp; Offices</h4>
                <p class="mb-0 text-muted">
                    A check in is late when it is after the shift start plus its grace. Users without a shift follow the default of
                    {{ $defaultShift['start_time'] }} with {{ $defaultShift['grace_minutes'] }} minutes of grace.
                    {{ $requireOffice ? 'Check ins are only accepted from an office location.' : 'The place of a check in is recorded, but any place is accepted.' }}
                </p>
            </div>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Shifts &amp; Offices</li>
                </ol>
            </nav>
        </div>

        <div class="alert alert-danger d-none" id="settingsError" role="alert"></div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0"><i class="mdi mdi-timer-outline me-1"></i> Shifts</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addShift">Add Shift</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead><tr><th>Name</th><th>Starts</th><th>Grace</th><th>Users</th><th></th></tr></thead>
                        <tbody>
                            @forelse($shifts as $shift)
                                <tr>
                                    <td>{{ $shift->name }}</td>
                                    <td>{{ $shift->start_time }}</td>
                                    <td>{{ $shift->grace_minutes }} min</td>
                                    <td>{{ $shift->users_count }}</td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shift-assign" data-id="{{ $shift->id }}" data-name="{{ $shift->name }}">Users</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary shift-edit" data-shift="{{ json_encode($shift->only(['id', 'name', 'start_time', 'grace_minutes'])) }}">Edit</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger shift-delete" data-id="{{ $shift->id }}" data-name="{{ $shift->name }}">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No shifts yet. Everyone follows the default shift.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0"><i class="mdi mdi-office-building-marker-outline me-1"></i> Office Locations</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addOffice">Add Office</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead><tr><th>Name</th><th>Point</th><th>Radius</th><th>IP addresses</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse($offices as $office)
                                <tr>
                                    <td>{{ $office->name }}</td>
                                    <td>{{ $office->lat !== null ? $office->lat.', '.$office->lng : '–' }}</td>
                                    <td>{{ $office->radius_m }} m</td>
                                    <td>{{ $office->allowed_ips ?: '–' }}</td>
                                    <td><span class="badge {{ $office->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $office->is_active ? 'Active' : 'Off' }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary office-edit" data-office="{{ json_encode($office->only(['id', 'name', 'lat', 'lng', 'radius_m', 'allowed_ips', 'is_active'])) }}">Edit</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger office-delete" data-id="{{ $office->id }}" data-name="{{ $office->name }}">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No office locations yet. Every check in is recorded as in the field.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="shiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="shiftForm" autocomplete="off">
                <div class="modal-header">
                    <h6 class="modal-title" id="shiftModalTitle">Shift</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="shift_name">Name</label><input class="form-control" id="shift_name" maxlength="100" required></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label" for="shift_start">Starts at</label><input type="time" class="form-control" id="shift_start" required></div>
                        <div class="col-6 mb-3"><label class="form-label" for="shift_grace">Grace (minutes)</label><input type="number" class="form-control" id="shift_grace" min="0" max="240" value="15" required></div>
                    </div>
                    <div class="text-danger d-none form-error" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <form class="modal-content" id="assignForm">
                <div class="modal-header">
                    <h6 class="modal-title" id="assignModalTitle">Users</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">A user can be on one shift only, so ticking a user here moves them from their current shift.</p>
                    @foreach($users as $user)
                        <div class="form-check">
                            <input class="form-check-input assign-user" type="checkbox" value="{{ $user->id }}" id="assign_user_{{ $user->id }}" data-shift="{{ $user->work_shift_id }}">
                            <label class="form-check-label" for="assign_user_{{ $user->id }}">{{ $user->name }}</label>
                        </div>
                    @endforeach
                    <div class="text-danger d-none form-error mt-2" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="officeForm" autocomplete="off">
                <div class="modal-header">
                    <h6 class="modal-title" id="officeModalTitle">Office Location</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="office_name">Name</label><input class="form-control" id="office_name" maxlength="100" required></div>
                    <div class="row">
                        <div class="col-4 mb-3"><label class="form-label" for="office_lat">Latitude</label><input type="number" step="any" class="form-control" id="office_lat"></div>
                        <div class="col-4 mb-3"><label class="form-label" for="office_lng">Longitude</label><input type="number" step="any" class="form-control" id="office_lng"></div>
                        <div class="col-4 mb-3"><label class="form-label" for="office_radius">Radius (m)</label><input type="number" class="form-control" id="office_radius" min="10" max="50000" value="200" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="office_ips">IP addresses</label>
                        <input class="form-control" id="office_ips" placeholder="203.0.113.10, 198.51.100.0/24">
                        <div class="form-text">Comma separated IPs or ranges. Give a point, IP addresses, or both.</div>
                    </div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="office_active" checked><label class="form-check-label" for="office_active">Active</label></div>
                    <div class="text-danger d-none form-error mt-2" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            const base = @json(url('attendance-settings'));
            let shiftId = null, officeId = null, assignId = null;

            function open(id) { bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show(); }

            function send(method, url, data, $form) {
                if ($form) { $form.find('.form-error').addClass('d-none'); $form.find('[type=submit]').prop('disabled', true); }
                $.ajax({ url: url, method: method, data: data, dataType: 'json' })
                    .done(function () { window.location.reload(); })
                    .fail(function (xhr) {
                        const errors = xhr.responseJSON && xhr.responseJSON.errors;
                        const text = errors ? errors[Object.keys(errors)[0]][0] : 'That could not be saved. Please try again.';
                        if ($form) { $form.find('.form-error').text(text).removeClass('d-none'); } else { $('#settingsError').text(text).removeClass('d-none'); }
                    })
                    .always(function () { if ($form) { $form.find('[type=submit]').prop('disabled', false); } });
            }

            $('#addShift').on('click', function () {
                shiftId = null;
                $('#shiftModalTitle').text('Add Shift');
                $('#shift_name').val(''); $('#shift_start').val('10:00'); $('#shift_grace').val(15);
                $('#shiftForm .form-error').addClass('d-none');
                open('shiftModal');
            });
            $('.shift-edit').on('click', function () {
                const shift = $(this).data('shift');
                shiftId = shift.id;
                $('#shiftModalTitle').text('Edit Shift');
                $('#shift_name').val(shift.name); $('#shift_start').val(shift.start_time); $('#shift_grace').val(shift.grace_minutes);
                $('#shiftForm .form-error').addClass('d-none');
                open('shiftModal');
            });
            $('#shiftForm').on('submit', function (e) {
                e.preventDefault();
                send(shiftId ? 'PUT' : 'POST', base + '/shifts' + (shiftId ? '/' + shiftId : ''),
                    { name: $('#shift_name').val(), start_time: $('#shift_start').val(), grace_minutes: $('#shift_grace').val() }, $(this));
            });
            $('.shift-delete').on('click', function () {
                if (confirm('Delete the shift "' + $(this).data('name') + '"? Its users fall back to the default shift.')) {
                    send('DELETE', base + '/shifts/' + $(this).data('id'));
                }
            });

            $('.shift-assign').on('click', function () {
                assignId = $(this).data('id');
                $('#assignModalTitle').text('Users on ' + $(this).data('name'));
                $('.assign-user').each(function () { $(this).prop('checked', String($(this).data('shift')) === String(assignId)); });
                $('#assignForm .form-error').addClass('d-none');
                open('assignModal');
            });
            $('#assignForm').on('submit', function (e) {
                e.preventDefault();
                const ids = $('.assign-user:checked').map(function () { return this.value; }).get();
                send('POST', base + '/shifts/' + assignId + '/users', { user_ids: ids }, $(this));
            });

            function fillOffice(office) {
                officeId = office ? office.id : null;
                $('#officeModalTitle').text(office ? 'Edit Office Location' : 'Add Office Location');
                $('#office_name').val(office ? office.name : '');
                $('#office_lat').val(office && office.lat !== null ? office.lat : '');
                $('#office_lng').val(office && office.lng !== null ? office.lng : '');
                $('#office_radius').val(office ? office.radius_m : 200);
                $('#office_ips').val(office && office.allowed_ips ? office.allowed_ips : '');
                $('#office_active').prop('checked', office ? !!office.is_active : true);
                $('#officeForm .form-error').addClass('d-none');
                open('officeModal');
            }
            $('#addOffice').on('click', function () { fillOffice(null); });
            $('.office-edit').on('click', function () { fillOffice($(this).data('office')); });
            $('#officeForm').on('submit', function (e) {
                e.preventDefault();
                send(officeId ? 'PUT' : 'POST', base + '/offices' + (officeId ? '/' + officeId : ''), {
                    name: $('#office_name').val(), lat: $('#office_lat').val(), lng: $('#office_lng').val(),
                    radius_m: $('#office_radius').val(), allowed_ips: $('#office_ips').val(),
                    is_active: $('#office_active').is(':checked') ? 1 : 0
                }, $(this));
            });
            $('.office-delete').on('click', function () {
                if (confirm('Delete the office "' + $(this).data('name') + '"?')) {
                    send('DELETE', base + '/offices/' + $(this).data('id'));
                }
            });
        });
    </script>
@endpush
