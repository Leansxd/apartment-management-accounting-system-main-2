<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'demoLogin' => ! app()->isProduction() && User::where('email', 'admin@demo.test')->exists(),
        ]);
    }

    public function login(Request $request, AuditTrail $audit)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            $audit->logAuthentication('LOGIN_FAILED', User::where('email', $credentials['email'])->first(), ['email' => $credentials['email']]);

            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();
        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor', [
                'user_id' => $user->id, 'remember' => $request->boolean('remember'), 'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->isAdmin() ? redirect()->intended(route('dashboard')) : redirect()->route('portal.home');
    }

    /** Oturumu kapatır ve oturum verisini tamamen siler (ortak bilgisayarda veri kalmaz). */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
