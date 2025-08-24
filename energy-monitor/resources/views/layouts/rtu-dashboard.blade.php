<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Energy Monitor') }} - RTU Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/css/rtu-widgets.css', 'resources/css/dashboard-customization.css', 'resources/js/app.js'])
    
    <!-- Filament Styles -->
    @filamentStyles
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Logo -->
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ config('app.name', 'Energy Monitor') }}
                            </h1>
                        </div>
                        
                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('filament.admin.pages.dashboard') }}" 
                               class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 transition duration-150 ease-in-out">
                                <x-heroicon-o-squares-2x2 class="w-4 h-4 mr-1" />
                                Standard Dashboard
                            </a>
                            @if(isset($gateway) && $gateway->isRTUGateway())
                                <a href="{{ route('dashboard.gateway', $gateway) }}" 
                                   class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 transition duration-150 ease-in-out">
                                    <x-heroicon-o-server class="w-4 h-4 mr-1" />
                                    Gateway View
                                </a>
                            @endif
                            <span class="inline-flex items-center px-1 pt-1 border-b-2 border-indigo-500 text-sm font-medium text-gray-900 dark:text-white">
                                <x-heroicon-o-cpu-chip class="w-4 h-4 mr-1" />
                                RTU Dashboard
                            </span>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        @if($gateway ?? null)
                            <div class="flex items-center space-x-2">
                                <x-heroicon-o-server class="w-4 h-4 text-gray-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $gateway->name }}</span>
                            </div>
                        @endif
                        
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ auth()->user()->name }}
                        </div>
                        
                        <!-- Section Controls -->
                        <div class="flex items-center space-x-2">
                            <button onclick="window.rtuSectionManager?.resetToDefaults()" 
                                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition duration-150 ease-in-out"
                                    title="Reset sections to default">
                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Filament Scripts -->
    @filamentScripts
    
    <!-- RTU Dashboard Specific Scripts -->
    <script>
        // Initialize section manager when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Add any RTU dashboard specific initialization here
            console.log('RTU Dashboard layout loaded');
            
            // Add global error handler for section operations
            window.addEventListener('error', function(e) {
                if (e.message.includes('rtuSectionManager')) {
                    console.warn('RTU Section Manager not available:', e.message);
                }
            });
        });
    </script>
</body>
</html>