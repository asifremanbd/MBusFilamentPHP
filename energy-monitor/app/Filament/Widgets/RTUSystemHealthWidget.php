<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RTUSystemHealthWidget extends Component
{
    protected string $view = 'filament.widgets.rtu-system-health-widget';
    
    public ?Gateway $gateway = null;

    public function mount(?Gateway $gateway = null): void
    {
        $this->gateway = $gateway;
    }

    public function render(): View
    {
        return view($this->view, [
            'data' => $this->getData(),
            'gateway' => $this->gateway
        ]);
    }

    public function getData(): array
    {
        if (!$this->gateway || !$this->gateway->isRTUGateway()) {
            return [
                'error' => 'Invalid or non-RTU gateway',
                'uptime' => null,
                'cpu_load' => null,
                'memory_usage' => null,
                'health_score' => 0,
                'overall_status' => 'unavailable',
                'last_updated' => null
            ];
        }

        $rtuDataService = app(RTUDataService::class);
        $systemHealth = $rtuDataService->getSystemHealth($this->gateway);
        
        // Check if this is an error response
        $hasError = isset($systemHealth['status']) && $systemHealth['status'] === 'error';
        
        // Extract data from fallback if error occurred
        if ($hasError && isset($systemHealth['fallback_data'])) {
            $fallbackData = $systemHealth['fallback_data'];
            $uptimeHours = $fallbackData['uptime_hours'];
            $cpuLoad = $fallbackData['cpu_load'];
            $memoryUsage = $fallbackData['memory_usage'];
            $healthScore = $fallbackData['health_score'];
            $overallStatus = $fallbackData['status'];
            $lastUpdated = $fallbackData['last_updated'];
        } else {
            $uptimeHours = $systemHealth['uptime_hours'];
            $cpuLoad = $systemHealth['cpu_load'];
            $memoryUsage = $systemHealth['memory_usage'];
            $healthScore = $systemHealth['health_score'];
            $overallStatus = $systemHealth['status'];
            $lastUpdated = $systemHealth['last_updated'];
        }
        
        return [
            'uptime' => [
                'value' => $uptimeHours,
                'unit' => 'hours',
                'status' => $this->getUptimeStatus($uptimeHours),
                'icon' => 'heroicon-o-clock',
                'formatted_value' => $this->formatUptime($uptimeHours)
            ],
            'cpu_load' => [
                'value' => $cpuLoad,
                'unit' => '%',
                'status' => $this->getCPUStatus($cpuLoad),
                'icon' => 'heroicon-o-cpu-chip',
                'threshold_warning' => 80,
                'threshold_critical' => 95,
                'formatted_value' => $this->formatPercentage($cpuLoad)
            ],
            'memory_usage' => [
                'value' => $memoryUsage,
                'unit' => '%',
                'status' => $this->getMemoryStatus($memoryUsage),
                'icon' => 'heroicon-o-server',
                'threshold_warning' => 85,
                'threshold_critical' => 95,
                'formatted_value' => $this->formatPercentage($memoryUsage)
            ],
            'health_score' => $healthScore ?? 0,
            'overall_status' => $overallStatus ?? 'unknown',
            'last_updated' => $lastUpdated,
            'error_info' => $hasError ? [
                'has_error' => true,
                'error_type' => $systemHealth['error_type'] ?? 'unknown',
                'message' => $systemHealth['message'] ?? 'Data collection failed',
                'retry_available' => $systemHealth['retry_available'] ?? false,
                'troubleshooting' => $systemHealth['troubleshooting'] ?? [],
                'cache_age' => $systemHealth['cache_age'] ?? null,
                'is_cached' => $systemHealth['fallback_data']['is_cached'] ?? false,
                'fallback_source' => $systemHealth['fallback_data']['fallback_source'] ?? 'none'
            ] : ['has_error' => false],
            'error' => $systemHealth['error'] ?? null
        ];
    }

    /**
     * Get CPU status based on load percentage
     */
    public function getCPUStatus(?float $cpuLoad): string
    {
        if ($cpuLoad === null) {
            return 'unknown';
        }
        
        if ($cpuLoad >= 95) {
            return 'critical';
        }
        
        if ($cpuLoad >= 80) {
            return 'warning';
        }
        
        return 'normal';
    }

    /**
     * Get memory status based on usage percentage
     */
    public function getMemoryStatus(?float $memoryUsage): string
    {
        if ($memoryUsage === null) {
            return 'unknown';
        }
        
        if ($memoryUsage >= 95) {
            return 'critical';
        }
        
        if ($memoryUsage >= 85) {
            return 'warning';
        }
        
        return 'normal';
    }

    /**
     * Get uptime status based on hours
     */
    public function getUptimeStatus(?int $uptimeHours): string
    {
        if ($uptimeHours === null) {
            return 'unknown';
        }
        
        if ($uptimeHours < 0) {
            return 'offline';
        }
        
        if ($uptimeHours === 0) {
            return 'warning'; // Recently restarted
        }
        
        return 'online';
    }

    /**
     * Format uptime hours into human-readable format
     */
    public function formatUptime(?int $uptimeHours): string
    {
        if ($uptimeHours === null) {
            return 'Data Unavailable';
        }
        
        if ($uptimeHours < 0) {
            return 'Offline';
        }
        
        if ($uptimeHours === 0) {
            return 'Recently Restarted';
        }
        
        if ($uptimeHours < 24) {
            return $uptimeHours . ' hours';
        }
        
        $days = floor($uptimeHours / 24);
        $remainingHours = $uptimeHours % 24;
        
        if ($remainingHours === 0) {
            return $days . ' days';
        }
        
        return $days . ' days, ' . $remainingHours . ' hours';
    }

    /**
     * Format percentage values with proper handling of null values
     */
    public function formatPercentage(?float $value): string
    {
        if ($value === null) {
            return 'Data Unavailable';
        }
        
        return number_format($value, 1) . '%';
    }

    /**
     * Get CSS class for status indicators
     */
    public function getStatusClass(string $status): string
    {
        return match($status) {
            'critical' => 'text-red-600 bg-red-50 border-red-200',
            'warning' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'normal', 'online' => 'text-green-600 bg-green-50 border-green-200',
            'offline' => 'text-gray-600 bg-gray-50 border-gray-200',
            'unknown', 'unavailable' => 'text-gray-500 bg-gray-100 border-gray-300',
            default => 'text-gray-500 bg-gray-100 border-gray-300'
        };
    }

    /**
     * Get status icon for different states
     */
    public function getStatusIcon(string $status): string
    {
        return match($status) {
            'critical' => 'heroicon-o-exclamation-triangle',
            'warning' => 'heroicon-o-exclamation-circle',
            'normal', 'online' => 'heroicon-o-check-circle',
            'offline' => 'heroicon-o-x-circle',
            'unknown', 'unavailable' => 'heroicon-o-question-mark-circle',
            default => 'heroicon-o-question-mark-circle'
        };
    }

    /**
     * Get overall health status color
     */
    public function getHealthScoreColor(int $healthScore): string
    {
        if ($healthScore >= 80) {
            return 'text-green-600';
        }
        
        if ($healthScore >= 60) {
            return 'text-yellow-600';
        }
        
        if ($healthScore >= 30) {
            return 'text-orange-600';
        }
        
        return 'text-red-600';
    }

    /**
     * Retry data collection for this widget
     */
    public function retryDataCollection(): array
    {
        if (!$this->gateway) {
            return [
                'success' => false,
                'message' => 'No gateway available for retry'
            ];
        }

        $retryService = app(\App\Services\RTURetryService::class);
        $result = $retryService->retryDataCollection($this->gateway, 'system_health');
        
        // Refresh widget data after retry
        $this->dispatch('refreshWidget');
        
        return $result;
    }

    /**
     * Get troubleshooting guidance for system health issues
     */
    public function getTroubleshootingGuidance(string $errorType): array
    {
        return match($errorType) {
            'timeout' => [
                'title' => 'Connection Timeout',
                'steps' => [
                    'Check network connectivity to RTU gateway',
                    'Verify gateway IP address is correct',
                    'Ensure gateway is powered on and operational',
                    'Check firewall settings'
                ]
            ],
            'connection_refused' => [
                'title' => 'Connection Refused',
                'steps' => [
                    'Verify RTU gateway is online',
                    'Check if gateway services are running',
                    'Confirm network routing to gateway',
                    'Restart gateway if safe to do so'
                ]
            ],
            'authentication' => [
                'title' => 'Authentication Failed',
                'steps' => [
                    'Verify RTU gateway credentials',
                    'Check if authentication method has changed',
                    'Ensure user account has proper permissions',
                    'Contact administrator for access'
                ]
            ],
            default => [
                'title' => 'General Communication Issue',
                'steps' => [
                    'Check RTU gateway status',
                    'Verify network connectivity',
                    'Review gateway logs for errors',
                    'Contact technical support if issue persists'
                ]
            ]
        };
    }
}