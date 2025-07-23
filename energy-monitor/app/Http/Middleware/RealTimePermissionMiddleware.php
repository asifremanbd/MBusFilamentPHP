<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SessionPermissionService;
use App\Services\PermissionCacheService;
use Illuminate\Support\Facades\Log;

class RealTimePermissionMiddleware
{
    protected SessionPermissionService $sessionService;
    protected PermissionCacheService $cacheService;

    public function __construct(
        SessionPermissionService $sessionService,
        PermissionCacheService $cacheService
    ) {
        $this->sessionService = $sessionService;
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = null, string $context = null): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }

        // Parse context parameters
        $contextData = $this->parseContext($request, $context);

        // Check session-based permissions first (faster)
        if ($permission && !$this->sessionService->hasSessionPermission($user, $permission, $contextData)) {
            // Log permission denial
            Log::warning('Real-time permission denied', [
                'user_id' => $user->id,
                'permission' => $permission,
                'context' => $contextData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);

            return $this->unauthorizedResponse('Insufficient permissions');
        }

        // Add permission context to request for downstream use
        $request->merge([
            'permission_context' => $contextData,
            'session_permissions' => $this->sessionService->getSessionPermissions($user),
        ]);

        // Add permission refresh header if permissions are about to expire
        $response = $next($request);
        
        $this->addPermissionHeaders($response, $user);

        return $response;
    }

    /**
     * Parse context parameters from request
     */
    protected function parseContext(Request $request, ?string $context): array
    {
        $contextData = [];

        if (!$context) {
            return $contextData;
        }

        // Parse context string like "gateway_id:route,device_id:input"
        $contextPairs = explode(',', $context);
        
        foreach ($contextPairs as $pair) {
            if (strpos($pair, ':') === false) {
                continue;
            }

            [$key, $source] = explode(':', $pair, 2);
            
            $value = match($source) {
                'route' => $request->route($key),
                'input' => $request->input($key),
                'header' => $request->header($key),
                default => $request->get($key),
            };

            if ($value !== null) {
                $contextData[$key] = $value;
            }
        }

        return $contextData;
    }

    /**
     * Add permission-related headers to response
     */
    protected function addPermissionHeaders(Response $response, $user): void
    {
        $permissionSummary = $this->sessionService->getSessionPermissionSummary($user);
        
        // Add header indicating when permissions should be refreshed
        if (isset($permissionSummary['next_refresh_due'])) {
            $response->headers->set('X-Permission-Refresh-At', $permissionSummary['next_refresh_due']);
        }

        // Add header indicating if permissions are about to expire (within 1 minute)
        $nextRefresh = $permissionSummary['next_refresh_due'] ?? null;
        if ($nextRefresh && strtotime($nextRefresh) - time() < 60) {
            $response->headers->set('X-Permission-Refresh-Required', 'true');
        }

        // Add permission fingerprint for client-side validation
        $permissionFingerprint = md5(serialize($permissionSummary['permissions_count'] ?? []));
        $response->headers->set('X-Permission-Fingerprint', $permissionFingerprint);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $message,
                'timestamp' => now()->toISOString(),
            ], 403);
        }

        return response()->view('errors.403', [
            'message' => $message,
        ], 403);
    }
}