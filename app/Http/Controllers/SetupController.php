<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class SetupController extends Controller
{
    public function __construct(private InstallService $install) {}

    public function show()
    {
        if ($this->install->installed()) {
            return redirect()->route('login');
        }

        return view('setup.index', [
            'requirements' => $this->install->requirements(),
            'needsToken' => filled(config('setup.token')),
            'allowDemo' => ! app()->isProduction(),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->install->installed()) {
            return redirect()->route('login');
        }

        $clean = 'required_if:mode,clean';
        $mysql = 'required_if:db_connection,mysql';
        $data = $request->validate([
            'setup_token' => ['nullable', 'string'],
            'mode' => ['required', Rule::in(app()->isProduction() ? ['clean'] : ['clean', 'demo'])],
            'db_connection' => ['required', Rule::in(['sqlite', 'mysql'])],
            'db_host' => [$mysql, 'nullable', 'string', 'max:120'],
            'db_port' => [$mysql, 'nullable', 'integer', 'between:1,65535'],
            'db_database' => [$mysql, 'nullable', 'string', 'max:64'],
            'db_username' => [$mysql, 'nullable', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'name' => [$clean, 'nullable', 'string', 'max:120'],
            'email' => [$clean, 'nullable', 'email', 'max:120'],
            'password' => [$clean, 'nullable', 'confirmed', Password::min(8)],
            'site_name' => [$clean, 'nullable', 'string', 'max:120'],
            'site_code' => [$clean, 'nullable', 'alpha_dash', 'max:20'],
            'site_blocks' => ['nullable', 'string', 'max:100'],
        ]);

        $sensitive = ['password', 'password_confirmation', 'db_password', 'setup_token'];
        $token = (string) config('setup.token');
        if ($token !== '' && ! hash_equals($token, (string) ($data['setup_token'] ?? ''))) {
            return back()->withInput($request->except($sensitive))->withErrors(['setup_token' => 'Kurulum kodu hatalı.']);
        }

        $lock = Cache::store('file')->lock('setup', 120);
        if (! $lock->get()) {
            return back()->withInput($request->except($sensitive))->withErrors(['install' => 'Kurulum şu an başka bir sekmede çalışıyor.']);
        }

        try {
            $owner = $this->install->install($data);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except($sensitive))->withErrors(['install' => 'Kurulum tamamlanamadı: '.$e->getMessage()]);
        } finally {
            $lock->release();
        }

        // Oturum sürücüsü kurulumla değiştiği için giriş, tek kullanımlık imzalı bağlantıyla yapılır
        Cache::store('file')->put('setup-login', $owner->id, now()->addMinutes(10));

        return redirect()->to(URL::temporarySignedRoute('setup.finish', now()->addMinutes(10), ['user' => $owner]));
    }

    public function finish(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature() && Cache::store('file')->pull('setup-login') === $user->id, 403);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('dashboard', ['tur' => 1]);
    }
}
