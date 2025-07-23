@extends('layouts.dashboard')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Dashboard Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Global Dashboard</h1>
                <p class="mt-1 text-sm text-gray-600">Monitor all your energy systems from a single view</p>
            </div>
            
            <!-- Dashboard Controls -->
            <div class="flex items-center space-x-3">
                <!-- Refresh Button -->
                <button onclick="window.dashboardManager.refreshAllWidgets()" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
                
                <!-- Customize Button -->
                <button onclick="window.dashboardCustomizer.openCustomizer()" 
                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                    </svg>
                    Customize
                </button>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid -->
    <div id="dashboard-grid" class="dashboard-grid" data-dashboard-type="global">
        <!-- System Overview Widget -->
        <div class="dashboard-widget" data-widget-id="system-overview" data-position='{"row": 0, "col": 0}' data-size='{"width": 6, "height": 4}'>
            <div class="widget-container">
                <div class="widget-header">
                    <h3 class="widget-title">System Overview</h3>
                    <div class="widget-controls">
                        <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('system-overview')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="widget-content" id="system-overview-content">
                    @include('dashboard.widgets.fallback.system-overview')
                </div>
            </div>
        </div>

        <!-- Cross Gateway Alerts Widget -->
        <div class="dashboard-widget" data-widget-id="cross-gateway-alerts" data-position='{"row": 0, "col": 6}' data-size='{"width": 6, "height": 4}'>
            <div class="widget-container">
                <div class="widget-header">
                    <h3 class="widget-title">Active Alerts</h3>
                    <div class="widget-controls">
                        <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('cross-gateway-alerts')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="widget-content" id="cross-gateway-alerts-content">
                    @include('dashboard.widgets.fallback.cross-gateway-alerts')
                </div>
            </div>
        </div>

        <!-- Top Consuming Gateways Widget -->
        <div class="dashboard-widget" data-widget-id="top-consuming-gateways" data-position='{"row": 4, "col": 0}' data-size='{"width": 8, "height": 5}'>
            <div class="widget-container">
                <div class="widget-header">
                    <h3 class="widget-title">Top Consuming Gateways</h3>
                    <div class="widget-controls">
                        <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('top-consuming-gateways')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="widget-content" id="top-consuming-gateways-content">
                    <div class="text-center py-8 text-gray-500">
                        <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p>Loading gateway consumption data...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health Widget -->
        <div class="dashboard-widget" data-widget-id="system-health" data-position='{"row": 4, "col": 8}' data-size='{"width": 4, "height": 5}'>
            <div class="widget-container">
                <div class="widget-header">
                    <h3 class="widget-title">System Health</h3>
                    <div class="widget-controls">
                        <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('system-health')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="widget-content" id="system-health-content">
                    <div class="text-center py-8 text-gray-500">
                        <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <p>Loading system health data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Customization Interface -->
    @include('dashboard.customization.widget-customizer')
</div>

<!-- Initialize Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard manager
    if (typeof window.dashboardManager !== 'undefined') {
        window.dashboardManager.init({
            dashboardType: 'global',
            apiEndpoints: {
                config: '/api/dashboard/config',
                widgets: '/api/dashboard/widgets/available',
                updateLayout: '/api/dashboard/config/widget/layout',
                updateVisibility: '/api/dashboard/config/widget/visibility'
            }
        });
    }
    
    // Load initial widget data
    setTimeout(() => {
        window.dashboardManager.loadAllWidgets();
    }, 100);
});
</script>
@endsection"