<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
    @php
        // type => [body-bg, header-bg]
        $toastConfigs = [
            'primary'   => ['bg-primary-transparent',   'bg-primary text-fixed-white'],
            'secondary' => ['bg-secondary-transparent', 'bg-secondary text-fixed-white'],
            'success'   => ['bg-success-transparent',   'bg-success text-fixed-white'],
            'danger'    => ['bg-danger-transparent',    'bg-danger text-fixed-white'],
            'warning'   => ['bg-warning-transparent',   'bg-warning text-fixed-white'],
            'info'      => ['bg-info-transparent',      'bg-info text-fixed-white'],
        ];
    @endphp

    @foreach($toastConfigs as $key => [$bodyBg, $headerBg])
        <div id="{{ $key }}Toast"
             class="toast colored-toast {{ $bodyBg }} mb-2"
             role="alert"
             aria-live="assertive"
             aria-atomic="true">

            <div class="toast-header {{ $headerBg }}">
                <img class="bd-placeholder-img rounded me-2"
                     src="{{ asset('backend/remark.png') }}"
                     alt="...">
                <strong class="me-auto">Remark</strong>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="toast"
                        aria-label="Close"></button>
            </div>

            <div class="toast-body fw-semibold">
                {{-- default text, JS/session diye overwrite hobe --}}
                Your toast message here.
            </div>
        </div>
    @endforeach
</div>

@if(Session::has('message'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Laravel er alert-type (eg: success, info, warning, error, danger, primary...)
            let type = "{{ Session::get('alert-type', 'info') }}";
            const msg  = @json(Session::get('message'));

            // error ke danger e map korlam
            const typeMap = {
                error : 'danger'
            };
            type = typeMap[type] || type;

            const toastId = type + 'Toast';
            const el = document.getElementById(toastId);
            if (!el) return;

            const body = el.querySelector('.toast-body');
            if (body) body.textContent = msg;

            const toast = new bootstrap.Toast(el, {
                delay: 4000,
                autohide: true
            });

            toast.show();
        });
    </script>
@endif

<script>
    function showAjaxToast(type, message) {

        const typeMap = {
            error: 'danger',
            success: 'success',
            info: 'info',
            warning: 'warning',
            primary: 'primary',
            secondary: 'secondary'
        };

        type = typeMap[type] || 'info';

        const toastId = type + 'Toast';
        const el = document.getElementById(toastId);

        if (!el) return;

        // update toast body message
        const body = el.querySelector('.toast-body');
        if (body) body.textContent = message;

        // show toast
        const toast = new bootstrap.Toast(el, {
            delay: 4000,
            autohide: true
        });

        toast.show();
    }
</script>
