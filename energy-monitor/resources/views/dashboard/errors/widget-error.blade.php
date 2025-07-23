{{-- Widget Error Component --}}
<div class="widget-error-container bg-red-50 border border-red-200 rounded-lg p-4 m-2">
    <div class="flex items-start">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-red-800">
                Widget Error: {{ $widget_name ?? $widget_id }}
            </h3>
            <div class="mt-2 text-sm text-red-700">
                <p>{{ $message ?? 'An error occurred while loading this widget.' }}</p>
            </div>
            
            @if(isset($retry_strategy) && $retry_strategy['available'])
                <div class="mt-3">
                    <button onclick="retryWidget('{{ $widget_id }}')" 
                            class="text-sm bg-red-600 hover:bg-red-700 text-white font-medium py-1 px-3 rounded">
                        Retry Loading
                    </button>
                    @if($retry_strategy['max_attempts'] > 1)
                        <span class="ml-2 text-xs text-red-600">
                            ({{ $retry_strategy['max_attempts'] }} attempts remaining)
                        </span>
                    @endif
                </div>
            @endif

            @if(isset($fallback_data) && !empty($fallback_data))
                <div class="mt-3">
                    <button onclick="showFallbackData('{{ $widget_id }}')" 
                            class="text-sm text-red-600 hover:text-red-800 underline">
                        Show Cached Data
                    </button>
                </div>
            @endif

            @if(isset($error_code))
                <div class="mt-2 text-xs text-red-500">
                    Error Code: {{ $error_code }}
                </div>
            @endif
        </div>
    </div>

    @if(isset($fallback_data) && !empty($fallback_data))
        <div id="fallback-data-{{ $widget_id }}" class="mt-4 hidden">
            <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                <h4 class="text-sm font-medium text-yellow-800 mb-2">Cached Data</h4>
                <div class="text-sm text-yellow-700">
                    @if(isset($fallback_data['_fallback']))
                        <p class="mb-2 text-xs">{{ $fallback_data['_fallback']['reason'] }}</p>
                    @endif
                    
                    {{-- Render fallback data based on widget type --}}
                    @if($widget_id === 'system-overview')
                        @include('dashboard.widgets.fallback.system-overview', ['data' => $fallback_data])
                    @elseif($widget_id === 'cross-gateway-alerts')
                        @include('dashboard.widgets.fallback.cross-gateway-alerts', ['data' => $fallback_data])
                    @else
                        <pre class="text-xs bg-gray-100 p-2 rounded overflow-auto max-h-32">{{ json_encode($fallback_data, JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
function retryWidget(widgetId) {
    const container = document.querySelector(`[data-widget-id="${widgetId}"]`);
    if (container) {
        // Show loading state
        container.innerHTML = '<div class="flex items-center justify-center p-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div><span class="ml-2">Retrying...</span></div>';
        
        // Trigger widget reload
        if (window.dashboardManager && window.dashboardManager.reloadWidget) {
            window.dashboardManager.reloadWidget(widgetId);
        } else {
            // Fallback: reload the page
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }
}

function showFallbackData(widgetId) {
    const fallbackDiv = document.getElementById(`fallback-data-${widgetId}`);
    if (fallbackDiv) {
        fallbackDiv.classList.toggle('hidden');
    }
}
</script>