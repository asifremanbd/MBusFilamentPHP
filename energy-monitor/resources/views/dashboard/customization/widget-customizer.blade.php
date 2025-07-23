{{-- Widget Customization Interface --}}
<div x-data="widgetCustomizer()" class="widget-customizer">
    <!-- Customization Toolbar -->
    <div class="customization-toolbar bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <button @click="toggleCustomizationMode()" 
                    :class="customizationMode ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                <span x-show="!customizationMode">Customize Dashboard</span>
                <span x-show="customizationMode">Exit Customization</span>
            </button>
            
            <div x-show="customizationMode" class="flex items-center space-x-2">
                <button @click="resetLayout()" 
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md">
                    Reset Layout
                </button>
                
                <button @click="saveLayout()" 
                        :disabled="!hasChanges"
                        :class="hasChanges ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                        class="px-3 py-2 text-sm rounded-md transition-colors">
                    Save Changes
                </button>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Dashboard Type Switcher -->
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button @click="switchDashboardType('global')"
                        :class="dashboardType === 'global' ? 'bg-white shadow-sm' : 'text-gray-600'"
                        class="px-3 py-1 text-sm font-medium rounded-md transition-all">
                    Global
                </button>
                <button @click="switchDashboardType('gateway')"
                        :class="dashboardType === 'gateway' ? 'bg-white shadow-sm' : 'text-gray-600'"
                        class="px-3 py-1 text-sm font-medium rounded-md transition-all">
                    Gateway
                </button>
            </div>

            <!-- Widget Visibility Toggle -->
            <div x-show="customizationMode" class="relative">
                <button @click="showWidgetPanel = !showWidgetPanel"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md">
                    Widgets
                </button>
                
                <!-- Widget Panel -->
                <div x-show="showWidgetPanel" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     @click.away="showWidgetPanel = false"
                     class="absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                    <div class="p-4">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Widget Visibility</h3>
                        <div class="space-y-2">
                            <template x-for="widget in availableWidgets" :key="widget.id">
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           :checked="widget.visible"
                                           @change="toggleWidgetVisibility(widget.id)"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700" x-text="widget.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid Container -->
    <div class="dashboard-grid-container p-4">
        <div :class="customizationMode ? 'customization-active' : ''"
             class="dashboard-grid grid gap-4 transition-all duration-300"
             :style="gridStyle">
            
            <template x-for="widget in visibleWidgets" :key="widget.id">
                <div :id="'widget-' + widget.id"
                     :data-widget-id="widget.id"
                     :style="getWidgetStyle(widget)"
                     class="widget-container relative bg-white rounded-lg shadow-sm border border-gray-200 transition-all duration-200"
                     :class="customizationMode ? 'customizable' : ''">
                    
                    <!-- Widget Header (shown in customization mode) -->
                    <div x-show="customizationMode" 
                         class="widget-header absolute -top-8 left-0 right-0 flex items-center justify-between bg-blue-600 text-white px-3 py-1 rounded-t-md text-xs">
                        <span x-text="widget.name"></span>
                        <div class="flex items-center space-x-1">
                            <button @click="hideWidget(widget.id)" 
                                    class="text-white hover:text-red-200 transition-colors">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Resize Handles (shown in customization mode) -->
                    <div x-show="customizationMode" class="resize-handles">
                        <div class="resize-handle resize-se" @mousedown="startResize($event, widget.id, 'se')"></div>
                        <div class="resize-handle resize-s" @mousedown="startResize($event, widget.id, 's')"></div>
                        <div class="resize-handle resize-e" @mousedown="startResize($event, widget.id, 'e')"></div>
                    </div>

                    <!-- Widget Content -->
                    <div class="widget-content p-4" :class="customizationMode ? 'pointer-events-none' : ''">
                        <!-- Widget content will be loaded here -->
                        <div x-html="widget.content || getWidgetPlaceholder(widget)"></div>
                    </div>

                    <!-- Loading Overlay -->
                    <div x-show="widget.loading" 
                         class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                            <span class="text-sm text-gray-600">Loading...</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Customization Instructions -->
    <div x-show="customizationMode" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-4"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         class="fixed bottom-4 left-4 right-4 bg-blue-50 border border-blue-200 rounded-lg p-4 z-40">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-blue-800">Customization Mode Active</h3>
                <p class="mt-1 text-sm text-blue-700">
                    Drag widgets to reposition them, use resize handles to change size, or use the Widgets panel to show/hide widgets.
                </p>
            </div>
            <button @click="customizationMode = false" 
                    class="flex-shrink-0 text-blue-400 hover:text-blue-600">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    </div>
</div>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    grid-auto-rows: 80px;
    min-height: 600px;
}

.widget-container {
    position: relative;
    overflow: hidden;
}

.customizable {
    cursor: move;
    border: 2px dashed transparent;
    transition: border-color 0.2s;
}

.customizable:hover {
    border-color: #3b82f6;
}

.customization-active .widget-container {
    margin-top: 2rem;
}

.resize-handles {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.resize-handle {
    position: absolute;
    background: #3b82f6;
    pointer-events: all;
    opacity: 0;
    transition: opacity 0.2s;
}

.customizable:hover .resize-handle {
    opacity: 1;
}

.resize-se {
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    cursor: se-resize;
    border-radius: 0 0 4px 0;
}

.resize-s {
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 4px;
    cursor: s-resize;
    border-radius: 0 0 2px 2px;
}

.resize-e {
    right: -2px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    cursor: e-resize;
    border-radius: 0 2px 2px 0;
}

.widget-content {
    height: 100%;
    overflow: auto;
}
</style>

<script>
function widgetCustomizer() {
    return {
        customizationMode: false,
        showWidgetPanel: false,
        dashboardType: '{{ $dashboardType ?? "global" }}',
        hasChanges: false,
        draggedWidget: null,
        resizingWidget: null,
        
        availableWidgets: @json($availableWidgets ?? []),
        visibleWidgets: @json($visibleWidgets ?? []),
        
        gridStyle: 'grid-template-columns: repeat(12, 1fr); grid-auto-rows: 80px;',
        
        init() {
            this.initializeDragAndDrop();
            this.loadWidgetContent();
            
            // Listen for widget updates
            window.addEventListener('widget-updated', (event) => {
                this.updateWidget(event.detail.widgetId, event.detail.content);
            });
            
            // Auto-save changes periodically
            setInterval(() => {
                if (this.hasChanges && !this.customizationMode) {
                    this.saveLayout();
                }
            }, 30000); // Save every 30 seconds
        },
        
        toggleCustomizationMode() {
            this.customizationMode = !this.customizationMode;
            this.showWidgetPanel = false;
            
            if (!this.customizationMode && this.hasChanges) {
                this.saveLayout();
            }
        },
        
        switchDashboardType(type) {
            if (this.dashboardType === type) return;
            
            if (this.hasChanges) {
                if (!confirm('You have unsaved changes. Switch dashboard type anyway?')) {
                    return;
                }
            }
            
            this.dashboardType = type;
            this.loadDashboardType(type);
        },
        
        async loadDashboardType(type) {
            try {
                const response = await fetch(`/api/dashboard/widgets?dashboard_type=${type}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.availableWidgets = data.widgets || [];
                    this.visibleWidgets = this.availableWidgets.filter(w => w.visible);
                    this.loadWidgetContent();
                    
                    // Update URL without page reload
                    const url = new URL(window.location);
                    url.searchParams.set('dashboard_type', type);
                    window.history.pushState({}, '', url);
                }
            } catch (error) {
                console.error('Failed to load dashboard type:', error);
                this.showNotification('Failed to switch dashboard type', 'error');
            }
        },
        
        toggleWidgetVisibility(widgetId) {
            const widget = this.availableWidgets.find(w => w.id === widgetId);
            if (widget) {
                widget.visible = !widget.visible;
                this.updateVisibleWidgets();
                this.hasChanges = true;
            }
        },
        
        hideWidget(widgetId) {
            this.toggleWidgetVisibility(widgetId);
        },
        
        updateVisibleWidgets() {
            this.visibleWidgets = this.availableWidgets.filter(w => w.visible);
        },
        
        getWidgetStyle(widget) {
            const position = widget.position || { row: 0, col: 0 };
            const size = widget.size || { width: 6, height: 4 };
            
            return {
                'grid-column': `${position.col + 1} / span ${size.width}`,
                'grid-row': `${position.row + 1} / span ${size.height}`,
            };
        },
        
        getWidgetPlaceholder(widget) {
            return `
                <div class="flex items-center justify-center h-full text-gray-400">
                    <div class="text-center">
                        <div class="text-2xl mb-2">📊</div>
                        <div class="text-sm font-medium">${widget.name}</div>
                        <div class="text-xs">Loading...</div>
                    </div>
                </div>
            `;
        },
        
        async loadWidgetContent() {
            for (const widget of this.visibleWidgets) {
                if (!widget.content) {
                    widget.loading = true;
                    try {
                        const response = await fetch(`/api/dashboard/widget/${widget.id}/data`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            widget.content = this.renderWidgetContent(widget, data);
                        } else {
                            widget.content = this.renderWidgetError(widget, 'Failed to load widget data');
                        }
                    } catch (error) {
                        widget.content = this.renderWidgetError(widget, error.message);
                    } finally {
                        widget.loading = false;
                    }
                }
            }
        },
        
        renderWidgetContent(widget, data) {
            // This would typically use a template system
            // For now, return a simple representation
            return `
                <div class="widget-data">
                    <h3 class="text-lg font-semibold mb-2">${widget.name}</h3>
                    <div class="text-sm text-gray-600">
                        Status: ${data.status || 'loaded'}
                    </div>
                </div>
            `;
        },
        
        renderWidgetError(widget, error) {
            return `
                <div class="flex items-center justify-center h-full text-red-400">
                    <div class="text-center">
                        <div class="text-2xl mb-2">⚠️</div>
                        <div class="text-sm font-medium">${widget.name}</div>
                        <div class="text-xs">${error}</div>
                    </div>
                </div>
            `;
        },
        
        updateWidget(widgetId, content) {
            const widget = this.visibleWidgets.find(w => w.id === widgetId);
            if (widget) {
                widget.content = content;
                widget.loading = false;
            }
        },
        
        initializeDragAndDrop() {
            // Initialize drag and drop functionality
            this.$nextTick(() => {
                this.setupDragAndDrop();
            });
        },
        
        setupDragAndDrop() {
            const grid = this.$el.querySelector('.dashboard-grid');
            if (!grid) return;
            
            // Use a library like Sortable.js or implement custom drag/drop
            // For now, we'll set up basic mouse event handlers
            
            grid.addEventListener('mousedown', (e) => {
                if (!this.customizationMode) return;
                
                const widgetContainer = e.target.closest('.widget-container');
                if (widgetContainer && !e.target.closest('.resize-handle')) {
                    this.startDrag(e, widgetContainer);
                }
            });
        },
        
        startDrag(e, widgetContainer) {
            e.preventDefault();
            this.draggedWidget = {
                element: widgetContainer,
                id: widgetContainer.dataset.widgetId,
                startX: e.clientX,
                startY: e.clientY,
                startRect: widgetContainer.getBoundingClientRect()
            };
            
            document.addEventListener('mousemove', this.handleDrag);
            document.addEventListener('mouseup', this.endDrag);
            
            widgetContainer.style.zIndex = '1000';
            widgetContainer.style.opacity = '0.8';
        },
        
        handleDrag(e) {
            if (!this.draggedWidget) return;
            
            const deltaX = e.clientX - this.draggedWidget.startX;
            const deltaY = e.clientY - this.draggedWidget.startY;
            
            this.draggedWidget.element.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
        },
        
        endDrag(e) {
            if (!this.draggedWidget) return;
            
            document.removeEventListener('mousemove', this.handleDrag);
            document.removeEventListener('mouseup', this.endDrag);
            
            // Calculate new grid position
            const newPosition = this.calculateGridPosition(e.clientX, e.clientY);
            this.updateWidgetPosition(this.draggedWidget.id, newPosition);
            
            // Reset styles
            this.draggedWidget.element.style.transform = '';
            this.draggedWidget.element.style.zIndex = '';
            this.draggedWidget.element.style.opacity = '';
            
            this.draggedWidget = null;
            this.hasChanges = true;
        },
        
        startResize(e, widgetId, direction) {
            e.preventDefault();
            e.stopPropagation();
            
            this.resizingWidget = {
                id: widgetId,
                direction: direction,
                startX: e.clientX,
                startY: e.clientY,
                startSize: this.getWidgetById(widgetId).size
            };
            
            document.addEventListener('mousemove', this.handleResize);
            document.addEventListener('mouseup', this.endResize);
        },
        
        handleResize(e) {
            if (!this.resizingWidget) return;
            
            const deltaX = e.clientX - this.resizingWidget.startX;
            const deltaY = e.clientY - this.resizingWidget.startY;
            
            const newSize = this.calculateNewSize(this.resizingWidget, deltaX, deltaY);
            this.updateWidgetSize(this.resizingWidget.id, newSize);
        },
        
        endResize() {
            if (!this.resizingWidget) return;
            
            document.removeEventListener('mousemove', this.handleResize);
            document.removeEventListener('mouseup', this.endResize);
            
            this.resizingWidget = null;
            this.hasChanges = true;
        },
        
        calculateGridPosition(clientX, clientY) {
            const grid = this.$el.querySelector('.dashboard-grid');
            const gridRect = grid.getBoundingClientRect();
            const gridCols = 12;
            const gridRows = Math.ceil(grid.scrollHeight / 80);
            
            const col = Math.floor(((clientX - gridRect.left) / gridRect.width) * gridCols);
            const row = Math.floor((clientY - gridRect.top) / 80);
            
            return {
                col: Math.max(0, Math.min(col, gridCols - 1)),
                row: Math.max(0, row)
            };
        },
        
        calculateNewSize(resizeInfo, deltaX, deltaY) {
            const gridColWidth = 80; // Approximate
            const gridRowHeight = 80;
            
            let newWidth = resizeInfo.startSize.width;
            let newHeight = resizeInfo.startSize.height;
            
            if (resizeInfo.direction.includes('e')) {
                newWidth = Math.max(1, resizeInfo.startSize.width + Math.round(deltaX / gridColWidth));
            }
            
            if (resizeInfo.direction.includes('s')) {
                newHeight = Math.max(1, resizeInfo.startSize.height + Math.round(deltaY / gridRowHeight));
            }
            
            return {
                width: Math.min(newWidth, 12),
                height: Math.min(newHeight, 10)
            };
        },
        
        getWidgetById(widgetId) {
            return this.visibleWidgets.find(w => w.id === widgetId);
        },
        
        updateWidgetPosition(widgetId, position) {
            const widget = this.getWidgetById(widgetId);
            if (widget) {
                widget.position = position;
            }
        },
        
        updateWidgetSize(widgetId, size) {
            const widget = this.getWidgetById(widgetId);
            if (widget) {
                widget.size = size;
            }
        },
        
        async saveLayout() {
            if (!this.hasChanges) return;
            
            try {
                const layoutData = this.visibleWidgets.map(widget => ({
                    widget_id: widget.id,
                    position: widget.position,
                    size: widget.size,
                    visible: widget.visible
                }));
                
                const response = await fetch('/api/dashboard/layout', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        dashboard_type: this.dashboardType,
                        layout_updates: layoutData
                    })
                });
                
                if (response.ok) {
                    this.hasChanges = false;
                    this.showNotification('Layout saved successfully', 'success');
                } else {
                    throw new Error('Failed to save layout');
                }
            } catch (error) {
                console.error('Save layout error:', error);
                this.showNotification('Failed to save layout', 'error');
            }
        },
        
        async resetLayout() {
            if (!confirm('Reset layout to default? This will undo all customizations.')) {
                return;
            }
            
            try {
                const response = await fetch('/api/dashboard/config/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        dashboard_type: this.dashboardType
                    })
                });
                
                if (response.ok) {
                    window.location.reload();
                } else {
                    throw new Error('Failed to reset layout');
                }
            } catch (error) {
                console.error('Reset layout error:', error);
                this.showNotification('Failed to reset layout', 'error');
            }
        },
        
        showNotification(message, type = 'info') {
            // Simple notification system
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    };
}
</script>