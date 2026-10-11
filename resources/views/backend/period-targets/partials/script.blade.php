<script>
    $(function () {
        const targetUrl = @json(route('period-targets.index'));
        const month = @json($month->format('Y-m'));

        const $form = $('#periodTargetForm');
        const $submitBtn = $('#periodTargetSubmitBtn');
        const $users = $('#user_ids');

        function clearErrors() {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('[data-error-for]').text('').removeClass('d-block');
        }

        // "projects.0.comments" from the validator is the input named "projects[0][comments]"
        function inputName(field) {
            const parts = field.split('.');
            return parts.shift() + parts.map(function (part) { return '[' + part + ']'; }).join('');
        }

        function showErrors(errors) {
            $.each(errors, function (field, messages) {
                const key = field.indexOf('user_ids') === 0 ? 'user_ids' : field;
                const name = key === 'user_ids' ? 'user_ids[]' : inputName(key);

                $form.find('[name="' + name + '"]').addClass('is-invalid');
                $form.find('[data-error-for="' + key + '"]').text(messages[0]).addClass('d-block');
            });
        }

        $('#selectAllUsersBtn').on('click', function () {
            $users.find('option').prop('selected', true);
            $users.trigger('change');
        });

        $('#clearUsersBtn').on('click', function () {
            $users.val(null).trigger('change');
        });

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            const buttonText = $submitBtn.text();

            clearErrors();
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: targetUrl,
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                toastr.success(response.message || 'Target set successfully.');
                setTimeout(function () { window.location.href = targetUrl + '?month=' + month; }, 800);
            }).fail(function (xhr) {
                $submitBtn.prop('disabled', false).text(buttonText);

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showErrors(xhr.responseJSON.errors);
                    return;
                }
                if (xhr.status === 403) {
                    toastr.error('You do not have permission to perform this action.');
                    return;
                }
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to set the target.');
            });
        });

        $('.period-target-delete').on('click', function () {
            const $button = $(this);

            if (!window.confirm('Delete the monthly target of this user for ' + month + '?')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: targetUrl + '/' + $button.data('user') + '?month=' + month,
                method: 'DELETE',
                dataType: 'json'
            }).done(function (response) {
                toastr.success(response.message);
                setTimeout(function () { window.location.reload(); }, 600);
            }).fail(function () {
                $button.prop('disabled', false);
                toastr.error('Failed to delete the target.');
            });
        });
    });
</script>
