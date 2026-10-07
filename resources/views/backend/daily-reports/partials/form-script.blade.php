<script>
    $(function () {
        const reportUrl = @json(route('daily-reports.index'));
        const createUrl = @json(route('daily-reports.create'));
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
        }

        // "projects.0.total_calls" from the validator is the input named "projects[0][total_calls]"
        function inputName(field) {
            const parts = field.split('.');
            return parts.shift() + parts.map(function (part) { return '[' + part + ']'; }).join('');
        }

        function showErrors(errors) {
            $.each(errors, function (field, messages) {
                $form.find('[name="' + inputName(field) + '"]').addClass('is-invalid');
                // shown explicitly, as the error of a hidden input has no invalid sibling to reveal it
                $form.find('[data-error-for="' + field + '"]').text(messages[0]).addClass('d-block');
            });
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
        $form.on('input', '.platform-count, .project-count', updateSums);

        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
        });

        // a new report follows the chosen day, so load whatever was already saved for it
        if (!reportId) {
            $('#report_date').on('change', function () {
                if (this.value) {
                    window.location.href = createUrl + '?date=' + encodeURIComponent(this.value);
                }
            });
        }

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
