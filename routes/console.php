<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/*
 * İlk yöneticiyi oluşturur veya var olan kullanıcıyı yönetici yapar.
 *   php artisan app:make-admin ornek@site.com --create --password="GucluSifre123" --super
 */
Artisan::command('app:make-admin {email} {--create : Kullanıcı yoksa oluştur} {--password= : Yeni kullanıcının şifresi} {--name= : Ad soyad} {--super : Tüm sitelere erişen süper admin}',
    function (string $email) {
        $user = User::where('email', $email)->first();
        if (! $user) {
            if (! $this->option('create')) {
                $this->error("{$email} bulunamadı. Oluşturmak için --create ekleyin.");

                return 1;
            }
            $password = $this->option('password') ?: Str::password(14);
            if (strlen($password) < 8) {
                $this->error('Şifre en az 8 karakter olmalıdır.');

                return 1;
            }
            $user = User::create(['name' => $this->option('name') ?: Str::before($email, '@'), 'email' => $email, 'password' => $password]);
            if (! $this->option('password')) {
                $this->warn("Oluşturulan şifre: {$password}  (giriş yaptıktan sonra değiştirin)");
            }
        }

        $user->forceFill(['role' => 'admin', 'is_super_admin' => (bool) $this->option('super') || $user->is_super_admin])->save();
        $this->info("{$email} artık ".($user->is_super_admin ? 'süper admin' : 'yönetici').'.');

        return 0;
    })->purpose('Panel yöneticisi oluşturur / atar');
