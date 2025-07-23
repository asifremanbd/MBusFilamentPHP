@extends('layouts.dashboard')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Dashboard Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Gateway Dashboard</h1>
                <p class="mt-1 text-sm text-gray-600">
                    @if(isset($gateway))
                        Monitoring {{ $gateway->name }} and its connected devices
                    @else
                        Select a gateway to monitor its devices and performance
                    @endif
                </p>
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

    @if(isset($gateway))
        <!-- Gateway Info Panel -->
        <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-3 {{ $gateway->communication_status === 'online' ? 'bg-green-500' : ($gateway->communication_status === 'warning' ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
                    <h2 class="text-lg font-medium text-gray-900">{{ $gateway->name }}</h2>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $gateway->communication_status === 'online' ? 'bg-green-100 text-green-800' : ($gateway->communication_status === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                    {{ ucfirst($gateway->communication_status ?? 'unknown') }}
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">IP Address</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $gateway->ip_address ?? 'Not set' }}</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Port</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $gateway->port ?? 'Not set' }}</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Connected Devices</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $deviceCount ?? 0 }}</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-gray-500">Last Communication</div>
                    <div class="text-lg font-semibold text-gray-900">
                        {{ $gateway->last_communication ? $gateway->last_communication->diffForHumans() : 'Never' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div id="dashboard-grid" class="dashboard-grid" data-dashboard-type="gateway" data-gateway-id="{{ $gateway->id }}">
            <!-- Real-time Readings Widget -->
            <div class="dashboard-widget" data-widget-id="real-time-readings" data-position='{"row": 0, "col": 0}' data-size='{"width": 8, "height": 6}'>
                <div class="widget-container">
                    <div class="widget-header">
                        <h3 class="widget-title">Real-time Readings</h3>
                        <div class="widget-controls">
                            <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('real-time-readings')">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="widget-content" id="real-time-readings-content">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <p>Loading real-time readings...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateway Stats Widget -->
            <div class="dashboard-widget" data-widget-id="gateway-stats" data-position='{"row": 0, "col": 8}' data-size='{"width": 4, "height": 6}'>
                <div class="widget-container">
                    <div class="widget-header">
                        <h3 class="widget-title">Gateway Statistics</h3>
                        <div class="widget-controls">
                            <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('gateway-stats')">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="widget-content" id="gateway-stats-content">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p>Loading gateway statistics...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateway Alerts Widget -->
            <div class="dashboard-widget" data-widget-id="gateway-alerts" data-position='{"row": 6, "col": 0}' data-size='{"width": 12, "height": 4}'>
                <div class="widget-container">
                    <div class="widget-header">
                        <h3 class="widget-title">Gateway Alerts</h3>
                        <div class="widget-controls">
                            <button class="widget-control-btn" onclick="window.dashboardManager.refreshWidget('gateway-alerts')">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="widget-content" id="gateway-alerts-content">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <p>Loading gateway alerts...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gateway Sidebar -->
        <div class="fixed right-0 top-16 h-full w-80 transform transition-transform duration-300 ease-in-out z-40" 
             x-data="{ sidebarOpen: false }" 
             :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'">
            
            <!-- Sidebar Toggle Button -->
            <button @click="sidebarOpen = !sidebarOpen" 
                    class="absolute left-0 top-1/2 transform -translate-x-full -translate-y-1/2 bg-white border border-r-0 border-gray-200 rounded-l-md p-2 shadow-sm hover:bg-gray-50">
                <svg x-show="!sidebarOpen" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <svg x-show="sidebarOpen" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            
            <!-- Sidebar Content -->
            <div class="h-full bg-white border-l border-gray-200 shadow-lg">
                @include('components.gateway-sidebar', [
                    'gateway' => $gateway,
                    'devices' => $devices ?? collect(),
                    'deviceCount' => $deviceCount ?? 0,
                    'activeAlerts' => $activeAlerts ?? [],
                    'activeAlertCount' => $activeAlertCount ?? 0
                ])
            </div>
        </div>

    @else
        <!-- No Gateway Selected State -->
        <div class="text-center py-12">
            <svg class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Gateway Selected</h3>
            <p class="text-sm text-gray-500 mb-4">
                Please select a gateway from the dropdown above to view monitoring data.
            </p>
        </div>
    @endif

    <!-- Dashboard Customization Interface -->
    @include('dashboard.customization.widget-customizer')
</div>

<!-- Initialize Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard manager
    if (typeof window.dashboardManager !== 'undefined') {
        window.dashboardManager.init({
            dashboardType: 'gateway',
            gatewayId: {{ $gateway->id ?? 'null' }},
            apiEndpoints: {
                config: '/api/dashboard/config',
                widgets: '/api/dashboard/widgets/available',
                updateLayout: '/api/dashboard/config/widget/layout',
                updateVisibility: '/api/dashboard/config/widget/visibility'
            }
        });
    }
    
    // Load initial widget data if gateway is selected
    @if(isset($gateway))
    setTimeout(() => {
        window.dashboardManager.loadAllWidgets();
    }, 100);
    @endif
});
</script>
@endsection"