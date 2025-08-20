@php
    $data = $this->getData();
    $sectionConfig = app(\App\Services\RTUDashboardSectionService::class)->getSectionConfiguration(auth()->user())['io_monitoring'] ?? [];
@endphp

<x-filament-widgets::widget>
    <x-rtu-collapsible-section 
        section-key="io_monitoring"
        title="I/O Monitoring & Control"
        icon="heroicon-o-bolt"
        :is-collapsed="$sectionConfig['is_collapsed'] ?? false"
        :display-order="$sectionConfig['display_order'] ?? 3">

        @if(isset($data['error']))
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-2 text-red-600">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    <span class="font-medium">{{ $data['error'] }}</span>
                </div>
            </div>
        @else
            <div class="space-y-6">
                <!-- Digital Inputs Section -->
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                        <x-heroicon-o-arrow-down-on-square class="h-4 w-4" />
                        Digital Inputs
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                        @foreach($data['digital_inputs'] as $key => $input)
                            <div class="p-4 border rounded-lg {{ $input['state_class'] }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <x-dynamic-component :component="$input['icon']" class="h-5 w-5" />
                                        <span class="font-medium">{{ $input['label'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold">{{ $input['state_text'] }}</span>
                                        <x-dynamic-component :component="$this->getStateIcon($input['status'])" class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Digital Outputs Section -->
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                        <x-heroicon-o-arrow-up-on-square class="h-4 w-4" />
                        Digital Outputs
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                        @foreach($data['digital_outputs'] as $key => $output)
                            <div class="p-4 border rounded-lg {{ $output['state_class'] }}" data-output="{{ $key }}">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <x-dynamic-component :component="$output['icon']" class="h-5 w-5" />
                                        <span class="font-medium">{{ $output['label'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold state-text">{{ $output['state_text'] }}</span>
                                        <x-dynamic-component :component="$this->getStateIcon($output['status'])" class="h-4 w-4" />
                                    </div>
                                </div>
                                
                                @if($output['controllable'])
                                    <button 
                                        type="button"
                                        onclick="toggleDigitalOutput('{{ $key }}', {{ $output['status'] ? 'false' : 'true' }}, {{ $data['gateway_id'] }})"
                                        class="w-full px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 {{ $this->getToggleButtonClass($output['status'], $output['controllable']) }}"
                                        @if(!$output['controllable'] || $output['status'] === null) disabled @endif
                                    >
                                        {{ $this->getToggleButtonText($output['status'], $output['controllable']) }}
                                    </button>
                                @else
                                    <div class="w-full px-3 py-2 text-sm font-medium text-center rounded-md bg-gray-100 text-gray-500">
                                        Control Disabled
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Analog Input Section -->
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                        <x-heroicon-o-lightning-bolt class="h-4 w-4" />
                        Analog Input
                    </h3>
                    <div class="p-4 border rounded-lg {{ $data['analog_input']['status_class'] }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-dynamic-component :component="$data['analog_input']['icon']" class="h-5 w-5" />
                                <span class="font-medium">Voltage Reading</span>
                                <span class="text-xs text-gray-500">({{ $data['analog_input']['range'] }})</span>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-semibold">{{ $data['analog_input']['formatted_value'] }}</div>
                                @if($data['analog_input']['voltage'] !== null)
                                    <div class="text-xs text-gray-500">Range: {{ $data['analog_input']['range'] }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last Updated -->
                @if($data['last_updated'])
                    <div class="text-xs text-gray-500 text-center">
                        Last updated: {{ $data['last_updated']->format('M j, Y g:i A') }}
                    </div>
                @endif
            </div>
        @endif
    </x-rtu-collapsible-section>
</x-filament-widgets::widget>

<!-- Success/Error Messages -->
<div id="io-control-messages" class="fixed top-4 right-4 z-50 space-y-2" style="display: none;">
    <!-- Messages will be inserted here by JavaScript -->
</div>

<script>
/**
 * Toggle digital output state via AJAX
 */
function toggleDigitalOutput(output, newState, gatewayId) {
    const button = event.target;
    const originalText = button.textContent;
    
    // Disable button and show loading state
    button.disabled = true;
    button.textContent = 'Processing...';
    button.classList.add('opacity-50');
    
    // Prepare CSRF token
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!token) {
        showMessage('CSRF token not found. Please refresh the page.', 'error');
        resetButton(button, originalText);
        return;
    }
    
    // Make AJAX request
    fetch(`/api/rtu/gateways/${gatewayId}/digital-output/${output}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            state: newState
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            
            // Update UI to reflect new state
            updateOutputUI(output, data.new_state);
            
            // Refresh the widget data after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Digital output control error:', error);
        showMessage(`Failed to control output: ${error.message}`, 'error');
        resetButton(button, originalText);
    });
}

/**
 * Reset button to original state
 */
function resetButton(button, originalText) {
    button.disabled = false;
    button.textContent = originalText;
    button.classList.remove('opacity-50');
}

/**
 * Update output UI elements to reflect new state
 */
function updateOutputUI(output, newState) {
    // Find the output container
    const outputContainer = document.querySelector(`[data-output="${output}"]`);
    if (!outputContainer) return;
    
    // Update state text
    const stateText = outputContainer.querySelector('.state-text');
    if (stateText) {
        stateText.textContent = newState ? 'ON' : 'OFF';
    }
    
    // Update button text
    const button = outputContainer.querySelector('button');
    if (button) {
        button.textContent = newState ? 'Turn OFF' : 'Turn ON';
        
        // Update button classes
        button.classList.remove('bg-green-500', 'hover:bg-green-600', 'bg-gray-500', 'hover:bg-gray-600');
        if (newState) {
            button.classList.add('bg-green-500', 'hover:bg-green-600');
        } else {
            button.classList.add('bg-gray-500', 'hover:bg-gray-600');
        }
    }
    
    // Update container classes
    outputContainer.classList.remove('text-green-600', 'bg-green-50', 'border-green-200', 'text-gray-600', 'bg-gray-50', 'border-gray-200');
    if (newState) {
        outputContainer.classList.add('text-green-600', 'bg-green-50', 'border-green-200');
    } else {
        outputContainer.classList.add('text-gray-600', 'bg-gray-50', 'border-gray-200');
    }
}

/**
 * Show success or error message
 */
function showMessage(message, type) {
    const container = document.getElementById('io-control-messages');
    const messageDiv = document.createElement('div');
    
    const bgColor = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
    
    messageDiv.className = `p-4 border rounded-lg ${bgColor} shadow-lg max-w-sm`;
    messageDiv.innerHTML = `
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                ${type === 'success' 
                    ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'
                    : '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />'
                }
            </svg>
            <span class="font-medium">${message}</span>
        </div>
    `;
    
    container.appendChild(messageDiv);
    container.style.display = 'block';
    
    // Auto-remove message after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
        if (container.children.length === 0) {
            container.style.display = 'none';
        }
    }, 5000);
}

// Add error handling for network issues
window.addEventListener('online', () => {
    showMessage('Connection restored', 'success');
});

window.addEventListener('offline', () => {
    showMessage('Connection lost. I/O controls may not work.', 'error');
});
</script>

<style>
/* Custom styles for I/O widget */
.io-widget-transition {
    transition: all 0.2s ease-in-out;
}

.io-control-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.io-control-button:active {
    transform: translateY(0);
}

/* Loading animation for buttons */
.io-button-loading {
    position: relative;
    overflow: hidden;
}

.io-button-loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: loading-shimmer 1.5s infinite;
}

@keyframes loading-shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>