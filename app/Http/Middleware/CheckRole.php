<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($user->role === $role) {
                return $next($request);
            }
        }

        // If no role matches, check for specific permissions
        if (in_array('admin', $roles) && $user->isAdmin()) {
            return $next($request);
        }

        if (in_array('manager', $roles) && $user->isManager()) {
            return $next($request);
        }

        if (in_array('sales', $roles) && $user->isSales()) {
            return $next($request);
        }

        // Check for combined permissions
        if (in_array('admin_or_manager', $roles) && ($user->isAdmin() || $user->isManager())) {
            return $next($request);
        }

        // If user doesn't have required role, abort with 403
        abort(403, 'Access denied. Insufficient permissions.');
    }
}
