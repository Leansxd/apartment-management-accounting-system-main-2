<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Yöneticinin kendi hesabında iki adımlı doğrulamayı açması, kapatması ve kurtarma kodlarını yenilemesi */
class AccountTwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $isPending = $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled();

        return view('account.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $isPending,
            'secret' => $isPending ? $user->two_factor_secret : null,
            'qrCode' => $isPending ? $this->twoFactor->qrCode($user, $user->two_factor_secret) : null,
            'remainingCodes' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        abort_if($request->user()->hasTwoFactorEnabled(), 422, 'İki adımlı doğrulama zaten açık.');

        $request->user()->forceFill(['two_factor_secret' => $this->twoFactor->generateSecret(), 'two_factor_last_timestamp' => null])->save();

        return redirect()->route('account.two-factor');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = preg_replace('/\s+/', '', $request->validate(['code' => ['required', 'string', 'max:32']], ['code.required' => 'Doğrulama kodunu yazın.'])['code']);
        abort_if($user->hasTwoFactorEnabled(), 422, 'İki adımlı doğrulama zaten açık.');
        if (! $this->twoFactor->verifyCode($user, $code)) {
            return back()->withErrors(['code' => 'Doğrulama kodu hatalı. Telefonunuzdaki güncel kodu yazın.']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes, 'remember_token' => Str::random(60)])->save();

        return redirect()->route('account.two-factor')->with(['status' => 'İki adımlı doğrulama açıldı.', 'recoveryCodes' => $codes]);
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        abort_unless($request->user()->hasTwoFactorEnabled(), 422, 'İki adımlı doğrulama kapalı.');

        $codes = $this->twoFactor->generateRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('account.two-factor')->with(['status' => 'Kurtarma kodları yenilendi, eski kodlar geçersiz.', 'recoveryCodes' => $codes]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            $this->confirmPassword($request);
        }
        $this->twoFactor->disable($request->user());

        return redirect()->route('account.two-factor')->with('status', 'İki adımlı doğrulama kapatıldı.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'current_password']], [
            'current_password.required' => 'Mevcut şifrenizi yazın.',
            'current_password.current_password' => 'Mevcut şifreniz hatalı.',
        ]);
    }
}
