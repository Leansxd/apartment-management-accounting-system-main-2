<?php

namespace App\Providers;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Olmayan bir alana / ilişkiye erişmek sessizce null dönmesin (geliştirmede hata verir)
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useTailwind();

        // Panelde bir sitenin verisine erişim: süper admin, site sahibi veya yönetici listesindekiler
        Gate::define('manage', fn (User $user, Apartment $apartment) => $apartment->isManagedBy($user));
        Gate::define('super-admin', fn (User $user) => $user->is_super_admin);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
        // Davet kodu tahminini engeller. Sayaç sunucuda tutulur; kullanıcı kendisi sıfırlayamaz.
        RateLimiter::for('invite', fn (Request $request) => [
            Limit::perHour(10)->by('invite-user:'.$request->user()?->id),
            Limit::perHour(30)->by('invite-ip:'.$request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Yerleşim: üst menüdeki site seçici ve aktif site
        // $site: adresteki site; $currentSite: üst çubukta seçili konut (adresteki, yoksa oturumda hatırlanan, yoksa ilk site)
        View::composer('components.layout', function ($view) {
            $user = auth()->user();
            $sites = $user?->accessibleApartments()->get(['id', 'name', 'code', 'city', 'owner_id']) ?? collect();
            $site = request()->route('apartment');
            $site = $site instanceof Apartment ? $site : null;
            $current = $site ?? $sites->firstWhere('id', session('selected_site_id')) ?? $sites->first();
            if ($current && session('selected_site_id') !== $current->id) {
                session(['selected_site_id' => $current->id]);
            }
            $openTickets = $current ? $current->tickets()->whereIn('status', ['open', 'in_progress'])->count() : 0;
            $view->with(['sites' => $sites, 'site' => $site, 'currentSite' => $current, 'openTickets' => $openTickets]);
        });
    }
}
