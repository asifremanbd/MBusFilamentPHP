<x-filament::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">System Overview</x-slot>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Devices:</span>
                    <span class="font-bold">{{ $this->totalDevices }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Total Gateways:</span>
                    <span class="font-bold">{{ $this->totalGateways }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Total Users:</span>
                    <span class="font-bold">{{ $this->totalUsers }}</span>
                </div>
            </div>
        </x-filament::section>
        
        <x-filament::section>
            <x-slot name="heading">Alert Statistics</x-slot>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Active Alerts:</span>
                    <span class="font-bold">{{ $this->activeAlerts }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Critical Alerts:</span>
                    <span class="font-bold text-red-500">{{ $this->criticalAlerts }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Today's Alerts:</span>
                    <span class="font-bold">{{ $this->todayAlerts }}</span>
                </div>
            </div>
        </x-filament::section>
        
        <x-filament::section>
            <x-slot name="heading">User Management</x-slot>
            
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Admin Users:</span>
                    <span class="font-bold">{{ $this->adminUsers }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Operator Users:</span>
                    <span class="font-bold">{{ $this->operatorUsers }}</span>
                </div>
                <div class="mt-4">
                    <x-filament::button
                        wire:click="redirectToUserManagement"
                        color="primary"
                        size="sm"
                    >
                        Manage Users
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
    
    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">Recent Alerts</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3">Device</th>
                            <th class="px-6 py-3">Parameter</th>
                            <th class="px-6 py-3">Value</th>
                            <th class="px-6 py-3">Severity</th>
                            <th class="px-6 py-3">Time</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->recentAlerts as $alert)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <td class="px-6 py-4">{{ $alert->device->name }}</td>
                                <td class="px-6 py-4">{{ $alert->parameter_name }}</td>
                                <td class="px-6 py-4">{{ $alert->value }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($alert->severity === 'critical') bg-red-100 text-red-800
                                        @elseif($alert->severity === 'warning') bg-yellow-100 text-yellow-800
                                        @else bg-blue-100 text-blue-800
                                        @endif
                                    ">
                                        {{ ucfirst($alert->severity) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">{{ $alert->timestamp->diffForHumans() }}</td>
                                <td class="px-6 py-4">
                                    @if($alert->resolved)
                                        <span class="text-green-500">Resolved</span>
                                    @else
                                        <span class="text-red-500">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament::page>