<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDivision
{
    /**
     * Handle an incoming request.
     * Check if user is internal staff (any division)
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $internalRoles = [
            'manajemen',
            'customer_care',
            'training_center',
            'rekrutmen',
            'finance',
            'marketing'
        ];

        if (!in_array($request->user()->role, $internalRoles)) {
            return response()->json([
                'message' => 'Unauthorized. This resource is only accessible by internal staff.',
            ], 403);
        }

        return $next($request);
    }
}
