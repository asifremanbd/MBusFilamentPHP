<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-bell class="w-5 h-5" />
                    <span>RTU Alerts</span>
                </div>
                
                <!-- Device Status Indicator -->
                <div class="flex items-center space-x-2">
                    @php
                        $deviceStatus = $this->getData()['device_status'];
                    @endphp
                    
                    <x-dynamic-component 
                        :component="'heroicon-o-' . str_replace('heroicon-o-', '', $deviceStatus['icon'])" 
                        class="w-4 h-4 text-{{ $deviceStatus['color'] }}-500" 
                    />
                    <span class="text-sm font-medium text-{{ $deviceStatus['color'] }}-600">
                        {{ $deviceStatus['text'] }}
                    </span>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            <!-- Alert Filters -->
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Device Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Device
                        </label>
                        <select 
                            id="device-filter" 
                            multiple
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                            @foreach($this->getData()['available_devices'] as $device)
                                <option value="{{ $device['id'] }}">{{ $device['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Severity Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Severity
                        </label>
                        <select 
                            id="severity-filter" 
                            multiple
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                            @foreach($this->getData()['severity_options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Time Range Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Time Range
                        </label>
                        <select 
                            id="time-range-filter"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                            @foreach($this->getData()['time_range_options'] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex items-end space-x-2">
                        <button 
                            id="apply-filters"
                            type="button"
                            class="px-4 py-2 bg-primary-600 text-white text-sm rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            Apply Filters
                        </button>
                        <button 
                            id="clear-filters"
                            type="button"
                            class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Custom Date Range (hidden by default) -->
                <div id="custom-date-range" class="mt-4 grid grid-cols-2 gap-4 hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Date
                        </label>
                        <input 
                            type="datetime-local" 
                            id="start-date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            End Date
                        </label>
                        <input 
                            type="datetime-local" 
                            id="end-date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                    </div>
                </div>
            </div>

            <!-- Alerts Display -->
            <div id="alerts-container">
                @php
                    $alertsData = $this->getData()['alerts_data'];
                @endphp

                @if(!$alertsData['has_alerts'])
                    <!-- No Active Alerts Display -->
                    <div class="text-center py-8">
                        <x-heroicon-o-check-circle class="w-16 h-16 text-green-500 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                            No Active Alerts
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            All systems are operating normally. No critical alerts are present.
                        </p>
                    </div>
                @else
                    <!-- Alert Summary -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Alert Summary
                            </h4>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $alertsData['status_summary'] }}
                            </span>
                        </div>
                        
                        <div class="mt-2 flex space-x-4">
                            @if($alertsData['critical_count'] > 0)
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $alertsData['critical_count'] }} Critical
                                    </span>
                                </div>
                            @endif
                            
                            @if($alertsData['warning_count'] > 0)
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $alertsData['warning_count'] }} Warning
                                    </span>
                                </div>
                            @endif
                            
                            @if($alertsData['info_count'] > 0)
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $alertsData['info_count'] }} Info
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Grouped Alerts List -->
                    <div class="space-y-2">
                        @foreach($alertsData['grouped_alerts'] as $alert)
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <!-- Severity Indicator -->
                                            @if($alert->severity === 'critical')
                                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                                            @elseif($alert->severity === 'warning')
                                                <x-heroicon-o-exclamation-circle class="w-5 h-5 text-yellow-500" />
                                            @else
                                                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500" />
                                            @endif
                                            
                                            <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $alert->type }}
                                            </h5>
                                            
                                            @if($alert->is_grouped && $alert->count > 1)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    {{ $alert->count }} occurrences
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $alert->message }}
                                        </p>
                                        
                                        <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Latest: {{ $alert->latest_timestamp->format('M j, Y g:i A') }}</span>
                                            @if($alert->is_grouped && $alert->count > 1)
                                                <span>First: {{ $alert->first_occurrence->format('M j, Y g:i A') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>

    <!-- JavaScript for Real-time Filtering -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deviceFilter = document.getElementById('device-filter');
            const severityFilter = document.getElementById('severity-filter');
            const timeRangeFilter = document.getElementById('time-range-filter');
            const customDateRange = document.getElementById('custom-date-range');
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            const applyFiltersBtn = document.getElementById('apply-filters');
            const clearFiltersBtn = document.getElementById('clear-filters');
            const alertsContainer = document.getElementById('alerts-container');

            // Show/hide custom date range
            timeRangeFilter.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.classList.remove('hidden');
                } else {
                    customDateRange.classList.add('hidden');
                }
            });

            // Apply filters
            applyFiltersBtn.addEventListener('click', function() {
                const filters = {
                    device_ids: Array.from(deviceFilter.selectedOptions).map(option => option.value),
                    severity: Array.from(severityFilter.selectedOptions).map(option => option.value),
                    time_range: timeRangeFilter.value,
                    start_date: startDateInput.value,
                    end_date: endDateInput.value
                };

                // Show loading state
                alertsContainer.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div><p class="mt-2 text-gray-500">Loading alerts...</p></div>';

                // Make AJAX request to filter alerts
                fetch(`/api/rtu/gateway/{{ $this->getData()['gateway']->id }}/alerts/filter`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Authorization': 'Bearer ' + (document.querySelector('meta[name="api-token"]')?.getAttribute('content') || '')
                    },
                    body: JSON.stringify({
                        filters: filters
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update alerts container with filtered results
                        alertsContainer.innerHTML = data.html;
                    } else {
                        console.error('Failed to filter alerts:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error filtering alerts:', error);
                    alertsContainer.innerHTML = '<div class="text-center py-8 text-red-500">Error loading alerts. Please try again.</div>';
                });
            });

            // Clear filters
            clearFiltersBtn.addEventListener('click', function() {
                deviceFilter.selectedIndex = -1;
                severityFilter.selectedIndex = -1;
                timeRangeFilter.value = 'last_day';
                customDateRange.classList.add('hidden');
                startDateInput.value = '';
                endDateInput.value = '';
                
                // Reload without filters
                window.location.href = window.location.pathname;
            });
        });
    </script>
</x-filament-widgets::widget>