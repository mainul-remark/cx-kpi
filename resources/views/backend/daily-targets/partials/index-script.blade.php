<script>
    $(function () {
        const targetUrl = @json(route('daily-targets.index'));
        const can = {
            edit: @json((bool) allowed('daily-targets.edit')),
            destroy: @json((bool) allowed('daily-targets.destroy'))
        };

        // an activity without a target is empty, which is not the same as a target of 0
        function count(data) {
            return data === null || data === undefined ? '&mdash;' : data;
        }

        // a total of approximate targets, none when neither side has one
        function sum(a, b) {
            return a === null && b === null ? count(null) : (parseInt(a, 10) || 0) + (parseInt(b, 10) || 0);
        }

        function escapeHtml(text) {
            return $('<div>').text(text === null || text === undefined ? '' : text).html();
        }

        // one line per project or platform that has a target: "Name: 5 calls, 3 comments, 2 replies"
        function breakdown(rows, relation) {
            const parts = [
                ['inbound_calls', 'outbound calls'],
                ['comments', 'comments'],
                ['message_replies', 'replies']
            ];

            const lines = (rows || []).map(function (row) {
                const values = parts
                    .filter(function (part) { return row[part[0]] !== null && row[part[0]] !== undefined; })
                    .map(function (part) { return row[part[0]] + ' ' + part[1]; });

                if (!values.length) return null;

                const name = row[relation] ? row[relation].name : '';
                return '<div><strong>' + escapeHtml(name) + ':</strong> ' + escapeHtml(values.join(', ')) + '</div>';
            }).filter(Boolean);

            return lines.length ? lines.join('') : count(null);
        }

        const columns = [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
            { data: 'target_date', name: 'target_date' },
            { data: 'user.name', name: 'user.name', orderable: false, defaultContent: '' },
            { data: 'outbound_calls', name: 'outbound_calls', searchable: false, render: count },
            { data: 'inbound_calls', name: 'inbound_calls', searchable: false, render: count },
            {
                data: 'platform_comments', name: 'platform_comments', orderable: false, searchable: false,
                render: function (data, type, row) { return sum(row.platform_comments, row.project_comments); }
            },
            {
                data: 'platform_messages', name: 'platform_messages', orderable: false, searchable: false,
                render: function (data, type, row) { return sum(row.platform_messages, row.project_messages); }
            },
            {
                data: 'project_calls', name: 'project_calls', orderable: false, searchable: false,
                render: function (data) { return breakdown(data, 'project'); }
            },
            {
                data: 'platform_replies', name: 'platform_replies', orderable: false, searchable: false,
                render: function (data) { return breakdown(data, 'social_platform'); }
            },
            { data: 'set_by_user.name', name: 'setByUser.name', orderable: false, searchable: false, defaultContent: '' },
            {
                data: 'id', name: 'id', orderable: false, searchable: false, width: '100px',
                render: function (id) {
                    let buttons = '';
                    if (can.edit) {
                        buttons += '<a href="' + targetUrl + '/' + id + '/edit" class="btn btn-outline-primary btn-sm" title="Edit Target"><i class="fa fa-pencil-alt"></i></a>';
                    }
                    if (can.destroy) {
                        buttons += '<button type="button" class="btn btn-outline-danger btn-sm ms-2 delete-daily-target" data-id="' + id + '" title="Delete Target"><i class="fa fa-trash-alt"></i></button>';
                    }
                    return buttons;
                }
            }
        ];

        const targetTable = $('#dailyTargetDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: targetUrl,
                data: function (params) {
                    params.from = $('#filter_from').val();
                    params.to = $('#filter_to').val();
                    params.user_id = $('#filter_user').val();
                }
            },
            columns: columns
        });

        $('#dailyTargetFilterForm').on('submit', function (e) {
            e.preventDefault();
            targetTable.ajax.reload();
        });

        $('#dailyTargetFilterReset').on('click', function () {
            $('#dailyTargetFilterForm')[0].reset();
            targetTable.ajax.reload();
        });

        $(document).on('click', '.delete-daily-target', function () {
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
                    url: targetUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Target deleted successfully.');
                    targetTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    if (xhr.status === 403) {
                        toastr.error('You do not have permission to perform this action.');
                        return;
                    }
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to delete target.');
                });
            });
        });
    });
</script>
