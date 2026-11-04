# Memoshome — Site & Apartman Yönetimi (Laravel)

Next.js + Firebase sürümünün Laravel 13 + SQLite/MySQL ile yeniden yazılmış hali.
Firebase hesabı veya anahtarı gerekmez; her şey yerelde çalışır.

## Çalıştırma (Windows)

Gerekenler: PHP 8.2+ ve Composer (bu bilgisayarda Herd Lite ile kurulu: `C:\Users\PC\.config\herd-lite\bin`), Node.js (yalnızca arayüzü yeniden derlemek için).

İlk kurulum (bir kez):

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

Çalıştırma:

```bash
php artisan serve
```

Tarayıcıda http://localhost:8000 adresini açın.

Demo hesaplar (`--seed` ile oluşur, yalnızca yerel deneme içindir):

| Rol | E-posta | Şifre |
| --- | --- | --- |
| Süper admin (panel) | admin@demo.test | password |
| Sakin (mobil API) | sakin@demo.test | password |

Demo verisi olmadan temiz başlamak ve kendi yöneticinizi oluşturmak için:

```bash
php artisan migrate:fresh
php artisan app:make-admin siz@ornek.com --create --password="GucluSifre123" --super
```

### MySQL (XAMPP) kullanmak isterseniz

`.env` içinde `DB_CONNECTION=mysql` yapıp `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=memoshome`, `DB_USERNAME=root`, `DB_PASSWORD=` ayarlayın, phpMyAdmin'de `memoshome` veritabanını oluşturun ve `php artisan migrate --seed` çalıştırın.

## Testler

```bash
php artisan test
```

Muhasebe kuralları (avans mahsubu, tahsilat dağıtımı, mükerrer tahakkuk engeli, eksi bakiye kontrolü, yevmiye dengesi), panel yetkileri (siteler arası erişim engeli) ve mobil API (davet kodu, sakin kapsamı, deneme sınırı, anket) test edilir.

## Modüller

- **Siteler, bloklar, daireler, daire grupları**; **üyeler/sakinler** (davet kodu ile mobil bağlantı)
- **Finans:** borç dağıtımı, aidat motoru (dönem bazlı, mükerrersiz), tahsilat (en eski borçtan kapatır, fazlası avans), kasa/banka, virman, gider/fatura onayı, firmalar
- **Gecikme zammı:** vadesi geçen borçlara aylık oran (KMK m.20: %5) ve ek süre; önizleme + tek tıkla yazma, aynı ay için ikinci kez zam yazılmaz
- **Düzeltme işlemleri:** tahsilat / gider / iade **iptali** (silmez, ters kayıt atar; borç yeniden açılır, kasa ve avans geri alınır), **avans iadesi** ve borca **mahsup**, borcun **taksitlendirilmesi** (kuruşu kuruşuna), **icra takibi** işareti, yazdırılabilir **makbuz**
- **Banka mutabakatı:** CSV ekstre içe aktarma (aynı ekstre ikinci kez yüklenmez), açıklamadan daire / üye adıyla otomatik eşleştirme, toplu tahsilata çevirme
- **Mali raporlar:** mizan, kasa defteri (yürüyen bakiye), mali durum tablosu (varlık / yükümlülük / net varlık), alacak yaşlandırma (0-30-60-90+ gün), yıllık bütçe–gerçekleşen karşılaştırması, gelir-gider tablosu, daire hesap ekstresi, yevmiye defteri
- **Personel bordrosu** (dönem başına tek maaş gideri, kasadan ödeme), **demirbaş kaydı** (doğrusal amortisman, net defter değeri), **sayaç faturası dağıtımı** (tüketime / m²'ye / eşit; paylar toplam tutara kuruşu kuruşuna eşit)
- **Operasyon:** görevler, destek talepleri, güvenlik & kargo (kargo/otopark/ziyaretçi), personel, stok, sayaçlar
- **Ortak alan rezervasyonu:** çalışma saati ve çakışma kontrolü, saatlik ücretin daire borcuna yazılması, onay akışı; mobilden de yapılır
- **İletişim:** duyurular, anketler (daire başına tek oy), karar defteri, bildirimler (isteğe bağlı e-posta ile), gecikmiş borç hatırlatıcı
- **Toplantılar:** genel kurul / yönetim kurulu, gündem, hazirun (katılım + vekil) ve **toplantı yeter sayısı** (sayı ve arsa payı çoğunluğu), kararlar toplantıya bağlanır
- **Belge arşivi:** sözleşme / fatura / tutanak yükleme (herkese açık olmayan depolama, yetkiyle indirme), geçerlilik tarihi uyarısı
- **Yetkiler:** site bazında rol — yönetici (tam), muhasebeci (yalnızca finans kayıtları), izleyici (salt okunur)
- **Üye geçmişi:** daireden ayrılan / devredilen sakinler silinmez, geçmiş listesinde tutulur
- **Raporlar:** sakin listesi, hazirun cetveli, bakiye listesi, detaylı borç, vadesi geçmiş borçlar, tahsilat makbuzları — yazdır/PDF ve Excel (CSV)
- **Kullanıcılar** ve **Emlak portföyü** (görselli ilanlar)

## Güvenlik modeli

- Panel yalnızca `role = admin` kullanıcılara açıktır. Rolü yalnızca süper admin değiştirebilir.
- Siteye ait her adres `/sites/{site}/...` altındadır: kullanıcı o siteyi yönetmiyorsa 403, kayıt başka siteye aitse 404 döner.
- Normal admin yalnızca kendi sitelerinin sakinlerini görür; süper admin hepsini görür.
- Muhasebe işlemleri tek transaction içinde ve satır kilitli yapılır; tutarlar kuruş (tam sayı) üzerinden hesaplanır. Kasa hareketleri ve yevmiye yalnızca eklenir; düzeltme ters kayıtla yapılır.
- Sakin erişimi doğrudan üyelik tablosundan hesaplanır: üye silinince veya bağlantı kaldırılınca erişim anında biter.
- Davet kodu deneme sınırı sunucuda tutulur (kullanıcı başına saatte 10, IP başına 30).

## Mobil API (Sanctum)

`Authorization: Bearer <token>` ile:

| Uç | Açıklama |
| --- | --- |
| `POST /api/register`, `POST /api/login` | `{name?, email, password, device_name}` → `{token, user}` |
| `GET /api/me` | Profil ve bağlı daireler |
| `POST /api/redeem-invite` | `{invite_code}` ile daireye bağlanma |
| `GET /api/sites/{id}/debts`, `/payments`, `/statement` | Sakinin kendi borç, ödeme ve ekstresi |
| `GET /api/sites/{id}/announcements`, `/notifications` | Duyurular ve bildirimler; `POST .../notifications/{n}/read` |
| `GET /api/sites/{id}/polls`, `POST .../polls/{p}/vote` | Anketler ve oy (`{option_id}`) |
| `GET/POST /api/sites/{id}/tickets` | Destek talepleri |
| `GET /api/sites/{id}/areas`, `/reservations`, `/meetings` | Ortak alanlar, rezervasyonlarım, toplantılar |
| `POST /api/sites/{id}/reservations`, `DELETE .../reservations/{r}` | Rezervasyon yap (`{common_area_id, date, start_time, end_time}`) / iptal et |

## Next.js sürümünden farklar

- Firestore verisi otomatik taşınmaz (Firebase erişimi olmadan dışa aktarılamaz); yeni kurulum boş veya demo verisiyle başlar.
- Mobil uygulama artık Firestore'a doğrudan değil, yukarıdaki REST API'ye bağlanmalıdır.
- "Aidat motoru" ve "otomatik borçlandırma kuralları" tek modülde birleştirildi.
- Arayüzde kullanılmayan eski `erp_*` koleksiyonları ve `apartment_members` modeli taşınmadı.
- PDF/Excel istemci kütüphaneleri yerine yazdır→PDF ve CSV kullanılır.
- Telefona anlık (push) bildirim için FCM gibi bir servis entegrasyonu ayrıca gerekir; bildirimler uygulama içinde saklanır.
