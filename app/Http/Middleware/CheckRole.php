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
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.', 'status' => 401], 401);
        }

        $userRole = strtolower($request->user()->role);

        // Normalize roles for checking
        $allowedRoles = array_map('strtolower', $roles);

        // Handle role normalization: 'Vendor' and 'supplier' are treated the same
        if ($userRole === 'vendor') {
            $userRole = 'supplier';
        }

        if (in_array('supplier', $allowedRoles) && in_array('vendor', $allowedRoles) === false) {
            $allowedRoles[] = 'vendor';
        }

        // Handle role normalization: 'ContentCreator' and 'creator' are treated the same
        if ($userRole === 'contentcreator') {
            $userRole = 'creator';
        }

        if (in_array('creator', $allowedRoles) && in_array('contentcreator', $allowedRoles) === false) {
            $allowedRoles[] = 'contentcreator';
        }

        if (! in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'message' => 'Forbidden: You do not have the required role to access this resource.',
                'status' => 403
            ], 403);
        }

        return $next($request);
    }
}
