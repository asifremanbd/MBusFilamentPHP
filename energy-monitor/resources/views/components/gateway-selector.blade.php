@php
    $currentGateway = $gateway ?? null;
    $availableGateways = \App\Models\Gateway::where('communication_status', '!=', 'offline')
        ->orderBy('name')
        ->get();
@endphp

<div class="flex items-center space-x-4">
    <!-- Gateway Selector Dropdown -->
    @if($availableGateways->count() > 0)
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" 
                    class="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @if($currentGateway)
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 rounded-full {{ 
                            $currentGateway->communication_status === 'online' ? 'bg-green-500' : 
                            ($currentGateway->communication_status === 'warning' ? 'bg-yellow-500' : 'bg-red-500') 
                        }}"></div>
                        <span>{{ $currentGateway->name }}</span>
                        @if($currentGateway->isRTUGateway())
                            <x-filament::badge color="info" size="sm">RTU</x-filament::badge>
                        @endif
                    </div>
                @else
                    <span>Select Gateway</span>
                @endif
                <x-heroicon-o-chevron-down class="w-4 h-4 ml-2" />
            </button>
            
            <div x-show="open" @click.away="open = false" 
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="origin-top-right absolute right-0 mt-2 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                <div class="py-1 max-h-64 overflow-y-auto">
                    @foreach($availableGateways as $gatewayOption)
                        <div class="px-4 py-2 hover:bg-gray-50 {{ $currentGateway && $currentGateway->id === $gatewayOption->id ? 'bg-blue-50' : '' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full {{ 
                                        $gatewayOption->communication_status === 'online' ? 'bg-green-500' : 
                                        ($gatewayOption->communication_status === 'warning' ? 'bg-yellow-500' : 'bg-red-500') 
                                    }}"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $gatewayOption->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $gatewayOption->ip_address ?? 'No IP' }} • 
                                            {{ ucfirst($gatewayOption->communication_status) }}
                                        </div>
                                    </div>
                                </div>
                                @if($gatewayOption->isRTUGateway())
                                    <x-filament::badge color="info" size="sm">RTU</x-filament::badge>
                                @endif
                            </div>
                            
                            <!-- Gateway Actions -->
                            <div class="mt-2 flex space-x-2">
                                <a href="{{ route('dashboard.gateway', $gatewayOption) }}" 
                                   class="text-xs text-blue-600 hover:text-blue-800">
                                    Standard View
                                </a>
                                @if($gatewayOption->isRTUGateway())
                                    <span class="text-xs text-gray-400">•</span>
                                    <a href="{{ route('dashboard.rtu', $gatewayOption) }}" 
                                       class="text-xs text-purple-600 hover:text-purple-800">
                                        RTU Dashboard
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    
                    @if($availableGateways->count() === 0)
                        <div class="px-4 py-2 text-sm text-gray-500">
                            No gateways available
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Dashboard Type Indicator -->
    @if($currentGateway)
        <div class="flex items-center space-x-2">
            @if(request()->routeIs('dashboard.rtu'))
                <x-filament::badge color="purple" size="sm">
                    <x-heroicon-o-cpu-chip class="w-3 h-3 mr-1" />
                    RTU Dashboard
                </x-filament::badge>
                <a href="{{ route('dashboard.gateway', $currentGateway) }}" 
                   class="text-xs text-gray-600 hover:text-gray-800 underline">
                    Switch to Standard
                </a>
            @elseif(request()->routeIs('dashboard.gateway'))
                <x-filament::badge color="blue" size="sm">
                    <x-heroicon-o-squares-2x2 class="w-3 h-3 mr-1" />
                    Standard Dashboard
                </x-filament::badge>
                @if($currentGateway->isRTUGateway())
                    <a href="{{ route('dashboard.rtu', $currentGateway) }}" 
                       class="text-xs text-purple-600 hover:text-purple-800 underline">
                        Switch to RTU
                    </a>
                @endif
            @endif
        </div>
    @endif
</div>