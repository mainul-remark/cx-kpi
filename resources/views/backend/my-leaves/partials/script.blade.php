<script>
    $(function () {
        const leaveUrl = @json(route('my-leaves.index'));
        const can = {
            destroy: @json((bool) allowed('my-leaves.destroy'))
        };
        const portions = @json($portions);
        const types = @json($types);
        const statuses = @json($statuses);
        const statusClasses = { pending: 'text-bg-warning', approved: 'text-bg-success', rejected: 'text-bg-danger' };
        const today = @json(today()->toDateString());

        const $form = $('#myLeaveForm');
        const $submitBtn = $('#myLeaveSubmitBtn');
        const modal = bootstrap.Modal.getOrCreateInstance($('#myLeaveModal')[0]);

        function escape(text) {
            return $('<div>').text(text || '').html();
        }

        function label(labels) {
            return function (data, type) {
                return type === 'display' ? escape(labels[data] || data) : data;
            };
        }

        const leaveTable = $('#myLeaveDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: leaveUrl
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
                { data: 'leave_date', name: 'leave_date' },
                { data: 'portion', name: 'portion', render: label(portions) },
                { data: 'type', name: 'type', render: label(types) },
                { data: 'note', name: 'note', orderable: false, className: 'text-wrap', render: label({}) },
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
                    data: 'id', name: 'id', orderable: false, searchable: false, width: '80px',
                    render: function (id, type, row) {
                        // a request that was decided on stays as it is
                        return can.destroy && row.status === 'pending'
                            ? '<button type="button" class="btn btn-outline-danger btn-sm withdraw-leave" data-id="' + id + '" title="Withdraw Request"><i class="fa fa-trash-alt"></i></button>'
                            : '';
                    }
                }
            ]
        });

        function clearErrors() {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('[data-error-for]').text('');
        }

        function showErrors(errors) {
            $.each(errors, function (field, messages) {
                $form.find('[name="' + field + '"]').addClass('is-invalid');
                $form.find('[data-error-for="' + field + '"]').text(messages[0]);
            });
            $form.find('.is-invalid').first().trigger('focus');
        }

        function errorMessage(xhr, fallback) {
            if (xhr.status === 403) return 'You do not have permission to perform this action.';
            if (xhr.status === 404) return 'Leave not found.';
            return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
        }

        $('#requestLeaveBtn').on('click', function () {
            $form[0].reset();
            clearErrors();
            $('#my_leave_from, #my_leave_to').val(today);
            modal.show();
        });

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            const buttonText = $submitBtn.text();

            clearErrors();
            $submitBtn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: leaveUrl,
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                modal.hide();
                toastr.success(response.message || 'Leave requested successfully.');
                leaveTable.ajax.reload();
            }).fail(function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showErrors(xhr.responseJSON.errors);
                    return;
                }
                toastr.error(errorMessage(xhr, 'Failed to send the leave request.'));
            }).always(function () {
                $submitBtn.prop('disabled', false).text(buttonText);
            });
        });

        $(document).on('click', '.withdraw-leave', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: "Withdraw this request?",
                text: "You can ask for the leave again afterwards.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, withdraw it!"
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: leaveUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Leave request withdrawn successfully.');
                    leaveTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(errorMessage(xhr, 'Failed to withdraw the leave request.'));
                    leaveTable.ajax.reload(null, false);
                });
            });
        });
    });
</script>
