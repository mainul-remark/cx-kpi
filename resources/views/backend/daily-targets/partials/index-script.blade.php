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

        const columns = [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
            { data: 'target_date', name: 'target_date' },
            { data: 'user.name', name: 'user.name', orderable: false, defaultContent: '' },
            { data: 'outbound_calls', name: 'outbound_calls', searchable: false, render: count },
            { data: 'inbound_calls', name: 'inbound_calls', searchable: false, render: count },
            { data: 'message_replies', name: 'message_replies', searchable: false, render: count },
            { data: 'platform_replies_total', name: 'platform_replies_total', searchable: false, render: count },
            { data: 'project_calls_total', name: 'project_calls_total', searchable: false, render: count },
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
