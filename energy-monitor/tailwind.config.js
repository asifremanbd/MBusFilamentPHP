import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                'rtu-primary': '#3b82f6',
                'rtu-secondary': '#64748b',
                'rtu-success': '#10b981',
                'rtu-warning': '#f59e0b',
                'rtu-danger': '#ef4444',
            }
        }
    }
}