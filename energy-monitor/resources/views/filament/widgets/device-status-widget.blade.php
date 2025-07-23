<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Connected Devices
        </x-slot>

        <x-slot name="headerEnd">
            @if($gateway ?? null)
                <x-filament::badge color="info">
                    {{ $gateway->name }}
                </x-filament::badge>
            @endif
        </x-slot>

        <div class="space-y-4">
            @forelse ($devices as $deviceData)
                @php
                    $device = $deviceData['device'];
                    $isOnline = $deviceData['isOnline'];
                    $latestReadings = $deviceData['latestReadings'];
                    $activeAlerts = $deviceData['activeAlerts'];
                    $lastReading = $deviceData['lastReading'];
                @endphp
                
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-3 h-3 rounded-full {{ $isOnline ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $device->name }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Slave ID: {{ $device->slave_id }} | Location: {{ $device->location_tag }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <x-filament::badge color="{{ $isOnline ? 'success' : 'danger' }}">
                                {{ $isOnline ? 'Online' : 'Offline' }}
                            </x-filament::badge>
                            
                            @if($activeAlerts > 0)
                                <x-filament::badge color="warning">
                                    {{ $activeAlerts }} Alert{{ $activeAlerts > 1 ? 's' : '' }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>
                    
                    @if($latestReadings->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mt-4">
                            @foreach($latestReadings as $parameterName => $reading)
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ $parameterName }}
                                    </div>
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                        {{ number_format($reading->value, 2) }} {{ $reading->register->unit }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ $reading->timestamp->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <x-heroicon-o-exclamation-triangle class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">No recent readings available</p>
                        </div>
                    @endif
                    
                    @if($lastReading)
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Last Update: {{ $lastReading->timestamp->format('M d, Y H:i:s') }}
                            </p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8">
                    <x-heroicon-o-device-phone-mobile class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Devices Found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($gateway ?? null)
                            No devices are configured for {{ $gateway->name }}.
                        @else
                            No gateway selected or no devices configured.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget> 