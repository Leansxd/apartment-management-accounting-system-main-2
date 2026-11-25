<?php

namespace App\Providers;

use App\Models\Apartment;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\InstallService;
use App\Services\ModuleStatsService;
use App\Support\SitePermissions;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
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
        $install = $this->app->make(InstallService::class);
        if (! $this->app->runningInConsole() && ! $install->installed()) {
            $install->prepareRuntime();
        }

        // Olmayan bir alana / ilişkiye erişmek sessizce null dönmesin (geliştirmede hata verir)
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useTailwind();

        AuditTrail::watchModels();
        Event::listen(Login::class, fn (Login $event) => app(AuditTrail::class)->logAuthentication('LOGIN', $event->user));
        Event::listen(Logout::class, fn (Logout $event) => app(AuditTrail::class)->logAuthentication('LOGOUT', $event->user));
        Event::listen(PasswordReset::class, fn (PasswordReset $event) => app(AuditTrail::class)->logAuthentication('PASSWORD_RESET', $event->user));

        // Panelde bir sitenin verisine erişim: süper admin, site sahibi veya yönetici listesindekiler
        Gate::define('manage', fn (User $user, Apartment $apartment) => $apartment->isManagedBy($user));
        Gate::define('super-admin', fn (User $user) => $user->is_super_admin);
        // Rota adıyla modül izni: @can('site-access', [$apartment, 'site.debts.index'])
        Gate::define('site-access', fn (User $user, Apartment $apartment, string $route) => SitePermissions::allows($apartment, $user, $route, false));
        // TC kimlik no ve davet kodu: yalnızca sakinleri yöneten rol görür
        Gate::define('view-member-secrets', fn (User $user, Apartment $apartment) => $apartment->roleOf($user) === 'manager');

        // Sıfırlama bağlantısı isteğin Host başlığından değil, APP_URL'den üretilir (Host zehirlenmesi)
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));

        // Hesap başına deneme sınırı + aynı IP'den çok hesabın denenmesine karşı IP sınırı
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
        // Doğrulama kodu tahmini: şifresi doğrulanmış hesap başına sayılır, yeniden girişle sıfırlanmaz
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('two-factor:'.$request->session()->get('two_factor.user_id').'|'.$request->ip()),
            Limit::perMinute(20)->by('two-factor-ip:'.$request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register-ip:'.$request->ip()));
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

        View::composer(array_keys(ModuleStatsService::VIEWS), function ($view) {
            $data = $view->getData();
            $view->with('moduleSummary', app(ModuleStatsService::class)->{ModuleStatsService::VIEWS[$view->name()]}($data['apartment'], $data));
        });
    }
}
