<script>
    $(function () {
        const canViewAll = @json($canViewAll);
        const attendanceUrl = @json(route('attendance.index'));
        const adjustUrl = @json(route('attendance.sessions.adjust', ['session' => '__ID__']));
        const statuses = {
            open: { label: 'Checked in', badge: 'bg-info' },
            completed: { label: 'Completed', badge: 'bg-success' },
            incomplete: { label: 'Did not check out', badge: 'bg-warning' },
            adjusted: { label: 'Corrected', badge: 'bg-primary' }
        };
        let rows = [];
        let editing = null;
        let logRequest = null;
        // the range the log on screen was loaded for, and the page of it
        let logRange = null;
        let logPage = 1;

        function esc(value) {
            return $('<div>').text(value === null || value === undefined ? '' : value).html();
        }

        function formatDate(date) {
            return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function hours(minutes) {
            if (minutes === null) return '–';
            return Math.floor(minutes / 60) + 'h ' + String(minutes % 60).padStart(2, '0') + 'm';
        }

        function place(label, location) {
            if (!location) return null;
            return label + ': <a href="https://www.google.com/maps?q=' + location.lat + ',' + location.lng + '" target="_blank" rel="noopener">map</a>';
        }

        function renderRows(checkIns) {
            rows = checkIns.rows;
            const columns = canViewAll ? 8 : 5;
            const $body = $('#checkInsTable tbody').empty();

            if (!rows.length) {
                $body.append('<tr><td colspan="' + columns + '" class="text-center text-muted">No check-ins in this period.</td></tr>');
            }

            rows.forEach(function (row, index) {
                const status = statuses[row.status];
                let html = '<tr><td>' + formatDate(row.work_date) + '</td>';
                if (canViewAll) html += '<td>' + esc(row.name) + '</td>';
                html += '<td>' + esc(row.checked_in) + '</td>' +
                    '<td>' + (row.checked_out ? esc(row.checked_out) : '–') + '</td>' +
                    '<td>' + hours(row.minutes) + (row.sessions > 1 ? ' <span class="badge bg-light text-muted border" title="Check-ins of the day">' + row.sessions + ' sessions</span>' : '') + '</td>' +
                    '<td><span class="badge ' + status.badge + '"' + (row.note ? ' title="' + esc(row.note) + '"' : '') + '>' + status.label + '</span></td>';
                if (canViewAll) {
                    const places = [place('in', row.in_location), place('out', row.out_location)].filter(Boolean);
                    html += '<td>' + (places.length ? places.join(' · ') : '<span class="text-muted">unavailable</span>') + '</td>' +
                        '<td>' + (row.status === 'incomplete'
                            ? '<button type="button" class="btn btn-sm btn-outline-primary check-in-adjust" data-index="' + index + '">Correct</button>'
                            : '') + '</td>';
                }
                $body.append(html + '</tr>');
            });

            logPage = checkIns.meta.page;
            window.renderAttendancePager($('#checkInsPager'), checkIns.meta, 'check-ins', 'check-ins-page');
        }

        // a new sheet: the first page of the log, and who is checked in now
        window.renderCheckIns = function (data) {
            logRange = data.range;

            if (canViewAll) {
                const now = data.currently_in || [];
                $('#checkInsNow').html(now.length
                    ? '<span class="fw-medium me-2">Checked in now (' + now.length + '):</span>' + now.map(function (person) {
                        return '<span class="badge bg-success-transparent me-1" title="since ' + esc(person.since) + '">' + esc(person.name) + '</span>';
                    }).join('')
                    : '<span class="text-muted">Nobody is checked in right now.</span>');
            }

            renderRows(data.check_ins);
        };

        // one page of the log from the server, for the range on screen and the user asked for
        function loadLogPage(page) {
            if (!logRange) return;
            if (logRequest) logRequest.abort();

            const data = $.extend({}, window.attendanceParams(), { section: 'check_ins', page: page, preset: 'custom', from: logRange.from, to: logRange.to });
            const $card = $('#checkInsTable').closest('.card-body').addClass('is-loading');

            logRequest = $.ajax({ url: attendanceUrl, method: 'GET', data: data, dataType: 'json' })
                .done(function (response) {
                    renderRows(response.check_ins);
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;
                    $('#attendanceFilterError').text('That page of the check-in log could not be loaded. Please try again.').removeClass('d-none');
                })
                .always(function () {
                    $card.removeClass('is-loading');
                });
        }

        $(document).on('click', '.check-ins-page', function () {
            loadLogPage(parseInt($(this).data('page'), 10));
        });

        if (!canViewAll) return;

        $(document).on('click', '.check-in-adjust', function () {
            editing = rows[$(this).data('index')];
            $('#adjustSummary').text(editing.name + ' · ' + formatDate(editing.work_date) + ' · checked in ' + editing.checked_in_input.slice(11));
            const day = editing.checked_in_input.slice(0, 10);
            $('#adjust_checked_in_at').val(editing.checked_in_input).attr({ min: day + 'T00:00', max: day + 'T23:59' });
            $('#adjust_checked_out_at').val(editing.checked_out_input || (day + 'T18:00')).attr('min', editing.checked_in_input);
            $('#adjust_note').val('');
            $('#adjustError').addClass('d-none');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustModal')).show();
        });

        $('#adjustForm').on('submit', function (e) {
            e.preventDefault();
            $('#adjustSave').prop('disabled', true);
            $('#adjustError').addClass('d-none');

            $.ajax({
                url: adjustUrl.replace('__ID__', editing.id),
                method: 'POST',
                dataType: 'json',
                data: { checked_in_at: $('#adjust_checked_in_at').val(), checked_out_at: $('#adjust_checked_out_at').val(), note: $('#adjust_note').val() }
            }).done(function () {
                bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
                // stays on the page of the log the correction was made on
                loadLogPage(logPage);
            }).fail(function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                $('#adjustError').text(errors ? errors[Object.keys(errors)[0]][0] : 'The times could not be saved. Please try again.').removeClass('d-none');
            }).always(function () {
                $('#adjustSave').prop('disabled', false);
            });
        });
    });
</script>
