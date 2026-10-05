(function () {
    const switcherCanvas = document.getElementById('switcher-canvas');
    if (!switcherCanvas) {
        return;
    }

    // const saveUrl = @json(route('site-settings.theme'));
    const html = document.documentElement;
    const allowed = {
        theme_style: ['light', 'dark'],
        direction: ['ltr', 'rtl'],
        navigation_style: ['horizontal', 'vertical'],
        navigation_menu_styles: ['menu-click', 'menu-hover', 'icon-click', 'icon-hover', 'default', 'closed', 'icontext', 'overlay', 'detached', 'doublemenu'],
        page_styles: ['regular', 'classic', 'modern'],
        layout_width: ['fullwidth', 'boxed'],
        menu_positions: ['fixed', 'scrollable'],
        header_positions: ['fixed', 'scrollable'],
        page_loader: ['enable', 'disable'],
        menu_colors: ['light', 'dark', 'color', 'gradient', 'transparent'],
    };

    const presetMap = {
        'switcher-primary': 'preset-1',
        'switcher-primary1': 'preset-2',
        'switcher-primary2': 'preset-3',
        'switcher-primary3': 'preset-4',
        'switcher-primary4': 'preset-5',
    };

    const bgPresetMap = {
        'switcher-background': 'preset-1',
        'switcher-background1': 'preset-2',
        'switcher-background2': 'preset-3',
        'switcher-background3': 'preset-4',
        'switcher-background4': 'preset-5',
    };

    let saveTimer = null;
    let lastPayloadHash = null;
    let interacted = false;

    function pickAllowed(value, options, fallback) {
        return options.includes(value) ? value : fallback;
    }

    function normalizeRgb(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        const compact = value.replace(/\s+/g, '');
        return /^\d{1,3},\d{1,3},\d{1,3}$/.test(compact) ? compact : null;
    }

    function detectThemePrimary(primaryCode) {
        for (const [id, key] of Object.entries(presetMap)) {
            const el = document.getElementById(id);
            if (el && el.checked) {
                return key;
            }
        }

        return primaryCode ? 'custom' : null;
    }

    function detectThemeBg(bgCode) {
        for (const [id, key] of Object.entries(bgPresetMap)) {
            const el = document.getElementById(id);
            if (el && el.checked) {
                return key;
            }
        }

        return bgCode ? 'custom' : null;
    }

    function buildPayload() {
        const navigationStyle = pickAllowed(html.getAttribute('data-nav-layout'), allowed.navigation_style, 'horizontal');
        const navStyle = html.getAttribute('data-nav-style');
        const verticalStyle = html.getAttribute('data-vertical-style');
        const primaryCode = normalizeRgb(localStorage.getItem('primaryRGB'));
        const bgCode = normalizeRgb(localStorage.getItem('bodyBgRGB'));

        let navigationMenuStyles = (verticalStyle && verticalStyle !== 'default') ? verticalStyle : (navStyle || 'menu-click');
        if (!allowed.navigation_menu_styles.includes(navigationMenuStyles)) {
            navigationMenuStyles = navigationStyle === 'vertical' ? 'overlay' : 'menu-click';
        }

        const pageStyleAttr = html.getAttribute('data-page-style');
        const pageStyles = pickAllowed(
            pageStyleAttr,
            allowed.page_styles,
            localStorage.getItem('valexclassic') ? 'classic' : (localStorage.getItem('valexmodern') ? 'modern' : 'regular')
        );

        const widthAttr = html.getAttribute('data-width');
        const layoutWidth = pickAllowed(widthAttr, allowed.layout_width, localStorage.getItem('valexboxed') ? 'boxed' : 'fullwidth');

        const menuPositionAttr = html.getAttribute('data-menu-position');
        const menuPositions = pickAllowed(menuPositionAttr, allowed.menu_positions, localStorage.getItem('valexMenuscrollable') ? 'scrollable' : 'fixed');

        const headerPositionAttr = html.getAttribute('data-header-position');
        const headerPositions = pickAllowed(headerPositionAttr, allowed.header_positions, localStorage.getItem('valexHeaderscrollable') ? 'scrollable' : 'fixed');

        const menuColors = pickAllowed(html.getAttribute('data-menu-styles'), allowed.menu_colors, 'light');
        const headerColors = pickAllowed(html.getAttribute('data-header-styles'), allowed.menu_colors, 'light');

        const payload = {
            theme_style: pickAllowed(html.getAttribute('data-theme-mode'), allowed.theme_style, 'light'),
            direction: pickAllowed(html.getAttribute('dir'), allowed.direction, 'ltr'),
            navigation_style: navigationStyle,
            navigation_menu_styles: navigationMenuStyles,
            page_styles: pageStyles,
            layout_width: layoutWidth,
            menu_positions: menuPositions,
            header_positions: headerPositions,
            page_loader: (html.getAttribute('loader') === 'enable' || localStorage.loaderEnable === 'true') ? 'enable' : 'disable',
            menu_colors: menuColors,
            menu_color_code: ['color', 'gradient'].includes(menuColors) ? primaryCode : null,
            header_colors: headerColors,
            header_color_code: ['color', 'gradient'].includes(headerColors) ? primaryCode : null,
            theme_primary: detectThemePrimary(primaryCode),
            theme_primary_code: primaryCode,
            theme_bg_color: detectThemeBg(bgCode),
            theme_bg_color_code: bgCode,
            menu_bg_img: html.getAttribute('data-bg-img') || null,
        };

        return payload;
    }

    function saveThemeSettings() {
        const payload = buildPayload();
        const payloadHash = JSON.stringify(payload);

        if (payloadHash === lastPayloadHash) {
            return;
        }

        lastPayloadHash = payloadHash;

        $.ajax({
            url: saveUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: payload
        }).fail(function (xhr) {
            console.error('Theme settings save failed.', xhr?.responseJSON || xhr?.statusText || xhr);
        });
    }

    function queueSave() {
        if (!interacted) {
            return;
        }

        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveThemeSettings, 400);
    }

    switcherCanvas.addEventListener('click', function () {
        interacted = true;
        setTimeout(queueSave, 120);
    });

    switcherCanvas.addEventListener('change', function () {
        interacted = true;
        queueSave();
    });

    const observer = new MutationObserver(function () {
        queueSave();
    });

    observer.observe(html, {
        attributes: true,
        attributeFilter: [
            'dir',
            'style',
            'loader',
            'data-theme-mode',
            'data-nav-layout',
            'data-nav-style',
            'data-vertical-style',
            'data-page-style',
            'data-width',
            'data-menu-position',
            'data-header-position',
            'data-menu-styles',
            'data-header-styles',
            'data-bg-img',
        ],
    });
})();
