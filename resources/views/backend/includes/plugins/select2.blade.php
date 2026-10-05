<link rel="stylesheet" href="{{ asset('backend/build/select2-4.1.0/select2.min.css') }}" />
<link rel="stylesheet" href="{{ asset('backend/build/select2-4.1.0/select2-bootstrap-5-theme.min.css') }}" />
<script src="{{ asset('backend/build/select2-4.1.0/select2.min.js') }}"></script>
<script>
    $(function () {
        $('.select-ele').each(function () {
            const $modal = $(this).closest('.modal');
            const placeholder = $(this).data('placeholder');
            const options = {
                theme: "bootstrap-5",
                dropdownParent: $modal.length ? $modal : $(this).parent(),
            };
            if (placeholder) {
                options.placeholder = placeholder;
                // allowClear needs a single-value select with an empty option
                options.allowClear = !$(this).prop('multiple') && $(this).find('option[value=""]').length > 0;
            }
            $(this).select2(options);
        });
    })
</script>
