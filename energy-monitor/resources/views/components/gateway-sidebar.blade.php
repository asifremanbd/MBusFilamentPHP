<div x-data="{
    open: true,
    activeTab: 'devices',
    tabs: [
        { id: 'devices', name: 'Devices', icon: 'cpu-chip' },
        { id: 'stats', name: 'Statistics', icon: 'chart-bar' },
        { id: 'alerts', name: 'Alerts', icon: 'exclamation-triangle' },
        { id: 'settings', name: 'Settings', icon: 'cog' }
    ]
}" class="gateway-sidebar bg-white border-r border-gray-200 flex flex-col h-full">
    <!-- Gateway Header -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-2 h-2 rounded-full mr-2" 
                     :class="{
                        'bg-green-500': '{{ $gateway->communication_status ?? 'unknown' }}' === 'online',
                        'bg-red-500': '{{ $gateway->communication_status ?? 'unknown' }}' === 'offline',
                        'bg-yellow-500': '{{ $gateway->communication_status ?? 'unknown' }}' === 'warning',
                        'bg-gray-500': '{{ $gateway->communication_status ?? 'unknown' }}' === 'unknown'
                     }"></div>
                <h2 class="text-lg font-medium text-gray-900 truncate max-w-[180px]" title="{{ $gateway->name ?? 'Unknown Gateway' }}">
                    {{ $gateway->name ?? 'Unknown Gateway' }}
                </h2>
            </div>
            <button @click="open = !open" class="text-gray-400 hover:text-gray-500">
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M15 10a.75.75 0 01-.75.75H7.612l2.158 1.96a.75.75 0 11-1.04 1.08l-3.5-3.25a.75.75 0 010-1.08l3.5-3.25a.75.75 0 111.04 1.08L7.612 9.25h6.638A.75.75 0 0115 10z" clip-rule="evenodd" />
                </svg>
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        
        <!-- Gateway Info -->
        <div x-show="open" class="mt-2 text-sm text-gray-500">
            <div class="flex items-center justify-between mb-1">
                <span>ID:</span>
                <span class="font-mono">{{ $gateway->id ?? 'N/A' }}</span>
            </div>
            <div class="flex items-center justify-between mb-1">
                <span>Devices:</span>
                <span>{{ $deviceCount ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span>Location:</span>
                <span class="truncate max-w-[120px]" title="{{ $gateway->gnss_location ?? 'Unknown' }}">{{ $gateway->gnss_location ?? 'Unknown' }}</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-gray-200">
        <nav class="flex" aria-label="Tabs">
            <template x-for="tab in tabs" :key="tab.id">
                <a href="#" 
                   @click.prevent="activeTab = tab.id"
                   :class="{
                       'text-blue-600 border-blue-500': activeTab === tab.id,
                       'text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== tab.id,
                       'flex-1 py-3 px-1 text-center border-b-2 text-sm font-medium': true,
                       'flex flex-col items-center justify-center': true
                   }">
                    <svg x-show="tab.icon === 'cpu-chip'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13 7H7v6h6V7z" />
                        <path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="tab.icon === 'chart-bar'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                    </svg>
                    <svg x-show="tab.icon === 'exclamation-triangle'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="tab.icon === 'cog'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    <span x-show="open" x-text="tab.name"></span>
                </a>
            </template>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-y-auto p-4" x-show="open">
        <!-- Devices Tab -->
        <div x-show="activeTab === 'devices'" class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-700">Devices</h3>
                <div class="relative">
                    <input type="text" placeholder="Search devices..." class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            @if(isset($devices) && $devices->count() > 0)
                <div class="space-y-2">
                    @foreach($devices as $device)
                        <a href="#" class="block p-3 bg-gray-50 hover:bg-gray-100 rounded-md transition-colors duration-150">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full mr-2 bg-green-500"></div>
                                    <span class="font-medium text-gray-900">{{ $device->name }}</span>
                                </div>
                                <span class="text-xs text-gray-500">{{ $device->slave_id ? 'ID: ' . $device->slave_id : '' }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">{{ $device->location_tag ?? 'No location' }}</div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>No devices found</p>
                </div>
            @endif
        </div>

        <!-- Stats Tab -->
        <div x-show="activeTab === 'stats'" class="space-y-4">
            <h3 class="text-sm font-medium text-gray-700">Gateway Statistics</h3>
            
            <div class="space-y-3">
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Communication Status</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->communication_status ?? 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Uptime</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->uptime ?? 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Last Communication</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->last_communication ? $gateway->last_communication->diffForHumans() : 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Data Throughput</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->data_throughput ?? '0' }} readings/min</div>
                </div>
            </div>
        </div>

        <!-- Alerts Tab -->
        <div x-show="activeTab === 'alerts'" class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-700">Active Alerts</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    {{ $activeAlertCount ?? 0 }}
                </span>
            </div>
            
            @if(isset($activeAlerts) && count($activeAlerts) > 0)
                <div class="space-y-2">
                    @foreach($activeAlerts as $alert)
                        <div class="p-3 rounded-md {{ $alert->severity === 'critical' ? 'bg-red-50' : ($alert->severity === 'warning' ? 'bg-yellow-50' : 'bg-blue-50') }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 rounded-full mr-2 {{ $alert->severity === 'critical' ? 'bg-red-500' : ($alert->severity === 'warning' ? 'bg-yellow-500' : 'bg-blue-500') }}"></span>
                                    <span class="font-medium {{ $alert->severity === 'critical' ? 'text-red-800' : ($alert->severity === 'warning' ? 'text-yellow-800' : 'text-blue-800') }}">{{ $alert->device->name }}</span>
                                </div>
                                <span class="text-xs {{ $alert->severity === 'critical' ? 'text-red-500' : ($alert->severity === 'warning' ? 'text-yellow-500' : 'text-blue-500') }}">{{ $alert->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="mt-1 text-sm {{ $alert->severity === 'critical' ? 'text-red-700' : ($alert->severity === 'warning' ? 'text-yellow-700' : 'text-blue-700') }}">{{ $alert->message }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>No active alerts</p>
                </div>
            @endif
        </div>

        <!-- Settings Tab -->
        <div x-show="activeTab === 'settings'" class="space-y-4">
            <h3 class="text-sm font-medium text-gray-700">Gateway Settings</h3>
            
            <div class="space-y-3">
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">IP Address</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->ip_address ?? 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Port</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->port ?? 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Protocol</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->protocol ?? 'Unknown' }}</div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-md">
                    <div class="text-xs text-gray-500 mb-1">Firmware Version</div>
                    <div class="text-sm font-medium text-gray-900">{{ $gateway->firmware_version ?? 'Unknown' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collapsed View -->
    <div x-show="!open" class="flex-1 overflow-y-auto">
        <div class="py-4">
            <template x-for="tab in tabs" :key="tab.id">
                <a href="#" 
                   @click.prevent="activeTab = tab.id"
                   :class="{
                       'text-blue-600 bg-blue-50': activeTab === tab.id,
                       'text-gray-500 hover:text-gray-700 hover:bg-gray-50': activeTab !== tab.id,
                       'flex items-center justify-center py-3': true
                   }">
                    <svg x-show="tab.icon === 'cpu-chip'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13 7H7v6h6V7z" />
                        <path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="tab.icon === 'chart-bar'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                    </svg>
                    <svg x-show="tab.icon === 'exclamation-triangle'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="tab.icon === 'cog'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                </a>
            </template>
        </div>
    </div>
</div>"