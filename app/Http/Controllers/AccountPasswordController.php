<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Giriş yapmış yöneticinin kendi şifresini değiştirmesi */
class AccountPasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    /** Şifre değişince bu oturum dışındaki oturumlar, beni hatırla çerezi ve mobil oturumlar geçersiz olur. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(8)],
        ], [
            'current_password.current_password' => 'Mevcut şifreniz hatalı.',
            'password.confirmed' => 'Yeni şifre tekrarı eşleşmiyor.',
            'password.different' => 'Yeni şifre mevcut şifrenizden farklı olmalıdır.',
        ], ['current_password' => 'mevcut şifre', 'password' => 'yeni şifre']);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();

        $request->session()->regenerate(true);
        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        return back()->with('status', 'Şifreniz değiştirildi.');
    }
}
