<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tarayıcı güvenlik başlıkları. CSP yerelde uygulanmaz: Vite geliştirme sunucusu ayrı bir kaynaktır.
 * Görünümlerde satır içi olay ve stil kullanıldığı için 'unsafe-inline' gereklidir.
 */
class SecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline'; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' data: https://fonts.gstatic.com; "
        ."img-src 'self' data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'self'";

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! app()->isLocal()) {
            $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
