<?php

namespace App\Http\Middleware;

use App\Services\SecurityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUnauthorizedAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Check if the response is a 403 (Forbidden) or 401 (Unauthorized)
        if ($response->getStatusCode() === 403 || $response->getStatusCode() === 401) {
            // Extract resource and action from the request
            $resource = $this->extractResourceFromRequest($request);
            $action = $this->extractActionFromRequest($request);
            
            // Log the unauthorized access attempt
            SecurityLogService::logUnauthorizedAccess($request, $resource, $action);
        }
        
        return $response;
    }
    
    /**
     * Extract resource name from the request
     */
    private function extractResourceFromRequest(Request $request): string
    {
        $path = $request->path();
        $segments = explode('/', $path);
        
        // Try to determine the resource from the URL
        if (count($segments) >= 2) {
            return $segments[1] ?? 'unknown';
        }
        
        return 'unknown';
    }
    
    /**
     * Extract action from the request
     */
    private function extractActionFromRequest(Request $request): string
    {
        $method = $request->method();
        
        return match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }
}
