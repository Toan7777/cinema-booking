<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Sử dụng: Route::middleware('role:ADMIN')
     * Có thể truyền nhiều role: 'role:ADMIN,STAFF'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role->name, $roles, true)) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập chức năng này',
            ], 403);
        }

        return $next($request);
    }
}
