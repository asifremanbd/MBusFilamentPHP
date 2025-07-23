/**
 * Alpine.js Dashboard Customizer Component
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardCustomizer', () => ({
        // State
        isCustomizing: false,
        showWidgetPanel: false,
        showGatewaySelector: false,
        currentDashboardType: 'global',
        currentGatewayId: null,
        availableGateways: [],
        widgets: {},
        draggedWidget: null,
        isDragging: false,
        
        // Configuration
        gridColumns: 12,
        gridRowHeight: 60,
        autoSave: true,
        autoSaveDelay: 2000,
        
        // Initialization
        init() {
            this.loadDashboardConfig();
            this.loadAvailableGateways();
            this.setupKeyboardShortcuts();
            
            // Auto-save timer
            this.autoSaveTimer = null;
            
            console.log('Dashboard Customizer initialized');
        },

        // Dashboard Configuration
        async loadDashboardConfig() {
            try {
                const response = await fetch('/api/dashboard/config', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        dashboard_type: this.currentDashboardType
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    this.applyConfiguration(data.config);
                }
            } catch (error) {
                console.error('Failed to load dashboard config:', error);
                this.$dispatch('notify', { message: 'Failed to load dashboard configuration', type: 'error' });
            }
        },

        async loadAvailableGateways() {
            try {
                const response = await fetch('/api/dashboard/gateways');
                if (response.ok) {
                    const data = await response.json();
                    this.availableGateways = data.gateways;
                }
            } catch (error) {
                console.error('Failed to load gateways:', error);
            }
        },

        applyConfiguration(config) {
            const widgetConfig = config.widget_config || {};
            const layoutConfig = config.layout_config || {};

            // Apply widget visibility
            Object.entries(widgetConfig.visibility || {}).forEach(([widgetId, visible]) => {
                const widget = document.querySelector(`[data-widget-id="${widgetId}"]`);
                if (widget) {
                    widget.style.display = visible ? 'block' : 'none';
                    
                    // Update widgets state
                    if (!this.widgets[widgetId]) {
                        this.widgets[widgetId] = {};
                    }
                    this.widgets[widgetId].visible = visible;
                }
            });

            // Apply widget positions and sizes
            Object.entries(layoutConfig.positions || {}).forEach(([widgetId, position]) => {
                const size = layoutConfig.sizes?.[widgetId] || { width: 6, height: 4 };
                this.positionWidget(widgetId, position, size);
            });
        },

        // Widget Management
        toggleWidgetVisibility(widgetId) {
            const widget = document.querySelector(`[data-widget-id="${widgetId}"]`);
            if (!widget) return;

            const isVisible = widget.style.display !== 'none';
            const newVisibility = !isVisible;
            
            widget.style.display = newVisibility ? 'block' : 'none';
            
            if (!this.widgets[widgetId]) {
                this.widgets[widgetId] = {};
            }
            this.widgets[widgetId].visible = newVisibility;

            this.scheduleAutoSave();
            this.$dispatch('notify', { 
                message: `Widget ${newVisibility ? 'shown' : 'hidden'}`, 
                type: 'info' 
            });
        },

        positionWidget(widgetId, position, size) {
            const widget = document.querySelector(`[data-widget-id="${widgetId}"]`);
            if (!widget) return;

            const gridColumnWidth = 100 / this.gridColumns;
            
            widget.style.cssText += `
                grid-column: ${position.col + 1} / span ${size.width};
                grid-row: ${position.row + 1} / span ${size.height};
            `;

            // Update widgets state
            if (!this.widgets[widgetId]) {
                this.widgets[widgetId] = {};
            }
            this.widgets[widgetId].position = position;
            this.widgets[widgetId].size = size;
        },

        // Drag and Drop
        startDrag(event, widgetId) {
            if (!this.isCustomizing) return;

            this.draggedWidget = widgetId;
            this.isDragging = true;
            
            const widget = event.target.closest('[data-widget-id]');
            widget.classList.add('dragging');
            
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/html', widget.outerHTML);
        },

        dragOver(event) {
            if (!this.isDragging) return;
            
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            
            const dropTarget = event.target.closest('.dashboard-grid');
            if (dropTarget) {
                dropTarget.classList.add('drag-over');
            }
        },

        drop(event) {
            if (!this.isDragging || !this.draggedWidget) return;

            event.preventDefault();
            
            const dropPosition = this.calculateDropPosition(event);
            this.moveWidget(this.draggedWidget, dropPosition);
            
            this.endDrag();
        },

        endDrag() {
            if (this.draggedWidget) {
                const widget = document.querySelector(`[data-widget-id="${this.draggedWidget}"]`);
                if (widget) {
                    widget.classList.remove('dragging');
                }
            }
            
            this.draggedWidget = null;
            this.isDragging = false;
            
            // Clear drag over states
            document.querySelectorAll('.drag-over').forEach(el => {
                el.classList.remove('drag-over');
            });
        },

        moveWidget(widgetId, position) {
            const currentWidget = this.widgets[widgetId] || {};
            const size = currentWidget.size || { width: 6, height: 4 };
            
            this.positionWidget(widgetId, position, size);
            this.scheduleAutoSave();
            
            this.$dispatch('notify', { 
                message: `Widget moved to row ${position.row + 1}, column ${position.col + 1}`, 
                type: 'success' 
            });
        },

        calculateDropPosition(event) {
            const grid = event.target.closest('.dashboard-grid');
            const rect = grid.getBoundingClientRect();
            
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            
            const col = Math.floor((x / rect.width) * this.gridColumns);
            const row = Math.floor(y / this.gridRowHeight);
            
            return {
                col: Math.max(0, Math.min(col, this.gridColumns - 1)),
                row: Math.max(0, row)
            };
        },

        // Dashboard Type Management
        async switchDashboard(dashboardType, gatewayId = null) {
            if (dashboardType === this.currentDashboardType && gatewayId === this.currentGatewayId) {
                return;
            }

            // Save current config before switching
            if (this.autoSave) {
                await this.saveDashboardConfig();
            }

            this.currentDashboardType = dashboardType;
            this.currentGatewayId = gatewayId;

            // Navigate to new dashboard
            const url = gatewayId 
                ? `/dashboard/gateway/${gatewayId}` 
                : '/dashboard/global';
                
            window.location.href = url;
        },

        // Customization Mode
        toggleCustomizationMode() {
            this.isCustomizing = !this.isCustomizing;
            
            if (this.isCustomizing) {
                document.body.classList.add('dashboard-customizing');
                this.$dispatch('notify', { 
                    message: 'Customization mode enabled - drag widgets to reposition', 
                    type: 'info' 
                });
            } else {
                document.body.classList.remove('dashboard-customizing');
                this.$dispatch('notify', { 
                    message: 'Customization mode disabled', 
                    type: 'info' 
                });
            }
        },

        // Configuration Management
        async saveDashboardConfig() {
            const layoutUpdates = this.generateLayoutUpdates();
            
            try {
                const response = await fetch('/api/dashboard/widget/layout', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        dashboard_type: this.currentDashboardType,
                        layout_updates: layoutUpdates
                    })
                });

                if (response.ok) {
                    this.$dispatch('notify', { message: 'Dashboard saved successfully', type: 'success' });
                } else {
                    throw new Error('Save failed');
                }
            } catch (error) {
                console.error('Failed to save dashboard:', error);
                this.$dispatch('notify', { message: 'Failed to save dashboard', type: 'error' });
            }
        },

        async resetDashboard() {
            if (!confirm('Are you sure you want to reset the dashboard to default layout?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/dashboard/config/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        dashboard_type: this.currentDashboardType
                    })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    throw new Error('Reset failed');
                }
            } catch (error) {
                console.error('Failed to reset dashboard:', error);
                this.$dispatch('notify', { message: 'Failed to reset dashboard', type: 'error' });
            }
        },

        generateLayoutUpdates() {
            const layoutUpdates = [];
            
            Object.entries(this.widgets).forEach(([widgetId, widget]) => {
                if (widget.position && widget.size) {
                    layoutUpdates.push({
                        widget_id: widgetId,
                        position: widget.position,
                        size: widget.size
                    });
                }
            });
            
            return layoutUpdates;
        },

        scheduleAutoSave() {
            if (!this.autoSave) return;
            
            if (this.autoSaveTimer) {
                clearTimeout(this.autoSaveTimer);
            }
            
            this.autoSaveTimer = setTimeout(() => {
                this.saveDashboardConfig();
            }, this.autoSaveDelay);
        },

        // Keyboard Shortcuts
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (event) => {
                // Ctrl/Cmd + S: Save dashboard
                if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                    event.preventDefault();
                    this.saveDashboardConfig();
                }
                
                // Ctrl/Cmd + R: Reset dashboard
                if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
                    event.preventDefault();
                    this.resetDashboard();
                }
                
                // Ctrl/Cmd + H: Toggle customization mode
                if ((event.ctrlKey || event.metaKey) && event.key === 'h') {
                    event.preventDefault();
                    this.toggleCustomizationMode();
                }
                
                // Escape: Exit customization mode
                if (event.key === 'Escape' && this.isCustomizing) {
                    this.toggleCustomizationMode();
                }
            });
        },

        // Utility Methods
        getWidgetName(widgetId) {
            const widgetNames = {
                'system-overview': 'System Overview',
                'cross-gateway-alerts': 'Cross-Gateway Alerts',
                'top-consuming-gateways': 'Top Consuming Gateways',
                'system-health': 'System Health',
                'gateway-device-list': 'Device List',
                'real-time-readings': 'Real-time Readings',
                'gateway-stats': 'Gateway Statistics',
                'gateway-alerts': 'Gateway Alerts'
            };
            
            return widgetNames[widgetId] || widgetId;
        },

        isWidgetVisible(widgetId) {
            return this.widgets[widgetId]?.visible !== false;
        },

        getAvailableWidgets() {
            const widgets = document.querySelectorAll('[data-widget-id]');
            return Array.from(widgets).map(widget => widget.dataset.widgetId);
        },

        // Gateway Management
        getGatewayName(gatewayId) {
            const gateway = this.availableGateways.find(g => g.id === gatewayId);
            return gateway?.name || `Gateway ${gatewayId}`;
        },

        // Event Handlers
        handleWidgetToggle(widgetId) {
            this.toggleWidgetVisibility(widgetId);
        },

        handleDashboardSwitch(dashboardType, gatewayId = null) {
            this.switchDashboard(dashboardType, gatewayId);
        },

        handleSave() {
            this.saveDashboardConfig();
        },

        handleReset() {
            this.resetDashboard();
        },

        handleCustomizationToggle() {
            this.toggleCustomizationMode();
        }
    }));

    // Notification component
    Alpine.data('notificationManager', () => ({
        notifications: [],
        
        init() {
            this.$watch('notifications', () => {
                // Auto-remove notifications after 3 seconds
                this.notifications.forEach((notification, index) => {
                    if (!notification.timer) {
                        notification.timer = setTimeout(() => {
                            this.removeNotification(index);
                        }, 3000);
                    }
                });
            });
            
            // Listen for notification events
            this.$el.addEventListener('notify', (event) => {
                this.addNotification(event.detail.message, event.detail.type);
            });
        },
        
        addNotification(message, type = 'info') {
            this.notifications.push({
                id: Date.now(),
                message,
                type,
                timer: null
            });
        },
        
        removeNotification(index) {
            const notification = this.notifications[index];
            if (notification?.timer) {
                clearTimeout(notification.timer);
            }
            this.notifications.splice(index, 1);
        }
    }));
});