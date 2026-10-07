<script>
    $(function () {
        const leaveUrl = @json(route('leaves.index'));
        const can = {
            edit: @json((bool) allowed('leaves.edit')),
            destroy: @json((bool) allowed('leaves.destroy')),
            approve: @json((bool) allowed('leaves.approve')),
            reject: @json((bool) allowed('leaves.reject'))
        };
        const portions = @json($portions);
        const types = @json($types);
        const statuses = @json($statuses);
        const statusClasses = { pending: 'text-bg-warning', approved: 'text-bg-success', rejected: 'text-bg-danger' };
        const today = @json(today()->toDateString());

        const $modal = $('#leaveModal');
        const $form = $('#leaveForm');
        const $submitBtn = $('#leaveSubmitBtn');
        const $users = $('#leave_user_ids');
        const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
        const modes = {
            create: { title: 'Set Leave', button: 'Save' },
            edit: { title: 'Edit Leave', button: 'Update' }
        };

        function escape(text) {
            return $('<div>').text(text || '').html();
        }

        const leaveTable = $('#leaveDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: leaveUrl,
                data: function (params) {
                    params.from = $('#filter_from').val();
                    params.to = $('#filter_to').val();
                    params.user_id = $('#filter_user').val();
                    params.status = $('#filter_status').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
                { data: 'leave_date', name: 'leave_date' },
                { data: 'user.name', name: 'user.name', orderable: false, defaultContent: '' },
                {
                    data: 'portion', name: 'portion',
                    render: function (data, type) {
                        return type === 'display' ? escape(portions[data] || data) : data;
                    }
                },
                {
                    data: 'type', name: 'type',
                    render: function (data, type) {
                        return type === 'display' ? escape(types[data] || data) : data;
                    }
                },
                {
                    data: 'note', name: 'note', orderable: false, className: 'text-wrap',
                    render: function (data, type) {
                        return type === 'display' ? escape(data) : data;
                    }
                },
                {
                    data: 'status', name: 'status',
                    render: function (data, type) {
                        return type === 'display'
                            ? '<span class="badge ' + (statusClasses[data] || 'text-bg-secondary') + '">' + escape(statuses[data] || data) + '</span>'
                            : data;
                    }
                },
                { data: 'approved_by_user.name', name: 'approvedByUser.name', orderable: false, searchable: false, defaultContent: '' },
                {
                    data: 'id', name: 'id', orderable: false, searchable: false, width: '180px',
                    render: function (id, type, row) {
                        let buttons = '';
                        if (can.approve && row.status !== 'approved') {
                            buttons += '<button type="button" class="btn btn-outline-success btn-sm me-2 decide-leave" data-id="' + id + '" data-decision="approve" title="Approve Leave"><i class="fa fa-check"></i></button>';
                        }
                        if (can.reject && row.status !== 'rejected') {
                            buttons += '<button type="button" class="btn btn-outline-warning btn-sm me-2 decide-leave" data-id="' + id + '" data-decision="reject" title="Reject Leave"><i class="fa fa-times"></i></button>';
                        }
                        if (can.edit) {
                            buttons += '<a href="javascript:void(0)" class="btn btn-outline-primary btn-sm me-2 edit-leave" data-id="' + id + '" title="Edit Leave"><i class="fa fa-pencil-alt"></i></a>';
                        }
                        if (can.destroy) {
                            buttons += '<button type="button" class="btn btn-outline-danger btn-sm delete-leave" data-id="' + id + '" title="Delete Leave"><i class="fa fa-trash-alt"></i></button>';
                        }
                        return buttons;
                    }
                }
            ]
        });

        $('#leaveFilterForm').on('submit', function (e) {
            e.preventDefault();
            leaveTable.ajax.reload();
        });

        $('#leaveFilterReset').on('click', function () {
            $('#leaveFilterForm')[0].reset();
            leaveTable.ajax.reload();
        });

        function clearErrors() {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('[data-error-for]').text('').removeClass('d-block');
        }

        function showErrors(errors) {
            $.each(errors, function (field, messages) {
                // "user_ids.2" is an entry of the one user select
                const key = field.indexOf('user_ids') === 0 ? 'user_ids' : field;
                const name = key === 'user_ids' ? 'user_ids[]' : key;

                $form.find('[name="' + name + '"]').addClass('is-invalid');
                // shown explicitly, as the select2 box sits between the select and its error
                $form.find('[data-error-for="' + key + '"]').text(messages[0]).addClass('d-block');
            });
        }

        function errorMessage(xhr, fallback) {
            if (xhr.status === 403) return 'You do not have permission to perform this action.';
            if (xhr.status === 404) return 'Leave not found.';
            return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
        }

        function setMode(mode, id) {
            $form[0].reset();
            $users.val(null).trigger('change');
            clearErrors();
            $form.data('mode', mode).data('id', id || null);

            // the fields of the other mode are disabled so they are not submitted
            $form.find('[data-leave-mode]').each(function () {
                const active = $(this).data('leave-mode') === mode;
                $(this).toggleClass('d-none', !active).find('input, select').prop('disabled', !active);
            });

            $('#leaveModalLabel').text(modes[mode].title);
            $submitBtn.text(modes[mode].button).prop('disabled', false);
        }

        $('#createLeaveBtn').on('click', function () {
            setMode('create');
            $('#leave_from, #leave_to').val(today);
            modal.show();
        });

        $('#selectAllLeaveUsersBtn').on('click', function () {
            $users.find('option').prop('selected', true);
            $users.trigger('change');
        });

        $('#clearLeaveUsersBtn').on('click', function () {
            $users.val(null).trigger('change');
        });

        $(document).on('click', '.edit-leave', function () {
            const id = $(this).data('id');

            $.ajax({
                url: leaveUrl + '/' + id + '/edit',
                method: 'GET',
                dataType: 'json'
            }).done(function (leave) {
                setMode('edit', id);
                $('#leave_user_name').val(leave.user ? leave.user.name : '');
                $('#leave_date').val(leave.leave_date);
                $('#leave_portion').val(leave.portion);
                $('#leave_type').val(leave.type);
                $('#leave_note').val(leave.note);
                modal.show();
            }).fail(function (xhr) {
                toastr.error(errorMessage(xhr, 'Failed to load leave details.'));
            });
        });

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            const isEdit = $form.data('mode') === 'edit';
            const buttonText = $submitBtn.text();

            clearErrors();
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: isEdit ? leaveUrl + '/' + $form.data('id') : leaveUrl,
                method: isEdit ? 'PUT' : 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                modal.hide();
                toastr.success(response.message || 'Leave saved successfully.');
                // keep the current page on edit, jump to the first page to show a new leave
                leaveTable.ajax.reload(null, !isEdit);
            }).fail(function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showErrors(xhr.responseJSON.errors);
                    return;
                }
                toastr.error(errorMessage(xhr, 'Failed to save leave.'));
            }).always(function () {
                $submitBtn.prop('disabled', false).text(buttonText);
            });
        });

        $(document).on('click', '.decide-leave', function () {
            const $button = $(this).prop('disabled', true);

            $.ajax({
                url: leaveUrl + '/' + $button.data('id') + '/' + $button.data('decision'),
                method: 'POST',
                dataType: 'json'
            }).done(function (response) {
                toastr.success(response.message || 'Leave updated successfully.');
                leaveTable.ajax.reload(null, false);
            }).fail(function (xhr) {
                $button.prop('disabled', false);
                toastr.error(errorMessage(xhr, 'Failed to update leave.'));
            });
        });

        $(document).on('click', '.delete-leave', function () {
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
                    url: leaveUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Leave deleted successfully.');
                    leaveTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(errorMessage(xhr, 'Failed to delete leave.'));
                });
            });
        });
    });
</script>
