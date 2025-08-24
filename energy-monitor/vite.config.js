import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/rtu-widgets.css',
                'resources/css/dashboard-customization.css',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
    ],
});
