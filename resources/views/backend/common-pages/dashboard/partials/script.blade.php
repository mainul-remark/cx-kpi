<script>
    $(function () {
        const dashboardUrl = @json(route('admin.dashboard'));
        const links = { attendance: @json(route('attendance.index')), leaves: @json(route('leaves.index')) };
        const canViewAll = @json($canViewAll);
        const defaultPreset = 'month';
        const metrics = @json(\App\Services\Dashboard\ReportDashboardService::METRICS);
        const scoreBands = @json(config('dashboard.score_bands'));

        // one colour per activity and one each for actual and target, the same wherever they appear
        const palette = {
            light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'],
            dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181']
        };
        // the meaning of a colour never changes: good is green, bad is red
        const status = {
            light: { good: '#1baf7a', fair: '#2a78d6', warn: '#eda100', bad: '#e34948', none: '#9aa0a6' },
            dark: { good: '#199e70', fair: '#3987e5', warn: '#c98500', bad: '#d55181', none: '#7b8087' }
        };

        const $from = $('#dashboard_from');
        const $to = $('#dashboard_to');
        const $user = $('#dashboard_user');
        const charts = {};
        const requests = {};
        // what each section last returned, so a change of theme can redraw without asking again
        const data = { overview: null, people: null, attendance: null, live: null };
        let refreshNext = false;

        const query = new URLSearchParams(window.location.search);
        const state = {
            preset: query.get('preset') || (query.get('from') && query.get('to') ? 'custom' : defaultPreset),
            from: query.get('from') || '',
            to: query.get('to') || '',
            user_id: canViewAll ? (query.get('user_id') || '') : ''
        };
        // the table of users is read page by page from the server
        const table = { page: 1, per_page: 10, sort: 'score', dir: 'desc', search: '' };

        function themeMode() {
            return document.documentElement.getAttribute('data-theme-mode') === 'dark' ? 'dark' : 'light';
        }

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : value).html();
        }

        function number(value) {
            return Number(value || 0).toLocaleString('en-US');
        }

        function formatDate(date) {
            return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function shortDate(date) {
            return new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
        }

        function rangeText(from, to) {
            return from === to ? formatDate(from) : formatDate(from) + ' – ' + formatDate(to);
        }

        function params() {
            const out = { preset: state.preset };
            if (state.preset === 'custom') {
                out.from = state.from;
                out.to = state.to;
            }
            if (state.user_id) {
                out.user_id = state.user_id;
            }
            return out;
        }

        function syncControls() {
            $('.dashboard-preset').each(function () {
                const active = $(this).data('preset') === state.preset;
                $(this).toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
            });
            $user.val(state.user_id);
        }

        function progress(pct) {
            const width = Math.max(0, Math.min(100, pct || 0));
            const tone = pct >= 100 ? 'bg-success' : (pct >= 70 ? 'bg-primary' : 'bg-warning');
            return '<div class="progress" role="progressbar" aria-valuenow="' + width + '" aria-valuemin="0" aria-valuemax="100">' +
                '<div class="progress-bar ' + tone + '" style="width: ' + width + '%"></div></div>';
        }

        function changeLine(kpi) {
            if (kpi.change_pct === null) {
                return '<span class="text-muted">' + (kpi.actual > 0 ? 'Nothing in the previous period' : 'No change') + '</span>';
            }
            if (kpi.change_pct === 0) {
                return '<span class="text-muted"><i class="mdi mdi-minus me-1"></i>Same as the previous period</span>';
            }
            const up = kpi.change_pct > 0;
            return '<span class="' + (up ? 'text-success' : 'text-danger') + '"><i class="mdi ' + (up ? 'mdi-arrow-up' : 'mdi-arrow-down') + ' me-1"></i>' +
                Math.abs(kpi.change_pct) + '% ' + (up ? 'up' : 'down') + '</span> <span class="text-muted">vs previous period</span>';
        }

        function tile(label, value, lines, href) {
            const body = '<div class="card db-kpi' + (href ? ' db-kpi-link' : '') + '"><div class="card-body">' +
                '<div class="db-kpi-label text-muted mb-1">' + escapeHtml(label) + '</div>' +
                '<div class="db-kpi-value mb-2">' + value + '</div>' +
                lines.map(function (line) { return '<div class="db-kpi-meta mt-1">' + line + '</div>'; }).join('') +
                '</div></div>';

            return '<div class="col-xxl-3 col-md-6 col-12">' +
                (href ? '<a href="' + href + '" class="text-reset text-decoration-none d-block">' + body + '</a>' : body) + '</div>';
        }

        const pending = '<span class="db-skeleton"></span>';

        function scoreTone(score) {
            if (score === null || score === undefined) return 'none';
            if (score >= (scoreBands.excellent || 90)) return 'good';
            if (score >= (scoreBands.good || 70)) return 'fair';
            if (score >= (scoreBands.fair || 50)) return 'warn';
            return 'bad';
        }

        function scoreBadge(score) {
            if (score === null || score === undefined) return '<span class="text-muted">&mdash;</span>';
            const tone = { good: 'success', fair: 'primary', warn: 'warning', bad: 'danger' }[scoreTone(score)];
            return '<span class="badge text-bg-' + tone + '">' + score + '</span>';
        }

        function renderLive() {
            const $live = $('#dashboardLive');
            const live = data.live && data.live.live;
            if (!$live.length || !live) return;

            const inside = live.checked_in_users.slice(0, 3).map(function (row) {
                return escapeHtml(row.name) + ' <span class="text-muted">since ' + escapeHtml(row.since) + '</span>';
            });
            const tiles = [
                tile('Checked In Now', number(live.checked_in) + '<small class="text-muted fs-6"> / ' + number(live.field_users) + '</small>',
                    inside.length ? inside : ['<span class="text-muted">Nobody is checked in</span>'], links.attendance),
                tile('Reports Today', number(live.reported) + (live.working_day ? '<small class="text-muted fs-6"> / ' + number(live.due) + ' due</small>' : ''),
                    live.working_day
                        ? [progress(live.due > 0 ? Math.round(live.reported / live.due * 100) : 0)]
                        : ['<span class="text-muted">Today is an off day</span>']),
                tile('On Leave Today', number(live.on_leave),
                    [live.pending_leave_days > 0
                        ? '<span class="text-warning"><i class="mdi mdi-clock-outline me-1"></i>' + number(live.pending_leave_days) + ' leave day' + (live.pending_leave_days === 1 ? '' : 's') + ' awaiting approval</span>'
                        : '<span class="text-muted">No leave awaiting approval</span>'], links.leaves),
                tile('Forgotten Check-outs', number(live.forgotten_checkouts),
                    ['<span class="text-muted">' + (live.forgotten_checkouts > 0 ? 'Closed by the system in the last 7 days' : 'None in the last 7 days') + '</span>'], links.attendance)
            ];

            $live.html(tiles.join(''));
        }

        function renderKpis() {
            const overview = data.overview;
            if (!overview) return;

            const tiles = overview.kpis.map(function (kpi) {
                const lines = [changeLine(kpi)];

                if (overview.show_targets) {
                    lines.push(kpi.target === null
                        ? '<span class="text-muted">No target set</span>'
                        : progress(kpi.achievement_pct) + '<div class="mt-1 text-muted">' + kpi.achievement_pct + '% of ' + number(kpi.target) + ' target</div>');
                }
                return tile(kpi.label, number(kpi.actual), lines);
            });

            if (overview.solved_issues) {
                tiles.push(tile(overview.solved_issues.label, number(overview.solved_issues.actual), [changeLine(overview.solved_issues)]));
            }

            const reports = overview.submissions;
            tiles.push(tile('Reports Submitted', number(reports.submitted), reports.pct === null
                ? ['<span class="text-muted">No working day in this period yet</span>']
                : [progress(reports.pct) + '<div class="mt-1 text-muted">' + reports.pct + '% of ' + number(reports.expected) + ' due</div>']));

            // the score and the attendance come from their own sections, and fill in when those arrive
            const insights = data.people && data.people.insights;
            if (!insights) {
                tiles.push(tile(canViewAll ? 'Average KPI Score' : 'My KPI Score', pending, ['&nbsp;']));
            } else if (insights.average_score === null) {
                tiles.push(tile(canViewAll ? 'Average KPI Score' : 'My KPI Score', '&mdash;', ['<span class="text-muted">No target set in this period</span>']));
            } else {
                tiles.push(tile(canViewAll ? 'Average KPI Score' : 'My KPI Score', insights.average_score + '<small class="text-muted fs-6"> / 100</small>',
                    [progress(insights.average_score) + (canViewAll ? '<div class="mt-1 text-muted">' + number(insights.scored) + ' of ' + number(insights.users) + ' users scored</div>' : '')]));
            }

            const attendance = data.attendance && data.attendance.summary;
            if (!attendance) {
                tiles.push(tile('Attendance Rate', pending, ['&nbsp;']));
                tiles.push(tile('Check-in Days', pending, ['&nbsp;']));
            } else {
                tiles.push(tile('Attendance Rate', attendance.pct === null ? '&mdash;' : attendance.pct + '%',
                    [attendance.pct === null ? '<span class="text-muted">No working day in this period yet</span>' : progress(attendance.pct),
                        '<span class="text-muted">' + number(attendance.present) + ' present · ' + number(attendance.absent) + ' absent · ' + number(attendance.leave) + ' on leave</span>'],
                    links.attendance));
                tiles.push(tile('Check-in Days', number(attendance.checked_in_days),
                    [attendance.incomplete_days > 0
                        ? '<span class="text-warning">' + number(attendance.incomplete_days) + ' without a check-out</span>'
                        : '<span class="text-muted">Every check-in was checked out</span>'], links.attendance));
            }

            $('#dashboardKpis').html(tiles.join(''));
        }

        function baseOptions(mode) {
            return {
                chart: { fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false }, animations: { speed: 300 }, background: 'transparent' },
                theme: { mode: mode },
                dataLabels: { enabled: false },
                grid: { borderColor: mode === 'dark' ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)', strokeDashArray: 3 },
                legend: { position: 'top', horizontalAlign: 'left', markers: { size: 6 } },
                tooltip: { theme: mode, y: { formatter: function (value) { return value === null || value === undefined ? 'No target' : number(value); } } },
                yaxis: { labels: { formatter: function (value) { return number(Math.round(value)); } } }
            };
        }

        function destroy(key) {
            if (charts[key]) {
                charts[key].destroy();
                delete charts[key];
            }
        }

        function draw(key, selector, options) {
            const $el = $(selector);
            if (!$el.length) return;

            destroy(key);
            charts[key] = new ApexCharts($el.empty()[0], options);
            charts[key].render();
        }

        function empty(key, selector, text) {
            const $el = $(selector);
            if (!$el.length) return;

            destroy(key);
            $el.html('<div class="db-empty text-muted">' + escapeHtml(text) + '</div>');
        }

        function renderTrend(mode) {
            const trend = data.overview.trend;
            const dates = trend.dates;
            const series = Object.keys(metrics).map(function (key) {
                return {
                    name: metrics[key],
                    data: dates.map(function (date, index) {
                        return [new Date(date + 'T00:00:00Z').getTime(), trend.series[key][index]];
                    })
                };
            });

            draw('trend', '#dashboardTrendChart', $.extend(true, baseOptions(mode), {
                chart: { type: dates.length === 1 ? 'bar' : 'line', height: 340 },
                colors: palette[mode],
                series: series,
                stroke: dates.length === 1 ? { show: true, width: 2, colors: ['transparent'] } : { width: 2, curve: 'straight' },
                plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '50%' } },
                markers: { size: dates.length > 1 && dates.length <= 31 ? 4 : 0, strokeWidth: 2, hover: { size: 6 } },
                xaxis: { type: 'datetime', labels: { datetimeUTC: true, format: 'dd MMM' }, tooltip: { enabled: false } },
                tooltip: { shared: true, intersect: false, x: { format: 'dd MMM yyyy' } }
            }));
        }

        function renderActivity(mode) {
            const overview = data.overview;
            const series = [{ name: 'Actual', data: overview.kpis.map(function (kpi) { return kpi.actual; }) }];
            if (overview.show_targets) {
                series.push({ name: 'Target', data: overview.kpis.map(function (kpi) { return kpi.target; }) });
            }

            draw('activity', '#dashboardActivityChart', $.extend(true, baseOptions(mode), {
                chart: { type: 'bar', height: 340 },
                colors: palette[mode].slice(0, 2),
                series: series,
                stroke: { show: true, width: 2, colors: ['transparent'] },
                plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '60%' } },
                legend: { show: overview.show_targets },
                xaxis: { categories: overview.kpis.map(function (kpi) { return kpi.label.split(' '); }) },
                tooltip: { shared: true, intersect: false }
            }));
        }

        function renderBreakdown(key, selector, rows, mode, emptyText) {
            const showTargets = data.overview.show_targets;

            if (!rows.length) {
                empty(key, selector, emptyText);
                return;
            }

            const series = [{ name: 'Actual', data: rows.map(function (row) { return row.actual; }) }];
            if (showTargets) {
                series.push({ name: 'Target', data: rows.map(function (row) { return row.target; }) });
            }

            draw(key, selector, $.extend(true, baseOptions(mode), {
                chart: { type: 'bar', height: Math.max(260, rows.length * (showTargets ? 56 : 40) + 90) },
                colors: palette[mode].slice(0, 2),
                series: series,
                stroke: { show: true, width: 2, colors: ['transparent'] },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, borderRadiusApplication: 'end', barHeight: '65%' } },
                legend: { show: showTargets },
                xaxis: { categories: rows.map(function (row) { return row.name; }), labels: { formatter: function (value) { return number(Math.round(value)); } } },
                // on horizontal bars this axis carries the names, so they must not go through the number format
                yaxis: { labels: { maxWidth: 180, formatter: function (value) { return value; } } },
                tooltip: { shared: true, intersect: false }
            }));
        }

        function renderPresence(mode) {
            const daily = data.attendance.daily;
            const colors = [status[mode].good, status[mode].bad, status[mode].fair];

            if (!daily.length || !data.attendance.summary.users) {
                empty('presence', '#dashboardPresenceChart', 'No field user to show.');
                return;
            }

            const point = function (key) {
                return daily.map(function (day) { return [new Date(day.date + 'T00:00:00Z').getTime(), day[key]]; });
            };

            draw('presence', '#dashboardPresenceChart', $.extend(true, baseOptions(mode), {
                chart: { type: 'bar', height: 340, stacked: true },
                colors: colors,
                series: [
                    { name: 'Present', data: point('present') },
                    { name: 'Absent', data: point('absent') },
                    { name: 'On leave', data: point('leave') }
                ],
                plotOptions: { bar: { borderRadius: 2, columnWidth: daily.length > 45 ? '90%' : '55%' } },
                xaxis: { type: 'datetime', labels: { datetimeUTC: true, format: 'dd MMM' }, tooltip: { enabled: false } },
                tooltip: { shared: true, intersect: false, x: { format: 'dd MMM yyyy' } }
            }));
        }

        function renderScoreSpread(mode) {
            const bands = data.people.insights.bands;
            const keys = ['excellent', 'good', 'fair', 'low', 'unscored'];
            const names = {
                excellent: 'Excellent (' + (scoreBands.excellent || 90) + '+)',
                good: 'Good (' + (scoreBands.good || 70) + '–' + ((scoreBands.excellent || 90) - 1) + ')',
                fair: 'Fair (' + (scoreBands.fair || 50) + '–' + ((scoreBands.good || 70) - 1) + ')',
                low: 'Low (below ' + (scoreBands.fair || 50) + ')',
                unscored: 'No target set'
            };
            const tones = { excellent: 'good', good: 'fair', fair: 'warn', low: 'bad', unscored: 'none' };
            const total = keys.reduce(function (sum, key) { return sum + (bands[key] || 0); }, 0);

            if (!total) {
                empty('score', '#dashboardScoreChart', 'No user to score.');
                return;
            }

            draw('score', '#dashboardScoreChart', {
                chart: { type: 'donut', height: 340, fontFamily: 'inherit', background: 'transparent' },
                theme: { mode: mode },
                series: keys.map(function (key) { return bands[key] || 0; }),
                labels: keys.map(function (key) { return names[key]; }),
                colors: keys.map(function (key) { return status[mode][tones[key]]; }),
                legend: { position: 'bottom', markers: { size: 6 } },
                dataLabels: { enabled: false },
                stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Users', formatter: function () { return number(total); } } } } } },
                tooltip: { theme: mode }
            });
        }

        function renderRanking(selector, rows, emptyText) {
            const $el = $(selector);
            if (!$el.length) return;

            if (!rows.length) {
                $el.html('<div class="db-empty text-muted">' + escapeHtml(emptyText) + '</div>');
                return;
            }

            $el.html(rows.map(function (row) {
                return '<div class="db-rank"><a class="db-rank-name db-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a>' +
                    '<span class="text-nowrap">' + scoreBadge(row.score) + '</span></div>';
            }).join(''));
        }

        function renderMissing() {
            const $el = $('#dashboardMissingList');
            if (!$el.length) return;

            const missing = data.attendance.missing;
            $('#dashboardMissingCount').text(number(missing.total));

            if (!missing.rows.length) {
                $el.html('<div class="db-empty text-muted">Every report due in this period is in.</div>');
                return;
            }

            const more = missing.total - missing.rows.length;
            $el.html(missing.rows.map(function (row) {
                return '<div class="db-rank"><div class="db-rank-name">' +
                    '<a class="db-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a>' +
                    '<div class="small text-muted text-truncate">' + escapeHtml(row.dates.map(shortDate).join(', ')) + (row.absent > row.dates.length ? ' …' : '') + '</div></div>' +
                    '<span class="badge text-bg-danger">' + row.absent + ' day' + (row.absent === 1 ? '' : 's') + '</span></div>';
            }).join('') + (more > 0
                ? '<div class="pt-2 text-center"><a href="' + links.attendance + '">and ' + number(more) + ' more in the attendance sheet</a></div>'
                : ''));
        }

        // the page numbers to offer: first, last and those around the current one, with null for a gap
        function pageNumbers(current, last) {
            const pages = [];
            for (let page = 1; page <= last; page++) {
                if (page === 1 || page === last || Math.abs(page - current) <= 1) {
                    pages.push(page);
                } else if (pages[pages.length - 1] !== null) {
                    pages.push(null);
                }
            }
            return pages;
        }

        function renderPager(meta) {
            const $pager = $('#dashboardUserPager');

            $pager.toggleClass('d-none', meta.total <= meta.per_page);
            $pager.find('.db-pager-info').text('Showing ' + meta.from + ' to ' + meta.to + ' of ' + number(meta.total) + ' users');

            function item(label, page, disabled, active) {
                return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                    (page === null
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link db-page" href="javascript:void(0)" data-page="' + page + '"' + (active ? ' aria-current="page"' : '') + '>' + label + '</a>') +
                    '</li>';
            }

            $pager.find('.pagination').html(
                item('Previous', meta.page > 1 ? meta.page - 1 : null, meta.page === 1) +
                pageNumbers(meta.page, meta.last_page).map(function (page) {
                    return page === null ? item('&hellip;', null, true) : item(page, page, false, page === meta.page);
                }).join('') +
                item('Next', meta.page < meta.last_page ? meta.page + 1 : null, meta.page === meta.last_page)
            );
        }

        function renderTable() {
            const $body = $('#dashboardUserTable tbody');
            if (!$body.length) return;

            $('#dashboardUserTable .db-sort').each(function () {
                const sorted = $(this).data('sort') === table.sort;
                $(this).toggleClass('is-sorted', sorted)
                    .find('.mdi').attr('class', 'mdi ' + (sorted ? (table.dir === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal'));
            });

            const rows = data.people.rows;
            const meta = data.people.meta;

            if (!rows.length) {
                $body.html('<tr><td colspan="12" class="text-center text-muted">' + (table.search ? 'No user matches the search.' : 'No field user found.') + '</td></tr>');
                $('#dashboardUserPager').addClass('d-none');
                return;
            }

            $body.html(rows.map(function (user, index) {
                const cells = Object.keys(metrics).map(function (key) {
                    return '<td class="text-end">' + number(user[key]) + '</td>';
                }).join('');

                return '<tr>' +
                    '<td>' + (meta.from + index) + '</td>' +
                    '<td><a class="db-user-link" data-id="' + user.id + '" title="Show only this user">' + escapeHtml(user.name) + '</a>' +
                    (user.employee_id ? ' <small class="text-muted">' + escapeHtml(user.employee_id) + '</small>' : '') + '</td>' +
                    '<td class="text-end">' + number(user.reports) + '</td>' +
                    cells +
                    '<td class="text-end">' + number(user.solved_issues) + '</td>' +
                    '<td class="text-end fw-semibold">' + number(user.total) + '</td>' +
                    '<td class="text-end">' + (user.target_total === null ? '&mdash;' : number(user.target_total)) + '</td>' +
                    '<td>' + (user.achievement_pct === null
                        ? '<span class="text-muted">No target set</span>'
                        : '<div class="d-flex align-items-center gap-2">' + progress(user.achievement_pct) + '<span>' + user.achievement_pct + '%</span></div>') + '</td>' +
                    '<td class="text-end">' + scoreBadge(user.score) + '</td>' +
                    '</tr>';
            }).join(''));

            renderPager(meta);
        }

        // a file built in the browser from rows the server sent
        function downloadCsv(name, rows, range) {
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
            link.download = name + '_' + range.from + '_to_' + range.to + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }

        function exportUsers() {
            const $button = $('#dashboardUserExport').prop('disabled', true);

            // the table only holds a page, the file needs every user
            $.ajax({
                url: dashboardUrl,
                method: 'GET',
                dataType: 'json',
                data: $.extend({ section: 'people', export: 1, sort: table.sort, dir: table.dir, search: table.search }, params())
            }).done(function (response) {
                const keys = Object.keys(metrics);
                const rows = [['Sl', 'User', 'Employee ID', 'Reports'].concat(keys.map(function (key) { return metrics[key]; }), ['Solved Issues', 'Total', 'Target', 'Achievement %', 'KPI Score'])];

                response.rows.forEach(function (user, index) {
                    rows.push([index + 1, user.name, user.employee_id, user.reports].concat(
                        keys.map(function (key) { return user[key]; }),
                        [user.solved_issues, user.total, user.target_total, user.achievement_pct, user.score]
                    ));
                });
                downloadCsv('field-user-performance', rows, response.range);
            }).fail(function () {
                $('#dashboardError').removeClass('d-none');
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        const renderers = {
            overview: function (mode) {
                renderKpis();
                renderTrend(mode);
                renderActivity(mode);
                renderBreakdown('projects', '#dashboardProjectChart', data.overview.projects, mode, 'No project calls in this period.');
                renderBreakdown('platforms', '#dashboardPlatformChart', data.overview.platforms, mode, 'No comment replies in this period.');
            },
            people: function (mode) {
                renderKpis();
                if (canViewAll) {
                    renderScoreSpread(mode);
                    renderRanking('#dashboardTopList', data.people.insights.top, 'No score to rank in this period.');
                    renderRanking('#dashboardAttentionList', data.people.insights.attention, 'No score to rank in this period.');
                    renderTable();
                }
            },
            attendance: function (mode) {
                renderKpis();
                renderPresence(mode);
                renderMissing();
            },
            live: renderLive
        };

        function fetchSection(name, extra) {
            if (requests[name]) requests[name].abort();

            const $sections = $('.db-section[data-section="' + name + '"]').addClass('is-loading');
            const payload = $.extend({ section: name }, params(), extra || {});

            if (name === 'people') {
                $.extend(payload, { page: table.page, per_page: table.per_page, sort: table.sort, dir: table.dir, search: table.search });
            }
            if (refreshNext && (name === 'people' || name === 'attendance')) {
                payload.refresh = 1;
            }

            const xhr = requests[name] = $.ajax({ url: dashboardUrl, method: 'GET', data: payload, dataType: 'json' })
                .done(function (response) {
                    data[name] = response;

                    if (name === 'people') {
                        // the server clamps a page past the end, so follow it
                        table.page = response.meta.page;
                    }
                    if (response.range && (name === 'overview' || !data.overview)) {
                        $from.val(response.range.from);
                        $to.val(response.range.to);
                    }
                    if (name === 'overview') {
                        $('#dashboardRangeText').text(rangeText(response.range.from, response.range.to) +
                            ' · compared with ' + rangeText(response.range.previous_from, response.range.previous_to));
                        $('#dashboardUpdated').text('Updated ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
                    }

                    renderers[name](themeMode());
                })
                .fail(function (jqXHR) {
                    if (jqXHR.statusText === 'abort') return;

                    if (jqXHR.status === 422 && jqXHR.responseJSON && jqXHR.responseJSON.errors) {
                        const errors = jqXHR.responseJSON.errors;
                        $('#dashboardFilterError').text(errors[Object.keys(errors)[0]][0]).removeClass('d-none');
                        return;
                    }
                    $('#dashboardError').removeClass('d-none');
                })
                .always(function () {
                    if (requests[name] === xhr) {
                        $sections.removeClass('is-loading');
                    }
                });
        }

        function loadAll() {
            syncControls();
            $('#dashboardError, #dashboardFilterError').addClass('d-none');

            // keeps the filters in the address, so the view can be bookmarked or shared
            window.history.replaceState(null, '', dashboardUrl + '?' + $.param(params()));

            // new figures reorder the users, so the table starts again from its first page
            table.page = 1;
            data.overview = data.people = data.attendance = null;

            fetchSection('overview');
            fetchSection('attendance');
            fetchSection('people');
            if (canViewAll) fetchSection('live');
            refreshNext = false;
        }

        $('.dashboard-preset').on('click', function () {
            state.preset = $(this).data('preset');
            loadAll();
        });

        $('#dashboardFilterForm').on('submit', function (e) {
            e.preventDefault();

            // dates typed in by hand make it a custom range, otherwise the chosen period stays
            if (data.overview && ($from.val() !== data.overview.range.from || $to.val() !== data.overview.range.to)) {
                state.preset = 'custom';
            }
            state.from = $from.val();
            state.to = $to.val();
            state.user_id = $user.val() || '';
            loadAll();
        });

        $user.on('change', function () {
            state.user_id = $(this).val() || '';
            loadAll();
        });

        $('#dashboardFilterReset').on('click', function () {
            state.preset = defaultPreset;
            state.from = state.to = state.user_id = '';
            loadAll();
        });

        $('#dashboardRefresh').on('click', function () {
            refreshNext = true;
            loadAll();
        });

        $('#dashboardRetry').on('click', loadAll);

        $(document).on('click', '.db-user-link', function () {
            state.user_id = String($(this).data('id'));
            loadAll();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // the table is read from the server, so paging, sorting and searching ask for the people section only
        $(document).on('click', '.db-page', function () {
            table.page = parseInt($(this).data('page'), 10);
            fetchSection('people');
        });

        $('#dashboardUserTable').on('click', '.db-sort', function () {
            const sort = $(this).data('sort');

            table.dir = table.sort === sort ? (table.dir === 'asc' ? 'desc' : 'asc') : (sort === 'name' ? 'asc' : 'desc');
            table.sort = sort;
            table.page = 1;
            fetchSection('people');
        });

        let searchTimer = null;
        $('#dashboardUserSearch').on('input', function () {
            const value = $(this).val().trim();

            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                if (value === table.search) return;

                table.search = value;
                table.page = 1;
                fetchSection('people');
            }, 300);
        });

        $('#dashboardUserExport').on('click', exportUsers);

        // the charts carry their own colours, so redraw them when the theme is switched
        new MutationObserver(function () {
            const mode = themeMode();

            $.each(renderers, function (name, render) {
                if (data[name]) render(mode);
            });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme-mode'] });

        loadAll();
    });
</script>
