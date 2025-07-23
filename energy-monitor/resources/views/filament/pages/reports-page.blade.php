<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Report Filters -->
        <x-filament::section>
            <x-slot name="heading">
                Report Configuration
            </x-slot>
            
            {{ $this->form }}
        </x-filament::section>

        <!-- Energy Report -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-bolt class="w-5 h-5 text-yellow-500" />
                    Energy Consumption Report
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                            <x-heroicon-o-bolt class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Consumption</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($energyReport['total_consumption'] ?? 0, 2) }} kWh</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                            <x-heroicon-o-lightning-bolt class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Power</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($energyReport['average_power'] ?? 0, 2) }} kW</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                            <x-heroicon-o-cpu-chip class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Peak Devices</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $energyReport['consumption_by_device']->count() ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                            <x-heroicon-o-clock class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Peak Hours</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $energyReport['peak_times']->count() ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($energyReport['consumption_by_device']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Consuming Devices -->
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Top Consuming Devices</h3>
                        <div class="space-y-2">
                            @foreach($energyReport['consumption_by_device']->take(5) as $device)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="font-medium">{{ $device->name }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ number_format($device->avg_consumption, 2) }} kWh
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Peak Consumption Times -->
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Peak Consumption Hours</h3>
                        <div class="space-y-2">
                            @foreach($energyReport['peak_times'] as $time)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="font-medium">{{ $time->hour }}:00 - {{ $time->hour + 1 }}:00</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ number_format($time->avg_power, 2) }} kW
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        <!-- System Health Report -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-heart class="w-5 h-5 text-green-500" />
                    System Health Report
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                            <x-heroicon-o-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Device Uptime</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $systemHealthReport['device_uptime_percentage'] ?? 0 }}%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                            <x-heroicon-o-cpu-chip class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Devices</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $systemHealthReport['active_devices'] ?? 0 }}/{{ $systemHealthReport['total_devices'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                            <x-heroicon-o-chart-bar class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Data Points</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($systemHealthReport['data_points_collected'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                            <x-heroicon-o-signal class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Gateways</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $systemHealthReport['gateway_status']->count() ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($systemHealthReport['gateway_status']))
                <div>
                    <h3 class="text-lg font-semibold mb-3">Gateway Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($systemHealthReport['gateway_status'] as $gateway)
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <h4 class="font-medium mb-2">{{ $gateway->name }}</h4>
                                <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <div>Total Devices: {{ $gateway->devices_count }}</div>
                                    <div>Active Devices: {{ $gateway->active_devices_count }}</div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full {{ $gateway->active_devices_count > 0 ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                        {{ $gateway->active_devices_count > 0 ? 'Online' : 'Offline' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>

        <!-- Usage Analytics -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-blue-500" />
                    Usage Analytics
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Parameters -->
                @if(!empty($usageAnalytics['top_parameters']))
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Most Monitored Parameters</h3>
                        <div class="space-y-2">
                            @foreach($usageAnalytics['top_parameters']->take(8) as $parameter)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="font-medium">{{ $parameter->parameter_name }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ number_format($parameter->reading_count) }} readings
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Daily Trends -->
                @if(!empty($usageAnalytics['daily_trends']))
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Daily Reading Trends</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($usageAnalytics['daily_trends'] as $trend)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="font-medium">{{ \Carbon\Carbon::parse($trend->date)->format('M d, Y') }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ number_format($trend->reading_count) }} readings
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>

        <!-- Alert Summary -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                    Alert Summary
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Alerts</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $alertSummary['total_alerts'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                            <x-heroicon-o-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Resolved</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $alertSummary['resolved_alerts'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                            <x-heroicon-o-fire class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Critical</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $alertSummary['critical_alerts'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                            <x-heroicon-o-chart-pie class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Resolution Rate</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $alertSummary['total_alerts'] > 0 ? round(($alertSummary['resolved_alerts'] / $alertSummary['total_alerts']) * 100, 1) : 0 }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Alerts by Severity -->
                @if(!empty($alertSummary['alerts_by_severity']))
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Alerts by Severity</h3>
                        <div class="space-y-2">
                            @foreach($alertSummary['alerts_by_severity'] as $severity)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full {{ 
                                            $severity->severity === 'critical' ? 'bg-red-500' : 
                                            ($severity->severity === 'high' ? 'bg-orange-500' : 
                                            ($severity->severity === 'medium' ? 'bg-yellow-500' : 'bg-green-500'))
                                        }}"></div>
                                        <span class="font-medium capitalize">{{ $severity->severity }}</span>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $severity->count }} alerts
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Alerts by Device -->
                @if(!empty($alertSummary['alerts_by_device']))
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Top Alert-Generating Devices</h3>
                        <div class="space-y-2">
                            @foreach($alertSummary['alerts_by_device']->take(8) as $device)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="font-medium">{{ $device->name }}</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $device->alert_count }} alerts
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>