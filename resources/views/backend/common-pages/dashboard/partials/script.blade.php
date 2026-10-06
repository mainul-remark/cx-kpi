<script>
    $(function () {
        const dashboardUrl = @json(route('admin.dashboard'));
        const canViewAll = @json($canViewAll);
        const defaultPreset = 'month';
        const metrics = @json(\App\Services\Dashboard\ReportDashboardService::METRICS);

        // one colour per activity and one each for actual and target, the same wherever they appear
        const palette = {
            light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'],
            dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181']
        };

        const $content = $('#dashboardContent');
        const $from = $('#dashboard_from');
        const $to = $('#dashboard_to');
        const $user = $('#dashboard_user');
        const charts = {};
        let request = null;
        let lastData = null;
        // what each attendance mark shows in its cell and means in words
        const attendanceMarks = {
            P: { text: 'P', label: 'Present' },
            A: { text: 'A', label: 'Absent' },
            O: { text: 'O', label: 'Off day' },
            H: { text: 'H', label: 'Holiday' },
            F: { text: '', label: 'Not due yet' },
            N: { text: '', label: 'Not joined yet' }
        };
        const perPage = 10;
        const pagers = {
            users: { id: 'dashboardUserPager', page: 1, render: function () { renderUsers(lastData); } },
            missing: { id: 'dashboardMissingPager', page: 1, render: function () { renderMissing(lastData); } },
            attendance: { id: 'dashboardAttendancePager', page: 1, render: function () { renderAttendance(lastData); } }
        };

        const query = new URLSearchParams(window.location.search);
        const state = {
            preset: query.get('preset') || (query.get('from') && query.get('to') ? 'custom' : defaultPreset),
            from: query.get('from') || '',
            to: query.get('to') || '',
            user_id: canViewAll ? (query.get('user_id') || '') : ''
        };

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

        function rangeText(from, to) {
            return from === to ? formatDate(from) : formatDate(from) + ' – ' + formatDate(to);
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

        function tile(label, value, lines) {
            return '<div class="col-xxl-2 col-md-4 col-6"><div class="card db-kpi"><div class="card-body">' +
                '<div class="db-kpi-label text-muted mb-1">' + escapeHtml(label) + '</div>' +
                '<div class="db-kpi-value mb-2">' + value + '</div>' +
                lines.map(function (line) { return '<div class="db-kpi-meta mt-1">' + line + '</div>'; }).join('') +
                '</div></div></div>';
        }

        function renderKpis(data) {
            const tiles = data.kpis.map(function (kpi) {
                const lines = [changeLine(kpi)];

                if (data.show_targets) {
                    lines.push(kpi.target === null
                        ? '<span class="text-muted">No target set</span>'
                        : progress(kpi.achievement_pct) + '<div class="mt-1 text-muted">' + kpi.achievement_pct + '% of ' + number(kpi.target) + ' target</div>');
                }
                return tile(kpi.label, number(kpi.actual), lines);
            });

            const reports = data.submissions;
            tiles.push(tile('Reports Submitted', number(reports.submitted), reports.pct === null
                ? ['<span class="text-muted">No working day in this period yet</span>']
                : [progress(reports.pct) + '<div class="mt-1 text-muted">' + reports.pct + '% of ' + number(reports.expected) + ' due</div>']));

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

        function draw(key, selector, options) {
            if (charts[key]) {
                charts[key].destroy();
                delete charts[key];
            }
            const $el = $(selector).empty();
            charts[key] = new ApexCharts($el[0], options);
            charts[key].render();
        }

        function empty(key, selector, text) {
            if (charts[key]) {
                charts[key].destroy();
                delete charts[key];
            }
            $(selector).html('<div class="db-empty text-muted">' + escapeHtml(text) + '</div>');
        }

        function renderTrend(data, mode) {
            const dates = data.trend.dates;
            const series = Object.keys(metrics).map(function (key) {
                return {
                    name: metrics[key],
                    data: dates.map(function (date, index) {
                        return [new Date(date + 'T00:00:00Z').getTime(), data.trend.series[key][index]];
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

        function renderActivity(data, mode) {
            const series = [{ name: 'Actual', data: data.kpis.map(function (kpi) { return kpi.actual; }) }];
            if (data.show_targets) {
                series.push({ name: 'Target', data: data.kpis.map(function (kpi) { return kpi.target; }) });
            }

            draw('activity', '#dashboardActivityChart', $.extend(true, baseOptions(mode), {
                chart: { type: 'bar', height: 340 },
                colors: palette[mode].slice(0, 2),
                series: series,
                stroke: { show: true, width: 2, colors: ['transparent'] },
                plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '60%' } },
                legend: { show: data.show_targets },
                xaxis: { categories: data.kpis.map(function (kpi) { return kpi.label.split(' '); }) },
                tooltip: { shared: true, intersect: false }
            }));
        }

        function renderBreakdown(key, selector, rows, data, mode, emptyText) {
            if (!rows.length) {
                empty(key, selector, emptyText);
                return;
            }

            const series = [{ name: 'Actual', data: rows.map(function (row) { return row.actual; }) }];
            if (data.show_targets) {
                series.push({ name: 'Target', data: rows.map(function (row) { return row.target; }) });
            }

            draw(key, selector, $.extend(true, baseOptions(mode), {
                chart: { type: 'bar', height: Math.max(260, rows.length * (data.show_targets ? 56 : 40) + 90) },
                colors: palette[mode].slice(0, 2),
                series: series,
                stroke: { show: true, width: 2, colors: ['transparent'] },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, borderRadiusApplication: 'end', barHeight: '65%' } },
                legend: { show: data.show_targets },
                xaxis: { categories: rows.map(function (row) { return row.name; }), labels: { formatter: function (value) { return number(Math.round(value)); } } },
                // on horizontal bars this axis carries the names, so they must not go through the number format
                yaxis: { labels: { maxWidth: 180, formatter: function (value) { return value; } } },
                tooltip: { shared: true, intersect: false }
            }));
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

        // shows one page of a list: draws its pager and returns the index of the first row on the page
        function paginate(key, total) {
            const $pager = $('#' + pagers[key].id);
            const lastPage = Math.max(1, Math.ceil(total / perPage));
            const current = pagers[key].page = Math.min(Math.max(1, pagers[key].page), lastPage);
            const start = (current - 1) * perPage;

            $pager.toggleClass('d-none', total <= perPage);
            $pager.find('.db-pager-info').text('Showing ' + (start + 1) + ' to ' + Math.min(start + perPage, total) + ' of ' + total + ' users');

            function item(label, page, disabled, active) {
                return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                    (page === null
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link db-page" href="javascript:void(0)" data-pager="' + key + '" data-page="' + page + '"' + (active ? ' aria-current="page"' : '') + '>' + label + '</a>') +
                    '</li>';
            }

            $pager.find('.pagination').html(
                item('Previous', current > 1 ? current - 1 : null, current === 1) +
                pageNumbers(current, lastPage).map(function (page) {
                    return page === null ? item('&hellip;', null, true) : item(page, page, false, page === current);
                }).join('') +
                item('Next', current < lastPage ? current + 1 : null, current === lastPage)
            );

            return start;
        }

        function renderUsers(data) {
            const $body = $('#dashboardUserTable tbody');
            if (!$body.length) return;

            if (!data.users || !data.users.length) {
                $body.html('<tr><td colspan="11" class="text-center text-muted">No field user found.</td></tr>');
                $('#dashboardUserPager').addClass('d-none');
                return;
            }

            const start = paginate('users', data.users.length);

            $body.html(data.users.slice(start, start + perPage).map(function (user, index) {
                const cells = Object.keys(metrics).map(function (key) {
                    return '<td class="text-end">' + number(user[key]) + '</td>';
                }).join('');

                return '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td><a class="db-user-link" data-id="' + user.id + '" title="Show only this user">' + escapeHtml(user.name) + '</a></td>' +
                    '<td class="text-end">' + number(user.reports) + '</td>' +
                    cells +
                    '<td class="text-end fw-semibold">' + number(user.total) + '</td>' +
                    '<td class="text-end">' + (user.target_total === null ? '&mdash;' : number(user.target_total)) + '</td>' +
                    '<td>' + (user.achievement_pct === null
                        ? '<span class="text-muted">No target set</span>'
                        : '<div class="d-flex align-items-center gap-2">' + progress(user.achievement_pct) + '<span>' + user.achievement_pct + '%</span></div>') + '</td>' +
                    '</tr>';
            }).join(''));
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

        // the users who owe a report, with the days they missed, most missed first
        function renderMissing(data) {
            const $body = $('#dashboardMissingTable tbody');
            if (!$body.length || !data.attendance) return;

            const days = data.attendance.days;
            const rows = data.attendance.rows
                .filter(function (row) { return row.absent > 0; })
                .sort(function (a, b) { return b.absent - a.absent || a.name.localeCompare(b.name); });

            $('#dashboardMissingCount').text(rows.length);

            if (!rows.length) {
                $body.html('<tr><td colspan="4" class="text-center text-muted">Every report due in this period is in.</td></tr>');
                $('#dashboardMissingPager').addClass('d-none');
                return;
            }

            const start = paginate('missing', rows.length);

            $body.html(rows.slice(start, start + perPage).map(function (row, index) {
                const missed = [];
                for (let i = 0; i < days.length; i++) {
                    if (row.marks.charAt(i) === 'A') missed.push(dayParts(days[i].date).short);
                }
                const shown = missed.slice(0, 12).join(', ') + (missed.length > 12 ? ' and ' + (missed.length - 12) + ' more' : '');

                return '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td><a class="db-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a></td>' +
                    '<td class="text-end">' + row.absent + '</td>' +
                    '<td class="text-wrap">' + escapeHtml(shown) + '</td>' +
                    '</tr>';
            }).join(''));
        }

        function renderAttendance(data) {
            const $table = $('#dashboardAttendanceTable');
            if (!$table.length || !data.attendance) return;

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
                '<tr><th rowspan="2" class="db-att-name">User</th><th rowspan="2" class="text-end">Present</th><th rowspan="2" class="text-end">Absent</th><th rowspan="2" class="text-end">Attendance</th>' + months + '</tr>' +
                '<tr>' + parts.map(function (part, index) {
                    return '<th class="db-att-day" title="' + escapeHtml(days[index].holiday || '') + '">' + part.number + '<small>' + part.weekday + '</small></th>';
                }).join('') + '</tr>'
            );

            if (!rows.length) {
                $table.find('tbody').html('<tr><td colspan="' + (days.length + 4) + '" class="text-center text-muted">No field user found.</td></tr>');
                $('#dashboardAttendancePager').addClass('d-none');
                return;
            }

            const start = paginate('attendance', rows.length);

            $table.find('tbody').html(rows.slice(start, start + perPage).map(function (row) {
                let cells = '';
                for (let i = 0; i < days.length; i++) {
                    const mark = row.marks.charAt(i);
                    const title = attendanceMarks[mark].label + (mark === 'H' && days[i].holiday ? ': ' + days[i].holiday : '') + ' · ' + parts[i].short;
                    cells += '<td class="db-att-cell db-att-' + mark + '" title="' + escapeHtml(title) + '">' + attendanceMarks[mark].text + '</td>';
                }

                return '<tr>' +
                    '<td class="db-att-name"><a class="db-user-link" data-id="' + row.id + '" title="Show only this user">' + escapeHtml(row.name) + '</a></td>' +
                    '<td class="text-end">' + row.present + '</td>' +
                    '<td class="text-end">' + row.absent + '</td>' +
                    '<td class="text-end">' + (row.pct === null ? '&mdash;' : row.pct + '%') + '</td>' +
                    cells +
                    '</tr>';
            }).join(''));
        }

        // a file built in the browser from the figures already on the page
        function downloadCsv(name, rows) {
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
            link.download = name + '_' + lastData.range.from + '_to_' + lastData.range.to + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }

        function exportUsers() {
            if (!lastData || !lastData.users) return;

            const keys = Object.keys(metrics);
            const rows = [['Sl', 'User', 'Reports'].concat(keys.map(function (key) { return metrics[key]; }), ['Total', 'Target', 'Achievement %'])];

            lastData.users.forEach(function (user, index) {
                rows.push([index + 1, user.name, user.reports].concat(
                    keys.map(function (key) { return user[key]; }),
                    [user.total, user.target_total, user.achievement_pct]
                ));
            });
            downloadCsv('field-user-performance', rows);
        }

        function exportAttendance() {
            if (!lastData || !lastData.attendance) return;

            const days = lastData.attendance.days;
            const rows = [['User', 'Present', 'Absent', 'Attendance %'].concat(days.map(function (day) { return day.date; }))];

            lastData.attendance.rows.forEach(function (row) {
                rows.push([row.name, row.present, row.absent, row.pct].concat(row.marks.split('').map(function (mark) {
                    return attendanceMarks[mark].label;
                })));
            });
            downloadCsv('attendance-sheet', rows);
        }

        function render(data) {
            const mode = themeMode();
            lastData = data;

            $('#dashboardRangeText').text(rangeText(data.range.from, data.range.to) +
                ' · compared with ' + rangeText(data.range.previous_from, data.range.previous_to));
            $from.val(data.range.from);
            $to.val(data.range.to);

            renderKpis(data);
            renderTrend(data, mode);
            renderActivity(data, mode);
            renderBreakdown('projects', '#dashboardProjectChart', data.projects, data, mode, 'No project calls in this period.');
            renderBreakdown('platforms', '#dashboardPlatformChart', data.platforms, data, mode, 'No comment replies in this period.');
            renderUsers(data);
            renderMissing(data);
            renderAttendance(data);
        }

        function load() {
            if (request) request.abort();

            syncControls();
            $content.addClass('is-loading');
            $('#dashboardError, #dashboardFilterError').addClass('d-none');

            const data = params();
            // keeps the filters in the address, so the view can be bookmarked or shared
            window.history.replaceState(null, '', dashboardUrl + '?' + $.param(data));

            request = $.ajax({ url: dashboardUrl, method: 'GET', data: data, dataType: 'json' })
                .done(function (response) {
                    // new figures reorder the users, so start again from the first page
                    $.each(pagers, function (key, pager) { pager.page = 1; });
                    render(response);
                    $content.removeClass('is-loading');
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') return;

                    $content.removeClass('is-loading');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        $('#dashboardFilterError').text(errors[Object.keys(errors)[0]][0]).removeClass('d-none');
                        return;
                    }
                    $('#dashboardError').removeClass('d-none');
                });
        }

        $('.dashboard-preset').on('click', function () {
            state.preset = $(this).data('preset');
            load();
        });

        $('#dashboardFilterForm').on('submit', function (e) {
            e.preventDefault();

            // dates typed in by hand make it a custom range, otherwise the chosen period stays
            if (lastData && ($from.val() !== lastData.range.from || $to.val() !== lastData.range.to)) {
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

        $('#dashboardFilterReset').on('click', function () {
            state.preset = defaultPreset;
            state.from = state.to = state.user_id = '';
            load();
        });

        $('#dashboardRetry').on('click', load);

        $(document).on('click', '.db-user-link', function () {
            state.user_id = String($(this).data('id'));
            load();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        $(document).on('click', '.db-page', function () {
            const pager = pagers[$(this).data('pager')];
            pager.page = parseInt($(this).data('page'), 10);
            pager.render();
        });

        $('#dashboardUserExport').on('click', exportUsers);
        $('#dashboardAttendanceExport').on('click', exportAttendance);

        // the charts carry their own colours, so redraw them when the theme is switched
        new MutationObserver(function () {
            if (lastData) render(lastData);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme-mode'] });

        load();
    });
</script>
