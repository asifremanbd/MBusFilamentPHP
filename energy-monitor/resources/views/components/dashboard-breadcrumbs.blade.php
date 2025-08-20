@php
    $currentGateway = $gateway ?? null;
    $routeName = request()->route()->getName();
@endphp

<nav class="flex" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <!-- Home -->
        <li class="inline-flex items-center">
            <a href="{{ route('filament.admin.pages.dashboard') }}" 
               class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                <x-heroicon-o-home class="w-4 h-4 mr-2" />
                Dashboard
            </a>
        </li>

        <!-- Global Dashboard -->
        @if($routeName === 'dashboard.global')
            <li aria-current="page">
                <div class="flex items-center">
                    <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                    <span class="ml-1 text-sm font-medium text-blue-600 md:ml-2">
                        Global Overview
                    </span>
                </div>
            </li>
        @endif

        <!-- Gateway Dashboard -->
        @if(in_array($routeName, ['dashboard.gateway', 'dashboard.rtu']) && $currentGateway)
            <li>
                <div class="flex items-center">
                    <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                    <a href="{{ route('dashboard.gateway', $currentGateway) }}" 
                       class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                        Gateways
                    </a>
                </div>
            </li>
            
            <li>
                <div class="flex items-center">
                    <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">
                        {{ $currentGateway->name }}
                    </span>
                </div>
            </li>

            @if($routeName === 'dashboard.rtu')
                <li aria-current="page">
                    <div class="flex items-center">
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        <span class="ml-1 text-sm font-medium text-purple-600 md:ml-2">
                            RTU Dashboard
                        </span>
                    </div>
                </li>
            @elseif($routeName === 'dashboard.gateway')
                <li aria-current="page">
                    <div class="flex items-center">
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        <span class="ml-1 text-sm font-medium text-blue-600 md:ml-2">
                            Standard View
                        </span>
                    </div>
                </li>
            @endif
        @endif
    </ol>
</nav>