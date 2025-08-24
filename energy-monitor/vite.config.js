import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/dashboard-customization.css',
                'resources/css/rtu-widgets.css',
                'resources/js/app.js',
                'resources/js/rtu-sections.js',
                'resources/js/rtu-widgets.js'
            ],
            refresh: true,
        }),
    ],
});
