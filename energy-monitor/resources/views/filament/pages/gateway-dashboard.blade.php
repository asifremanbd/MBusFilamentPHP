<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Gateway Selection Form --}}
        <x-filament::section>
            <x-slot name="heading">
                Gateway Selection
            </x-slot>
            
            <x-slot name="description">
                Choose a gateway to monitor its devices and view real-time data.
            </x-slot>

            {{ $this->form }}
        </x-filament::section>

        {{-- Gateway Information is now shown in the stats overview widget --}}

        {{-- Header Widgets (Stats Overview) --}}
        @if($this->getHeaderWidgets())
            <div class="filament-widgets space-y-6">
                @foreach($this->getHeaderWidgets() as $widget)
                    @livewire($widget, $this->getHeaderWidgetData())
                @endforeach
            </div>
        @endif

        {{-- Main Widgets --}}
        @if($this->getWidgets())
            <div class="filament-widgets grid grid-cols-1 xl:grid-cols-2 gap-6">
                @foreach($this->getWidgets() as $widget)
                    <div class="xl:col-span-{{ $widget === \App\Filament\Widgets\DeviceStatusWidget::class ? '2' : '1' }}">
                        @livewire($widget, $this->getWidgetData())
                    </div>
                @endforeach
            </div>
        @endif

        {{-- No Gateway Selected State --}}
        @if(!$gateway)
            <x-filament::section>
                <div class="text-center py-12">
                    <x-heroicon-o-signal class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Gateway Selected</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Please select a gateway from the dropdown above to view monitoring data.
                    </p>
                    @if(\App\Models\Gateway::count() === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <a href="{{ route('filament.admin.resources.gateways.create') }}" 
                               class="text-blue-600 hover:text-blue-800 underline">
                                Create your first gateway
                            </a>
                            to get started with monitoring.
                        </p>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>

    {{-- Auto-refresh script --}}
    <script>
        // Auto-refresh widgets every 30 seconds
        setInterval(function() {
            Livewire.dispatch('$refresh');
        }, 30000);

        // Listen for gateway changes and refresh widgets
        document.addEventListener('livewire:init', () => {
            Livewire.on('gateway-changed', (event) => {
                // Refresh all widgets when gateway changes
                setTimeout(() => {
                    Livewire.dispatch('$refresh');
                }, 100);
            });
        });
    </script>
</x-filament-panels::page>
