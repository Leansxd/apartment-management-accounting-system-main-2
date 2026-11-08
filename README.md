<div align="center">

# 🏢 Memoshome

### Site, Apartman ve Muhasebe Yönetim Sistemi

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)

**Aidat · Tahsilat · Kasa & Banka · Gider · Raporlar · Toplantı · Rezervasyon · Mobil API**

</div>

---

## 🗺️ Kurulum Yol Haritası

![1](https://img.shields.io/badge/1-Ara%C3%A7lar-0ea5e9?style=flat-square)
![2](https://img.shields.io/badge/2-MySQL-22c55e?style=flat-square)
![3](https://img.shields.io/badge/3-Proje-eab308?style=flat-square)
![4](https://img.shields.io/badge/4-Paketler-f97316?style=flat-square)
![5](https://img.shields.io/badge/5-Ayarlar-ef4444?style=flat-square)
![6](https://img.shields.io/badge/6-Veritaban%C4%B1-ec4899?style=flat-square)
![7](https://img.shields.io/badge/7-Demo_Veri-8b5cf6?style=flat-square)
![8](https://img.shields.io/badge/8-Ba%C5%9Flat-10b981?style=flat-square)

> [!NOTE]
> Bu rehber **Windows** içindir ve yaklaşık **15 dakika** sürer. Tüm komutları **PowerShell** penceresine sırayla yapıştırın.

> [!IMPORTANT]
> `kurulum.bat` dosyasını **kullanmayın**. O dosya şu an kapalı olan kurulum sihirbazını açmaya çalışır. Aşağıdaki adımları izleyin.

---

## 1️⃣ Gerekli araçları kurun

| Araç | Ne için | Nereden |
| --- | --- | --- |
| 🐘 **PHP 8.3+ ve Composer** | Uygulamayı çalıştırır | Aşağıdaki komut |
| 🟢 **Node.js 22 LTS** | Arayüzü derler | [nodejs.org](https://nodejs.org) |
| 🐬 **XAMPP** | MySQL veritabanı | [apachefriends.org](https://www.apachefriends.org) |
| 🔀 **Git** | Projeyi indirir | [git-scm.com](https://git-scm.com) |

PHP ve Composer'ı kurmak için PowerShell'e şunu yapıştırın:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows'))
```

Bittiğinde **PowerShell'i kapatıp yeniden açın**, sonra kontrol edin:

```powershell
php -v
```

```powershell
composer -V
```

```powershell
node -v
```

> [!WARNING]
> `php -v` çıktısı **8.3 veya üzeri** olmalıdır. XAMPP'in kendi PHP'si çoğu zaman daha eskidir ve bu proje onunla **çalışmaz**. Eski sürüm görürseniz [Sorun Giderme](#-sorun-giderme) bölümüne bakın.

---

## 2️⃣ MySQL'i başlatın

1. **XAMPP Control Panel** uygulamasını açın.
2. **MySQL** satırındaki **Start** düğmesine basın.
3. Satır **yeşil** olmalıdır. Apache'yi başlatmanız gerekmez.

---

## 3️⃣ Projeyi indirin

```powershell
git clone https://github.com/Leansxd/apartment-management-accounting-system-main-2.git
```

```powershell
cd apartment-management-accounting-system-main-2
```

> [!TIP]
> Depo **özeldir**, Git sizden GitHub girişi ister. Alternatif olarak GitHub'da **Code → Download ZIP** ile indirip klasöre çıkarabilir, PowerShell'de `cd` ile o klasöre girebilirsiniz.

---

## 4️⃣ Paketleri kurun ve arayüzü derleyin

```powershell
composer install
```

```powershell
npm install --ignore-scripts
```

```powershell
npm run build
```

---

## 5️⃣ Ayar dosyasını oluşturun

```powershell
copy .env.example .env
```

> [!IMPORTANT]
> Bu adımı atlamayın ve 7. adımdan **önce** yapın. Atlanırsa kurulum yanlış veritabanına yapılır.

Standart XAMPP'te `.env` dosyasını **değiştirmeniz gerekmez**. MySQL'inizin şifresi veya farklı bir portu varsa `.env` içindeki `DB_PASSWORD` ve `DB_PORT` satırlarını düzenleyin.

---

## 6️⃣ Veritabanını oluşturun

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE memoshome CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Komut hiçbir şey yazmadan bitiyorsa başarılıdır.

---

## 7️⃣ Tabloları ve demo verisini yükleyin

```powershell
php artisan app:setup --demo
```

Sonunda **`Kurulum tamamlandı.`** yazısını görmelisiniz. Hazır bir site, 12 daire, sakinler, borçlar ve tahsilatlar oluşur.

---

## 8️⃣ Sistemi başlatın 🚀

```powershell
php artisan serve
```

Tarayıcıda **http://localhost:8000** adresini açın. Giriş ekranındaki **Demo yönetici** düğmesine basmanız yeterlidir.

| 👤 Rol | 📧 E-posta | 🔑 Şifre |
| --- | --- | --- |
| Süper admin (panel) | `admin@demo.test` | `password` |
| Sakin (mobil API) | `sakin@demo.test` | `password` |

> [!CAUTION]
> Demo hesaplarının şifresi herkesçe bilinir. Bu verilerle sistemi **internete açmayın**.

---

## 🔁 Her gün açarken

1. **XAMPP Control Panel → MySQL → Start**
2. Proje klasöründe PowerShell açın ve çalıştırın:

```powershell
php artisan serve
```

PowerShell penceresi açık kaldığı sürece sistem çalışır. Kapatmak için `Ctrl + C` tuşlarına basın.

---

## 🎯 Sunumdan önce kontrol listesi

- [ ] XAMPP'te MySQL **yeşil**
- [ ] `php artisan serve` penceresi **açık**
- [ ] http://localhost:8000 açılıyor
- [ ] **Demo yönetici** ile giriş yapılıyor
- [ ] Panelde rakamlar ve grafikler görünüyor

---

## 🧰 Sorun Giderme

| 🔴 Görülen | 🟢 Çözüm |
| --- | --- |
| `php -v` eski sürüm gösteriyor | XAMPP'in PHP'si önde. PATH'ten `C:\xampp\php` satırını kaldırın veya sona alın, PowerShell'i yeniden açın. |
| `php` veya `composer` tanınmıyor | 1. adımdaki komuttan sonra PowerShell'i kapatıp yeniden açın. |
| `npm.ps1 cannot be loaded` | Önce `Set-ExecutionPolicy -Scope Process Bypass` çalıştırın, sonra `npm` komutunu tekrarlayın. |
| `SQLSTATE[HY000] [2002]` | MySQL çalışmıyor. XAMPP'te **Start** düğmesine basın. |
| `Unknown database 'memoshome'` | 6. adımı yapın. |
| `Access denied for user 'root'` | `.env` içindeki `DB_PASSWORD` satırına MySQL şifrenizi yazın. |
| `Demo verisi yalnızca boş veritabanına yüklenebilir` | Veritabanında kayıt var. Temizlemek için aşağıdaki sıfırlamayı uygulayın. |
| `Unable to locate file in Vite manifest` | `npm run build` çalıştırın. |
| Sayfa stilsiz veya boş görünüyor | `public` klasöründe `hot` dosyası varsa silin, sayfayı yenileyin. |
| 8000 portu dolu | `php artisan serve --port=8080` ile başlatıp http://localhost:8080 adresini açın. |
| XAMPP'te MySQL hemen kapanıyor | 3306 portunu başka program kullanıyor olabilir. XAMPP **Logs** düğmesinden hatayı okuyun. |

### Demo veriyi silip gerçek kullanıma geçmek

> [!CAUTION]
> Bu komut veritabanındaki **tüm kayıtları siler**.

```powershell
php artisan migrate:fresh
```

```powershell
php artisan app:setup
```

Komut, **Temiz kurulum** seçeneğiyle yönetici hesabınızı ve ilk sitenizi sorar.

---

## 🧪 Testler

```bash
php artisan test
```

Muhasebe kuralları (avans mahsubu, tahsilat dağıtımı, mükerrer tahakkuk engeli, eksi bakiye kontrolü, yevmiye dengesi), panel yetkileri (siteler arası erişim engeli) ve mobil API (davet kodu, sakin kapsamı, deneme sınırı, anket) test edilir.

## 📦 Modüller

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

## 🔐 Güvenlik modeli

- Panel yalnızca `role = admin` kullanıcılara açıktır. Rolü yalnızca süper admin değiştirebilir.
- Siteye ait her adres `/sites/{site}/...` altındadır: kullanıcı o siteyi yönetmiyorsa 403, kayıt başka siteye aitse 404 döner.
- Normal admin yalnızca kendi sitelerinin sakinlerini görür; süper admin hepsini görür.
- Muhasebe işlemleri tek transaction içinde ve satır kilitli yapılır; tutarlar kuruş (tam sayı) üzerinden hesaplanır. Kasa hareketleri ve yevmiye yalnızca eklenir; düzeltme ters kayıtla yapılır.
- Sakin erişimi doğrudan üyelik tablosundan hesaplanır: üye silinince veya bağlantı kaldırılınca erişim anında biter.
- Davet kodu deneme sınırı sunucuda tutulur (kullanıcı başına saatte 10, IP başına 30).

## 📱 Mobil API (Sanctum)

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

## 🔄 Next.js sürümünden farklar

- Firestore verisi otomatik taşınmaz (Firebase erişimi olmadan dışa aktarılamaz); yeni kurulum boş veya demo verisiyle başlar.
- Mobil uygulama artık Firestore'a doğrudan değil, yukarıdaki REST API'ye bağlanmalıdır.
- "Aidat motoru" ve "otomatik borçlandırma kuralları" tek modülde birleştirildi.
- Arayüzde kullanılmayan eski `erp_*` koleksiyonları ve `apartment_members` modeli taşınmadı.
- PDF/Excel istemci kütüphaneleri yerine yazdır→PDF ve CSV kullanılır.
- Telefona anlık (push) bildirim için FCM gibi bir servis entegrasyonu ayrıca gerekir; bildirimler uygulama içinde saklanır.
