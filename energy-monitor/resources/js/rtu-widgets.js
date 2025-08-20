/**
 * RTU Dashboard Widget JavaScript
 * Handles interactive elements for RTU widgets including I/O controls, 
 * alert filtering, and metric selection
 */

class RTUWidgetManager {
    constructor() {
        this.init();
        this.bindEvents();
    }

    init() {
        console.log('RTU Widget Manager initialized');
        this.setupCSRFToken();
        this.initializeWidgets();
    }

    setupCSRFToken() {
        // Ensure CSRF token is available for AJAX requests
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            window.Laravel = window.Laravel || {};
            window.Laravel.csrfToken = token.getAttribute('content');
        }
    }

    initializeWidgets() {
        this.initIOControls();
        this.initAlertFilters();
        this.initTrendControls();
        this.initNetworkStatus();
        this.initSystemHealth();
    }

    bindEvents() {
        // Global event listeners
        document.addEventListener('DOMContentLoaded', () => {
            this.refreshWidgetData();
        });

        // Network status monitoring
        window.addEventListener('online', () => {
            this.showNotification('Connection restored', 'success');
            this.refreshWidgetData();
        });

        window.addEventListener('offline', () => {
            this.showNotification('Connection lost. Some features may not work.', 'warning');
        });

        // Auto-refresh widgets every 30 seconds
        setInterval(() => {
            this.refreshWidgetData();
        }, 30000);
    }

    /**
     * I/O Controls Management
     */
    initIOControls() {
        const ioButtons = document.querySelectorAll('[data-io-control]');
        
        ioButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleIOControl(button);
            });
        });
    }

    async handleIOControl(button) {
        const output = button.dataset.ioControl;
        const gatewayId = button.dataset.gatewayId;
        const currentState = button.dataset.currentState === 'true';
        const newState = !currentState;

        if (!output || !gatewayId) {
            this.showNotification('Invalid control configuration', 'error');
            return;
        }

        // Update button state
        this.setButtonLoading(button, true);

        try {
            const response = await this.makeAPIRequest(`/api/rtu/gateways/${gatewayId}/digital-output/${output}`, {
                method: 'POST',
                body: JSON.stringify({ state: newState })
            });

            if (response.success) {
                this.showNotification(response.message, 'success');
                this.updateIOButtonState(button, newState);
                
                // Refresh the widget after a short delay
                setTimeout(() => {
                    this.refreshIOWidget(gatewayId);
                }, 1000);
            } else {
                throw new Error(response.message || 'Control operation failed');
            }
        } catch (error) {
            console.error('I/O control error:', error);
            this.showNotification(`Failed to control output: ${error.message}`, 'error');
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    updateIOButtonState(button, newState) {
        const container = button.closest('[data-output]');
        if (!container) return;

        // Update button text and state
        button.textContent = newState ? 'Turn OFF' : 'Turn ON';
        button.dataset.currentState = newState.toString();

        // Update state text
        const stateText = container.querySelector('.state-text');
        if (stateText) {
            stateText.textContent = newState ? 'ON' : 'OFF';
        }

        // Update visual styling
        this.updateIOVisualState(container, newState);
    }

    updateIOVisualState(container, isActive) {
        const classes = {
            active: ['text-green-600', 'bg-green-50', 'border-green-200'],
            inactive: ['text-gray-600', 'bg-gray-50', 'border-gray-200']
        };

        // Remove all state classes
        Object.values(classes).flat().forEach(cls => {
            container.classList.remove(cls);
        });

        // Add appropriate state classes
        const stateClasses = isActive ? classes.active : classes.inactive;
        stateClasses.forEach(cls => {
            container.classList.add(cls);
        });
    }

    setButtonLoading(button, isLoading) {
        if (isLoading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Processing...';
            button.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            button.disabled = false;
            button.textContent = button.dataset.originalText || button.textContent;
            button.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    /**
     * Alert Filtering Management
     */
    initAlertFilters() {
        const applyButton = document.getElementById('apply-filters');
        const clearButton = document.getElementById('clear-filters');
        const timeRangeSelect = document.getElementById('time-range-filter');
        const customDateRange = document.getElementById('custom-date-range');

        if (applyButton) {
            applyButton.addEventListener('click', () => this.applyAlertFilters());
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => this.clearAlertFilters());
        }

        if (timeRangeSelect && customDateRange) {
            timeRangeSelect.addEventListener('change', (e) => {
                if (e.target.value === 'custom') {
                    customDateRange.classList.remove('hidden');
                } else {
                    customDateRange.classList.add('hidden');
                }
            });
        }
    }

    async applyAlertFilters() {
        const deviceFilter = document.getElementById('device-filter');
        const severityFilter = document.getElementById('severity-filter');
        const timeRangeFilter = document.getElementById('time-range-filter');
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');
        const alertsContainer = document.getElementById('alerts-container');

        if (!alertsContainer) return;

        const filters = {
            device_ids: deviceFilter ? Array.from(deviceFilter.selectedOptions).map(opt => opt.value) : [],
            severity: severityFilter ? Array.from(severityFilter.selectedOptions).map(opt => opt.value) : [],
            time_range: timeRangeFilter ? timeRangeFilter.value : 'last_day',
            start_date: startDate ? startDate.value : null,
            end_date: endDate ? endDate.value : null
        };

        // Show loading state
        this.setContainerLoading(alertsContainer, true);

        try {
            const gatewayId = this.getGatewayIdFromPage();
            const response = await this.makeAPIRequest(`/api/rtu/gateway/${gatewayId}/alerts/filter`, {
                method: 'POST',
                body: JSON.stringify({ filters })
            });

            if (response.success) {
                alertsContainer.innerHTML = response.html;
                this.showNotification('Filters applied successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to apply filters');
            }
        } catch (error) {
            console.error('Alert filtering error:', error);
            this.showNotification(`Failed to filter alerts: ${error.message}`, 'error');
            alertsContainer.innerHTML = '<div class="text-center py-8 text-red-500">Error loading alerts. Please try again.</div>';
        } finally {
            this.setContainerLoading(alertsContainer, false);
        }
    }

    clearAlertFilters() {
        const deviceFilter = document.getElementById('device-filter');
        const severityFilter = document.getElementById('severity-filter');
        const timeRangeFilter = document.getElementById('time-range-filter');
        const customDateRange = document.getElementById('custom-date-range');
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');

        if (deviceFilter) deviceFilter.selectedIndex = -1;
        if (severityFilter) severityFilter.selectedIndex = -1;
        if (timeRangeFilter) timeRangeFilter.value = 'last_day';
        if (customDateRange) customDateRange.classList.add('hidden');
        if (startDate) startDate.value = '';
        if (endDate) endDate.value = '';

        // Reload page to show unfiltered results
        window.location.href = window.location.pathname;
    }

    /**
     * Trend Widget Controls
     */
    initTrendControls() {
        const metricCheckboxes = document.querySelectorAll('input[name="selected_metrics[]"]');
        const timeRangeSelect = document.querySelector('select[wire\\:model\\.live="timeRange"]');

        metricCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.handleMetricSelection();
            });
        });

        if (timeRangeSelect) {
            timeRangeSelect.addEventListener('change', () => {
                this.handleTimeRangeChange();
            });
        }
    }

    handleMetricSelection() {
        const selectedMetrics = Array.from(document.querySelectorAll('input[name="selected_metrics[]"]:checked'))
            .map(cb => cb.value);

        if (selectedMetrics.length === 0) {
            this.showNotification('Please select at least one metric to display', 'warning');
            return;
        }

        // Trigger Livewire update if available
        if (window.Livewire) {
            window.Livewire.emit('updateSelectedMetrics', selectedMetrics);
        }

        this.showNotification('Chart updated with selected metrics', 'success');
    }

    handleTimeRangeChange() {
        // Trigger chart refresh
        if (window.Livewire) {
            window.Livewire.emit('refreshChart');
        }
    }

    /**
     * Network Status Widget
     */
    initNetworkStatus() {
        // Add periodic signal quality checks
        this.monitorSignalQuality();
    }

    monitorSignalQuality() {
        const signalBars = document.querySelectorAll('.signal-strength-bar');
        
        signalBars.forEach(bar => {
            const value = parseFloat(bar.dataset.value);
            if (!isNaN(value)) {
                this.animateSignalBar(bar, value);
            }
        });
    }

    animateSignalBar(bar, targetValue) {
        let currentValue = 0;
        const increment = targetValue / 20; // 20 steps animation
        
        const animate = () => {
            if (currentValue < targetValue) {
                currentValue += increment;
                bar.style.width = `${Math.min(currentValue, 100)}%`;
                requestAnimationFrame(animate);
            }
        };
        
        animate();
    }

    /**
     * System Health Widget
     */
    initSystemHealth() {
        this.animateHealthMetrics();
        this.setupHealthThresholdAlerts();
    }

    animateHealthMetrics() {
        const progressBars = document.querySelectorAll('.metric-progress-fill');
        
        progressBars.forEach(bar => {
            const targetWidth = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = targetWidth;
            }, 100);
        });
    }

    setupHealthThresholdAlerts() {
        const cpuMetric = document.querySelector('[data-metric="cpu_load"]');
        const memoryMetric = document.querySelector('[data-metric="memory_usage"]');

        if (cpuMetric) {
            const cpuValue = parseFloat(cpuMetric.dataset.value);
            if (cpuValue > 90) {
                this.showNotification('Critical: CPU usage is very high', 'error');
            } else if (cpuValue > 80) {
                this.showNotification('Warning: CPU usage is high', 'warning');
            }
        }

        if (memoryMetric) {
            const memoryValue = parseFloat(memoryMetric.dataset.value);
            if (memoryValue > 95) {
                this.showNotification('Critical: Memory usage is very high', 'error');
            } else if (memoryValue > 85) {
                this.showNotification('Warning: Memory usage is high', 'warning');
            }
        }
    }

    /**
     * Utility Methods
     */
    async makeAPIRequest(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.Laravel?.csrfToken || ''
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    setContainerLoading(container, isLoading) {
        if (isLoading) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading...</p>
                </div>
            `;
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);

        // Add click to dismiss
        notification.addEventListener('click', () => {
            notification.remove();
        });
    }

    getGatewayIdFromPage() {
        // Try to extract gateway ID from URL or page data
        const urlParts = window.location.pathname.split('/');
        const gatewayIndex = urlParts.indexOf('gateway');
        
        if (gatewayIndex !== -1 && urlParts[gatewayIndex + 1]) {
            return urlParts[gatewayIndex + 1];
        }

        // Fallback: look for data attribute
        const gatewayElement = document.querySelector('[data-gateway-id]');
        if (gatewayElement) {
            return gatewayElement.dataset.gatewayId;
        }

        console.warn('Could not determine gateway ID from page');
        return null;
    }

    async refreshWidgetData() {
        const widgets = document.querySelectorAll('[data-widget-refresh]');
        
        widgets.forEach(async (widget) => {
            const widgetType = widget.dataset.widgetRefresh;
            const gatewayId = this.getGatewayIdFromPage();
            
            if (!gatewayId) return;

            try {
                await this.refreshSpecificWidget(widgetType, gatewayId);
            } catch (error) {
                console.error(`Failed to refresh ${widgetType} widget:`, error);
            }
        });
    }

    async refreshSpecificWidget(widgetType, gatewayId) {
        switch (widgetType) {
            case 'io':
                return this.refreshIOWidget(gatewayId);
            case 'network':
                return this.refreshNetworkWidget(gatewayId);
            case 'system':
                return this.refreshSystemWidget(gatewayId);
            case 'alerts':
                return this.refreshAlertsWidget(gatewayId);
            default:
                console.warn(`Unknown widget type: ${widgetType}`);
        }
    }

    async refreshIOWidget(gatewayId) {
        // Implement I/O widget refresh logic
        if (window.Livewire) {
            window.Livewire.emit('refreshIOWidget', gatewayId);
        }
    }

    async refreshNetworkWidget(gatewayId) {
        // Implement network widget refresh logic
        if (window.Livewire) {
            window.Livewire.emit('refreshNetworkWidget', gatewayId);
        }
    }

    async refreshSystemWidget(gatewayId) {
        // Implement system widget refresh logic
        if (window.Livewire) {
            window.Livewire.emit('refreshSystemWidget', gatewayId);
        }
    }

    async refreshAlertsWidget(gatewayId) {
        // Implement alerts widget refresh logic
        if (window.Livewire) {
            window.Livewire.emit('refreshAlertsWidget', gatewayId);
        }
    }
}

// Global functions for backward compatibility
window.toggleDigitalOutput = function(output, newState, gatewayId) {
    const button = event.target;
    button.dataset.ioControl = output;
    button.dataset.gatewayId = gatewayId;
    button.dataset.currentState = (!newState).toString();
    
    if (window.rtuWidgetManager) {
        window.rtuWidgetManager.handleIOControl(button);
    }
};

// Initialize RTU Widget Manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.rtuWidgetManager = new RTUWidgetManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RTUWidgetManager;
}