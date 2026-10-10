<script>
    $(function () {
        const reportUrl = @json(route('daily-reports.index'));
        const reportId = @json($isEdit ? $report->id : null);
        const isNewReport = @json(!$report);
        const can = {
            index: @json((bool) allowed('daily-reports.index')),
            show: @json((bool) allowed('daily-reports.show'))
        };

        const $form = $('#dailyReportForm');
        const $submitBtn = $('#dailyReportSubmitBtn');

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
                const $input = $form.find('[name="' + inputName(field) + '"]').addClass('is-invalid');
                // shown explicitly, as the error of a hidden input has no invalid sibling to reveal it
                $form.find('[data-error-for="' + field + '"]').text(messages[0]).addClass('d-block');

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

            $form.find('.is-invalid:visible').first().trigger('focus');
        }

        function updateSums() {
            $('[data-sum-of]').each(function () {
                let sum = 0;
                $form.find('.' + $(this).data('sum-of')).each(function () {
                    sum += parseInt($(this).val(), 10) || 0;
                });
                $(this).text(sum);
            });
        }

        updateSums();
        $form.on('input', 'input[type="number"]', updateSums);

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            // saving over a report that is already there needs no warning
            if (!isNewReport) {
                saveReport();
                return;
            }

            Swal.fire({
                title: "Submit this report?",
                text: "Once the report is submitted, it can't be edited or deleted later. Please make sure all the information is correct.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, submit it!",
                cancelButtonText: "Review again"
            }).then(function (result) {
                if (result.isConfirmed) saveReport();
            });
        });

        function saveReport() {
            const buttonText = $submitBtn.text();

            clearErrors();
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: reportId ? reportUrl + '/' + reportId : reportUrl,
                method: reportId ? 'PUT' : 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (response) {
                toastr.success(response.message || 'Daily report saved successfully.');

                const target = can.show ? reportUrl + '/' + response.data.id : (can.index ? reportUrl : null);
                if (target) {
                    setTimeout(function () { window.location.href = target; }, 800);
                    return;
                }
                $submitBtn.prop('disabled', false).text('Update');
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
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save daily report.');
            });
        }
    });
</script>
