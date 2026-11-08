<?php

namespace App\Http\Middleware;

use App\Services\InstallService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(private InstallService $install) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('setup', 'setup/*') && ! $this->install->installed()) {
            return redirect('/setup');
        }

        return $next($request);
    }
}
