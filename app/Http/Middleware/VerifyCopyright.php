<?php

namespace App\Http\Middleware;

use App\Support\CopyrightGuard;
use Closure;
use Illuminate\Http\Request;

/**
 * Kunci seluruh aplikasi bila teks hak cipta AnonymouSL diubah/dihapus.
 * Berpasangan dengan App\Support\CopyrightGuard.
 */
class VerifyCopyright
{
    public function handle(Request $request, Closure $next)
    {
        if (! CopyrightGuard::passes()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Integritas hak cipta aplikasi gagal diverifikasi.',
                ], 403);
            }

            return response()->view('errors.copyright', [], 403);
        }

        return $next($request);
    }
}
