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

                @allowed('social-platforms.index')
                    <li class="slide">
                        <a href="{{ route('social-platforms.index') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92zM18 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM6 13c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm12 7.02c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>
                            <span class="side-menu__label">Social Platforms</span>
                        </a>
                    </li>
                @endallowed

{{--                @allowed('daily-reports.create')--}}
{{--                    <li class="slide">--}}
{{--                        <a href="{{ route('daily-reports.create') }}" class="side-menu__item">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zm-8.5-2.5l6-6-1.41-1.41-4.59 4.58-2.09-2.08L7 13l3.5 3.5z"/></svg>--}}
{{--                            <span class="side-menu__label">Daily Report</span>--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endallowed--}}

{{--                @allowed('daily-reports.index')--}}
{{--                    <li class="slide">--}}
{{--                        <a href="{{ route('daily-reports.index') }}" class="side-menu__item">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.25 2.52.77-1.28-3.52-2.09V8H12z"/></svg>--}}
{{--                            <span class="side-menu__label">Report History</span>--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endallowed--}}

                @allowed('daily-reports.team')
                    <li class="slide">
                        <a href="{{ route('daily-reports.team') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            <span class="side-menu__label">Team Reports</span>
                        </a>
                    </li>
                @endallowed

                @allowed('attendance.index')
                    <li class="slide">
                        <a href="{{ route('attendance.index') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zm-8.5-2.5l6-6-1.41-1.41-4.59 4.58-2.09-2.08L7 13l3.5 3.5z"/></svg>
                            <span class="side-menu__label">Attendance</span>
                        </a>
                    </li>
                @endallowed

                @allowed('kpi.index')
                    <li class="slide">
                        <a href="{{ route('kpi.index') }}" class="side-menu__item">
                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/></svg>
                            <span class="side-menu__label">KPI</span>
                        </a>
                    </li>
                @endallowed

{{--                @allowed('daily-targets.index')--}}
{{--                    <li class="slide">--}}
{{--                        <a href="{{ route('daily-targets.index') }}" class="side-menu__item">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>--}}
{{--                            <span class="side-menu__label">Daily Targets</span>--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endallowed--}}

{{--                @allowed('holidays.index')--}}
{{--                    <li class="slide">--}}
{{--                        <a href="{{ route('holidays.index') }}" class="side-menu__item">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9.41 17L12 14.41 14.59 17 16 15.59 13.41 13 16 10.41 14.59 9 12 11.59 9.41 9 8 10.41 10.59 13 8 15.59 9.41 17z"/></svg>--}}
{{--                            <span class="side-menu__label">Holidays</span>--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endallowed--}}
                <!-- Reports menu with nested sub menu -->
                @if(allowed('daily-reports.create') || allowed('daily-reports.index') || allowed('my-leaves.index'))
                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zm-8.5-2.5l6-6-1.41-1.41-4.59 4.58-2.09-2.08L7 13l3.5 3.5z"/></svg>
                        <span class="side-menu__label">Daily Reports</span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1 ms-1">
                            <a href="javascript:void(0);">Daily Reports</a>
                        </li>
                        @allowed('daily-reports.create')
                            <li class="slide">
                                <a href="{{ route('daily-reports.create') }}" class="side-menu__item">Daily Report</a>
                            </li>
                        @endallowed
                        @allowed('daily-reports.index')
                            <li class="slide">
                                <a href="{{ route('daily-reports.index') }}" class="side-menu__item">Report History</a>
                            </li>
                        @endallowed
                        @allowed('my-leaves.index')
                            <li class="slide">
                                <a href="{{ route('my-leaves.index') }}" class="side-menu__item">My Leaves</a>
                            </li>
                        @endallowed
                    </ul>
                </li>
                @endif
                <!-- Targets menu with nested sub menu -->
                @if(allowed('daily-targets.index') || allowed('holidays.index') || allowed('leaves.index'))
                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        <span class="side-menu__label">Daily Targets</span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1 ms-1">
                            <a href="javascript:void(0);">Daily Targets</a>
                        </li>
                        @allowed('daily-targets.index')
                            <li class="slide">
                                <a href="{{ route('daily-targets.index') }}" class="side-menu__item">Daily Targets</a>
                            </li>
                        @endallowed
                        @allowed('holidays.index')
                            <li class="slide">
                                <a href="{{ route('holidays.index') }}" class="side-menu__item">Holidays</a>
                            </li>
                        @endallowed
                        @allowed('leaves.index')
                            <li class="slide">
                                <a href="{{ route('leaves.index') }}" class="side-menu__item">Leaves</a>
                            </li>
                        @endallowed
                    </ul>
                </li>
                @endif


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
