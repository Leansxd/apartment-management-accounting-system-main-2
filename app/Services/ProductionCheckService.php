<?php

namespace App\Services;

use App\Models\User;
use Throwable;

/** Canlıya çıkmadan önce ayarları denetler: "fail" engeldir, "warn" gözden geçirilmelidir. */
class ProductionCheckService
{
    public function __construct(private DatabaseBackupService $backups) {}

    /** @return list<array{label:string, status:'ok'|'warn'|'fail', detail:string}> */
    public function results(): array
    {
        $url = (string) config('app.url');
        $mailer = (string) config('mail.default');
        $lastBackup = $this->backups->lastBackupAt();

        return [
            $this->check('Ortam', app()->environment('production'), 'fail', 'APP_ENV=production olmalı.'),
            $this->check('Hata ekranı', ! config('app.debug'), 'fail', 'APP_DEBUG=false olmalı.'),
            $this->check('Uygulama anahtarı', filled(config('app.key')), 'fail', 'php artisan key:generate çalıştırın.'),
            $this->check('Demo hesapları', ! $this->hasDemoUsers(), 'fail', 'Şifresi herkesçe bilinen demo hesaplarını kaldırın.'),
            $this->check('Arayüz derlemesi', is_file(public_path('build/manifest.json')), 'fail', 'npm run build çalıştırın.'),
            $this->check('Yazma izni', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'fail', 'storage ve bootstrap/cache yazılabilir olmalı.'),
            $this->check('HTTPS adresi', str_starts_with($url, 'https://'), 'warn', 'APP_URL https:// ile başlamalı.'),
            $this->check('Güvenli çerez', (bool) config('session.secure'), 'warn', 'HTTPS adresinde otomatik açılır.'),
            $this->check('E-posta sunucusu', ! in_array($mailer, ['log', 'array'], true), 'warn', 'MAIL_MAILER ayarlanmazsa e-postalar gönderilmez.'),
            $this->check('Gönderen adresi', ! str_ends_with((string) config('mail.from.address'), '@example.com'), 'warn', 'MAIL_FROM_ADDRESS gerçek bir adres olmalı.'),
            $this->check('Kuyruk', config('queue.default') !== 'sync', 'warn', 'QUEUE_CONNECTION=database olmalı.'),
            $this->check('Veritabanı', config('database.default') !== 'sqlite', 'warn', 'Canlıda MySQL önerilir.'),
            $this->check('Dosya bağlantısı', file_exists(public_path('storage')), 'warn', 'php artisan storage:link çalıştırın.'),
            $this->check('Yönetici iki adım', ! $this->hasAdminsWithoutTwoFactor(), 'warn', 'Tüm yöneticiler iki adımlı doğrulamayı açmalı.'),
            $this->check('Son yedek', $lastBackup?->gt(now()->subHours(26)) ?? false, 'warn', $lastBackup ? 'Son yedek '.$lastBackup->format('d.m.Y H:i').' tarihli.' : 'Henüz yedek yok: php artisan app:backup'),
        ];
    }

    /** @param 'warn'|'fail' $severity */
    private function check(string $label, bool $passed, string $severity, string $advice): array
    {
        return ['label' => $label, 'status' => $passed ? 'ok' : $severity, 'detail' => $passed ? '' : $advice];
    }

    private function hasAdminsWithoutTwoFactor(): bool
    {
        try {
            return User::where('role', 'admin')->whereNull('two_factor_confirmed_at')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function hasDemoUsers(): bool
    {
        try {
            return User::where('email', 'like', '%@demo.test')->exists();
        } catch (Throwable) {
            return false; // veritabanına ulaşılamıyorsa bu satır değil bağlantı sorunu öne çıkar
        }
    }
}
