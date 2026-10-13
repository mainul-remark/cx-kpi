<script>
    $(function () {
        const reportUrl = @json(route('daily-reports.index'));
        const listUrl = @json(route($listRoute));
        const isTeam = @json($isTeam);
        const can = {
            show: @json((bool) allowed('daily-reports.show')),
            edit: @json((bool) allowed('daily-reports.edit')),
            destroy: @json((bool) allowed('daily-reports.destroy'))
        };

        function count(data) {
            return data || 0;
        }

        function escapeHtml(value) {
            return $('<div>').text(value).html();
        }

        // the total, with each project / platform that contributed to it listed underneath
        function totalWithBreakdown(total, items) {
            let html = '<div class="fw-semibold">' + total + '</div>';
            (items || []).forEach(function (item) {
                const icon = item.type === 'project' ? 'mdi-briefcase-outline' : 'mdi-share-variant';
                html += '<div class="small text-muted"><i class="mdi ' + icon + ' me-1"></i>'
                    + escapeHtml(item.name) + ': ' + item.count + '</div>';
            });
            return html;
        }

        const columns = [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
            { data: 'report_date', name: 'report_date' },
            { data: 'user.name', name: 'user.name', orderable: false, defaultContent: '' },
            { data: 'outbound_calls', name: 'outbound_calls', searchable: false },
            { data: 'order_processing', name: 'order_processing', searchable: false },
            { data: 'inbound_calls', name: 'inbound_calls', searchable: false },
            {
                data: 'platform_comments', name: 'platform_comments', orderable: false, searchable: false,
                render: function (data, type, row) {
                    return totalWithBreakdown(count(row.platform_comments) + count(row.project_comments), row.comments_breakdown);
                }
            },
            {
                data: 'platform_messages', name: 'platform_messages', orderable: false, searchable: false,
                render: function (data, type, row) {
                    return totalWithBreakdown(count(row.platform_messages) + count(row.project_messages), row.messages_breakdown);
                }
            },
            {
                data: 'id', name: 'id', orderable: false, searchable: false, width: '140px',
                render: function (id, type, row) {
                    let buttons = '';
                    if (can.show) {
                        buttons += '<a href="' + reportUrl + '/' + id + '" class="btn btn-outline-primary btn-sm" title="View Report"><i class="fa fa-eye"></i></a>';
                    }
                    // changed by the user it belongs to, or by a corporate user
                    if (can.edit && row.can_edit) {
                        buttons += '<a href="' + reportUrl + '/' + id + '/edit" class="btn btn-outline-primary btn-sm ms-2" title="Edit Report"><i class="fa fa-pencil-alt"></i></a>';
                    }
                    if (can.destroy && row.is_own) {
                        buttons += '<button type="button" class="btn btn-outline-danger btn-sm ms-2 delete-daily-report" data-id="' + id + '" title="Delete Report"><i class="fa fa-trash-alt"></i></button>';
                    }
                    return buttons;
                }
            }
        ];

        const reportTable = $('#dailyReportDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: listUrl,
                data: function (params) {
                    params.from = $('#filter_from').val();
                    params.to = $('#filter_to').val();
                    if (isTeam) {
                        params.user_id = $('#filter_user').val();
                    }
                }
            },
            columns: columns
        });

        $('#dailyReportFilterForm').on('submit', function (e) {
            e.preventDefault();
            reportTable.ajax.reload();
        });

        $('#dailyReportFilterReset').on('click', function () {
            $('#dailyReportFilterForm')[0].reset();
            reportTable.ajax.reload();
        });

        $(document).on('click', '.delete-daily-report', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: reportUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Daily report deleted successfully.');
                    reportTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    if (xhr.status === 403) {
                        toastr.error('You do not have permission to perform this action.');
                        return;
                    }
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to delete daily report.');
                });
            });
        });
    });
</script>
