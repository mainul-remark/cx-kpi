<script>
    $(function () {
        const socialPlatformUrl = @json(route('social-platforms.index'));
        const can = {
            show: @json((bool) allowed('social-platforms.show')),
            edit: @json((bool) allowed('social-platforms.edit')),
            destroy: @json((bool) allowed('social-platforms.destroy'))
        };

        const $modal = $('#socialPlatformModal');
        const $form = $('#socialPlatformForm');
        const $submitBtn = $('#socialPlatformSubmitBtn');
        const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
        const modes = {
            create: { title: 'Create Social Platform', button: 'Save' },
            edit: { title: 'Edit Social Platform', button: 'Update' },
            show: { title: 'Social Platform Details', button: '' }
        };

        const socialPlatformTable = $('#socialPlatformDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: socialPlatformUrl
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
                { data: 'name', name: 'name' },
                {
                    data: 'notes', name: 'notes',
                    render: function (data, type) {
                        if (type !== 'display' || !data) return data || '';
                        return '<span class="d-inline-block text-truncate align-bottom" style="max-width: 320px">' + data + '</span>';
                    }
                },
                { data: 'slug', name: 'slug' },
                {
                    data: 'active', name: 'active', searchable: false,
                    render: function (data, type, row) {
                        if (type !== 'display') return data;

                        let html = data
                            ? '<span class="badge text-bg-success">Active</span>'
                            : '<span class="badge text-bg-danger">Inactive</span>';

                        if (row.has_outbound_calls) html += ' <span class="badge text-bg-primary">Outbound Calls</span>';
                        if (row.has_comments) html += ' <span class="badge text-bg-info">Comments</span>';
                        if (row.has_message_replies) html += ' <span class="badge text-bg-warning">Message Replies</span>';

                        return html;
                    }
                },
                {
                    data: 'id', name: 'id', orderable: false, searchable: false, width: '140px',
                    render: function (id) {
                        let buttons = '';
                        if (can.show) {
                            buttons += '<a href="javascript:void(0)" class="btn btn-outline-primary btn-sm view-social-platform" data-id="' + id + '" title="View Social Platform"><i class="fa fa-eye"></i></a>';
                        }
                        if (can.edit) {
                            buttons += '<a href="javascript:void(0)" class="btn btn-outline-primary btn-sm ms-2 edit-social-platform" data-id="' + id + '" title="Edit Social Platform"><i class="fa fa-pencil-alt"></i></a>';
                        }
                        if (can.destroy) {
                            buttons += '<button type="button" class="btn btn-outline-danger btn-sm ms-2 delete-social-platform" data-id="' + id + '" title="Delete Social Platform"><i class="fa fa-trash-alt"></i></button>';
                        }
                        return buttons;
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
            if (xhr.status === 404) return 'Social Platform not found.';
            return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
        }

        function setMode(mode, id) {
            const readOnly = mode === 'show';

            $form[0].reset();
            clearErrors();
            $form.data('mode', mode).data('id', id || null);
            $('#socialPlatformModalLabel').text(modes[mode].title);
            $submitBtn.text(modes[mode].button).toggleClass('d-none', readOnly).prop('disabled', false);
            $form.find('[name]').prop('disabled', readOnly);
            $('#socialPlatformSlugGroup').toggleClass('d-none', !readOnly);
        }

        function fillForm(socialPlatform) {
            $('#social_platform_name').val(socialPlatform.name);
            $('#social_platform_slug').val(socialPlatform.slug);
            $('#social_platform_notes').val(socialPlatform.notes || '');
            $('#social_platform_has_outbound_calls').prop('checked', !!socialPlatform.has_outbound_calls);
            $('#social_platform_has_comments').prop('checked', !!socialPlatform.has_comments);
            $('#social_platform_has_message_replies').prop('checked', !!socialPlatform.has_message_replies);
            $('#social_platform_active').prop('checked', !!socialPlatform.active);
        }

        function openWithSocialPlatform(mode, id) {
            $.ajax({
                url: socialPlatformUrl + '/' + id + (mode === 'edit' ? '/edit' : ''),
                method: 'GET',
                dataType: 'json'
            }).done(function (socialPlatform) {
                setMode(mode, id);
                fillForm(socialPlatform);
                modal.show();
            }).fail(function (xhr) {
                toastr.error(errorMessage(xhr, 'Failed to load social platform details.'));
            });
        }

        $('#createSocialPlatformBtn').on('click', function () {
            setMode('create');
            modal.show();
        });

        $(document).on('click', '.view-social-platform', function () {
            openWithSocialPlatform('show', $(this).data('id'));
        });

        $(document).on('click', '.edit-social-platform', function () {
            openWithSocialPlatform('edit', $(this).data('id'));
        });

        $modal.on('shown.bs.modal', function () {
            if ($form.data('mode') !== 'show') {
                $('#social_platform_name').trigger('focus');
            }
        });

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            const mode = $form.data('mode');
            if (mode === 'show') return;

            const isEdit = mode === 'edit';
            const buttonText = $submitBtn.text();

            clearErrors();
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: isEdit ? socialPlatformUrl + '/' + $form.data('id') : socialPlatformUrl,
                method: isEdit ? 'PUT' : 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                modal.hide();
                toastr.success(response.message || 'Social Platform saved successfully.');
                // keep the current page on edit, jump to the first page to show a new social platform
                socialPlatformTable.ajax.reload(null, !isEdit);
            }).fail(function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showErrors(xhr.responseJSON.errors);
                    return;
                }
                toastr.error(errorMessage(xhr, 'Failed to save social platform.'));
            }).always(function () {
                $submitBtn.prop('disabled', false).text(buttonText);
            });
        });

        $(document).on('click', '.delete-social-platform', function () {
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
                    url: socialPlatformUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Social Platform deleted successfully.');
                    socialPlatformTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(errorMessage(xhr, 'Failed to delete social platform.'));
                });
            });
        });
    });
</script>
