<aside class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div class="main-sidebar-header">
        <a href="{{ route('admin.dashboard') }}" class="header-logo">
{{--            <img src="{{ asset('/') }}backend/build/assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">--}}
{{--            <img src="{{ asset('/') }}backend/build/assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">--}}
{{--            <img src="{{ asset('/') }}backend/build/assets/images/brand-logos/desktop-white.png" alt="logo" class="desktop-white">--}}
{{--            <img src="{{ asset('/') }}backend/build/assets/images/brand-logos/toggle-white.png" alt="logo" class="toggle-white">--}}
            <img src="{{ asset('/backend/remark.png') }}" alt="logo" class="desktop-logo">
            <img src="{{ asset('/backend/remark-logo.png') }}" alt="logo" class="toggle-logo">
            <img src="{{ asset('/backend/remark.png') }}" alt="logo" class="desktop-white">
            <img src="{{ asset('/backend/remark-logo.png') }}" alt="logo" class="toggle-white">
        </a>
    </div>
    <!-- End::main-sidebar-header -->

    <!-- Start::main-sidebar -->
    <div class="main-sidebar" id="sidebar-scroll">

        <!-- Start::nav -->
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path> </svg>
            </div>
            <ul class="main-menu ">
                <!-- Start::slide__category -->
                <li class="slide__category"><span class="category-name">Main</span></li>
                <!-- End::slide__category -->

                <!-- Start::slide -->

                @allowed('admin.dashboard')
                    <li class="slide">
                        <a href="{{ route('admin.dashboard') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24" ><path d="M0 0h24v24H0V0z" fill="none"/><path d="M5 5h4v6H5zm10 8h4v6h-4zM5 17h4v2H5zM15 5h4v2h-4z" opacity=".3"/><path d="M3 13h8V3H3v10zm2-8h4v6H5V5zm8 16h8V11h-8v10zm2-8h4v6h-4v-6zM13 3v6h8V3h-8zm6 4h-4V5h4v2zM3 21h8v-6H3v6zm2-4h4v2H5v-2z"/></svg>
                            <span class="side-menu__label">Dashboard</span>
{{--                            <span class="badge bg-success ms-auto menu-badge">1</span>--}}
                        </a>
                    </li>
                @endallowed
                <!-- End::slide -->

                <!-- Start::slide__category -->
                <li class="slide__category"><span class="category-name">General</span></li>
                <!-- End::slide__category -->

                <!-- Start::slide -->
                <!-- KV menu -->
                @allowedAny(['Admin-Role', 'Admin-Users', 'Admin-UserStoreAssignment'])
                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#607d8b"
                            stroke-width="1"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M12.5 19h-7.5a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2h4l3 3h7a2 2 0 0 1 2 2v3" />
                            <path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M19.001 15.5v1.5" />
                            <path d="M19.001 21v1.5" />
                            <path d="M22.032 17.25l-1.299 .75" />
                            <path d="M17.27 20l-1.3 .75" />
                            <path d="M15.97 17.25l1.3 .75" />
                            <path d="M20.733 20l1.3 .75" />
                        </svg>


                        <span class="side-menu__label ms-1">Manage</span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1">
                            <a href="javascript:void(0);">Manage</a>
                        </li>
                        @allowed('roles.index')
                            <li class="slide">
                                <a href="{{ route('roles.index') }}" class="side-menu__item">Roles</a>
                            </li>
                        @endallowed

                        @allowed('users.index')
                            <li class="slide">
                                <a href="{{ route('users.index') }}" class="side-menu__item">Users</a>
                            </li>
                        @endallowed

                    </ul>
                </li>
                @endallowedAny

                @allowed('projects.index')
                    <li class="slide">
                        <a href="{{ route('projects.index') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/></svg>
                            <span class="side-menu__label">Projects</span>
                        </a>
                    </li>
                @endallowed
                <!-- sample menu with nested sub menu -->
                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">
{{--                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M5.64 9l-.6 3h13.92l-.6-3H5.64zm12.19-2L19 12.5V19c0 .55-.45 1-1 1h-1c-.55 0-1-.45-1-1v-1H8v1c0 .55-.45 1-1 1H6c-.55 0-1-.45-1-1v-6.5L6.17 7H4V5h16v2h-2.17zM8 14c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1zm10 0c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1z" opacity=".3"/><path d="M4 5h16v2h-2.17l1.17 5.5V19c0 .55-.45 1-1 1h-1c-.55 0-1-.45-1-1v-1H8v1c0 .55-.45 1-1 1H6c-.55 0-1-.45-1-1v-6.5L6.17 7H4V5zm1.64 4l-.6 3h13.92l-.6-3H5.64zM7 15c.55 0 1-.45 1-1s-.45-1-1-1-1 .45-1 1 .45 1 1 1zm10 0c.55 0 1-.45 1-1s-.45-1-1-1-1 .45-1 1 .45 1 1 1z"/></svg>--}}
                        <svg  xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                              fill="currentColor" viewBox="0 0 24 24" >
                            <!--Boxicons v3.0.8 https://boxicons.com | License  https://docs.boxicons.com/free-->
                            <path d="M21.93 7.66c-.02-.05-.04-.11-.07-.16a1 1 0 0 0-.06-.08c-.03-.04-.06-.09-.1-.12-.03-.03-.06-.04-.09-.07-.04-.03-.07-.06-.11-.09h-.01l-9-5.01a.99.99 0 0 0-.97 0l-9.01 5H2.5c-.04.02-.07.06-.11.09a.6.6 0 0 0-.09.07c-.04.04-.07.08-.1.12-.02.03-.05.05-.06.08-.03.05-.05.1-.07.16-.01.03-.03.05-.03.08-.02.08-.04.17-.04.26v8c0 .36.2.7.51.87l9 5 .15.06c.03.01.06.03.09.03a1.1 1.1 0 0 0 .5 0c.03 0 .06-.02.09-.03.05-.02.1-.03.15-.06l9-5c.32-.18.51-.51.51-.87v-8c0-.09-.01-.18-.04-.26 0-.03-.02-.05-.03-.08ZM12 4.15l6.94 3.86-2.44 1.36-6.94-3.86zm-4.5 2.5 6.94 3.86L12 11.87 5.06 8.01zM4 9.71l7 3.89v5.71l-7-3.89zm16 5.71-7 3.89V13.6l2.5-1.39v3.21l2-1.11V11.1L20 9.71z"></path>
                        </svg>
                        <span class="side-menu__label">Assets</span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1 ms-1">
                            <a href="javascript:void(0);">Assets</a>
                        </li>
                        @allowed('asset-types.index')
                            <li class="slide">
                                <a href="{{ route('asset-types.index') }}" class="side-menu__item">Asset Categories</a>
                            </li>
                        @endallowed

                        @allowed('assets.index')
                            <li class="slide">
                                <a href="{{ route('assets.index') }}" class="side-menu__item">Assets</a>
                            </li>
                        @endallowed

                        @if(auth()->id() == 1)
                            {{--                        @allowed('assets.assign-asset-to-brand')--}}
                            {{--                            <li class="slide">--}}
                            {{--                                <a href="{{ route('assets.assign-asset-to-brand') }}" class="side-menu__item">Assign Asset to Brand</a>--}}
                            {{--                            </li>--}}
                            {{--                        @endallowed--}}
                        @endif


                        @allowed('assets.planogram-histories')
                            <li class="slide">
                                <a href="{{ route('assets.planogram-histories') }}" class="side-menu__item">Planogram Histories</a>
                            </li>
                        @endallowed
                    </ul>
                </li>


                <!-- Start::slide -->
{{--                <li class="slide">--}}
{{--                    <a href="{{ route('site-settings.index') }}" class="side-menu__item">--}}
{{--                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>--}}
{{--                        <span class="side-menu__label">Basic Setting</span>--}}
{{--                    </a>--}}
{{--                </li>--}}
                <!-- End::slide -->

            </ul>
            <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path> </svg></div>
        </nav>
        <!-- End::nav -->

    </div>
    <!-- End::main-sidebar -->

</aside>
