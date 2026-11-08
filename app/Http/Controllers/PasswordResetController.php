<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /** Sonuç ne olursa olsun aynı yanıt döner: sayfa hangi e-postaların kayıtlı olduğunu açık etmez. */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }

        return back()->with('status', 'Bu e-posta adresi kayıtlıysa şifre sıfırlama bağlantısı gönderildi.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    /** Şifre değişince beni hatırla çerezi ve mobil oturumlar da geçersiz olur. */
    public function reset(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return back()->onlyInput('email')->withErrors(['email' => 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.']);
        }

        return redirect()->route('login')->with('status', 'Şifreniz değiştirildi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}
