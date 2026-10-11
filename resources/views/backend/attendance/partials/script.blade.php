<script>
    $(function () {
        const attendanceUrl = @json(route('attendance.index'));
        const canViewAll = @json($canViewAll);
        const defaultPreset = 'month';

        const $content = $('#attendanceContent');
        const $from = $('#attendance_from');
        const $to = $('#attendance_to');
        const $user = $('#attendance_user');
        let request = null;
        let lastData = null;
        let sheetRequest = null;
        // what each attendance mark shows in its cell and means in words
        const marks = {
            P: { text: 'P', label: 'Present' },
            A: { text: 'A', label: 'Absent' },
            L: { text: 'L', label: 'On leave' },
            O: { text: 'O', label: 'Off day' },
            H: { text: 'H', label: 'Holiday' },
            F: { text: '', label: 'Not due yet' },
            N: { text: '', label: 'Not joined yet' }
        };

        const query = new URLSearchParams(window.location.search);
        const state = {
            preset: query.get('preset') || (query.get('from') && query.get('to') ? 'custom' : defaultPreset),
            from: query.get('from') || '',
            to: query.get('to') || '',
            user_id: canViewAll ? (query.get('user_id') || '') : ''
        };

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : value).html();
        }

        function formatDate(date) {
            return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function dayParts(date) {
            const day = new Date(date + 'T00:00:00');
            return {
                number: date.slice(8),
                weekday: day.toLocaleDateString('en-GB', { weekday: 'short' }),
                month: day.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }),
                short: day.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
            };
        }

        function params() {
            const data = { preset: state.preset };
            if (state.preset === 'custom') {
                data.from = state.from;
                data.to = state.to;
            }
            if (state.user_id) {
                data.user_id = state.user_id;
            }
            return data;
        }

        function syncControls() {
            $('.attendance-preset').each(function () {
                const active = $(this).data('preset') === state.preset;
                $(this).toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
            });
            $user.val(state.user_id);
        }

        function tile(label, value, note) {
            return '<div class="col-xl-3 col-6"><div class="card att-kpi"><div class="card-body">' +
                '<div class="att-kpi-label text-muted mb-1">' + label + '</div>' +
                '<div class="att-kpi-value">' + value + '</div>' +
                '<div class="text-muted mt-1" style="font-size: .75rem">' + note + '</div>' +
                '</div></div></div>';
        }

        function renderKpis(data) {
            const summary = data.attendance.summary;
            const present = summary.present, absent = summary.absent, leave = summary.leave;
            const due = present + absent;

            $('#attendanceKpis').html(
                tile('Field Users', summary.users, 'on this sheet') +
                tile('Present', present, 'days with a report') +
                tile('Absent', absent, 'working days without one') +
                tile('Attendance', due > 0 ? (Math.round(present / due * 1000) / 10) + '%' : '&mdash;', (due > 0 ? 'of ' + due + ' days due' : 'No working day in this period yet') + (leave > 0 ? ', ' + leave + ' on leave' : ''))
            );
        }

        // the page numbers to offer: first, last and those around the current one, with null for a gap
        function pageNumbers(current, last) {
            const pages = [];
            for (let number = 1; number <= last; number++) {
                if (number === 1 || number === last || Math.abs(number - current) <= 1) {
                    pages.push(number);
                } else if (pages[pages.length - 1] !== null) {
                    pages.push(null);
                }
            }
            return pages;
        }

        // draws the pager of a section from the meta the server sent; shared with the check-in log
        window.renderAttendancePager = function ($pager, meta, unit, linkClass) {
            $pager.toggleClass('d-none', meta.last_page <= 1);
            $pager.find('.att-pager-info').text('Showing ' + meta.from + ' to ' + meta.to + ' of ' + meta.total + ' ' + unit);

            function item(label, target, disabled, active) {
                return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                    (target === null
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link ' + linkClass + '" href="javascript:void(0)" data-page="' + target + '"' + (active ? ' aria-current="page"' : '') + '>' + label + '</a>') +
                    '</li>';
            }

            $pager.find('.pagination').html(
                item('Previous', meta.page > 1 ? meta.page - 1 : null, meta.page === 1) +
                pageNumbers(meta.page, meta.last_page).map(function (number) {
                    return number === null ? item('&hellip;', null, true) : item(number, number, false, number === meta.page);
                }).join('') +
                item('Next', meta.page < meta.last_page ? meta.page + 1 : null, meta.page === meta.last_page)
            );
        };

        // the filters in force, for the sections that load their own pages
        window.attendanceParams = params;

        function renderTable(data) {
            const $table = $('#attendanceTable');
            const days = data.attendance.days;
            const rows = data.attendance.rows;
            const parts = days.map(function (day) { return dayParts(day.date); });

            // one heading cell per month, spanning its days
            let months = '';
            for (let i = 0; i < parts.length;) {
                let span = 1;
                while (i + span < parts.length && parts[i + span].month === parts[i].month) span++;
                months += '<th colspan="' + span + '" class="text-center">' + parts[i].month + '</th>';
                i += span;
            }

            $table.find('thead').html(
                '<tr><th rowspan="2" class="att-name">User</th><th rowspan="2" class="text-end">Present</th><th rowspan="2" class="text-end">Absent</th><th rowspan="2" class="text-end">Leave</th><th rowspan="2" class="text-end">Attendance</th>' + months + '</tr>' +
                '<tr>' + parts.map(function (part, index) {
                    return '<th class="att-day" title="' + escapeHtml(days[index].holiday || '') + '">' + part.number + '<small>' + part.weekday + '</small></th>';
                }).join('') + '</tr>'
            );

            if (!rows.length) {
                $table.find('tbody').html('<tr><td colspan="' + (days.length + 5) + '" class="text-center text-muted">No field user found.</td></tr>');
                $('#attendancePager').addClass('d-none');
                return;
            }

            window.renderAttendancePager($('#attendancePager'), data.attendance.meta, 'users', 'att-page');

            $table.find('tbody').html(rows.map(function (row) {
                let cells = '';
                for (let i = 0; i < days.length; i++) {
                    const mark = row.marks.charAt(i);
                    const title = marks[mark].label + (mark === 'H' && days[i].holiday ? ': ' + days[i].holiday : '') + ' · ' + parts[i].short;
                    cells += '<td class="att-cell att-' + mark + '" title="' + escapeHtml(title) + '">' + marks[mark].text + '</td>';
                }

                const name = canViewAll
                    ? '<a class="att-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a>'
                    : escapeHtml(row.name);

                return '<tr>' +
                    '<td class="att-name">' + name + '</td>' +
                    '<td class="text-end">' + row.present + '</td>' +
                    '<td class="text-end">' + row.absent + '</td>' +
                    '<td class="text-end">' + row.leave + '</td>' +
                    '<td class="text-end">' + (row.pct === null ? '&mdash;' : row.pct + '%') + '</td>' +
                    cells +
                    '</tr>';
            }).join(''));
        }

        function render(data) {
            lastData = data;
            window.renderCheckIns(data);

            $('#attendanceRangeText').text(data.range.from === data.range.to
                ? formatDate(data.range.from)
                : formatDate(data.range.from) + ' – ' + formatDate(data.range.to));
            $from.val(data.range.from);
            $to.val(data.range.to);

            renderKpis(data);
            renderTable(data);
        }

        // the file is built in the browser, from every row of the sheet and not only those of the page on screen
        function exportCsv() {
            if (!lastData) return;

            const $button = $('#attendanceExport').prop('disabled', true);

            $.ajax({ url: attendanceUrl, method: 'GET', data: $.extend({}, params(), { export: 1 }), dataType: 'json' })
                .done(function (response) {
                    downloadCsv(response.attendance, response.range);
                })
                .fail(function () {
                    $('#attendanceFilterError').text('The export could not be prepared. Please try again.').removeClass('d-none');
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        }

        function downloadCsv(attendance, range) {
            const days = attendance.days;
            const rows = [['User', 'Present', 'Absent', 'Leave', 'Attendance %'].concat(days.map(function (day) { return day.date; }))];

            attendance.rows.forEach(function (row) {
                rows.push([row.name, row.present, row.absent, row.leave, row.pct].concat(row.marks.split('').map(function (mark) {
                    return marks[mark].label;
                })));
            });

            const csv = rows.map(function (row) {
                return row.map(function (value) {
                    let text = value === null || value === undefined ? '' : String(value);
                    // a spreadsheet would run a cell starting with one of these as a formula
                    if (/^[=+\-@]/.test(text) && isNaN(Number(text))) text = "'" + text;
                    return '"' + text.replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\r\n');

            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = 'attendance-sheet_' + range.from + '_to_' + range.to + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }

        function load() {
            if (request) request.abort();

            syncControls();
            $content.addClass('is-loading');
            $('#attendanceError, #attendanceFilterError').addClass('d-none');

            const data = params();
            // keeps the filters in the address, so the view can be bookmarked or shared
            window.history.replaceState(null, '', attendanceUrl + '?' + $.param(data));

            // a new sheet starts on its first page, and so does the check-in log
            if (sheetRequest) sheetRequest.abort();

            request = $.ajax({ url: attendanceUrl, method: 'GET', data: data, dataType: 'json' })
                .done(function (response) {
                    render(response);
                    $content.removeClass('is-loading');
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;

                    $content.removeClass('is-loading');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        $('#attendanceFilterError').text(errors[Object.keys(errors)[0]][0]).removeClass('d-none');
                        return;
                    }
                    $('#attendanceError').removeClass('d-none');
                });
        }

        $('.attendance-preset').on('click', function () {
            state.preset = $(this).data('preset');
            load();
        });

        $('#attendanceFilterForm').on('submit', function (e) {
            e.preventDefault();

            // dates typed in by hand make it a custom range, otherwise the chosen period stays
            if (!lastData || $from.val() !== lastData.range.from || $to.val() !== lastData.range.to) {
                state.preset = 'custom';
            }
            state.from = $from.val();
            state.to = $to.val();
            state.user_id = $user.val() || '';
            load();
        });

        $user.on('change', function () {
            state.user_id = $(this).val() || '';
            load();
        });

        $('#attendanceFilterReset').on('click', function () {
            state.preset = defaultPreset;
            state.from = state.to = state.user_id = '';
            load();
        });

        $('#attendanceRetry').on('click', load);

        $(document).on('click', '.att-user-link', function () {
            state.user_id = String($(this).data('id'));
            load();
        });

        // turning a page asks the server for that page of the sheet only, the tiles and the log stay as they are
        $(document).on('click', '.att-page', function () {
            if (!lastData) return;

            if (sheetRequest) sheetRequest.abort();
            $content.addClass('is-loading');

            const data = $.extend({}, params(), { section: 'sheet', page: parseInt($(this).data('page'), 10), summary: 0 });

            // the range the sheet on screen was loaded for, whatever the filters were changed to since
            data.preset = 'custom';
            data.from = lastData.range.from;
            data.to = lastData.range.to;

            sheetRequest = $.ajax({ url: attendanceUrl, method: 'GET', data: data, dataType: 'json' })
                .done(function (response) {
                    lastData.attendance.days = response.attendance.days;
                    lastData.attendance.rows = response.attendance.rows;
                    lastData.attendance.meta = response.attendance.meta;
                    renderTable(lastData);
                    $content.removeClass('is-loading');
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;

                    $content.removeClass('is-loading');
                    $('#attendanceFilterError').text('That page could not be loaded. Please try again.').removeClass('d-none');
                });
        });

        $('#attendanceExport').on('click', exportCsv);

        load();
    });
</script>
