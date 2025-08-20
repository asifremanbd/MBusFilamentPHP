@extends('layouts.rtu-dashboard')

@section('content')
<div class="space-y-6">
    <!-- Dashboard Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <!-- Gateway Status Indicator -->
                <div class="flex items-center space-x-3">
                    <div class="w-4 h-4 rounded-full {{ 
                        $gateway->communication_status === 'online' ? 'bg-green-500' : 
                        ($gateway->communication_status === 'warning' ? 'bg-yellow-500' : 'bg-red-500') 
                    }}"></div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $gateway->name }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            RTU Gateway Dashboard • {{ $gateway->gateway_type ?? 'Teltonika RUT956' }}
                        </p>
                    </div>
                </div>
                
                <!-- Gateway Type Badge -->
                <x-filament::badge color="info" size="lg">
                    RTU Gateway
                </x-filament::badge>
            </div>
            
            <!-- Dashboard Controls -->
            <div class="flex items-center space-x-3">
                <!-- Switch to Standard Dashboard -->
                <a href="{{ route('dashboard.gateway', $gateway) }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                    <x-heroicon-o-squares-2x2 class="w-4 h-4 mr-1.5" />
                    Standard View
                </a>
                
                <!-- Refresh Dashboard -->
                <button onclick="refreshRTUDashboard()" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                    <x-heroicon-o-arrow-path class="w-4 h-4 mr-1.5" />
                    Refresh
                </button>
                
                <!-- Auto-refresh Toggle -->
                <div class="flex items-center space-x-2">
                    <label class="text-sm text-gray-700 dark:text-gray-300">Auto-refresh</label>
                    <input type="checkbox" 
                           id="auto-refresh-toggle" 
                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                           onchange="toggleAutoRefresh(this.checked)">
                </div>
            </div>
        </div>
        
        <!-- Breadcrumb Navigation -->
        <nav class="flex mt-4 pt-4 border-t border-gray-200 dark:border-gray-600" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('filament.admin.pages.dashboard') }}" 
                       class="inline-flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        <x-heroicon-o-home class="w-4 h-4 mr-2" />
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        <a href="{{ route('dashboard.gateway', $gateway) }}" 
                           class="ml-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 md:ml-2">
                            Gateways
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        <span class="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ml-2">
                            {{ $gateway->name }}
                        </span>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        <span class="ml-1 text-sm font-medium text-blue-600 dark:text-blue-400 md:ml-2">
                            RTU Dashboard
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Quick Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Communication Status -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Communication</p>
                    <p class="text-lg font-semibold {{ 
                        $gateway->communication_status === 'online' ? 'text-green-600' : 
                        ($gateway->communication_status === 'warning' ? 'text-yellow-600' : 'text-red-600') 
                    }}">
                        {{ ucfirst($gateway->communication_status ?? 'Unknown') }}
                    </p>
                </div>
                <x-heroicon-o-signal class="w-8 h-8 {{ 
                    $gateway->communication_status === 'online' ? 'text-green-500' : 
                    ($gateway->communication_status === 'warning' ? 'text-yellow-500' : 'text-red-500') 
                }}" />
            </div>
        </div>

        <!-- System Health -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">System Health</p>
                    <p class="text-lg font-semibold text-blue-600">
                        {{ $systemHealth['health_score'] ?? 0 }}/100
                    </p>
                </div>
                <x-heroicon-o-cpu-chip class="w-8 h-8 text-blue-500" />
            </div>
        </div>

        <!-- Active Alerts -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Alerts</p>
                    <p class="text-lg font-semibold {{ 
                        ($groupedAlerts['critical_count'] ?? 0) > 0 ? 'text-red-600' : 
                        (($groupedAlerts['warning_count'] ?? 0) > 0 ? 'text-yellow-600' : 'text-green-600') 
                    }}">
                        {{ $groupedAlerts['status_summary'] ?? 'All Systems OK' }}
                    </p>
                </div>
                <x-heroicon-o-exclamation-triangle class="w-8 h-8 {{ 
                    ($groupedAlerts['critical_count'] ?? 0) > 0 ? 'text-red-500' : 
                    (($groupedAlerts['warning_count'] ?? 0) > 0 ? 'text-yellow-500' : 'text-green-500') 
                }}" />
            </div>
        </div>

        <!-- Last Update -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last Update</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $gateway->last_system_update ? $gateway->last_system_update->diffForHumans() : 'Never' }}
                    </p>
                </div>
                <x-heroicon-o-clock class="w-8 h-8 text-gray-500" />
            </div>
        </div>
    </div>

    <!-- RTU Dashboard Widgets -->
    <div class="space-y-6" id="rtu-dashboard-widgets">
        <!-- System Health Section -->
        <div class="rtu-widget-section" data-section="system">
            @livewire('rtu-system-health-widget', ['gateway' => $gateway])
        </div>

        <!-- Network Status Section -->
        <div class="rtu-widget-section" data-section="network">
            @livewire('rtu-network-status-widget', ['gateway' => $gateway])
        </div>

        <!-- I/O Monitoring Section -->
        <div class="rtu-widget-section" data-section="io">
            @livewire('rtu-io-monitoring-widget', ['gateway' => $gateway])
        </div>

        <!-- Alerts Section -->
        <div class="rtu-widget-section" data-section="alerts">
            @livewire('rtu-alerts-widget', ['gateway' => $gateway])
        </div>

        <!-- Trend Analysis Section -->
        <div class="rtu-widget-section" data-section="trends">
            @livewire('rtu-trend-widget', ['gateway' => $gateway])
        </div>
    </div>

    <!-- Error State -->
    @if(isset($error))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
            <div class="flex items-center">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500 mr-3" />
                <div>
                    <h3 class="text-lg font-medium text-red-800 dark:text-red-200">{{ $error }}</h3>
                    @if(isset($message))
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                    @endif
                    @if(isset($suggested_action))
                        <div class="mt-3">
                            @if($suggested_action['action'] === 'redirect')
                                <a href="{{ $suggested_action['url'] }}" 
                                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                    {{ $suggested_action['message'] }}
                                </a>
                            @elseif($suggested_action['action'] === 'contact_admin')
                                <p class="text-sm text-red-700 dark:text-red-300">{{ $suggested_action['message'] }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Loading Overlay -->
<div id="rtu-loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center h-full">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                <span class="text-gray-900 dark:text-white">Refreshing RTU Dashboard...</span>
            </div>
        </div>
    </div>
</div>

<!-- RTU Dashboard Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize RTU dashboard
    initializeRTUDashboard();
    
    // Set up auto-refresh if enabled
    const autoRefreshEnabled = localStorage.getItem('rtu-auto-refresh') === 'true';
    document.getElementById('auto-refresh-toggle').checked = autoRefreshEnabled;
    
    if (autoRefreshEnabled) {
        startAutoRefresh();
    }
});

let autoRefreshInterval = null;

/**
 * Initialize RTU Dashboard
 */
function initializeRTUDashboard() {
    console.log('RTU Dashboard initialized for gateway:', {{ $gateway->id }});
    
    // Add error handling for widget loading
    window.addEventListener('error', function(e) {
        console.error('RTU Dashboard error:', e.error);
        showNotification('An error occurred while loading the dashboard', 'error');
    });
    
    // Initialize section collapse states
    if (typeof window.rtuSectionManager !== 'undefined') {
        window.rtuSectionManager.init();
    }
}

/**
 * Refresh RTU Dashboard Data
 */
function refreshRTUDashboard() {
    showLoadingOverlay();
    
    // Refresh all Livewire components
    const widgets = document.querySelectorAll('[wire\\:id]');
    widgets.forEach(widget => {
        const component = Livewire.find(widget.getAttribute('wire:id'));
        if (component) {
            component.call('$refresh');
        }
    });
    
    // Refresh quick status overview
    fetch(`/api/rtu/gateway/{{ $gateway->id }}/status`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateQuickStatus(data.status);
            showNotification('Dashboard refreshed successfully', 'success');
        } else {
            showNotification('Failed to refresh dashboard status', 'error');
        }
    })
    .catch(error => {
        console.error('Error refreshing dashboard:', error);
        showNotification('Error refreshing dashboard', 'error');
    })
    .finally(() => {
        hideLoadingOverlay();
    });
}

/**
 * Toggle Auto-refresh
 */
function toggleAutoRefresh(enabled) {
    localStorage.setItem('rtu-auto-refresh', enabled.toString());
    
    if (enabled) {
        startAutoRefresh();
        showNotification('Auto-refresh enabled (30 seconds)', 'info');
    } else {
        stopAutoRefresh();
        showNotification('Auto-refresh disabled', 'info');
    }
}

/**
 * Start Auto-refresh
 */
function startAutoRefresh() {
    stopAutoRefresh(); // Clear any existing interval
    
    autoRefreshInterval = setInterval(() => {
        refreshRTUDashboard();
    }, 30000); // 30 seconds
}

/**
 * Stop Auto-refresh
 */
function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

/**
 * Update Quick Status Overview
 */
function updateQuickStatus(status) {
    // This would update the quick status cards with new data
    // Implementation depends on the specific status structure
    console.log('Updating quick status:', status);
}

/**
 * Show Loading Overlay
 */
function showLoadingOverlay() {
    document.getElementById('rtu-loading-overlay').classList.remove('hidden');
}

/**
 * Hide Loading Overlay
 */
function hideLoadingOverlay() {
    document.getElementById('rtu-loading-overlay').classList.add('hidden');
}

/**
 * Show Notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});
</script>
@endsection