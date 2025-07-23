{{-- System Overview Widget Fallback --}}
<div class="fallback-widget-content">
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white p-3 rounded border">
            <h5 class="text-xs font-medium text-gray-600 mb-1">Energy Consumption</h5>
            <div class="text-lg font-semibold text-gray-400">
                {{ $data['total_energy_consumption']['current_kw'] ?? 0 }} kW
            </div>
            <div class="text-xs text-gray-500">
                Total: {{ $data['total_energy_consumption']['total_kwh'] ?? 0 }} kWh
            </div>
        </div>

        <div class="bg-white p-3 rounded border">
            <h5 class="text-xs font-medium text-gray-600 mb-1">Active Devices</h5>
            <div class="text-lg font-semibold text-gray-400">
                {{ $data['active_devices_count']['active'] ?? 0 }} / {{ $data['active_devices_count']['total'] ?? 0 }}
            </div>
            <div class="text-xs text-gray-500">
                {{ $data['active_devices_count']['offline'] ?? 0 }} offline
            </div>
        </div>

        <div class="bg-white p-3 rounded border">
            <h5 class="text-xs font-medium text-gray-600 mb-1">Critical Alerts</h5>
            <div class="text-lg font-semibold text-red-400">
                {{ $data['critical_alerts_count']['critical'] ?? 0 }}
            </div>
            <div class="text-xs text-gray-500">
                {{ $data['critical_alerts_count']['warning'] ?? 0 }} warnings
            </div>
        </div>

        <div class="bg-white p-3 rounded border">
            <h5 class="text-xs font-medium text-gray-600 mb-1">System Health</h5>
            <div class="text-lg font-semibold text-gray-400">
                {{ $data['system_health_score']['score'] ?? 0 }}%
            </div>
            <div class="text-xs text-gray-500">
                {{ ucfirst($data['system_health_score']['status'] ?? 'unknown') }}
            </div>
        </div>
    </div>

    @if(isset($data['_fallback']))
        <div class="mt-3 text-xs text-yellow-600 bg-yellow-50 p-2 rounded">
            ⚠️ Displaying cached data from {{ \Carbon\Carbon::parse($data['_fallback']['generated_at'])->diffForHumans() }}
        </div>
    @endif
</div>