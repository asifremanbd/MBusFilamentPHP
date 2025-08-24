/**
 * Dashboard Manager
 * Handles general dashboard functionality and interactions
 */

class DashboardManager {
    constructor() {
        this.init();
    }

    init() {
        console.log('Dashboard Manager initialized');
        this.setupCSRFToken();
        this.bindEvents();
    }

    setupCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            window.Laravel = window.Laravel || {};
            window.Laravel.csrfToken = token.getAttribute('content');
        }
    }

    bindEvents() {
        // Add any general dashboard event listeners here
        document.addEventListener('DOMContentLoaded', () => {
            this.initializeDashboard();
        });
    }

    initializeDashboard() {
        // Initialize dashboard components
        console.log('Dashboard components initialized');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.dashboardManager = new DashboardManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardManager;
}