{{-- Cross-Gateway Alerts Widget Fallback --}}
<div class="fallback-widget-content">
    @if(empty($data['critical_alerts']) && empty($data['warning_alerts']) && empty($data['recent_alerts']))
        <div class="text-center py-6">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Alert Data Available</h3>
            <p class="mt-1 text-sm text-gray-500">Alert information is currently unavailable.</p>
        </div>
    @else
        <div class="space-y-3">
            @if(!empty($data['critical_alerts']))
                <div class="bg-red-50 border border-red-200 rounded p-3">
                    <h5 class="text-sm font-medium text-red-800 mb-2">Critical Alerts</h5>
                    <div class="space-y-1">
                        @foreach(array_slice($data['critical_alerts'], 0, 3) as $alert)
                            <div class="text-sm text-red-700">
                                <span class="font-medium">{{ $alert['device_name'] ?? 'Unknown Device' }}</span>
                                @if(isset($alert['parameter_name']))
                                    - {{ $alert['parameter_name'] }}
                                @endif
                            </div>
                        @endforeach
                        @if(count($data['critical_alerts']) > 3)
                            <div class="text-xs text-red-600">
                                +{{ count($data['critical_alerts']) - 3 }} more critical alerts
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if(!empty($data['warning_alerts']))
                <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                    <h5 class="text-sm font-medium text-yellow-800 mb-2">Warning Alerts</h5>
                    <div class="space-y-1">
                        @foreach(array_slice($data['warning_alerts'], 0, 2) as $alert)
                            <div class="text-sm text-yellow-700">
                                <span class="font-medium">{{ $alert['device_name'] ?? 'Unknown Device' }}</span>
                                @if(isset($alert['parameter_name']))
                                    - {{ $alert['parameter_name'] }}
                                @endif
                            </div>
                        @endforeach
                        @if(count($data['warning_alerts']) > 2)
                            <div class="text-xs text-yellow-600">
                                +{{ count($data['warning_alerts']) - 2 }} more warnings
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if(!empty($data['recent_alerts']))
                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                    <h5 class="text-sm font-medium text-blue-800 mb-2">Recent Activity</h5>
                    <div class="text-sm text-blue-700">
                        {{ count($data['recent_alerts']) }} recent alerts
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if(isset($data['_fallback']))
        <div class="mt-3 text-xs text-yellow-600 bg-yellow-50 p-2 rounded">
            ⚠️ Displaying cached alert data from {{ \Carbon\Carbon::parse($data['_fallback']['generated_at'])->diffForHumans() }}
        </div>
    @endif
</div>