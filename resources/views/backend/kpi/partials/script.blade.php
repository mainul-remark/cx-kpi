<script>
    $(function () {
        const kpiUrl = @json(route('kpi.index'));
        const canViewAll = @json($canViewAll);
        const defaultPreset = 'month';
        const perPage = 25;

        const $content = $('#kpiContent');
        const $from = $('#kpi_from');
        const $to = $('#kpi_to');
        const $user = $('#kpi_user');
        const detailModal = bootstrap.Modal.getOrCreateInstance($('#kpiDetailModal')[0]);
        let request = null;
        let lastData = null;
        let page = 1;
        // what each day of a user is called in the details
        const statuses = {
            worked: 'Worked',
            absent: 'Absent',
            leave: 'On leave',
            off: 'Off day',
            not_joined: 'Not joined yet'
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

        function number(value) {
            return Number(value || 0).toLocaleString('en-US');
        }

        function dash(value, suffix) {
            return value === null || value === undefined ? '&mdash;' : number(value) + (suffix || '');
        }

        function percent(value) {
            return value === null || value === undefined ? '&mdash;' : value + '%';
        }

        function formatDate(date) {
            return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function rangeText(range) {
            return range.from === range.to ? formatDate(range.from) : formatDate(range.from) + ' – ' + formatDate(range.to);
        }

        function barClass(score) {
            if (score >= 100) return 'bg-success';
            if (score >= 75) return 'bg-primary';
            if (score >= 50) return 'bg-warning';
            return 'bg-danger';
        }

        function scoreBar(score) {
            if (score === null || score === undefined) {
                return '<span class="text-muted">No target set</span>';
            }
            return '<div class="d-flex align-items-center gap-2">' +
                '<div class="progress kpi-bar"><div class="progress-bar ' + barClass(score) + '" style="width: ' + Math.min(score, 100) + '%"></div></div>' +
                '<span class="fw-semibold">' + score + '</span></div>';
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
            $('.kpi-preset').each(function () {
                const active = $(this).data('preset') === state.preset;
                $(this).toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
            });
            $user.val(state.user_id);
        }

        function tile(label, value, note) {
            return '<div class="col-xl-3 col-6"><div class="card kpi-tile"><div class="card-body">' +
                '<div class="kpi-tile-label text-muted mb-1">' + label + '</div>' +
                '<div class="kpi-tile-value">' + value + '</div>' +
                '<div class="text-muted mt-1" style="font-size: .75rem">' + note + '</div>' +
                '</div></div></div>';
        }

        function renderTiles(data) {
            const summary = data.summary;

            $('#kpiTiles').html(
                tile(canViewAll ? 'Team KPI' : 'My KPI', percent(summary.pct), data.show_targets && summary.target_total > 0
                    ? number(summary.actual_total) + ' of ' + number(summary.target_total) + ' target'
                    : (summary.pct === null ? 'No target set in this period' : number(summary.actual_total) + ' completed against the target')) +
                tile(canViewAll ? 'Average Score' : 'My Score', dash(summary.average_score), canViewAll
                    ? summary.scored + ' of ' + summary.users + ' users with a target'
                    : 'KPI capped at ' + @json(\App\Services\Kpi\EmployeeKpiService::MAX_SCORE)) +
                tile('Absent Days', number(summary.absent), 'working days without a report') +
                tile('Leave Days', number(summary.leave), 'official leave, not counted') +
                (summary.late_days !== undefined
                    ? tile('Late Check-ins', number(summary.late_days), number(summary.incomplete_days) + ' without a check out')
                    : '')
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

        // draws the pager and returns the index of the first row on the page
        function paginate(total) {
            const $pager = $('#kpiPager');
            const lastPage = Math.max(1, Math.ceil(total / perPage));
            page = Math.min(Math.max(1, page), lastPage);
            const start = (page - 1) * perPage;

            $pager.toggleClass('d-none', total <= perPage);
            $pager.find('.kpi-pager-info').text('Showing ' + (start + 1) + ' to ' + Math.min(start + perPage, total) + ' of ' + total + ' users');

            function item(label, target, disabled, active) {
                return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                    (target === null
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link kpi-page" href="javascript:void(0)" data-page="' + target + '"' + (active ? ' aria-current="page"' : '') + '>' + label + '</a>') +
                    '</li>';
            }

            $pager.find('.pagination').html(
                item('Previous', page > 1 ? page - 1 : null, page === 1) +
                pageNumbers(page, lastPage).map(function (number) {
                    return number === null ? item('&hellip;', null, true) : item(number, number, false, number === page);
                }).join('') +
                item('Next', page < lastPage ? page + 1 : null, page === lastPage)
            );

            return start;
        }

        function renderTable(data) {
            const $body = $('#kpiTable tbody');
            const rows = data.rows;

            if (!rows.length) {
                $body.html('<tr><td colspan="' + (canViewAll ? 10 : 9) + '" class="text-center text-muted">No field user found.</td></tr>');
                $('#kpiPager').addClass('d-none');
                return;
            }

            const start = paginate(rows.length);

            $body.html(rows.slice(start, start + perPage).map(function (row, index) {
                const name = canViewAll
                    ? '<a class="kpi-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a>'
                    : escapeHtml(row.name);

                return '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + name + '</td>' +
                    (canViewAll ? '<td class="text-end">' + (row.target_total > 0 ? number(row.target_total) : '&mdash;') + '</td>' : '') +
                    '<td class="text-end">' + number(row.actual_total) + '</td>' +
                    '<td class="text-end">' + percent(row.pct) + '</td>' +
                    '<td>' + scoreBar(row.score) + '</td>' +
                    '<td class="text-end">' + number(row.worked) + '</td>' +
                    '<td class="text-end">' + number(row.absent) + '</td>' +
                    '<td class="text-end">' + number(row.leave) + '</td>' +
                    '<td><button type="button" class="btn btn-outline-primary btn-sm kpi-detail" data-id="' + row.id + '" title="Day by day"><i class="fa fa-eye"></i></button></td>' +
                    '</tr>';
            }).join(''));
        }

        function render(data) {
            lastData = data;

            $('#kpiRangeText').text(rangeText(data.range));
            $from.val(data.range.from);
            $to.val(data.range.to);

            renderTiles(data);
            renderTable(data);
        }

        function renderDetail(data) {
            const user = data.user;
            const showTargets = data.show_targets;

            const activities = user.breakdown.map(function (activity) {
                return '<tr>' +
                    '<td>' + escapeHtml(activity.label) + '</td>' +
                    (showTargets ? '<td class="text-end">' + dash(activity.target) + '</td>' : '') +
                    '<td class="text-end">' + number(activity.actual) + '</td>' +
                    '<td class="text-end">' + percent(activity.pct) + '</td>' +
                    '</tr>';
            }).join('');

            const days = user.days.map(function (day) {
                const status = statuses[day.status] + (day.half_leave ? ' (half-day leave)' : '');

                return '<tr>' +
                    '<td>' + formatDate(day.date) + '</td>' +
                    '<td><span class="kpi-status kpi-status-' + day.status + '">' + status + '</span></td>' +
                    (showTargets ? '<td class="text-end">' + dash(day.target) + '</td>' : '') +
                    '<td class="text-end">' + dash(day.actual) + '</td>' +
                    '<td class="text-end">' + percent(day.pct) + '</td>' +
                    '</tr>';
            }).join('');

            $('#kpiDetailModalLabel').text(user.name);
            $('#kpiDetailRange').text(rangeText(data.range));
            $('#kpiDetailBody').html(
                '<div class="d-flex flex-wrap gap-4 mb-3">' +
                    '<div><div class="text-muted">KPI</div><div class="fs-5 fw-semibold">' + percent(user.pct) + '</div></div>' +
                    '<div><div class="text-muted">Score</div><div class="fs-5 fw-semibold">' + dash(user.score) + '</div></div>' +
                    '<div><div class="text-muted">Days Worked</div><div class="fs-5 fw-semibold">' + number(user.worked) + '</div></div>' +
                    '<div><div class="text-muted">Absent</div><div class="fs-5 fw-semibold">' + number(user.absent) + '</div></div>' +
                    '<div><div class="text-muted">Leave</div><div class="fs-5 fw-semibold">' + number(user.leave) + '</div></div>' +
                    (user.late_days !== undefined
                        ? '<div><div class="text-muted">Checked In</div><div class="fs-5 fw-semibold">' + number(user.checked_in_days) + '</div></div>' +
                          '<div><div class="text-muted">Late</div><div class="fs-5 fw-semibold">' + number(user.late_days) + '</div></div>' +
                          '<div><div class="text-muted">No Check Out</div><div class="fs-5 fw-semibold">' + number(user.incomplete_days) + '</div></div>'
                        : '') +
                '</div>' +
                '<h6>By Activity</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-bordered table-sm align-middle mb-0"><thead><tr>' +
                    '<th>Activity</th>' + (showTargets ? '<th class="text-end">Target</th>' : '') + '<th class="text-end">Completed</th><th class="text-end">KPI</th>' +
                '</tr></thead><tbody>' + activities + '</tbody></table></div>' +
                '<h6>Day by Day</h6>' +
                (days
                    ? '<div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0"><thead><tr>' +
                        '<th>Date</th><th>Day</th>' + (showTargets ? '<th class="text-end">Target</th>' : '') + '<th class="text-end">Completed</th><th class="text-end">KPI</th>' +
                      '</tr></thead><tbody>' + days + '</tbody></table></div>'
                    : '<p class="text-muted mb-0">No day of this period has come yet.</p>')
            );
        }

        function load() {
            if (request) request.abort();

            syncControls();
            $content.addClass('is-loading');
            $('#kpiError, #kpiFilterError').addClass('d-none');

            const data = params();
            // keeps the filters in the address, so the view can be bookmarked or shared
            window.history.replaceState(null, '', kpiUrl + '?' + $.param(data));
            $('#kpiExport').attr('href', kpiUrl + '/export?' + $.param(data));

            request = $.ajax({ url: kpiUrl, method: 'GET', data: data, dataType: 'json' })
                .done(function (response) {
                    page = 1;
                    render(response);
                    $content.removeClass('is-loading');
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;

                    $content.removeClass('is-loading');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        $('#kpiFilterError').text(errors[Object.keys(errors)[0]][0]).removeClass('d-none');
                        return;
                    }
                    $('#kpiError').removeClass('d-none');
                });
        }

        $('.kpi-preset').on('click', function () {
            state.preset = $(this).data('preset');
            load();
        });

        $('#kpiFilterForm').on('submit', function (e) {
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

        $('#kpiFilterReset').on('click', function () {
            state.preset = defaultPreset;
            state.from = state.to = state.user_id = '';
            load();
        });

        $('#kpiRetry').on('click', load);

        $(document).on('click', '.kpi-user-link', function () {
            state.user_id = String($(this).data('id'));
            load();
        });

        $(document).on('click', '.kpi-page', function () {
            page = parseInt($(this).data('page'), 10);
            renderTable(lastData);
        });

        $(document).on('click', '.kpi-detail', function () {
            if (!lastData) return;

            const $button = $(this).prop('disabled', true);

            // the days of the range on screen, whatever the filters were changed to since
            $.ajax({
                url: kpiUrl + '/' + $(this).data('id'),
                method: 'GET',
                data: { preset: 'custom', from: lastData.range.from, to: lastData.range.to },
                dataType: 'json'
            }).done(function (response) {
                renderDetail(response);
                detailModal.show();
            }).fail(function (xhr) {
                $('#kpiFilterError')
                    .text(xhr.status === 403 ? 'You do not have permission to see these details.' : 'The details could not be loaded.')
                    .removeClass('d-none');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });

        const $month = $('#kpi_month');
        let monthlyRequest = null;

        function loadMonthly() {
            const $body = $('#kpiMonthlyTable tbody');
            const columns = canViewAll ? 10 : 9;

            if (!$month.length || !$month.val()) return;
            if (monthlyRequest) monthlyRequest.abort();

            monthlyRequest = $.ajax({ url: kpiUrl + '/monthly', method: 'GET', data: { month: $month.val() }, dataType: 'json' })
                .done(function (response) {
                    if (!response.rows.length) {
                        $body.html('<tr><td colspan="' + columns + '" class="text-center text-muted">No score has been frozen for this month.</td></tr>');
                        return;
                    }

                    $body.html(response.rows.map(function (row, index) {
                        return '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(row.name) + '</td>' +
                            (canViewAll ? '<td class="text-end">' + (row.target_total > 0 ? number(row.target_total) : '&mdash;') + '</td>' : '') +
                            '<td class="text-end">' + number(row.actual_total) + '</td>' +
                            '<td class="text-end">' + percent(row.pct) + '</td>' +
                            '<td>' + scoreBar(row.score) + '</td>' +
                            '<td class="text-end">' + number(row.worked) + '</td>' +
                            '<td class="text-end">' + number(row.absent) + '</td>' +
                            '<td class="text-end">' + number(row.leave) + '</td>' +
                            '<td>' + formatDate(row.generated_at) + '</td>' +
                            '</tr>';
                    }).join(''));
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;
                    $body.html('<tr><td colspan="' + columns + '" class="text-center text-danger">The monthly scores could not be loaded.</td></tr>');
                });
        }

        $month.on('change', loadMonthly);

        load();
        loadMonthly();
    });
</script>
