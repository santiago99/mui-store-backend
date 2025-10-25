<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LogRouteActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        Log::debug('LOG_ROUTE_ACTIVITY', 'aaaaa');
        if (env('LOG_ROUTE_ACTIVITY', false)) {
            $auth = auth()->guard('api');
            // Log the route, method, URL, and any other relevant details
            Log::debug('Route Accessed', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route() ? $request->route()->getName() : 'N/A', // Get route name if available
                'controller_action' => $request->route() ? $request->route()->getActionName() : 'N/A', // Get controller action
                'user_id' => $auth->guest() ? 'N/A' : $auth->id(), // Log authenticated user ID if applicable
            ]);
        }

        return $response;
    }
}
