<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Alert Rule Creation Form -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-plus class="w-5 h-5 text-green-500" />
                    Create New Alert Rule
                </div>
            </x-slot>
            
            <x-slot name="description">
                Configure automatic alerts for critical parameters. Rules will trigger notifications when values exceed defined thresholds.
            </x-slot>
            
            {{ $this->form }}
            
            <div class="mt-4">
                <x-filament::button type="submit" wire:click="createAlertRule">
                    Create Alert Rule
                </x-filament::button>
            </div>
        </x-filament::section>

        <!-- Alert Rules Information -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500" />
                        Alert Conditions
                    </div>
                </x-slot>
                
                <div class="space-y-2 text-sm">
                    <div><strong>Greater Than:</strong> Triggers when value exceeds threshold</div>
                    <div><strong>Less Than:</strong> Triggers when value falls below threshold</div>
                    <div><strong>Outside Range:</strong> Triggers when value is outside normal range</div>
                    <div><strong>Between:</strong> Triggers when value is within specified range</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-yellow-500" />
                        Severity Levels
                    </div>
                </x-slot>
                
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <strong>Low:</strong> Minor issues, informational
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                        <strong>Medium:</strong> Moderate issues requiring attention
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                        <strong>High:</strong> Serious issues needing prompt action
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <strong>Critical:</strong> Urgent issues requiring immediate action
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-bell class="w-4 h-4 text-purple-500" />
                        Notification Methods
                    </div>
                </x-slot>
                
                <div class="space-y-2 text-sm">
                    <div><strong>Email:</strong> Sent to users with email notifications enabled</div>
                    <div><strong>SMS:</strong> Sent to users with SMS notifications enabled</div>
                    <div><strong>Dashboard:</strong> Real-time alerts displayed in dashboard</div>
                    <div><strong>System Log:</strong> All alerts logged for audit trail</div>
                </div>
            </x-filament::section>
        </div>

        <!-- Critical Parameters Table -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-rectangle-stack class="w-5 h-5 text-red-500" />
                    Critical Parameters
                </div>
            </x-slot>
            
            <x-slot name="description">
                These parameters are marked as critical and should have alert rules configured. Click "Create Alert Rule" to set up monitoring.
            </x-slot>
            
            {{ $this->table }}
        </x-filament::section>

        <!-- Best Practices -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-light-bulb class="w-5 h-5 text-yellow-500" />
                    Best Practices for Alert Rules
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold mb-2">Threshold Setting</h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li>• Set thresholds based on equipment specifications</li>
                        <li>• Consider normal operating variations</li>
                        <li>• Use historical data to determine appropriate levels</li>
                        <li>• Test rules with non-critical parameters first</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Severity Assignment</h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li>• Critical: Equipment damage or safety risks</li>
                        <li>• High: Performance degradation or efficiency loss</li>
                        <li>• Medium: Maintenance required or trending issues</li>
                        <li>• Low: Informational or minor deviations</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Rule Management</h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li>• Regularly review and adjust thresholds</li>
                        <li>• Disable rules during maintenance periods</li>
                        <li>• Document rule purposes and expected actions</li>
                        <li>• Monitor alert frequency to avoid fatigue</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Response Planning</h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li>• Define clear response procedures for each severity</li>
                        <li>• Assign responsible personnel for different alert types</li>
                        <li>• Establish escalation procedures for unresolved alerts</li>
                        <li>• Train staff on alert interpretation and response</li>
                    </ul>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>