@props([
    'gateway',
    'preferences' => null,
    'config' => []
])

<div class="rtu-preference-manager bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">
            <x-heroicon-o-cog-6-tooth class="w-5 h-5 inline mr-2" />
            Trend Chart Preferences
        </h3>
        <button 
            type="button" 
            class="text-sm text-gray-500 hover:text-gray-700"
            onclick="togglePreferenceManager()"
        >
            <x-heroicon-o-x-mark class="w-5 h-5" />
        </button>
    </div>

    <form id="preference-form" class="space-y-4">
        @csrf
        
        <!-- Selected Metrics -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Selected Metrics
            </label>
            <div class="grid grid-cols-2 gap-2">
                @foreach($config['available_metrics'] ?? [] as $key => $label)
                    <label class="flex items-center space-x-2 p-2 border rounded-md hover:bg-gray-50 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="selected_metrics[]" 
                            value="{{ $key }}"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            {{ in_array($key, $preferences->selected_metrics ?? []) ? 'checked' : '' }}
                        >
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-1">Select at least one metric to display</p>
        </div>

        <!-- Time Range -->
        <div>
            <label for="time_range" class="block text-sm font-medium text-gray-700 mb-2">
                Time Range
            </label>
            <select 
                id="time_range" 
                name="time_range" 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                @foreach($config['available_time_ranges'] ?? [] as $key => $label)
                    <option 
                        value="{{ $key }}" 
                        {{ ($preferences->time_range ?? '24h') === $key ? 'selected' : '' }}
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Chart Type -->
        <div>
            <label for="chart_type" class="block text-sm font-medium text-gray-700 mb-2">
                Chart Type
            </label>
            <select 
                id="chart_type" 
                name="chart_type" 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                @foreach($config['available_chart_types'] ?? [] as $key => $label)
                    <option 
                        value="{{ $key }}" 
                        {{ ($preferences->chart_type ?? 'line') === $key ? 'selected' : '' }}
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between pt-4 border-t border-gray-200">
            <button 
                type="button" 
                onclick="resetPreferences()"
                class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50"
            >
                Reset to Defaults
            </button>
            
            <div class="space-x-2">
                <button 
                    type="button" 
                    onclick="togglePreferenceManager()"
                    class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    Save Preferences
                </button>
            </div>
        </div>
    </form>

    <!-- Status Messages -->
    <div id="preference-status" class="mt-4 hidden">
        <div class="p-3 rounded-md" id="preference-message"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('preference-form');
    const statusDiv = document.getElementById('preference-status');
    const messageDiv = document.getElementById('preference-message');

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        savePreferences();
    });

    // Validate form on change
    form.addEventListener('change', function() {
        validateForm();
    });

    function savePreferences() {
        const formData = new FormData(form);
        const data = {
            selected_metrics: formData.getAll('selected_metrics[]'),
            time_range: formData.get('time_range'),
            chart_type: formData.get('chart_type')
        };

        // Validate at least one metric is selected
        if (data.selected_metrics.length === 0) {
            showMessage('Please select at least one metric.', 'error');
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;

        // Send request
        fetch(`/api/rtu/gateway/{{ $gateway->id }}/preferences`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showMessage('Preferences saved successfully!', 'success');
                // Refresh the trend widget
                window.dispatchEvent(new CustomEvent('refresh-trend-widget'));
                // Close preference manager after short delay
                setTimeout(() => {
                    togglePreferenceManager();
                }, 1500);
            } else {
                showMessage(result.message || 'Failed to save preferences.', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving preferences:', error);
            showMessage('An error occurred while saving preferences.', 'error');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    function resetPreferences() {
        if (!confirm('Are you sure you want to reset preferences to defaults?')) {
            return;
        }

        fetch(`/api/rtu/gateway/{{ $gateway->id }}/preferences/reset`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Authorization': 'Bearer ' + getAuthToken()
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showMessage('Preferences reset to defaults!', 'success');
                // Reload the page to reflect changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage(result.message || 'Failed to reset preferences.', 'error');
            }
        })
        .catch(error => {
            console.error('Error resetting preferences:', error);
            showMessage('An error occurred while resetting preferences.', 'error');
        });
    }

    function validateForm() {
        const selectedMetrics = form.querySelectorAll('input[name="selected_metrics[]"]:checked');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (selectedMetrics.length === 0) {
            submitBtn.disabled = true;
            showMessage('Please select at least one metric.', 'warning');
        } else {
            submitBtn.disabled = false;
            hideMessage();
        }
    }

    function showMessage(message, type) {
        statusDiv.classList.remove('hidden');
        messageDiv.textContent = message;
        messageDiv.className = 'p-3 rounded-md';
        
        switch (type) {
            case 'success':
                messageDiv.classList.add('bg-green-50', 'text-green-800', 'border', 'border-green-200');
                break;
            case 'error':
                messageDiv.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
                break;
            case 'warning':
                messageDiv.classList.add('bg-yellow-50', 'text-yellow-800', 'border', 'border-yellow-200');
                break;
            default:
                messageDiv.classList.add('bg-blue-50', 'text-blue-800', 'border', 'border-blue-200');
        }
    }

    function hideMessage() {
        statusDiv.classList.add('hidden');
    }

    function getAuthToken() {
        // Get auth token from meta tag or localStorage
        const token = document.querySelector('meta[name="api-token"]')?.getAttribute('content') ||
                     localStorage.getItem('auth_token') ||
                     sessionStorage.getItem('auth_token');
        return token;
    }

    // Initial validation
    validateForm();
});

function togglePreferenceManager() {
    const manager = document.querySelector('.rtu-preference-manager');
    if (manager) {
        manager.classList.toggle('hidden');
    }
}
</script>

<style>
.rtu-preference-manager {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.rtu-preference-manager.hidden {
    display: none;
}

/* Backdrop */
.rtu-preference-manager::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: -1;
}
</style>