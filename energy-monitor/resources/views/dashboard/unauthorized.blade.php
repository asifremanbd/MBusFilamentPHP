@extends('layouts.dashboard')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <!-- Unauthorized Icon -->
            <x-heroicon-o-shield-exclamation class="mx-auto h-16 w-16 text-red-500" />
            
            <!-- Error Title -->
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
                {{ $error ?? 'Access Denied' }}
            </h2>
            
            <!-- Error Message -->
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $message ?? 'You do not have permission to access this resource.' }}
            </p>
        </div>

        <!-- Suggested Actions -->
        @if(isset($suggested_action))
            <div class="mt-8">
                @if($suggested_action['action'] === 'contact_admin')
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <div class="flex">
                            <x-heroicon-o-information-circle class="h-5 w-5 text-blue-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Need Access?
                                </h3>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    {{ $suggested_action['message'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Available Actions -->
        <div class="mt-8 space-y-4">
            <button onclick="window.history.back()" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <x-heroicon-o-arrow-left class="h-4 w-4 mr-2" />
                Go Back
            </button>
            
            <a href="{{ route('filament.admin.pages.dashboard') }}" 
               class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <x-heroicon-o-home class="h-4 w-4 mr-2" />
                Return to Dashboard
            </a>
        </div>

        <!-- User Information -->
        <div class="mt-8 p-4 bg-gray-100 dark:bg-gray-800 rounded-md">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Current User</h4>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                Logged in as: {{ auth()->user()->name ?? 'Unknown' }}
            </p>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                Email: {{ auth()->user()->email ?? 'Unknown' }}
            </p>
        </div>
    </div>
</div>
@endsection