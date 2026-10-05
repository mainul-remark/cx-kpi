@php
    $siteSetting = \App\Models\SiteSetting::first();

    $themeStyle = $siteSetting?->theme_style === 'dark' ? 'dark' : 'light';
    $direction = $siteSetting?->direction === 'rtl' ? 'rtl' : 'ltr';
    $navigationStyle = $siteSetting?->navigation_style === 'horizontal' ? 'horizontal' : 'vertical';

    $navStyleChoices = ['menu-click', 'menu-hover', 'icon-click', 'icon-hover'];
    $verticalStyleChoices = ['default', 'closed', 'icontext', 'overlay', 'detached', 'doublemenu'];
    $navigationMenuStyles = $siteSetting?->navigation_menu_styles;
    $navStyleAttr = in_array($navigationMenuStyles, $navStyleChoices, true) ? $navigationMenuStyles : 'menu-click';
    $verticalStyleAttr = in_array($navigationMenuStyles, $verticalStyleChoices, true) ? $navigationMenuStyles : 'overlay';

    $pageStyles = in_array($siteSetting?->page_styles, ['regular', 'classic', 'modern'], true) ? $siteSetting->page_styles : 'regular';
    $layoutWidth = $siteSetting?->layout_width === 'boxed' ? 'boxed' : 'fullwidth';
    $menuPositions = $siteSetting?->menu_positions === 'scrollable' ? 'scrollable' : 'fixed';
    $headerPositions = $siteSetting?->header_positions === 'scrollable' ? 'scrollable' : 'fixed';
    $pageLoader = $siteSetting?->page_loader === 'enable' ? 'enable' : 'disable';
    $menuColors = in_array($siteSetting?->menu_colors, ['light', 'dark', 'color', 'gradient', 'transparent'], true) ? $siteSetting->menu_colors : 'light';
    $headerColors = in_array($siteSetting?->header_colors, ['light', 'dark', 'color', 'gradient', 'transparent'], true) ? $siteSetting->header_colors : 'light';

    $themeBootstrap = [
        'theme_style' => $themeStyle,
        'direction' => $direction,
        'navigation_style' => $navigationStyle,
        'navigation_menu_styles' => $navigationMenuStyles,
        'page_styles' => $pageStyles,
        'layout_width' => $layoutWidth,
        'menu_positions' => $menuPositions,
        'header_positions' => $headerPositions,
        'page_loader' => $pageLoader,
        'menu_colors' => $menuColors,
        'header_colors' => $headerColors,
        'theme_primary_code' => $siteSetting?->theme_primary_code,
        'theme_bg_color_code' => $siteSetting?->theme_bg_color_code,
        'menu_bg_img' => $siteSetting?->menu_bg_img,
    ];

    $menuBgImg = in_array($siteSetting?->menu_bg_img, ['bgimg1', 'bgimg2', 'bgimg3', 'bgimg4', 'bgimg5'], true) ? $siteSetting->menu_bg_img : null;
@endphp

<!DOCTYPE html>
<html lang="en" dir="{{ $direction }}" data-nav-layout="{{ $navigationStyle }}" @if($navigationStyle === 'horizontal') data-nav-style="{{ $navStyleAttr }}" @else data-vertical-style="{{ $verticalStyleAttr }}" @endif data-page-style="{{ $pageStyles }}" data-width="{{ $layoutWidth }}" data-menu-position="{{ $menuPositions }}" data-header-position="{{ $headerPositions }}" data-theme-mode="{{ $themeStyle }}" data-header-styles="{{ $headerColors }}" data-menu-styles="{{ $menuColors }}" data-toggled="close" @if($menuBgImg) data-bg-img="{{ $menuBgImg }}" @endif loader="{{ $pageLoader }}">

<meta http-equiv="content-type" content="text/html;charset=UTF-8" /><!-- /Added by HTTrack -->
<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="Description" content="Laravel Bootstrap Responsive Admin Web Dashboard Template">
    <meta name="Author" content="Spruko Technologies Private Limited">
    <meta name="keywords" content="laravel, framework laravel, laravel template, admin, laravel dashboard, template dashboard, admin dashboard ui, bootstrap dashboard, laravel framework, vite laravel, bootstrap 5 templates, laravel admin panel, laravel tailwind, admin panel, template admin, bootstrap admin panel.">
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- TITLE -->
    <title> Remark HB - @yield('title') </title>

    @include('backend.includes.assets.theme-switcher-custom')

    @include('backend.includes.assets.style')
</head>

<body class="">
{{--<div class="alert alert-primary" role="alert">--}}
{{--    <p class="">This is prototype </p>--}}
{{--</div>--}}

<!-- Switcher -->
@include('backend.includes.switcher')
<!-- End switcher -->

<!-- Loader -->
<div id="loader" >
    <img src="{{ asset('/') }}backend/build/assets/images/media/loader.svg" alt="">
</div>
<!-- Loader -->

<div class="page ">

    <!-- Main-Header -->
    @include('backend.includes.header')
    <!-- End Main-Header -->




    <!-- Country-selector modal -->
    <!-- Start::Off-canvas sidebar-->
{{--    @include('backend.includes.notification-right-side')--}}
    <!-- End::Off-canvas sidebar-->

    <!-- End Country-selector modal -->

    <!--Main-Sidebar-->
    @include('backend.includes.menu')

    <!-- End Main-Sidebar-->

    <!-- Start::app-content -->
    <div class="main-content app-content card pb-5" style="border-radius: 0px!important; box-shadow: none;">


        <div class="alert alert-primary text-center mb-0" role="alert">
            <strong>Prototype Notice:</strong>
            This application is currently open for testing and review. Some features may not work as expected
            Please report any issues to the developer or contact us via WhatsApp at 01646688970.
        </div>

        @yield('body')
    </div>
    <!-- End::content  -->

    <!-- Footer opened -->
    @include('backend.includes.footer')
    <!-- End Footer -->



</div>

<!-- Modals -->
@yield('modal')

<!-- SCRIPTS -->
<!-- Scroll To Top -->
<div class="scrollToTop">
    <span class="arrow"><i class="las la-angle-double-up"></i></span>
</div>
<div id="responsive-overlay"></div>
<!-- Scroll To Top -->

@include('backend.includes.assets.script')


</body>


</html>
