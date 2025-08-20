import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/rtu-sections.js',
                'resources/js/rtu-widgets.js'
            ],
            refresh: true,
        }),
    ],
});
