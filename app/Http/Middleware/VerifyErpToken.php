<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga sederhana untuk endpoint BACA yang dikonsumsi ERP 420F.
 * Membandingkan Bearer token dengan config('integrasi.erp_token') secara aman.
 */
class VerifyErpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('integrasi.erp_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json([
                'ok' => false,
                'message' => 'Token tidak valid.',
                'data' => null,
                'errors' => ['unauthorized'],
            ], 401);
        }

        return $next($request);
    }
}
