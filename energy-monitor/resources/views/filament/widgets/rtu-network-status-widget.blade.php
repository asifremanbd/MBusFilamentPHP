@php
    $data = $this->getData();
    $sectionConfig = app(\App\Services\RTUDashboardSectionService::class)->getSectionConfiguration(auth()->user())['network_status'] ?? [];
@endphp

<x-filament-widgets::widget>
    <x-rtu-collapsible-section 
        section-key="network_status"
        title="Network Status"
        icon="heroicon-o-signal"
        :is-collapsed="$sectionConfig['is_collapsed'] ?? false"
        :display-order="$sectionConfig['display_order'] ?? 2">
        
        @if($gateway ?? null)
            <div class="mb-4">
                <x-filament::badge color="info">
                    {{ $gateway->name }}
                </x-filament::badge>
            </div>
        @endif

        <div class="space-y-6">
            @if($data['error'] ?? false)
                <div class="text-center py-8">
                    <x-heroicon-o-exclamation-triangle class="w-12 h-12 mx-auto text-red-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Network Status Unavailable</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $data['error'] }}
                    </p>
                </div>
            @else
                <!-- WAN Connection Section -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <x-dynamic-component 
                                :component="$data['wan_connection']['icon']" 
                                class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                            />
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">WAN Connection</h4>
                        </div>
                        <x-filament::badge :color="match($data['wan_connection']['status']) {
                            'connected' => 'success',
                            'disconnected' => 'danger',
                            'connecting' => 'warning',
                            default => 'gray'
                        }">
                            {{ ucfirst($data['wan_connection']['status']) }}
                        </x-filament::badge>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">IP Address:</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white font-mono">
                                {{ $data['wan_connection']['ip_address'] }}
                            </span>
                        </div>
                        
                        @if($data['wan_connection']['status'] === 'connected')
                            <div class="flex items-center space-x-1 text-green-600 dark:text-green-400">
                                <x-heroicon-o-check-circle class="w-4 h-4" />
                                <span class="text-xs">Connection Active</span>
                            </div>
                        @elseif($data['wan_connection']['status'] === 'disconnected')
                            <div class="flex items-center space-x-1 text-red-600 dark:text-red-400">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                <span class="text-xs">Connection Lost</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- SIM Card Details Section -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center space-x-2 mb-3">
                        <x-dynamic-component 
                            :component="$data['sim_details']['icon']" 
                            class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                        />
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">SIM Card Details</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">ICCID</span>
                            <p class="text-sm font-medium text-gray-900 dark:text-white font-mono">
                                {{ $data['sim_details']['iccid'] }}
                            </p>
                        </div>
                        
                        <div class="space-y-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">APN</span>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $data['sim_details']['apn'] }}
                            </p>
                        </div>
                        
                        <div class="space-y-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Operator</span>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $data['sim_details']['operator'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Signal Quality Section -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <x-dynamic-component 
                                :component="$data['signal_quality']['icon']" 
                                class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                            />
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Signal Quality</h4>
                        </div>
                        <x-filament::badge :color="match($data['signal_quality']['overall_status']) {
                            'excellent' => 'success',
                            'good' => 'success',
                            'fair' => 'warning',
                            'poor' => 'danger',
                            default => 'gray'
                        }">
                            {{ ucfirst($data['signal_quality']['overall_status']) }}
                        </x-filament::badge>
                    </div>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- RSSI -->
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                {{ $data['signal_quality']['rssi']['label'] }}
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                @if($data['signal_quality']['rssi']['value'] !== null)
                                    {{ $data['signal_quality']['rssi']['value'] }}
                                    <span class="text-xs text-gray-500">{{ $data['signal_quality']['rssi']['unit'] }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                            @if($data['signal_quality']['rssi']['value'] !== null)
                                <div class="mt-2">
                                    @php
                                        $rssiValue = $data['signal_quality']['rssi']['value'];
                                        $rssiStatus = $rssiValue > -70 ? 'excellent' : ($rssiValue > -85 ? 'good' : ($rssiValue > -100 ? 'fair' : 'poor'));
                                        $rssiColor = match($rssiStatus) {
                                            'excellent' => 'bg-green-500',
                                            'good' => 'bg-green-400',
                                            'fair' => 'bg-yellow-500',
                                            'poor' => 'bg-red-500',
                                            default => 'bg-gray-400'
                                        };
                                        // Convert RSSI to percentage (rough approximation)
                                        $rssiPercentage = max(0, min(100, (($rssiValue + 120) / 50) * 100));
                                    @endphp
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-600">
                                        <div class="h-2 rounded-full {{ $rssiColor }}" style="width: {{ $rssiPercentage }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- RSRP -->
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                {{ $data['signal_quality']['rsrp']['label'] }}
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                @if($data['signal_quality']['rsrp']['value'] !== null)
                                    {{ $data['signal_quality']['rsrp']['value'] }}
                                    <span class="text-xs text-gray-500">{{ $data['signal_quality']['rsrp']['unit'] }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                        </div>

                        <!-- RSRQ -->
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                {{ $data['signal_quality']['rsrq']['label'] }}
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                @if($data['signal_quality']['rsrq']['value'] !== null)
                                    {{ $data['signal_quality']['rsrq']['value'] }}
                                    <span class="text-xs text-gray-500">{{ $data['signal_quality']['rsrq']['unit'] }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                        </div>

                        <!-- SINR -->
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                {{ $data['signal_quality']['sinr']['label'] }}
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                @if($data['signal_quality']['sinr']['value'] !== null)
                                    {{ $data['signal_quality']['sinr']['value'] }}
                                    <span class="text-xs text-gray-500">{{ $data['signal_quality']['sinr']['unit'] }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Signal Quality Indicators -->
                    @if($data['signal_quality']['overall_status'] !== 'unknown')
                        <div class="mt-4 p-3 rounded-lg {{ match($data['signal_quality']['overall_status']) {
                            'excellent' => 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800',
                            'good' => 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800',
                            'fair' => 'bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800',
                            'poor' => 'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800',
                            default => 'bg-gray-50 border border-gray-200 dark:bg-gray-900/20 dark:border-gray-800'
                        } }}">
                            <div class="flex items-center space-x-2">
                                @if($data['signal_quality']['overall_status'] === 'excellent' || $data['signal_quality']['overall_status'] === 'good')
                                    <x-heroicon-o-signal class="w-5 h-5 text-green-600 dark:text-green-400" />
                                    <span class="text-sm font-medium text-green-800 dark:text-green-200">
                                        Strong signal quality - optimal performance expected
                                    </span>
                                @elseif($data['signal_quality']['overall_status'] === 'fair')
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                                    <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Moderate signal quality - performance may be affected
                                    </span>
                                @else
                                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-600 dark:text-red-400" />
                                    <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                        Poor signal quality - connection issues may occur
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Last Updated -->
                @if($data['last_updated'])
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center space-x-1">
                            <x-heroicon-o-clock class="w-3 h-3" />
                            <span>Last Updated: {{ $data['last_updated']->format('M d, Y H:i:s') }}</span>
                            <span class="text-gray-400">•</span>
                            <span>{{ $data['last_updated']->diffForHumans() }}</span>
                        </p>
                    </div>
                @endif
            @endif
        </div>
    </x-rtu-collapsible-section>
</x-filament-widgets::widget>