<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga sederhana untuk endpoint BACA read-only yang dikonsumsi ERP eksternal.
 * Membandingkan Bearer token dengan token yang sesuai secara aman.
 *
 * Parameter `$which` memilih token mana yang berlaku:
 *   - 'erp' (default) → `integrasi.erp_token`  (ERP 420F)
 *   - 'tm'            → `integrasi.tm_token`    (ERP TM420 — token berbeda, terpisah)
 * Token TM sengaja dipisah agar pencabutan/rotasi salah satu tak menyentuh yang lain.
 */
class VerifyErpToken
{
    public function handle(Request $request, Closure $next, string $which = 'erp'): Response
    {
        $expected = (string) config($which === 'tm' ? 'integrasi.tm_token' : 'integrasi.erp_token');
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
