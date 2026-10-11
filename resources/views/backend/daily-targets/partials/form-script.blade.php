<script>
    $(function () {
        const targetUrl = @json(route('daily-targets.index'));
        const can = {
            index: @json((bool) allowed('daily-targets.index'))
        };

        const $form = $('#dailyTargetForm');
        const $submitBtn = $('#dailyTargetSubmitBtn');
        const $users = $('#user_ids');

        function clearErrors() {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('[data-error-for]').text('').removeClass('d-block');
            $form.find('[data-tab-errors]').text('').addClass('d-none');
        }

        // "projects.0.total_calls" from the validator is the input named "projects[0][total_calls]"
        function inputName(field) {
            const parts = field.split('.');
            return parts.shift() + parts.map(function (part) { return '[' + part + ']'; }).join('');
        }

        function showErrors(errors) {
            const perTab = {};
            let firstTab = null;

            $.each(errors, function (field, messages) {
                // "user_ids.2" is an entry of the one user select
                const key = field.indexOf('user_ids') === 0 ? 'user_ids' : field;
                const name = key === 'user_ids' ? 'user_ids[]' : inputName(key);

                const $input = $form.find('[name="' + name + '"]').addClass('is-invalid');
                // shown explicitly, as the error of a hidden input has no invalid sibling to reveal it
                $form.find('[data-error-for="' + key + '"]').text(messages[0]).addClass('d-block');

                // tell which tab holds the error, as the other one is hidden
                const pane = $input.first().closest('.tab-pane').attr('id');
                if (pane) {
                    perTab[pane] = (perTab[pane] || 0) + 1;
                    firstTab = firstTab || pane;
                }
            });

            $.each(perTab, function (pane, total) {
                $form.find('[data-tab-errors="' + pane + '"]').text(total).removeClass('d-none');
            });

            // open the first tab with an error, unless the open one already has one
            const open = $form.find('.tab-pane.active').attr('id');
            if (firstTab && !perTab[open]) {
                bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#' + firstTab + '"]')).show();
            }

            const $first = $form.find('[data-error-for].d-block').first();
            if ($first.length) {
                $first.closest('.card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
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

                if (can.index) {
                    setTimeout(function () { window.location.href = targetUrl; }, 800);
                    return;
                }
                $submitBtn.prop('disabled', false).text(buttonText);
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
    });
</script>
