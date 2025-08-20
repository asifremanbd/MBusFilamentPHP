/**
 * RTU Dashboard Collapsible Sections Management
 * Handles section collapse/expand functionality with persistent user preferences
 */

class RTUSectionManager {
    constructor() {
        this.sections = new Map();
        this.apiEndpoint = '/api/rtu/sections';
        this.isInitialized = false;
        this.pendingUpdates = new Set();
        
        this.init();
    }

    /**
     * Initialize the section manager
     */
    async init() {
        try {
            await this.loadSectionStates();
            this.bindEvents();
            this.isInitialized = true;
            console.log('RTU Section Manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize RTU Section Manager:', error);
        }
    }

    /**
     * Load section states from the server
     */
    async loadSectionStates() {
        try {
            const response = await fetch(`${this.apiEndpoint}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success && data.sections) {
                this.sections.clear();
                Object.entries(data.sections).forEach(([key, config]) => {
                    this.sections.set(key, config);
                });
                
                this.applySectionStates();
            }
        } catch (error) {
            console.error('Failed to load section states:', error);
            // Initialize with default states if loading fails
            this.initializeDefaultStates();
        }
    }

    /**
     * Apply loaded section states to the DOM
     */
    applySectionStates() {
        this.sections.forEach((config, sectionKey) => {
            const sectionElement = document.querySelector(`[data-section="${sectionKey}"]`);
            if (sectionElement) {
                this.setSectionState(sectionElement, config.is_collapsed, false);
            }
        });
    }

    /**
     * Initialize default section states
     */
    initializeDefaultStates() {
        const defaultSections = [
            'system_health',
            'network_status', 
            'io_monitoring',
            'alerts',
            'trends'
        ];

        defaultSections.forEach((sectionKey, index) => {
            this.sections.set(sectionKey, {
                is_collapsed: false,
                display_order: index + 1
            });
        });
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Make toggleSection globally available
        window.toggleSection = (sectionKey) => this.toggleSection(sectionKey);
        
        // Handle page visibility changes to sync states
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isInitialized) {
                this.loadSectionStates();
            }
        });

        // Handle beforeunload to save any pending changes
        window.addEventListener('beforeunload', () => {
            if (this.pendingUpdates.size > 0) {
                // Use sendBeacon for reliable delivery
                this.flushPendingUpdates(true);
            }
        });
    }

    /**
     * Toggle section collapse state
     */
    async toggleSection(sectionKey) {
        const sectionElement = document.querySelector(`[data-section="${sectionKey}"]`);
        if (!sectionElement) {
            console.warn(`Section element not found: ${sectionKey}`);
            return;
        }

        const contentElement = sectionElement.querySelector('.section-content');
        const collapseIcon = sectionElement.querySelector('.collapse-icon');
        
        if (!contentElement || !collapseIcon) {
            console.warn(`Section content or icon not found for: ${sectionKey}`);
            return;
        }

        const isCurrentlyCollapsed = contentElement.classList.contains('collapsed');
        const newCollapsedState = !isCurrentlyCollapsed;

        // Update UI immediately for responsiveness
        this.setSectionState(sectionElement, newCollapsedState, true);

        // Update local state
        const currentConfig = this.sections.get(sectionKey) || { display_order: 0 };
        this.sections.set(sectionKey, {
            ...currentConfig,
            is_collapsed: newCollapsedState
        });

        // Save to server (debounced)
        this.debouncedSaveState(sectionKey, newCollapsedState);
    }

    /**
     * Set section visual state
     */
    setSectionState(sectionElement, isCollapsed, animate = false) {
        const contentElement = sectionElement.querySelector('.section-content');
        const collapseIcon = sectionElement.querySelector('.collapse-icon');
        
        if (!contentElement || !collapseIcon) return;

        if (animate) {
            // Add loading state
            sectionElement.classList.add('loading');
            
            // Remove existing animation classes
            contentElement.classList.remove('expanding', 'collapsing');
            
            if (isCollapsed) {
                contentElement.classList.add('collapsing');
                setTimeout(() => {
                    contentElement.classList.remove('expanded', 'collapsing');
                    contentElement.classList.add('collapsed');
                    contentElement.style.maxHeight = '0';
                    sectionElement.classList.remove('loading');
                }, 300);
            } else {
                contentElement.classList.add('expanding');
                contentElement.classList.remove('collapsed');
                contentElement.style.maxHeight = '1000px';
                setTimeout(() => {
                    contentElement.classList.remove('expanding');
                    contentElement.classList.add('expanded');
                    contentElement.style.maxHeight = '';
                    sectionElement.classList.remove('loading');
                }, 300);
            }
        } else {
            // Immediate state change
            if (isCollapsed) {
                contentElement.classList.remove('expanded');
                contentElement.classList.add('collapsed');
                contentElement.style.maxHeight = '0';
            } else {
                contentElement.classList.remove('collapsed');
                contentElement.classList.add('expanded');
                contentElement.style.maxHeight = '';
            }
        }

        // Update collapse icon rotation
        if (isCollapsed) {
            collapseIcon.classList.add('rotate-180');
        } else {
            collapseIcon.classList.remove('rotate-180');
        }
    }

    /**
     * Debounced save state to prevent excessive API calls
     */
    debouncedSaveState(sectionKey, isCollapsed) {
        // Clear existing timeout for this section
        if (this.saveTimeouts && this.saveTimeouts[sectionKey]) {
            clearTimeout(this.saveTimeouts[sectionKey]);
        }

        if (!this.saveTimeouts) {
            this.saveTimeouts = {};
        }

        // Set new timeout
        this.saveTimeouts[sectionKey] = setTimeout(() => {
            this.saveStateToServer(sectionKey, isCollapsed);
            delete this.saveTimeouts[sectionKey];
        }, 500); // 500ms debounce
    }

    /**
     * Save section state to server
     */
    async saveStateToServer(sectionKey, isCollapsed) {
        try {
            this.pendingUpdates.add(sectionKey);

            const response = await fetch(`${this.apiEndpoint}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    section_name: sectionKey,
                    is_collapsed: isCollapsed
                })
            });

            const data = await response.json();
            
            if (!data.success) {
                console.error('Failed to save section state:', data.message);
                // Optionally revert UI state here
            }

        } catch (error) {
            console.error('Error saving section state:', error);
            // Optionally show user notification about sync failure
        } finally {
            this.pendingUpdates.delete(sectionKey);
        }
    }

    /**
     * Flush pending updates (for page unload)
     */
    flushPendingUpdates(useBeacon = false) {
        this.pendingUpdates.forEach(sectionKey => {
            const config = this.sections.get(sectionKey);
            if (config) {
                const payload = JSON.stringify({
                    section_name: sectionKey,
                    is_collapsed: config.is_collapsed
                });

                if (useBeacon && navigator.sendBeacon) {
                    navigator.sendBeacon(`${this.apiEndpoint}/update`, payload);
                } else {
                    // Synchronous request as fallback
                    fetch(`${this.apiEndpoint}/update`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        credentials: 'same-origin',
                        body: payload,
                        keepalive: true
                    }).catch(error => {
                        console.error('Failed to flush pending update:', error);
                    });
                }
            }
        });
        
        this.pendingUpdates.clear();
    }

    /**
     * Reset all sections to default state
     */
    async resetToDefaults() {
        try {
            const response = await fetch(`${this.apiEndpoint}/reset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                // Reload section states and apply them
                await this.loadSectionStates();
                console.log('Sections reset to defaults successfully');
            } else {
                console.error('Failed to reset sections:', data.message);
            }

        } catch (error) {
            console.error('Error resetting sections:', error);
        }
    }

    /**
     * Get current section state
     */
    getSectionState(sectionKey) {
        return this.sections.get(sectionKey) || { is_collapsed: false, display_order: 0 };
    }

    /**
     * Check if manager is ready
     */
    isReady() {
        return this.isInitialized;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we're on an RTU dashboard page
    if (document.querySelector('.rtu-collapsible-section')) {
        window.rtuSectionManager = new RTUSectionManager();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RTUSectionManager;
}