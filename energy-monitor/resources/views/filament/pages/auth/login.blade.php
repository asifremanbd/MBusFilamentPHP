<x-filament-panels::page.simple>
    <div class="fi-simple-main-ctn flex w-full flex-grow items-center justify-center">
        <div class="fi-simple-main mx-auto w-full max-w-md space-y-8 bg-white px-6 py-12 shadow-2xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:rounded-xl sm:px-12">
            
            {{-- Custom Header Section --}}
            <div class="text-center">
                <div class="mx-auto h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Energy Monitor
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Sign in to your account to continue
                </p>
            </div>

            {{-- Login Form --}}
            <div>
                {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.before') }}

                <x-filament-panels::form wire:submit="authenticate">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>

                {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.after') }}
            </div>

            {{-- Custom Footer Section --}}
            <div class="text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    © {{ date('Y') }} Energy Monitor System. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    {{-- Custom Background --}}
    <style>
        body {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            min-height: 100vh;
        }
        
        .fi-simple-main {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .fi-input-wrapper input {
            transition: all 0.3s ease;
        }
        
        .fi-input-wrapper input:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</x-filament-panels::page.simple>