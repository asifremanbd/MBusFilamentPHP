@if(!$alertsData['has_alerts'])
    <!-- No Active Alerts Display -->
    <div class="text-center py-8">
        <x-heroicon-o-check-circle class="w-16 h-16 text-green-500 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
            No Active Alerts
        </h3>
        <p class="text-gray-500 dark:text-gray-400">
            @if(request()->has('severity') || request()->has('device_ids') || request()->has('time_range'))
                No alerts match the current filters.
            @else
                All systems are operating normally. No critical alerts are present.
            @endif
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