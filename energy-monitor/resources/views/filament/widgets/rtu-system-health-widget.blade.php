@php
    $data = $this->getData();
    $sectionConfig = app(\App\Services\RTUDashboardSectionService::class)->getSectionConfiguration(auth()->user())['system_health'] ?? [];
    $hasError = !empty($data['error']) || $data['error_info']['has_error'];
    $errorInfo = $data['error_info'] ?? ['has_error' => false];
@endphp

<x-filament-widgets::widget>
    <x-rtu-collapsible-section 
        section-key="system_health"
        title="System Health"
        icon="heroicon-o-cpu-chip"
        :is-collapsed="$sectionConfig['is_collapsed'] ?? false"
        :display-order="$sectionConfig['display_order'] ?? 1">
        
        @if($errorInfo['has_error'] && $errorInfo['is_cached'])
            <x-slot name="badge">
                <x-filament::badge color="warning" size="sm">
                    Cached Data
                </x-filament::badge>
            </x-slot>
        @endif
        
        @if($errorInfo['has_error'] && $errorInfo['retry_available'])
            <x-slot name="actions">
                <x-filament::button 
                    wire:click="retryDataCollection"
                    size="sm"
                    color="primary"
                    outlined
                    icon="heroicon-o-arrow-path">
                    Retry
                </x-filament::button>
            </x-slot>
        @endif
        
        @if($gateway ?? null)
            <div class="mb-4">
                <x-filament::badge color="info">
                    {{ $gateway->name }}
                </x-filament::badge>
            </div>
        @endif

        <div class="space-y-4">
            @if($hasError)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-red-800 dark:text-red-200 mb-1">
                                {{ $errorInfo['message'] ?? $data['error'] ?? 'System Health Data Unavailable' }}
                            </h4>
                            
                            @if($errorInfo['cache_age'])
                                <p class="text-xs text-red-600 dark:text-red-300 mb-2">
                                    Showing cached data from {{ $errorInfo['cache_age'] }} minutes ago
                                </p>
                            @endif
                            
                            @if(!empty($errorInfo['troubleshooting']))
                                <details class="mt-2">
                                    <summary class="text-xs text-red-600 dark:text-red-300 cursor-pointer hover:text-red-800 dark:hover:text-red-100">
                                        View Troubleshooting Steps
                                    </summary>
                                    <div class="mt-2 space-y-1">
                                        @foreach(array_slice($errorInfo['troubleshooting'], 0, 4) as $step)
                                            <div class="text-xs text-red-600 dark:text-red-300 flex items-start space-x-1">
                                                <span class="text-red-400 mt-1">•</span>
                                                <span>{{ $step }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if(!$hasError || ($hasError && $errorInfo['fallback_source'] !== 'none'))
                <!-- Overall Health Score -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Health Score</h3>
                            <p class="text-2xl font-bold {{ $this->getHealthScoreColor($data['health_score']) }} mt-1">
                                {{ $data['health_score'] }}/100
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-dynamic-component 
                                :component="$this->getStatusIcon($data['overall_status'])" 
                                class="w-8 h-8 {{ $this->getHealthScoreColor($data['health_score']) }}" 
                            />
                            <x-filament::badge :color="match($data['overall_status']) {
                                'critical' => 'danger',
                                'warning' => 'warning', 
                                'normal' => 'success',
                                'offline' => 'gray',
                                default => 'gray'
                            }">
                                {{ ucfirst($data['overall_status']) }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>

                <!-- System Metrics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Router Uptime -->
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border {{ $this->getStatusClass($data['uptime']['status']) }}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <x-dynamic-component 
                                    :component="$data['uptime']['icon']" 
                                    class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                                />
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Router Uptime</h4>
                            </div>
                            <x-dynamic-component 
                                :component="$this->getStatusIcon($data['uptime']['status'])" 
                                class="w-4 h-4" 
                            />
                        </div>
                        
                        <div class="space-y-1">
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $data['uptime']['formatted_value'] }}
                            </p>
                            <x-filament::badge :color="match($data['uptime']['status']) {
                                'online' => 'success',
                                'warning' => 'warning',
                                'offline' => 'danger',
                                default => 'gray'
                            }" size="sm">
                                {{ ucfirst($data['uptime']['status']) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <!-- CPU Load -->
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border {{ $this->getStatusClass($data['cpu_load']['status']) }}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <x-dynamic-component 
                                    :component="$data['cpu_load']['icon']" 
                                    class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                                />
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">CPU Load</h4>
                            </div>
                            <x-dynamic-component 
                                :component="$this->getStatusIcon($data['cpu_load']['status'])" 
                                class="w-4 h-4" 
                            />
                        </div>
                        
                        <div class="space-y-2">
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $data['cpu_load']['formatted_value'] }}
                            </p>
                            
                            @if($data['cpu_load']['value'] !== null)
                                <!-- CPU Load Progress Bar -->
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div class="h-2 rounded-full transition-all duration-300 {{ 
                                        $data['cpu_load']['status'] === 'critical' ? 'bg-red-500' : 
                                        ($data['cpu_load']['status'] === 'warning' ? 'bg-yellow-500' : 'bg-green-500') 
                                    }}" style="width: {{ min($data['cpu_load']['value'], 100) }}%"></div>
                                </div>
                                
                                <!-- Threshold Indicators -->
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>0%</span>
                                    <span class="text-yellow-600">{{ $data['cpu_load']['threshold_warning'] }}%</span>
                                    <span class="text-red-600">{{ $data['cpu_load']['threshold_critical'] }}%</span>
                                    <span>100%</span>
                                </div>
                            @endif
                            
                            <x-filament::badge :color="match($data['cpu_load']['status']) {
                                'critical' => 'danger',
                                'warning' => 'warning',
                                'normal' => 'success',
                                default => 'gray'
                            }" size="sm">
                                {{ ucfirst($data['cpu_load']['status']) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <!-- Memory Usage -->
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border {{ $this->getStatusClass($data['memory_usage']['status']) }}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <x-dynamic-component 
                                    :component="$data['memory_usage']['icon']" 
                                    class="w-5 h-5 text-gray-600 dark:text-gray-400" 
                                />
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Memory Usage</h4>
                            </div>
                            <x-dynamic-component 
                                :component="$this->getStatusIcon($data['memory_usage']['status'])" 
                                class="w-4 h-4" 
                            />
                        </div>
                        
                        <div class="space-y-2">
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $data['memory_usage']['formatted_value'] }}
                            </p>
                            
                            @if($data['memory_usage']['value'] !== null)
                                <!-- Memory Usage Progress Bar -->
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div class="h-2 rounded-full transition-all duration-300 {{ 
                                        $data['memory_usage']['status'] === 'critical' ? 'bg-red-500' : 
                                        ($data['memory_usage']['status'] === 'warning' ? 'bg-yellow-500' : 'bg-green-500') 
                                    }}" style="width: {{ min($data['memory_usage']['value'], 100) }}%"></div>
                                </div>
                                
                                <!-- Threshold Indicators -->
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>0%</span>
                                    <span class="text-yellow-600">{{ $data['memory_usage']['threshold_warning'] }}%</span>
                                    <span class="text-red-600">{{ $data['memory_usage']['threshold_critical'] }}%</span>
                                    <span>100%</span>
                                </div>
                            @endif
                            
                            <x-filament::badge :color="match($data['memory_usage']['status']) {
                                'critical' => 'danger',
                                'warning' => 'warning',
                                'normal' => 'success',
                                default => 'gray'
                            }" size="sm">
                                {{ ucfirst($data['memory_usage']['status']) }}
                            </x-filament::badge>
                        </div>
                    </div>
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