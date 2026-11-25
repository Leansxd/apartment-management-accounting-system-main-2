<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditTrail;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Şifresi doğrulanan kullanıcıdan girişi tamamlamak için doğrulama kodu ister. */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) ? view('auth.two-factor-challenge') : $this->expired($request);
    }

    public function store(Request $request, TwoFactorService $twoFactor, AuditTrail $audit): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->expired($request);
        }

        $code = preg_replace('/\s+/', '', $request->validate(['code' => ['required', 'string', 'max:32']], ['code.required' => 'Doğrulama kodunu yazın.'])['code']);
        $remainingCodes = str_contains($code, '-') ? $twoFactor->consumeRecoveryCode($user, $code) : null;
        if ($remainingCodes === null && ! $twoFactor->verifyCode($user, $code)) {
            $audit->logAuthentication('TWO_FACTOR_FAILED', $user);

            return back()->withErrors(['code' => 'Doğrulama kodu hatalı.']);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember');
        $request->session()->forget('two_factor');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return ($user->isAdmin() ? redirect()->intended(route('dashboard')) : redirect()->route('portal.home'))->with($remainingCodes === null ? [] : [
            'status' => "Kurtarma kodu kullanıldı, {$remainingCodes} kod kaldı.",
        ]);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor');
        if (! $pending || $pending['expires_at'] < now()->timestamp) {
            return null;
        }
        $user = User::find($pending['user_id']);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function expired(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor');

        return redirect()->route('login')->withErrors(['email' => 'Doğrulama süresi doldu, yeniden giriş yapın.']);
    }
}
