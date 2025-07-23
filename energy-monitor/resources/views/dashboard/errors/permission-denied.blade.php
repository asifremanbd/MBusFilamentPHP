@extends('layouts.app')

@section('title', 'Access Denied')

@section('content')
<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="mx-auto h-12 w-12 text-red-600">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
            {{ $error }}
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            {{ $message }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <div class="space-y-6">
                @if(isset($fallback_action))
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Suggested Action
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>{{ $fallback_action['message'] }}</p>
                                </div>
                                @if($fallback_action['action'] === 'redirect' && isset($fallback_action['url']))
                                    <div class="mt-4">
                                        <a href="{{ $fallback_action['url'] }}" class="text-sm bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                                            Go to Alternative
                                        </a>
                                    </div>
                                @elseif($fallback_action['action'] === 'refresh' && isset($fallback_action['refresh_url']))
                                    <div class="mt-4">
                                        <a href="{{ $fallback_action['refresh_url'] }}" class="text-sm bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                                            Refresh Page
                                        </a>
                                    </div>
                                @elseif($fallback_action['action'] === 'contact_admin')
                                    <div class="mt-4">
                                        <p class="text-sm text-blue-700">
                                            Contact: {{ $fallback_action['contact_info'] ?? 'your system administrator' }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($fallback_action['available_gateways']) && count($fallback_action['available_gateways']) > 0)
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <h4 class="text-sm font-medium text-green-800 mb-2">Available Gateways</h4>
                        <div class="space-y-2">
                            @foreach($fallback_action['available_gateways'] as $gatewayId => $gatewayName)
                                <a href="{{ route('dashboard.gateway', ['gateway' => $gatewayId]) }}" 
                                   class="block text-sm text-green-700 hover:text-green-900 hover:underline">
                                    {{ $gatewayName }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex space-x-4">
                    <a href="{{ route('dashboard.global') }}" 
                       class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded text-center">
                        Global Dashboard
                    </a>
                    <a href="{{ url()->previous() }}" 
                       class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded text-center">
                        Go Back
                    </a>
                </div>

                @if(app()->environment('local') && isset($context))
                    <details class="mt-6">
                        <summary class="text-sm text-gray-500 cursor-pointer">Debug Information</summary>
                        <div class="mt-2 text-xs text-gray-400 bg-gray-100 p-3 rounded">
                            <pre>{{ json_encode($context, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection