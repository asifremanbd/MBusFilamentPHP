<?php

namespace App\Widgets;

use App\Models\User;
use App\Services\UserPermissionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseWidget
{
    protected User $user;
    protected UserPermissionService $permissionService;
    protected array $config;
    protected ?int $gatewayId;
    protected int $cacheTtl = 300; // 5 minutes default cache

    public function __construct(User $user, array $config = [], ?int $gatewayId = null)
    {
        $this->user = $user;
        $this->config = $config;
        $this->gatewayId = $gatewayId;
        $this->permissionService = app(UserPermissionService::class);
    }

    /**
     * Get widget data with permission checking and caching
     */
    public function render(): array
    {
        try {
            // Check if user has permission to access this widget
            if (!$this->hasPermission()) {
                return $this->getUnauthorizedResponse();
            }

            // Try to get cached data first
            $cacheKey = $this->getCacheKey();
            if ($this->shouldCache()) {
                $cachedData = Cache::get($cacheKey);
                if ($cachedData !== null) {
                    return $this->formatResponse($cachedData, true);
                }
            }

            // Get fresh data
            $data = $this->getData();

            // Cache the data if caching is enabled
            if ($this->shouldCache()) {
                Cache::put($cacheKey, $data, $this->cacheTtl);
            }

            return $this->formatResponse($data, false);

        } catch (\Exception $e) {
            Log::error('Widget rendering error', [
                'widget_class' => get_class($this),
                'user_id' => $this->user->id,
                'gateway_id' => $this->gatewayId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getErrorResponse($e);
        }
    }

    /**
     * Check if user has permission to access this widget
     */
    protected function hasPermission(): bool
    {
        return $this->permissionService->canAccessWidget(
            $this->user,
            $this->getWidgetType(),
            $this->getPermissionConfig()
        );
    }

    /**
     * Get widget-specific data (to be implemented by child classes)
     */
    abstract protected function getData(): array;

    /**
     * Get widget type identifier
     */
    abstract protected function getWidgetType(): string;

    /**
     * Get widget display name
     */
    abstract protected function getWidgetName(): string;

    /**
     * Get widget description
     */
    abstract protected function getWidgetDescription(): string;

    /**
     * Get permission configuration for this widget instance
     */
    protected function getPermissionConfig(): array
    {
        $config = [];

        if ($this->gatewayId) {
            $config['gateway_id'] = $this->gatewayId;
        }

        if (isset($this->config['device_ids'])) {
            $config['device_ids'] = $this->config['device_ids'];
        }

        return array_merge($config, $this->config);
    }

    /**
     * Format the response with metadata
     */
    protected function formatResponse(array $data, bool $fromCache = false): array
    {
        return [
            'widget_type' => $this->getWidgetType(),
            'widget_name' => $this->getWidgetName(),
            'widget_description' => $this->getWidgetDescription(),
            'status' => 'success',
            'data' => $data,
            'metadata' => [
                'user_id' => $this->user->id,
                'gateway_id' => $this->gatewayId,
                'from_cache' => $fromCache,
                'generated_at' => now()->toISOString(),
                'cache_ttl' => $this->cacheTtl,
            ]
        ];
    }

    /**
     * Get unauthorized response
     */
    protected function getUnauthorizedResponse(): array
    {
        return [
            'widget_type' => $this->getWidgetType(),
            'widget_name' => $this->getWidgetName(),
            'status' => 'unauthorized',
            'message' => 'You do not have permission to access this widget',
            'data' => [],
            'metadata' => [
                'user_id' => $this->user->id,
                'gateway_id' => $this->gatewayId,
                'generated_at' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Get error response
     */
    protected function getErrorResponse(\Exception $e): array
    {
        return [
            'widget_type' => $this->getWidgetType(),
            'widget_name' => $this->getWidgetName(),
            'status' => 'error',
            'message' => 'Widget failed to load',
            'error' => app()->environment('local') ? $e->getMessage() : 'Internal error',
            'data' => $this->getFallbackData(),
            'metadata' => [
                'user_id' => $this->user->id,
                'gateway_id' => $this->gatewayId,
                'generated_at' => now()->toISOString(),
                'retry_available' => true,
            ]
        ];
    }

    /**
     * Get fallback data when widget fails to load
     */
    protected function getFallbackData(): array
    {
        return [];
    }

    /**
     * Get cache key for this widget instance
     */
    protected function getCacheKey(): string
    {
        $keyParts = [
            'widget',
            $this->getWidgetType(),
            $this->user->id,
            $this->gatewayId ?? 'global',
            md5(serialize($this->config))
        ];

        return implode(':', array_filter($keyParts));
    }

    /**
     * Determine if this widget should be cached
     */
    protected function shouldCache(): bool
    {
        return $this->cacheTtl > 0;
    }

    /**
     * Set cache TTL for this widget
     */
    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;
        return $this;
    }

    /**
     * Disable caching for this widget
     */
    public function disableCache(): self
    {
        $this->cacheTtl = 0;
        return $this;
    }

    /**
     * Clear cache for this widget
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Get widget configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update widget configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Get widget metadata
     */
    public function getMetadata(): array
    {
        return [
            'widget_type' => $this->getWidgetType(),
            'widget_name' => $this->getWidgetName(),
            'widget_description' => $this->getWidgetDescription(),
            'cache_enabled' => $this->shouldCache(),
            'cache_ttl' => $this->cacheTtl,
            'requires_gateway' => $this->requiresGateway(),
            'permission_config' => $this->getPermissionConfig(),
        ];
    }

    /**
     * Check if this widget requires a gateway context
     */
    protected function requiresGateway(): bool
    {
        return false;
    }

    /**
     * Validate widget configuration
     */
    protected function validateConfig(): bool
    {
        if ($this->requiresGateway() && !$this->gatewayId) {
            throw new \InvalidArgumentException('This widget requires a gateway ID');
        }

        return true;
    }

    /**
     * Get widget category for grouping
     */
    protected function getWidgetCategory(): string
    {
        return 'general';
    }

    /**
     * Get widget priority for ordering (lower = higher priority)
     */
    protected function getWidgetPriority(): int
    {
        return 100;
    }

    /**
     * Check if widget supports real-time updates
     */
    protected function supportsRealTimeUpdates(): bool
    {
        return false;
    }

    /**
     * Get real-time update interval in seconds
     */
    protected function getRealTimeUpdateInterval(): int
    {
        return 30;
    }
}