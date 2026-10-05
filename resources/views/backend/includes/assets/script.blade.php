<!-- jQuery (Select2 compatible) -->
<script src="{{ asset('/backend/build/code.jquery.com/jquery-3.6.1.min.js') }}"></script>

<script>
    const base_url = "{!! url('/') !!}/";
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $(function () {
        // show arrow down icon on horizontal menu view
        // var dataLayout = $('html').attr('data-nav-layout');
        // if (dataLayout == 'horizontal')
        // {
        //     $('.has-sub .fe-chevron-right').each(function () {
        //         $(this).removeClass('fe-chevron-right').addClass('fe-chevron-down');
        //     })
        // }
    })
</script>

<!-- Popper JS -->
<script src="{{ asset('/') }}backend/build/assets/libs/%40popperjs/core/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="{{ asset('/') }}backend/build/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Node Waves JS-->
<script src="{{ asset('/') }}backend/build/assets/libs/node-waves/waves.min.js"></script>

<!-- Simplebar JS -->
<script src="{{ asset('/') }}backend/build/assets/libs/simplebar/simplebar.min.js"></script>
<link rel="modulepreload" href="{{ asset('/') }}backend/build/assets/simplebar-B35Aj-bA.js" />
<script type="module" src="{{ asset('/') }}backend/build/assets/simplebar-B35Aj-bA.js"></script>
<!-- Color Picker JS -->
<script src="{{ asset('/') }}backend/build/assets/libs/%40simonwep/pickr/pickr.es5.min.js"></script>

<!-- custom scripts -->
<script src="{{ asset('/backend/build/assets/libs/helper/script.js') }}"></script>
<!-- custom template scripts -->
<script src="{{ asset('/backend/build/custom-design/script.js') }}"></script>

@yield('scripts')
@stack('scripts')


<!-- Sticky JS -->
<script src="{{ asset('/') }}backend/build/assets/sticky.js"></script>

<!-- Custom-Switcher JS -->
<link rel="modulepreload" href="{{ asset('/') }}backend/build/assets/custom-switcher-CDFJCGB8.js" />
<script type="module" src="{{ asset('/') }}backend/build/assets/custom-switcher-CDFJCGB8.js"></script>
<!-- APP JS-->
<link rel="modulepreload" href="{{ asset('/backend/build/assets/app-ClKBXEo6.js') }}" />
<script type="module" src="{{ asset('/backend/build/assets/app-ClKBXEo6.js') }}"></script>
<!-- Center horizontal menu - JS files have been modified to use removeProperty instead of setting margin to 0 -->
<script>const saveUrl = @json(route('site-settings.theme'));</script>
<script src="{{ asset('/backend/build/custom-mod-assets/js/master-file-theme-settings.js') }}"></script>
<!-- END SCRIPTS -->

