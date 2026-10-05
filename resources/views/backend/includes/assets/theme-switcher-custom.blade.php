@if($siteSetting)
    <script>
        (function () {
            const theme = @json($themeBootstrap);
            const navStyleValues = ['menu-click', 'menu-hover', 'icon-click', 'icon-hover'];
            const verticalStyleValues = ['default', 'closed', 'icontext', 'overlay', 'detached', 'doublemenu'];

            function setBooleanKey(key, enabled) {
                if (enabled) {
                    localStorage.setItem(key, 'true');
                } else {
                    localStorage.removeItem(key);
                }
            }

            setBooleanKey('valexdarktheme', theme.theme_style === 'dark');

            if (theme.direction === 'rtl') {
                setBooleanKey('valexrtl', true);
                localStorage.removeItem('valexltr');
            } else {
                setBooleanKey('valexltr', true);
                localStorage.removeItem('valexrtl');
            }

            if (theme.navigation_style === 'horizontal') {
                localStorage.setItem('valexlayout', 'horizontal');
            } else {
                localStorage.removeItem('valexlayout');
            }

            if (navStyleValues.includes(theme.navigation_menu_styles)) {
                localStorage.setItem('valexnavstyles', theme.navigation_menu_styles);
                localStorage.removeItem('valexverticalstyles');
            } else if (verticalStyleValues.includes(theme.navigation_menu_styles) && theme.navigation_menu_styles !== 'default') {
                localStorage.setItem('valexverticalstyles', theme.navigation_menu_styles);
                localStorage.removeItem('valexnavstyles');
            } else {
                localStorage.setItem('valexnavstyles', 'menu-click');
                localStorage.removeItem('valexverticalstyles');
            }

            localStorage.removeItem('valexregular');
            localStorage.removeItem('valexclassic');
            localStorage.removeItem('valexmodern');
            if (theme.page_styles === 'classic') {
                setBooleanKey('valexclassic', true);
            } else if (theme.page_styles === 'modern') {
                setBooleanKey('valexmodern', true);
            } else {
                setBooleanKey('valexregular', true);
            }

            if (theme.layout_width === 'boxed') {
                setBooleanKey('valexboxed', true);
                localStorage.removeItem('valexfullwidth');
            } else {
                setBooleanKey('valexfullwidth', true);
                localStorage.removeItem('valexboxed');
            }

            setBooleanKey('valexMenufixed', theme.menu_positions === 'fixed');
            setBooleanKey('valexMenuscrollable', theme.menu_positions === 'scrollable');
            setBooleanKey('valexHeaderfixed', theme.header_positions === 'fixed');
            setBooleanKey('valexHeaderscrollable', theme.header_positions === 'scrollable');

            localStorage.setItem('loaderEnable', theme.page_loader === 'enable' ? 'true' : 'false');
            localStorage.setItem('valexMenu', theme.menu_colors);
            localStorage.setItem('valexHeader', theme.header_colors);

            if (theme.theme_primary_code) {
                localStorage.setItem('primaryRGB', String(theme.theme_primary_code).replace(/\s+/g, ''));
            } else {
                localStorage.removeItem('primaryRGB');
            }

            if (theme.theme_bg_color_code) {
                var bgParts = String(theme.theme_bg_color_code).replace(/\s+/g, '').split(',').map(Number);
                localStorage.setItem('bodyBgRGB', bgParts[0] + ', ' + bgParts[1] + ', ' + bgParts[2]);
                localStorage.setItem('bodylightRGB', (bgParts[0]+14) + ', ' + (bgParts[1]+14) + ', ' + (bgParts[2]+14));
            } else {
                localStorage.removeItem('bodyBgRGB');
                localStorage.removeItem('bodylightRGB');
            }

            if (theme.menu_bg_img) {
                localStorage.setItem('bgimg', theme.menu_bg_img);
            } else {
                localStorage.removeItem('bgimg');
            }
        })();
    </script>
@endif

@if($siteSetting?->theme_bg_color_code)
    @php
        $bgParts = array_map('intval', explode(',', $siteSetting->theme_bg_color_code));
        $bgLightParts = [($bgParts[0] ?? 0) + 14, ($bgParts[1] ?? 0) + 14, ($bgParts[2] ?? 0) + 14];
    @endphp
    <style>
        html {
            --body-bg-rgb: {{ $bgParts[0] }}, {{ $bgParts[1] }}, {{ $bgParts[2] }};
            --body-bg-rgb2: {{ $bgLightParts[0] }}, {{ $bgLightParts[1] }}, {{ $bgLightParts[2] }};
            --light-rgb: {{ $bgLightParts[0] }}, {{ $bgLightParts[1] }}, {{ $bgLightParts[2] }};
            --form-control-bg: rgb({{ $bgLightParts[0] }}, {{ $bgLightParts[1] }}, {{ $bgLightParts[2] }});
            --input-border: rgba(255,255,255,0.1);
        }
    </style>
@endif

@if($siteSetting?->theme_primary_code)
    <style>
        html {
            --primary-rgb: {{ $siteSetting->theme_primary_code }};
        }
    </style>
@endif
