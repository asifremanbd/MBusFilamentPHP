<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $data = $this->getData();
        @endphp

        {{-- Widget Header --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-chart-bar class="w-5 h-5 text-gray-500" />
                <h3 class="text-lg font-medium text-gray-900">24-Hour Readings Trend</h3>
            </div>
            
            <div class="flex items-center space-x-3">
                {{-- Preferences Button --}}
                <button 
                    type="button"
                    onclick="togglePreferenceManager()"
                    class="inline-flex items-center px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50"
                    title="Customize Chart Preferences"
                >
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4 mr-1" />
                    Preferences
                </button>
                
                {{-- Time Range Selector --}}
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">Time Range:</label>
                    <select 
                        wire:model.live="timeRange" 
                        class="rounded-md border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @foreach($this->getTimeRangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Check if widget should be hidden --}}
        @if($this->shouldHide())
            {{-- Widget is completely hidden when no data is available --}}
            <div style="display: none;"></div>
        @elseif(!$data['has_data'])
            {{-- Show message when no data but metrics are available --}}
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <x-heroicon-o-chart-bar class="w-16 h-16 text-gray-300 mb-4" />
                <h4 class="text-lg font-medium text-gray-500 mb-2">No Trend Data Available</h4>
                <p class="text-sm text-gray-400 max-w-md">
                    {{ $data['message'] }}
                </p>
                @if(!empty($data['available_metrics']))
                    <p class="text-xs text-gray-400 mt-2">
                        Available metrics: {{ implode(', ', array_map('ucfirst', str_replace('_', ' ', $data['available_metrics']))) }}
                    </p>
                @endif
            </div>
        @else
            {{-- Metric Selection Interface --}}
            @if(count($data['available_metrics']) > 1)
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Select Metrics to Display:</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach($data['available_metrics'] as $metricKey)
                            @php
                                $config = $this->getMetricConfiguration($metricKey);
                                $isSelected = in_array($metricKey, $data['selected_metrics']);
                            @endphp
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedMetrics" 
                                    value="{{ $metricKey }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    {{ $isSelected ? 'checked' : '' }}
                                >
                                <div class="flex items-center space-x-1">
                                    @if(isset($config['icon']))
                                        <x-dynamic-component :component="$config['icon']" class="w-4 h-4" style="color: {{ $config['color'] }}" />
                                    @endif
                                    <span class="text-sm text-gray-700">{{ $config['label'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Chart Container --}}
            <div class="relative">
                @if(!empty($data['chart_data']['series']))
                    {{-- Chart will be rendered here --}}
                    <div 
                        id="rtu-trend-chart-{{ $this->gateway->id }}" 
                        class="w-full"
                        wire:ignore
                    ></div>

                    {{-- Current Values Display --}}
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($data['selected_metrics'] as $metricKey)
                            @php
                                $config = $this->getMetricConfiguration($metricKey);
                                $latestValue = null;
                                
                                // Get the latest value for this metric
                                if (isset($data['chart_data']['series'])) {
                                    foreach ($data['chart_data']['series'] as $series) {
                                        if (str_contains(strtolower($series['name']), strtolower(str_replace('_', ' ', $metricKey)))) {
                                            $latestValue = end($series['data'])['y'] ?? null;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        @if(isset($config['icon']))
                                            <x-dynamic-component :component="$config['icon']" class="w-5 h-5" style="color: {{ $config['color'] }}" />
                                        @endif
                                        <span class="text-sm font-medium text-gray-700">{{ $config['label'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-semibold {{ $this->getMetricStatusClass($metricKey, $latestValue) }}">
                                            {{ $this->formatMetricValue($metricKey, $latestValue) }}
                                        </div>
                                        <div class="text-xs text-gray-500">Current</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Fallback when no chart data is available --}}
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <x-heroicon-o-exclamation-triangle class="w-12 h-12 text-yellow-400 mb-3" />
                        <h4 class="text-md font-medium text-gray-600 mb-1">Chart Data Unavailable</h4>
                        <p class="text-sm text-gray-500">
                            Selected metrics have no data points for the chosen time range.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Chart Initialization Script --}}
            @if(!empty($data['chart_data']['series']))
                @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const chartElement = document.querySelector('#rtu-trend-chart-{{ $this->gateway->id }}');
                        if (!chartElement) return;

                        const chartData = @json($data['chart_data']);
                        const chartOptions = @json($data['chart_options']);
                        
                        // Prepare series data
                        const series = chartData.series.map(serie => ({
                            name: serie.name,
                            data: serie.data.map(point => [new Date(point.x).getTime(), point.y]),
                            color: serie.color
                        }));

                        // Configure Y-axes for multiple metrics
                        const yAxes = [];
                        const usedAxes = [...new Set(chartData.series.map(s => s.yAxis))];
                        
                        usedAxes.forEach((axisIndex, index) => {
                            const seriesForAxis = chartData.series.filter(s => s.yAxis === axisIndex);
                            const firstSeries = seriesForAxis[0];
                            
                            yAxes.push({
                                seriesName: firstSeries.name,
                                opposite: index > 0,
                                axisTicks: { show: true },
                                axisBorder: { 
                                    show: true, 
                                    color: firstSeries.color 
                                },
                                labels: { 
                                    style: { colors: firstSeries.color },
                                    formatter: function(value) {
                                        return value.toFixed(1) + ' ' + firstSeries.unit;
                                    }
                                },
                                title: {
                                    text: firstSeries.name + ' (' + firstSeries.unit + ')',
                                    style: { color: firstSeries.color }
                                }
                            });
                        });

                        // Merge chart options
                        const finalOptions = {
                            ...chartOptions,
                            series: series,
                            yaxis: yAxes.length > 1 ? yAxes : yAxes[0] || {},
                            tooltip: {
                                ...chartOptions.tooltip,
                                y: {
                                    formatter: function(value, { seriesIndex }) {
                                        const serie = chartData.series[seriesIndex];
                                        return value.toFixed(2) + ' ' + serie.unit;
                                    }
                                }
                            }
                        };

                        // Create and render chart
                        const chart = new ApexCharts(chartElement, finalOptions);
                        chart.render();

                        // Listen for Livewire updates
                        Livewire.on('refresh-chart', () => {
                            chart.destroy();
                            setTimeout(() => {
                                location.reload(); // Simple refresh for now
                            }, 100);
                        });

                        // Store chart instance for potential cleanup
                        chartElement._apexChart = chart;
                    });
                </script>
                @endpush
            @endif
        @endif

        {{-- Error Display --}}
        @if(isset($data['error']))
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                    <span class="text-sm font-medium text-red-800">Error</span>
                </div>
                <p class="mt-1 text-sm text-red-700">{{ $data['error'] }}</p>
            </div>
        @endif

        {{-- Last Updated Timestamp --}}
        @if(isset($data['end_time']))
            <div class="mt-4 text-xs text-gray-500 text-right">
                Last updated: {{ $data['end_time']->format('M j, Y g:i A') }}
            </div>
        @endif
    </x-filament::section>

    {{-- Preference Manager Modal (Hidden by default) --}}
    @if($this->gateway)
        @php
            $configService = app(\App\Services\RTUDashboardConfigService::class);
            $preferences = $configService->getTrendPreferences(auth()->user(), $this->gateway);
            $config = $configService->getDashboardConfig(auth()->user());
        @endphp
        
        <div class="rtu-preference-manager hidden">
            <x-rtu-preference-manager 
                :gateway="$this->gateway" 
                :preferences="$preferences" 
                :config="$config" 
            />
        </div>
    @endif

    {{-- Preference Manager Scripts --}}
    @push('scripts')
    <script>
        // Listen for preference updates
        window.addEventListener('refresh-trend-widget', function() {
            // Refresh the Livewire component
            Livewire.find('{{ $this->getId() }}').call('$refresh');
        });

        function togglePreferenceManager() {
            const manager = document.querySelector('.rtu-preference-manager');
            if (manager) {
                manager.classList.toggle('hidden');
            }
        }

        // Close preference manager when clicking outside
        document.addEventListener('click', function(event) {
            const manager = document.querySelector('.rtu-preference-manager');
            const trigger = event.target.closest('[onclick*="togglePreferenceManager"]');
            
            if (manager && !manager.classList.contains('hidden') && !trigger && !manager.contains(event.target)) {
                manager.classList.add('hidden');
            }
        });

        // Close preference manager on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const manager = document.querySelector('.rtu-preference-manager');
                if (manager && !manager.classList.contains('hidden')) {
                    manager.classList.add('hidden');
                }
            }
        });
    </script>
    @endpush
</x-filament-widgets::widget>