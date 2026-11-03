<?php

namespace App\Http\Middleware;

use App\Models\Apartment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobil API: kullanıcı bu sitede en az bir aktif (silinmemiş) üyeliğe bağlı olmalıdır.
 * Erişim üyelik tablosundan hesaplanır; üyelik silinince veya bağlantı kaldırılınca anında biter.
 */
class EnsureResident
{
    public function handle(Request $request, Closure $next): Response
    {
        $apartment = $request->route('apartment');
        $unitIds = $apartment instanceof Apartment ? $request->user()->residentUnitIds($apartment) : [];
        abort_if($unitIds === [], 403, 'Bu siteye bağlı bir daireniz yok.');

        $request->attributes->set('unit_ids', $unitIds);

        return $next($request);
    }
}
