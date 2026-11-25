<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;

/**
 * Site içi modül izinleri. Yönetici (ve site sahibi, süper admin) her modülde tam yetkilidir; muhasebeci ve
 * izleyici için her modülün seviyesi site ayarlarından değiştirilebilir. Hiçbir modüle bağlı olmayan rotalar
 * (genel durum, arama, site ayarları, yönetici atama) herkese okunur, değiştirmek yalnızca yöneticiye açıktır.
 */
final class SitePermissions
{
    public const NONE = 'none';

    public const READ = 'read';

    public const WRITE = 'write';

    public const LEVELS = [self::NONE => 'Erişim yok', self::READ => 'Görüntüleme', self::WRITE => 'Görüntüleme + işlem'];

    /** Seviyesi ayarlanabilen roller */
    public const ROLES = ['accountant', 'viewer'];

    /**
     * Modül => etiket, menü grubu, kapsadığı rota önekleri (site. sonrası ilk parça) ve muhasebecinin varsayılan
     * seviyesi. İzleyicinin varsayılanı her modülde görüntülemedir.
     *
     * @var array<string, array{label: string, group: string, routes: list<string>, accountant: string}>
     */
    public const MODULES = [
        'reports' => ['label' => 'Rapor Merkezi', 'group' => 'Genel', 'routes' => ['reports'], 'accountant' => self::READ],
        'members' => ['label' => 'Sakinler & Üyeler', 'group' => 'Genel', 'routes' => ['members'], 'accountant' => self::READ],
        'units' => ['label' => 'Bloklar, Daireler & Gruplar', 'group' => 'Genel', 'routes' => ['units', 'blocks', 'groups'], 'accountant' => self::READ],
        'collections' => ['label' => 'Tahsilat & Makbuzlar', 'group' => 'Finans', 'routes' => ['collections', 'transactions'], 'accountant' => self::WRITE],
        'debts' => ['label' => 'Borçlandırma & Taksit / İcra', 'group' => 'Finans', 'routes' => ['debts'], 'accountant' => self::WRITE],
        'late-fees' => ['label' => 'Gecikme Zammı', 'group' => 'Finans', 'routes' => ['late-fees'], 'accountant' => self::WRITE],
        'advances' => ['label' => 'Avans & Mahsup', 'group' => 'Finans', 'routes' => ['advances'], 'accountant' => self::WRITE],
        'expenses' => ['label' => 'Giderler & Kategoriler', 'group' => 'Finans', 'routes' => ['expenses', 'categories'], 'accountant' => self::WRITE],
        'bank' => ['label' => 'Banka Hareketleri', 'group' => 'Finans', 'routes' => ['bank'], 'accountant' => self::WRITE],
        'cashboxes' => ['label' => 'Kasalar & Virman', 'group' => 'Finans', 'routes' => ['cashboxes', 'transfers'], 'accountant' => self::WRITE],
        'vendors' => ['label' => 'Firma / Tedarikçiler', 'group' => 'Finans', 'routes' => ['vendors'], 'accountant' => self::WRITE],
        'meter-bills' => ['label' => 'Sayaç Faturası Dağıtımı', 'group' => 'Finans', 'routes' => ['meter-bills'], 'accountant' => self::WRITE],
        'finance' => ['label' => 'Finans Özeti', 'group' => 'Finans', 'routes' => ['finance'], 'accountant' => self::READ],
        'accounting' => ['label' => 'Mali Tablolar & Ekstreler', 'group' => 'Muhasebe', 'routes' => ['income-expense', 'statements', 'accounting'], 'accountant' => self::READ],
        'ledger' => ['label' => 'Yevmiye Defteri', 'group' => 'Muhasebe', 'routes' => ['ledger'], 'accountant' => self::WRITE],
        'budget' => ['label' => 'Bütçe & Gerçekleşen', 'group' => 'Muhasebe', 'routes' => ['budget'], 'accountant' => self::WRITE],
        'dues' => ['label' => 'Aidat & Otomatik Tahakkuk', 'group' => 'Operasyon', 'routes' => ['dues'], 'accountant' => self::WRITE],
        'tasks' => ['label' => 'Görev & Bakım', 'group' => 'Operasyon', 'routes' => ['tasks'], 'accountant' => self::READ],
        'reservations' => ['label' => 'Rezervasyon & Ortak Alanlar', 'group' => 'Operasyon', 'routes' => ['reservations', 'areas'], 'accountant' => self::READ],
        'tickets' => ['label' => 'Arıza & Destek Talepleri', 'group' => 'Operasyon', 'routes' => ['tickets'], 'accountant' => self::READ],
        'security' => ['label' => 'Güvenlik, Ziyaretçi & Kargo', 'group' => 'Operasyon', 'routes' => ['security'], 'accountant' => self::READ],
        'inventory' => ['label' => 'Stok & Sarf Malzeme', 'group' => 'Operasyon', 'routes' => ['inventory'], 'accountant' => self::READ],
        'assets' => ['label' => 'Demirbaş Kaydı', 'group' => 'Operasyon', 'routes' => ['assets'], 'accountant' => self::WRITE],
        'meters' => ['label' => 'Sayaç & Endeks Okuma', 'group' => 'Operasyon', 'routes' => ['meters'], 'accountant' => self::READ],
        'staff' => ['label' => 'Personel', 'group' => 'Operasyon', 'routes' => ['staff'], 'accountant' => self::READ],
        'payroll' => ['label' => 'Personel Bordrosu', 'group' => 'Operasyon', 'routes' => ['payroll'], 'accountant' => self::WRITE],
        'notifications' => ['label' => 'Duyuru & Toplu Bildirim', 'group' => 'İletişim & İdari', 'routes' => ['notifications', 'announcements'], 'accountant' => self::READ],
        'reminders' => ['label' => 'Borç Hatırlatıcı', 'group' => 'İletişim & İdari', 'routes' => ['reminders'], 'accountant' => self::READ],
        'polls' => ['label' => 'Anketler', 'group' => 'İletişim & İdari', 'routes' => ['polls'], 'accountant' => self::READ],
        'meetings' => ['label' => 'Genel Kurul & Toplantılar', 'group' => 'İletişim & İdari', 'routes' => ['meetings'], 'accountant' => self::READ],
        'decisions' => ['label' => 'Karar Defteri', 'group' => 'İletişim & İdari', 'routes' => ['decisions'], 'accountant' => self::READ],
        'documents' => ['label' => 'Belge Arşivi', 'group' => 'İletişim & İdari', 'routes' => ['documents'], 'accountant' => self::READ],
        'audit' => ['label' => 'İşlem & Denetim Kayıtları', 'group' => 'İletişim & İdari', 'routes' => ['audit'], 'accountant' => self::READ],
    ];

    /** Rota adının (ör. site.debts.store) bağlı olduğu modül; modül dışı rotada null */
    public static function moduleForRoute(string $routeName): ?string
    {
        if (! str_starts_with($routeName, 'site.')) {
            return null;
        }
        $prefix = explode('.', substr($routeName, 5))[0];
        foreach (self::MODULES as $module => $definition) {
            if (in_array($prefix, $definition['routes'], true)) {
                return $module;
            }
        }

        return null;
    }

    /** @return array<string, string> modül => seviye */
    public static function defaults(string $role): array
    {
        return array_map(fn (array $definition) => $role === 'accountant' ? $definition['accountant'] : self::READ, self::MODULES);
    }

    /** @return array<string, array<string, string>> rol => modül => seviye (kayıtlı ayar + varsayılanlar) */
    public static function matrix(Apartment $apartment): array
    {
        $saved = $apartment->role_permissions ?? [];
        $matrix = [];
        foreach (self::ROLES as $role) {
            foreach (self::defaults($role) as $module => $default) {
                $level = $saved[$role][$module] ?? $default;
                $matrix[$role][$module] = array_key_exists($level, self::LEVELS) ? $level : $default;
            }
        }

        return $matrix;
    }

    /** Kullanıcı bu rotayı açabilir ($write = false) veya bu rotayla kayıt değiştirebilir ($write = true) mi? */
    public static function allows(Apartment $apartment, User $user, string $routeName, bool $write): bool
    {
        $role = $apartment->roleOf($user);
        if ($role === null) {
            return false;
        }
        if ($role === 'manager') {
            return true;
        }

        $module = self::moduleForRoute($routeName);
        if ($module === null) {
            return ! $write;
        }
        $level = self::matrix($apartment)[$role][$module] ?? self::NONE;

        return $write ? $level === self::WRITE : $level !== self::NONE;
    }
}
