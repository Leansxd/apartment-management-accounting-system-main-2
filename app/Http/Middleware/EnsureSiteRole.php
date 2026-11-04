<?php

namespace App\Http\Middleware;

use App\Models\Apartment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitedeki yönetici rolü: manager her şeyi yapar; accountant yalnızca finans işlemlerini değiştirir;
 * viewer yalnızca görüntüler. Okuma (GET) tüm roller için açıktır. Site erişimi ayrıca 'can:manage' ile denetlenir.
 */
class EnsureSiteRole
{
    /** Muhasebecinin yazabildiği rota öneklerini (site. sonrası) */
    private const ACCOUNTANT_WRITES = [
        'collections.', 'debts.', 'cashboxes.', 'transactions.', 'transfers.', 'categories.', 'expenses.', 'vendors.',
        'dues.', 'late-fees.', 'advances.', 'bank.', 'budget.', 'payroll.', 'assets.', 'meter-bills.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $apartment = $request->route('apartment');
        if (! $apartment instanceof Apartment || $request->isMethodSafe()) {
            return $next($request);
        }

        $role = $apartment->roleOf($request->user());
        abort_if($role === null, 403, 'Bu siteye erişiminiz yok.');
        if ($role === 'manager') {
            return $next($request);
        }
        abort_if($role === 'viewer', 403, 'İzleyici rolü yalnızca görüntüleyebilir.');

        $name = (string) $request->route()->getName();
        $allowed = collect(self::ACCOUNTANT_WRITES)->contains(fn ($prefix) => str_starts_with($name, "site.{$prefix}"));
        abort_unless($allowed, 403, 'Bu işlem için yönetici rolü gerekir (muhasebeci yalnızca finans kayıtlarını değiştirebilir).');

        return $next($request);
    }
}
