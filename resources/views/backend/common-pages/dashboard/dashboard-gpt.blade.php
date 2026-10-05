@extends('backend.master')

@section('title', 'Dashboard')

@section('body')
    @php
        $kpis = $dashboardData['kpis'] ?? [];
        $assetMix = collect($dashboardData['assetMix'] ?? []);
        $topStores = collect($dashboardData['topStores'] ?? []);
        $userSectorMix = collect($dashboardData['userSectorMix'] ?? []);
        $brandCoverage = collect($dashboardData['brandCoverage'] ?? []);
        $monthlyAssets = collect($dashboardData['monthlyAssets'] ?? []);
        $monthlyUsers = collect($dashboardData['monthlyUsers'] ?? []);
        $monthlyVmIssues = collect($dashboardData['monthlyVmIssues'] ?? []);
        $planogramActivity = collect($dashboardData['planogramActivity'] ?? []);
        $operationalHealth = $dashboardData['operationalHealth'] ?? [];
        $recentUsers = collect($dashboardData['recentUsers'] ?? []);
        $recentPlanograms = collect($dashboardData['recentPlanograms'] ?? []);
    @endphp

    <div class="container-fluid pt-3 dashboard-page">
        <div class="dashboard-hero mb-4">
            <div>
                <span class="dashboard-kicker">Operations Overview</span>
                <h3 class="dashboard-title mb-1">Retail Asset Intelligence Dashboard</h3>
                <p class="dashboard-subtitle mb-0">
                    Central visibility across stores, assets, planograms, brand assignments, and VM execution.
                </p>
            </div>
            <div class="dashboard-hero-user">
                <div class="dashboard-hero-user__label">Signed in as</div>
                <div class="dashboard-hero-user__name">{{ auth()->user()->name ?? 'Admin' }}</div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach($kpis as $key => $kpi)
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="dashboard-kpi-card dashboard-kpi-card--{{ $key }}">
                        <div class="dashboard-kpi-card__meta">{{ $kpi['label'] ?? 'Metric' }}</div>
                        <div class="dashboard-kpi-card__value">{{ number_format((int) ($kpi['value'] ?? 0)) }}</div>
                        <div class="dashboard-kpi-card__sub">{{ $kpi['meta'] ?? '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Monthly Growth</h5>
                            <p class="text-muted mb-0">Assets, users, and VM issue creation trend.</p>
                        </div>
                    </div>
                    <div id="monthly-growth-chart" class="dashboard-chart"></div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">User Sector Mix</h5>
                            <p class="text-muted mb-0">Field vs corporate user distribution.</p>
                        </div>
                    </div>
                    <div id="user-sector-chart" class="dashboard-chart dashboard-chart--sm"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Asset Mix by Type</h5>
                            <p class="text-muted mb-0">Asset portfolio split by category.</p>
                        </div>
                    </div>
                    <div id="asset-mix-chart" class="dashboard-chart"></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Top Stores by Asset Count</h5>
                            <p class="text-muted mb-0">Stores carrying the largest asset footprint.</p>
                        </div>
                    </div>
                    <div id="top-stores-chart" class="dashboard-chart"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-7">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Brand Coverage</h5>
                            <p class="text-muted mb-0">Current asset-to-brand assignment volume.</p>
                        </div>
                    </div>
                    <div id="brand-coverage-chart" class="dashboard-chart"></div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Operational Health</h5>
                            <p class="text-muted mb-0">Quick quality and readiness indicators.</p>
                        </div>
                    </div>
                    <div class="dashboard-health-grid">
                        <div class="dashboard-health-card">
                            <span>Assets With Planogram</span>
                            <strong>{{ number_format((int) ($operationalHealth['assets_with_planogram'] ?? 0)) }}</strong>
                        </div>
                        <div class="dashboard-health-card">
                            <span>Assets With KV Slot</span>
                            <strong>{{ number_format((int) ($operationalHealth['assets_with_kv_slot'] ?? 0)) }}</strong>
                        </div>
                        <div class="dashboard-health-card">
                            <span>Users Assigned to Stores</span>
                            <strong>{{ number_format((int) ($operationalHealth['store_assigned_users'] ?? 0)) }}</strong>
                        </div>
                        <div class="dashboard-health-card">
                            <span>VM Issues Solved</span>
                            <strong>{{ number_format((int) ($operationalHealth['vm_solved'] ?? 0)) }}</strong>
                        </div>
                    </div>
                    <div class="dashboard-mini-divider"></div>
                    <div class="dashboard-panel__header pt-0">
                        <div>
                            <h6 class="mb-1">Planogram Activity</h6>
                            <p class="text-muted mb-0">Recent planogram updates over time.</p>
                        </div>
                    </div>
                    <div id="planogram-activity-chart" class="dashboard-chart dashboard-chart--sm"></div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Recent Users</h5>
                            <p class="text-muted mb-0">Latest users added to the platform.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 dashboard-table">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Employee ID</th>
                                <th>Sector</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentUsers as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="text-muted small">{{ $user->email }}</div>
                                    </td>
                                    <td>{{ $user->employee_id ?: 'N/A' }}</td>
                                    <td>
                                        <span class="badge {{ $user->usages_sector === 'field' ? 'bg-info-transparent text-info' : 'bg-primary-transparent text-primary' }}">
                                            {{ ucfirst($user->usages_sector ?: 'unknown') }}
                                        </span>
                                    </td>
                                    <td>{{ optional($user->created_at)->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No recent users found.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="dashboard-panel h-100">
                    <div class="dashboard-panel__header">
                        <div>
                            <h5 class="mb-1">Recent Planogram Updates</h5>
                            <p class="text-muted mb-0">Latest planogram changes across stores and assets.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 dashboard-table">
                            <thead>
                            <tr>
                                <th>Store</th>
                                <th>Asset</th>
                                <th>Brand</th>
                                <th>Changed</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentPlanograms as $history)
                                <tr>
                                    <td>{{ $history->store?->title ?: 'N/A' }}</td>
                                    <td>{{ $history->asset?->name ?: 'N/A' }}</td>
                                    <td>{{ $history->brand?->name ?: 'N/A' }}</td>
                                    <td>{{ optional($history->created_at)->format('d M Y, h:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No recent planogram activity found.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .dashboard-page {
            padding-bottom: 24px;
        }

        .dashboard-hero {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 16px;
            padding: 24px 28px;
            border-radius: 20px;
            background:
                radial-gradient(circle at top left, rgba(var(--primary-rgb), 0.22), transparent 38%),
                linear-gradient(135deg, #0f172a 0%, #172554 50%, #1e293b 100%);
            color: #fff;
            overflow: hidden;
        }

        .dashboard-kicker {
            display: inline-block;
            margin-bottom: 8px;
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .dashboard-title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
        }

        .dashboard-subtitle {
            max-width: 780px;
            color: rgba(255, 255, 255, 0.76);
        }

        .dashboard-hero-user {
            min-width: 220px;
            padding: 16px 18px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
        }

        .dashboard-hero-user__label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 6px;
        }

        .dashboard-hero-user__name {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }

        .dashboard-kpi-card,
        .dashboard-panel {
            height: 100%;
            border: 1px solid var(--default-border, #e9ebec);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.06);
        }

        .dashboard-kpi-card {
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-kpi-card::after {
            content: '';
            position: absolute;
            top: -24px;
            right: -24px;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(var(--primary-rgb), 0.08);
        }

        .dashboard-kpi-card__meta {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted, #6c757d);
            margin-bottom: 12px;
        }

        .dashboard-kpi-card__value {
            font-size: 2rem;
            line-height: 1;
            font-weight: 700;
            color: var(--default-text-color);
            margin-bottom: 10px;
        }

        .dashboard-kpi-card__sub {
            font-size: 0.88rem;
            color: var(--text-muted, #6c757d);
            max-width: 200px;
        }

        .dashboard-panel {
            padding: 20px 20px 16px;
        }

        .dashboard-panel__header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 10px;
            margin-bottom: 14px;
        }

        .dashboard-chart {
            width: 100%;
            height: 340px;
        }

        .dashboard-chart--sm {
            height: 280px;
        }

        .dashboard-health-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .dashboard-health-card {
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }

        .dashboard-health-card span {
            display: block;
            font-size: 0.82rem;
            color: var(--text-muted, #6c757d);
            margin-bottom: 8px;
        }

        .dashboard-health-card strong {
            font-size: 1.3rem;
            color: var(--default-text-color);
        }

        .dashboard-mini-divider {
            height: 1px;
            background: var(--default-border, #e9ebec);
            margin: 18px 0;
        }

        .dashboard-table thead th {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #6c757d);
            border-bottom-width: 1px;
        }

        .dashboard-table tbody td {
            vertical-align: middle;
        }

        @media (max-width: 991.98px) {
            .dashboard-hero {
                flex-direction: column;
                align-items: start;
            }

            .dashboard-hero-user {
                min-width: 100%;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-panel,
            .dashboard-kpi-card,
            .dashboard-hero {
                border-radius: 16px;
            }

            .dashboard-health-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-chart {
                height: 300px;
            }

            .dashboard-chart--sm {
                height: 260px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const assetMix = @json($assetMix->map(fn ($item) => ['name' => $item->name, 'y' => (int) $item->total])->values());
            const topStores = @json($topStores->map(fn ($item) => ['name' => $item->title, 'y' => (int) $item->assets_count])->values());
            const userSectorMix = @json($userSectorMix->map(fn ($item) => ['name' => ucfirst($item->usages_sector ?? 'Unknown'), 'y' => (int) $item->total])->values());
            const brandCoverage = @json($brandCoverage->map(fn ($item) => ['name' => $item->name, 'y' => (int) $item->total])->values());
            const monthlyCategories = @json($monthlyAssets->pluck('period')->unique()->values());
            const monthlyAssetsSeries = @json($monthlyAssets->pluck('total')->map(fn ($value) => (int) $value)->values());
            const monthlyUsersSeries = @json($monthlyUsers->pluck('total')->map(fn ($value) => (int) $value)->values());
            const monthlyVmSeries = @json($monthlyVmIssues->pluck('total')->map(fn ($value) => (int) $value)->values());
            const planogramCategories = @json($planogramActivity->pluck('activity_date')->values());
            const planogramSeries = @json($planogramActivity->pluck('total')->map(fn ($value) => (int) $value)->values());

            const baseChartOptions = {
                credits: { enabled: false },
                exporting: { enabled: false },
                title: { text: null },
                legend: {
                    itemStyle: {
                        fontWeight: '500'
                    }
                },
                xAxis: {
                    lineColor: '#dbe4f0',
                    tickColor: '#dbe4f0',
                    labels: { style: { color: '#64748b' } }
                },
                yAxis: {
                    title: { text: null },
                    gridLineColor: '#eef2f7',
                    labels: { style: { color: '#64748b' } }
                },
                tooltip: {
                    borderRadius: 12,
                    backgroundColor: 'rgba(15,23,42,0.92)',
                    style: { color: '#fff' }
                }
            };

            Highcharts.chart('monthly-growth-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'spline',
                    backgroundColor: 'transparent'
                },
                xAxis: {
                    categories: monthlyCategories
                },
                series: [
                    {
                        name: 'Assets',
                        data: monthlyAssetsSeries,
                        color: '#2563eb'
                    },
                    {
                        name: 'Users',
                        data: monthlyUsersSeries,
                        color: '#0f766e'
                    },
                    {
                        name: 'VM Issues',
                        data: monthlyVmSeries,
                        color: '#dc2626'
                    }
                ]
            }));

            Highcharts.chart('user-sector-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'pie',
                    backgroundColor: 'transparent'
                },
                plotOptions: {
                    pie: {
                        innerSize: '62%',
                        dataLabels: {
                            enabled: true,
                            format: '{point.name}: {point.y}'
                        }
                    }
                },
                series: [{
                    name: 'Users',
                    data: userSectorMix,
                    colors: ['#2563eb', '#14b8a6']
                }]
            }));

            Highcharts.chart('asset-mix-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'column',
                    backgroundColor: 'transparent'
                },
                xAxis: {
                    type: 'category'
                },
                series: [{
                    name: 'Assets',
                    data: assetMix,
                    color: '#1d4ed8'
                }]
            }));

            Highcharts.chart('top-stores-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'bar',
                    backgroundColor: 'transparent'
                },
                xAxis: {
                    type: 'category'
                },
                series: [{
                    name: 'Assets',
                    data: topStores,
                    color: '#0f766e'
                }]
            }));

            Highcharts.chart('brand-coverage-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'column',
                    backgroundColor: 'transparent'
                },
                xAxis: {
                    type: 'category'
                },
                series: [{
                    name: 'Assignments',
                    data: brandCoverage,
                    color: '#7c3aed'
                }]
            }));

            Highcharts.chart('planogram-activity-chart', Highcharts.merge(baseChartOptions, {
                chart: {
                    type: 'areaspline',
                    backgroundColor: 'transparent'
                },
                xAxis: {
                    categories: planogramCategories
                },
                series: [{
                    name: 'Planogram Updates',
                    data: planogramSeries,
                    color: '#ea580c',
                    fillOpacity: 0.18
                }]
            }));
        });
    </script>
@endpush
