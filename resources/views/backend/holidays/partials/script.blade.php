<script>
    $(function () {
        const holidayUrl = @json(route('holidays.index'));
        const can = {
            edit: @json((bool) allowed('holidays.edit')),
            destroy: @json((bool) allowed('holidays.destroy'))
        };

        const $modal = $('#holidayModal');
        const $form = $('#holidayForm');
        const $submitBtn = $('#holidaySubmitBtn');
        const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
        const modes = {
            create: { title: 'Create Holiday', button: 'Save' },
            edit: { title: 'Edit Holiday', button: 'Update' }
        };

        const holidayTable = $('#holidayDataTable').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            order: [],
            ajax: {
                url: holidayUrl
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '60px' },
                { data: 'holiday_date', name: 'holiday_date' },
                {
                    data: 'title', name: 'title',
                    render: function (data, type) {
                        return type === 'display' ? $('<div>').text(data || '').html() : data;
                    }
                },
                {
                    data: 'id', name: 'id', orderable: false, searchable: false, width: '100px',
                    render: function (id) {
                        let buttons = '';
                        if (can.edit) {
                            buttons += '<a href="javascript:void(0)" class="btn btn-outline-primary btn-sm edit-holiday" data-id="' + id + '" title="Edit Holiday"><i class="fa fa-pencil-alt"></i></a>';
                        }
                        if (can.destroy) {
                            buttons += '<button type="button" class="btn btn-outline-danger btn-sm ms-2 delete-holiday" data-id="' + id + '" title="Delete Holiday"><i class="fa fa-trash-alt"></i></button>';
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
            if (xhr.status === 404) return 'Holiday not found.';
            return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
        }

        function setMode(mode, id) {
            $form[0].reset();
            clearErrors();
            $form.data('mode', mode).data('id', id || null);
            $('#holidayModalLabel').text(modes[mode].title);
            $submitBtn.text(modes[mode].button).prop('disabled', false);
        }

        $('#createHolidayBtn').on('click', function () {
            setMode('create');
            modal.show();
        });

        $(document).on('click', '.edit-holiday', function () {
            const id = $(this).data('id');

            $.ajax({
                url: holidayUrl + '/' + id + '/edit',
                method: 'GET',
                dataType: 'json'
            }).done(function (holiday) {
                setMode('edit', id);
                $('#holiday_date').val(holiday.holiday_date);
                $('#holiday_title').val(holiday.title);
                modal.show();
            }).fail(function (xhr) {
                toastr.error(errorMessage(xhr, 'Failed to load holiday details.'));
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
                url: isEdit ? holidayUrl + '/' + $form.data('id') : holidayUrl,
                method: isEdit ? 'PUT' : 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                modal.hide();
                toastr.success(response.message || 'Holiday saved successfully.');
                // keep the current page on edit, jump to the first page to show a new holiday
                holidayTable.ajax.reload(null, !isEdit);
            }).fail(function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showErrors(xhr.responseJSON.errors);
                    return;
                }
                toastr.error(errorMessage(xhr, 'Failed to save holiday.'));
            }).always(function () {
                $submitBtn.prop('disabled', false).text(buttonText);
            });
        });

        const $importModal = $('#holidayImportModal');
        const $importForm = $('#holidayImportForm');
        const $importFile = $('#holiday_import_file');
        const $importSubmitBtn = $('#holidayImportSubmitBtn');
        const importModal = bootstrap.Modal.getOrCreateInstance($importModal[0]);

        function clearImportErrors() {
            $importFile.removeClass('is-invalid');
            $('#holidayImportFileError').text('');
            $('#holidayImportRowErrors').empty();
            $('#holidayImportRowErrorsWrap').addClass('d-none');
        }

        function showImportFileError(message) {
            $importFile.addClass('is-invalid');
            $('#holidayImportFileError').text(message);
        }

        $('#importHolidayBtn').on('click', function () {
            importModal.show();
        });

        $importModal.on('hidden.bs.modal', function () {
            $importForm[0].reset();
            clearImportErrors();
        });

        $importFile.on('change', clearImportErrors);

        $importForm.on('submit', function (e) {
            e.preventDefault();
            clearImportErrors();

            if (!$importFile[0].files.length) {
                showImportFileError('Please select an import file.');
                return;
            }

            const formData = new FormData();
            formData.append('file', $importFile[0].files[0]);

            $importSubmitBtn.prop('disabled', true).text('Importing...');

            $.ajax({
                url: @json(route('holidays.import')),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (response) {
                importModal.hide();
                toastr.success(response.message || 'Holidays imported successfully.');
                holidayTable.ajax.reload();
            }).fail(function (xhr) {
                const errors = xhr.status === 422 && xhr.responseJSON ? xhr.responseJSON.errors : null;

                if (errors && errors.file) {
                    showImportFileError(errors.file[0]);
                    return;
                }
                // one entry per row of the file that could not be read
                if (Array.isArray(errors)) {
                    $('#holidayImportRowErrors').html(errors.map(function (failure) {
                        return $('<li>').text('Row ' + failure.row + ': ' + failure.errors.join(' ')).prop('outerHTML');
                    }).join(''));
                    $('#holidayImportRowErrorsWrap').removeClass('d-none');
                }
                toastr.error(errorMessage(xhr, 'Failed to import holidays.'));
            }).always(function () {
                $importSubmitBtn.prop('disabled', false).text('Import');
            });
        });

        $(document).on('click', '.delete-holiday', function () {
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
                    url: holidayUrl + '/' + id,
                    method: 'DELETE',
                    dataType: 'json'
                }).done(function (response) {
                    toastr.success(response.message || 'Holiday deleted successfully.');
                    holidayTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(errorMessage(xhr, 'Failed to delete holiday.'));
                });
            });
        });
    });
</script>
