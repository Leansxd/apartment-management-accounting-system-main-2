<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Yönetim paneli yalnızca admin rolündeki kullanıcılara açıktır (sakinler mobil API'yi kullanır). */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Bu panel yalnızca yöneticilere açıktır.']);
        }

        return $next($request);
    }
}
