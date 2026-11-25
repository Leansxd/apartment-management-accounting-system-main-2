<?php

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Debt;
use App\Models\DuesDefinition;
use App\Models\Member;
use App\Models\Property;
use App\Models\Staff;
use App\Models\Unit;
use App\Models\User;
use App\Services\BankImportService;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\MeterBillService;
use App\Services\PayrollService;
use App\Services\ReservationService;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Birbirinden farklı beş site ve 18 aylık geçmişle kapsamlı demo verisi üretir.
 * Saat geriye alınarak her işlem gerçek servislerden geçer: yevmiye, kasa ve denetim kayıtları tutarlı kalır.
 * Yalnızca boş veritabanına ve canlı olmayan ortama yüklenir. Kişi, telefon ve firma bilgileri kurgusaldır.
 *
 *   php artisan db:seed --class=RealisticDemoSeeder
 */
class RealisticDemoSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 17;

    private const FIRST_NAMES = ['Ahmet', 'Mehmet', 'Mustafa', 'Ali', 'Hasan', 'Hüseyin', 'İbrahim', 'Osman', 'Yusuf', 'Murat', 'Emre', 'Burak', 'Cem', 'Serkan', 'Tolga', 'Kerem', 'Deniz', 'Onur', 'Barış', 'Volkan', 'Ayşe', 'Fatma', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Merve', 'Selin', 'Derya', 'Gamze', 'Pınar', 'Esra', 'Sibel', 'Tuğba', 'Nazlı', 'Ceren', 'Aslı', 'Bahar', 'Gül', 'Leyla'];

    private const LAST_NAMES = ['Yılmaz', 'Kaya', 'Demir', 'Çelik', 'Şahin', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir', 'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Korkmaz', 'Erdoğan', 'Güneş', 'Aksoy', 'Tekin', 'Bulut', 'Karaca', 'Taş', 'Ünal', 'Avcı', 'Bozkurt', 'Sarı', 'Güler', 'Turan', 'Keskin'];

    private const COMPANY_TYPES = ['Hukuk Bürosu', 'Mimarlık', 'Yazılım A.Ş.', 'Mali Müşavirlik', 'Danışmanlık Ltd.', 'Reklam Ajansı', 'Sigorta Acentesi', 'Mühendislik Ltd.', 'Lojistik A.Ş.', 'Dış Ticaret Ltd.'];

    private const CARGO_COMPANIES = ['Yurtiçi Kargo', 'Aras Kargo', 'MNG Kargo', 'Sürat Kargo', 'PTT Kargo', 'Trendyol Express', 'Hepsijet'];

    /** Her sitenin karakterini belirleyen ayarlar */
    private const SITES = [
        [
            'code' => 'KORDON', 'name' => 'Kordon Park Evleri', 'city' => 'İzmir', 'plate' => '35', 'address' => 'Bostanlı Mah. Cemal Gürsel Cad. No:214 Karşıyaka / İzmir',
            'blocks' => ['A' => 12, 'B' => 12, 'C' => 12], 'kind' => 'residence', 'areas' => [85, 110, 135, 160], 'tenant_rate' => 0.35, 'vacant_rate' => 0.06,
            'dues' => [['name' => 'Aylık Aidat', 'calc' => 'fixed', 'amounts' => [2025 => 4800, 2026 => 6400], 'due_day' => 10]],
            'opening' => ['cash' => 45000, 'bank' => 320000, 'bank_name' => 'İş Bankası Karşıyaka Şubesi'], 'late_fee' => true, 'auto' => true, 'profiles' => ['punctual' => 58, 'late' => 22, 'partial' => 8, 'advance' => 6, 'chronic' => 6],
            'staff' => [['Ramazan Tuna', 'Güvenlik', 32500], ['Sevgi Altun', 'Temizlik', 28000], ['Hakan Uzun', 'Teknik', 36000], ['Yaşar Çiftçi', 'Bahçe', 29500]],
            'common_areas' => [['Açık yüzme havuzu', 0, '09:00', '20:00', 30, false], ['Tenis kortu', 250, '08:00', '22:00', 4, false], ['Toplantı salonu', 600, '10:00', '23:00', 40, true]],
            'gas_bill' => true, 'meter_types' => ['Doğalgaz', 'Su'],
            'expenses' => [['Temizlik', 'Ege Temizlik Hizmetleri Ltd.', 'Blok ve ortak alan temizliği', 18500, 20, 1.0, 1], ['Elektrik', null, 'Ortak alan elektrik faturası', 14200, 20, 1.15, 1], ['Su', null, 'Havuz ve bahçe su faturası', 8600, 10, 0.8, 1], ['Asansör Bakım', 'Gediz Asansör Servis A.Ş.', 'Asansör aylık bakım sözleşmesi', 9200, 20, 1.0, 1], ['Bahçe ve Peyzaj', 'Yeşil Körfez Peyzaj', 'Peyzaj ve ilaçlama', 7400, 20, 0.7, 2], ['Havuz Bakımı', 'Mavi Havuz Teknik', 'Havuz kimyasalı ve filtre bakımı', 11800, 20, 0.6, 1], ['Sigorta', 'Körfez Sigorta Acentesi', 'Ortak alan ve deprem sigortası primi', 92000, 0, 1.0, 12]],
            'tickets' => ['C blok çatı katında su sızıntısı', 'Havuz duşlarında sıcak su yok', 'Tenis kortu filesi yırtılmış', 'Kapalı otoparkta su birikintisi', 'Çocuk parkı salıncak zinciri kopuk'],
            'announcements' => [['Havuz sezonu açılıyor', 'Açık yüzme havuzumuz 1 Haziran itibarıyla 09:00-20:00 saatleri arasında hizmete açılacaktır. Havuz kurallarına uyulması rica olunur.', 'normal'], ['Doğalgaz kesintisi', 'İZGAZ çalışması nedeniyle salı günü 09:00-13:00 saatleri arasında doğalgaz kesintisi yaşanacaktır.', 'high'], ['Kapalı otopark boyama çalışması', 'Otopark zemin boyası nedeniyle B bölümü üç gün kullanıma kapalı olacaktır.', 'normal']],
            'polls' => [['Havuz açılış saati 08:00 olsun mu?', ['Evet', 'Hayır, 09:00 kalsın']], ['Blok girişlerine yüz tanıma sistemi kurulsun mu?', ['Kurulsun', 'Kartlı sistem yeterli', 'Fikrim yok']], ['Kış bahçesi kafeterya olarak işletilsin mi?', ['Evet', 'Hayır']]],
            'decisions' => [['Aidat tutarının güncellenmesi', 'Artan enerji ve personel giderleri nedeniyle aylık aidatın 6.400 TL olarak uygulanmasına oy çokluğuyla karar verildi.'], ['Isı pay ölçer sisteminin yenilenmesi', 'Ömrünü tamamlayan pay ölçerlerin tüm bloklarda değiştirilmesine ve bedelin arsa payına göre dağıtılmasına karar verildi.'], ['Havuz filtre sisteminin değişimi', 'Havuz filtre grubunun Mavi Havuz Teknik firmasından alınan teklif doğrultusunda yenilenmesine karar verildi.']],
            'assets' => [['Doğalgaz kazanı 400 kW', 'Diğer', 28, 620000, 15, 'Kazan dairesi'], ['Hidrofor grubu', 'Diğer', 22, 145000, 10, 'Teknik oda'], ['Asansör A Blok', 'Asansör', 60, 780000, 25, 'A blok'], ['Asansör B Blok', 'Asansör', 60, 780000, 25, 'B blok'], ['Asansör C Blok', 'Asansör', 14, 910000, 25, 'C blok'], ['Jeneratör 150 kVA', 'Jeneratör', 40, 540000, 15, 'Otopark -1'], ['Havuz pompa ve filtre grubu', 'Havuz', 9, 210000, 8, 'Havuz teknik odası'], ['IP kamera sistemi 32 kanal', 'Güvenlik Sistemi', 16, 118000, 6, 'Güvenlik kulübesi']],
        ],
        [
            'code' => 'CAMLI', 'name' => 'Çamlıtepe Villaları', 'city' => 'Ankara', 'plate' => '06', 'address' => 'Çamlıtepe Mah. Meşe Sok. Çankaya / Ankara',
            'blocks' => ['V' => 18], 'kind' => 'villa', 'areas' => [240, 285, 320, 410], 'tenant_rate' => 0.1, 'vacant_rate' => 0.0,
            'dues' => [['name' => 'Metrekare Aidatı', 'calc' => 'sqm', 'amounts' => [2025 => 36, 2026 => 46], 'due_day' => 15], ['name' => 'Bahçe Bakım Payı', 'calc' => 'fixed', 'amounts' => [2025 => 650, 2026 => 900], 'due_day' => 15]],
            'opening' => ['cash' => 20000, 'bank' => 510000, 'bank_name' => 'Garanti BBVA Çankaya Şubesi'], 'late_fee' => false, 'auto' => false, 'profiles' => ['punctual' => 80, 'late' => 8, 'partial' => 0, 'advance' => 12, 'chronic' => 0],
            'staff' => [['Nihat Ergin', 'Güvenlik', 38000], ['Cengiz Toprak', 'Güvenlik', 38000], ['Adem Yeşil', 'Bahçe', 34000], ['Ferhat Duman', 'Teknik', 41000]],
            'common_areas' => [['Sosyal tesis', 900, '10:00', '23:00', 80, true], ['Kapalı spor salonu', 0, '07:00', '22:00', 15, false], ['Basketbol sahası', 150, '09:00', '21:00', 12, false]],
            'gas_bill' => false, 'meter_types' => ['Su'],
            'expenses' => [['Bahçe ve Peyzaj', 'Başkent Peyzaj Mimarlık', 'Ortak alan peyzaj ve çim bakımı', 32000, 20, 0.5, 1], ['Güvenlik', 'Kale Güvenlik Sistemleri', 'Çevre güvenlik ve bariyer bakımı', 12500, 20, 1.0, 1], ['Elektrik', null, 'Çevre aydınlatma elektrik faturası', 16800, 20, 1.2, 1], ['Su', null, 'Sulama suyu faturası', 21000, 10, 0.25, 1], ['Bakım Onarım', 'Anıl Yapı Tamirat', 'Yol, kaldırım ve çevre duvarı onarımı', 26000, 20, 0.7, 3], ['Sigorta', 'Başkent Sigorta', 'Sosyal tesis ve mesuliyet sigortası', 68000, 0, 1.0, 12]],
            'tickets' => ['Villa önü kaldırım taşı oynuyor', 'Ana giriş bariyeri geç açılıyor', 'Sulama hattı patlağı', 'Sokak lambası gece yanmıyor', 'Sosyal tesis saunası ısınmıyor'],
            'announcements' => [['İlaçlama çalışması', 'Bahçelerde kene ve haşere ilaçlaması cumartesi sabahı yapılacaktır. Evcil hayvanlarınızı içeride tutunuz.', 'high'], ['Kar küreme planı', 'Kar yağışında ana yol öncelikli olarak temizlenecek, ara yollar sonrasında açılacaktır.', 'normal'], ['Misafir araç kaydı', 'Misafir araçlarının giriş öncesi güvenliğe plaka ile bildirilmesi zorunludur.', 'normal']],
            'polls' => [['Site girişine ikinci güvenlik noktası eklensin mi?', ['Evet', 'Hayır']], ['Yaz etkinliği için tercih', ['Açık hava sineması', 'Bahçe konseri', 'Barbekü günü']]],
            'decisions' => [['Çevre duvarı güçlendirmesi', 'Kuzey cephe çevre duvarının güçlendirilmesine ve bedelin ek aidat olarak toplanmasına oy birliğiyle karar verildi.'], ['Peyzaj sözleşmesinin yenilenmesi', 'Başkent Peyzaj Mimarlık ile sözleşmenin bir yıl uzatılmasına karar verildi.']],
            'assets' => [['Plaka tanıma bariyer sistemi', 'Güvenlik Sistemi', 18, 165000, 7, 'Ana giriş'], ['Çim biçme traktörü', 'Bahçe Ekipmanı', 30, 240000, 10, 'Bahçe deposu'], ['Jeneratör 80 kVA', 'Jeneratör', 52, 310000, 15, 'Sosyal tesis'], ['Spor salonu ekipmanları', 'Diğer', 12, 280000, 6, 'Kapalı spor salonu'], ['Kar küreme ataşmanı', 'Bahçe Ekipmanı', 20, 58000, 8, 'Bahçe deposu']],
        ],
        [
            'code' => 'BOGAZ', 'name' => 'Boğaz İş Merkezi', 'city' => 'İstanbul', 'plate' => '34', 'address' => 'Levent Mah. Büyükdere Cad. No:98 Beşiktaş / İstanbul',
            'blocks' => ['Kule' => 24, 'Ek' => 8], 'kind' => 'office', 'areas' => [65, 95, 140, 220], 'tenant_rate' => 0.7, 'vacant_rate' => 0.13,
            'dues' => [['name' => 'İşletme Payı', 'calc' => 'sqm', 'amounts' => [2025 => 92, 2026 => 118], 'due_day' => 5]],
            'opening' => ['cash' => 15000, 'bank' => 880000, 'bank_name' => 'Yapı Kredi Levent Ticari'], 'late_fee' => true, 'auto' => false, 'profiles' => ['punctual' => 62, 'late' => 28, 'partial' => 4, 'advance' => 0, 'chronic' => 6],
            'staff' => [['Sedat Karakuş', 'Güvenlik', 40000], ['Bekir Ateş', 'Güvenlik', 40000], ['Songül Ay', 'Temizlik', 31000], ['Levent Şen', 'Teknik', 52000], ['Dilara Soylu', 'Yönetim', 58000]],
            'common_areas' => [['Konferans salonu', 1500, '08:00', '20:00', 60, true], ['Toplantı odası 12. kat', 450, '08:00', '20:00', 12, false]],
            'gas_bill' => false, 'meter_types' => ['Elektrik'],
            'expenses' => [['Temizlik', 'Boğaziçi Tesis Yönetimi', 'Ofis koridor ve cephe temizliği', 42000, 20, 1.0, 1], ['Elektrik', null, 'Ortak alan ve klima santrali elektriği', 58000, 20, 1.25, 1], ['Asansör Bakım', 'Marmara Asansör A.Ş.', 'Altı asansör aylık bakım', 26500, 20, 1.0, 1], ['Güvenlik', 'Boğaz Güvenlik Teknolojileri', 'Turnike ve kamera bakım sözleşmesi', 18000, 20, 1.0, 1], ['Bakım Onarım', 'Levent Mekanik Ltd.', 'Klima santrali filtre ve mekanik bakım', 34000, 20, 1.4, 2], ['Sigorta', 'Marmara Sigorta', 'Bina tam kapsamlı sigorta primi', 245000, 0, 1.0, 12]],
            'tickets' => ['12. kat klima soğutmuyor', 'Turnike kartım okumuyor', 'Kule asansörü 2 uzun süre bekletiyor', 'Otoparkta ayrılmış yerime araç park edilmiş', 'Ortak WC kağıt havlu bitmiş', 'Toplantı odası projektörü arızalı'],
            'announcements' => [['Yangın tatbikatı', 'Perşembe 14:00 yangın tatbikatı yapılacaktır. Tüm kullanıcıların tahliye planına uyması gerekmektedir.', 'high'], ['Hafta sonu çalışma izni', 'Hafta sonu binaya giriş için cuma 16:00\'ya kadar yönetime bildirim yapılmalıdır.', 'normal'], ['Elektrik bakım kesintisi', 'Pazar 08:00-12:00 arası ana pano bakımı nedeniyle enerji kesintisi planlanmıştır.', 'high']],
            'polls' => [['Lobiye kahve noktası açılsın mı?', ['Evet', 'Hayır']], ['Otopark giriş saati uzatılsın mı?', ['23:00\'e kadar', 'Mevcut kalsın', '24 saat açık']]],
            'decisions' => [['Klima santrali yenileme bütçesi', 'Kule klima santrallerinin iki etapta yenilenmesi için bütçe ayrılmasına karar verildi.'], ['Yönetim sözleşmesi', 'Bina yönetim ofisi personel yapısının korunmasına oy birliğiyle karar verildi.']],
            'assets' => [['Klima santrali K1', 'Diğer', 58, 1250000, 15, 'Teras'], ['Klima santrali K2', 'Diğer', 6, 1480000, 15, 'Teras'], ['Turnike geçiş sistemi', 'Güvenlik Sistemi', 26, 320000, 8, 'Lobi'], ['Jeneratör 500 kVA', 'Jeneratör', 66, 2150000, 20, 'Bodrum -3'], ['Ofis mobilyaları yönetim', 'Ofis', 34, 96000, 5, 'Yönetim ofisi'], ['Eski yangın panosu', 'Güvenlik Sistemi', 100, 74000, 8, 'Lobi']],
        ],
        [
            'code' => 'LARA', 'name' => 'Lara Marin Rezidans', 'city' => 'Antalya', 'plate' => '07', 'address' => 'Güzeloba Mah. Lara Cad. No:407 Muratpaşa / Antalya',
            'blocks' => ['Marin' => 22, 'Palmiye' => 18], 'kind' => 'residence', 'areas' => [55, 78, 105, 150], 'tenant_rate' => 0.5, 'vacant_rate' => 0.1,
            'dues' => [['name' => 'Aylık Aidat', 'calc' => 'fixed', 'amounts' => [2025 => 6500, 2026 => 8400], 'due_day' => 1], ['name' => 'Havuz ve Spa Payı', 'calc' => 'fixed', 'amounts' => [2025 => 750, 2026 => 1000], 'due_day' => 1]],
            'opening' => ['cash' => 30000, 'bank' => 640000, 'bank_name' => 'Akbank Lara Şubesi'], 'late_fee' => true, 'auto' => true, 'profiles' => ['punctual' => 50, 'late' => 24, 'partial' => 6, 'advance' => 16, 'chronic' => 4],
            'staff' => [['Ümit Sevinç', 'Güvenlik', 33000], ['Oğuz Tan', 'Güvenlik', 33000], ['Melek Durmaz', 'Temizlik', 29000], ['Gülay Eren', 'Temizlik', 29000], ['Sinan Akın', 'Teknik', 39000], ['Ferdi Yurt', 'Bahçe', 30000]],
            'common_areas' => [['Kapalı havuz ve spa', 0, '08:00', '22:00', 20, false], ['Fitness salonu', 0, '06:00', '23:00', 18, false], ['Barbekü alanı', 300, '12:00', '23:00', 16, true], ['Çatı terası', 1200, '17:00', '23:30', 50, true]],
            'gas_bill' => false, 'meter_types' => ['Su', 'Isınma'],
            'expenses' => [['Temizlik', 'Akdeniz Tesis Temizlik', 'Rezidans genel temizlik hizmeti', 29500, 20, 1.1, 1], ['Elektrik', null, 'Ortak alan, spa ve havuz elektriği', 37500, 20, 1.3, 1], ['Su', null, 'Havuz, sulama ve ortak su faturası', 22000, 10, 1.5, 1], ['Havuz Bakımı', 'Antalya Havuz Kimya', 'Kapalı ve açık havuz bakımı', 19000, 20, 1.3, 1], ['Asansör Bakım', 'Toroslar Asansör', 'Dört asansör aylık bakım', 15600, 20, 1.0, 1], ['Bahçe ve Peyzaj', 'Palmiye Peyzaj', 'Palmiye budama ve sulama bakımı', 13500, 20, 1.4, 2], ['Sigorta', 'Akdeniz Sigorta', 'Ortak alan sigorta primi', 124000, 0, 1.0, 12]],
            'tickets' => ['Spa saunası çalışmıyor', 'Havuz klor kokusu yoğun', 'Çatı terası aydınlatması arızalı', 'Klima dış ünitesi gürültü yapıyor', 'Fitness koşu bandı bozuk', 'Kısa dönem kiracı gürültü şikayeti'],
            'announcements' => [['Kısa dönem kiralama uyarısı', 'Yönetim planı gereği günlük kiralama yasaktır. Tespit edilen dairelere yaptırım uygulanacaktır.', 'high'], ['Palmiye budama', 'Çarşamba günü site içi palmiye budaması nedeniyle araç yolu kısmen kapalı olacaktır.', 'normal'], ['Spa bakım takvimi', 'Spa alanı her ayın ilk pazartesi günü bakım nedeniyle kapalıdır.', 'normal']],
            'polls' => [['Spa çalışma saatleri uzatılsın mı?', ['Evet, 23:00\'e', 'Mevcut kalsın']], ['Plaj servisi başlatılsın mı?', ['Evet, ek ücretle', 'Evet, aidata dahil', 'Hayır']], ['Evcil hayvan parkı yapılsın mı?', ['Evet', 'Hayır', 'Çekimserim']]],
            'decisions' => [['Günlük kiralama yasağı yaptırımı', 'Yönetim planına aykırı günlük kiralama yapan bağımsız bölümlere ihtar gönderilmesine karar verildi.'], ['Spa ekipman yenileme', 'Sauna ve buhar odası ekipmanlarının yenilenmesi için ek bütçe ayrılmasına karar verildi.'], ['Aidat güncellemesi', 'Aylık aidatın 8.400 TL olarak uygulanmasına oy çokluğuyla karar verildi.']],
            'assets' => [['Spa ve sauna ekipmanı', 'Havuz', 24, 460000, 8, 'Spa'], ['Havuz ısı pompası', 'Havuz', 13, 335000, 10, 'Teknik oda'], ['Asansör Marin', 'Asansör', 72, 860000, 25, 'Marin blok'], ['Asansör Palmiye', 'Asansör', 72, 860000, 25, 'Palmiye blok'], ['Fitness ekipmanları', 'Diğer', 19, 390000, 6, 'Fitness salonu'], ['Jeneratör 200 kVA', 'Jeneratör', 48, 720000, 15, 'Otopark'], ['Peyzaj sulama otomasyonu', 'Bahçe Ekipmanı', 11, 88000, 7, 'Bahçe']],
        ],
        [
            'code' => 'YVADI', 'name' => 'Yeşilvadi Apartmanı', 'city' => 'Bursa', 'plate' => '16', 'address' => 'Ertuğrul Mah. Çınar Sok. No:7 Nilüfer / Bursa',
            'blocks' => ['A' => 10], 'kind' => 'small', 'areas' => [90, 105], 'tenant_rate' => 0.4, 'vacant_rate' => 0.1,
            'dues' => [['name' => 'Aylık Aidat', 'calc' => 'fixed', 'amounts' => [2025 => 850, 2026 => 1200], 'due_day' => 20]],
            'opening' => ['cash' => 24000, 'bank' => null, 'bank_name' => null], 'late_fee' => false, 'auto' => false, 'profiles' => ['punctual' => 40, 'late' => 30, 'partial' => 10, 'advance' => 0, 'chronic' => 20],
            'staff' => [],
            'common_areas' => [],
            'gas_bill' => false, 'meter_types' => [],
            'expenses' => [['Temizlik', null, 'Merdiven temizliği', 2200, 0, 1.0, 1], ['Elektrik', null, 'Bina ortak elektrik faturası', 780, 20, 1.2, 1], ['Bakım Onarım', 'Usta Hilmi Tesisat', 'Tesisat ve küçük onarımlar', 3400, 0, 1.0, 3], ['Asansör Bakım', 'Uludağ Asansör', 'Asansör aylık bakımı', 1700, 20, 1.0, 1]],
            'tickets' => ['Bina giriş kapısı otomatiği bozuk', 'Merdiven lambası yanmıyor', 'Bodrumda rutubet kokusu'],
            'announcements' => [['Aidat ödemeleri', 'Aidatlar her ayın 20\'sine kadar yöneticiye elden veya kasaya ödenebilir.', 'normal'], ['Çatı tamiri', 'Çatı aktarma çalışması önümüzdeki hafta başlayacaktır.', 'normal']],
            'polls' => [['Bina dış cephe boyansın mı?', ['Evet, bu yıl', 'Gelecek yıl', 'Hayır']]],
            'decisions' => [['Çatı aktarması ek ödemesi', 'Çatı aktarma bedelinin daire başına eşit olarak toplanmasına karar verildi.']],
            'assets' => [['Asansör motoru', 'Asansör', 90, 120000, 20, 'Makine dairesi']],
        ],
    ];

    private const COMMON_TICKETS = [
        ['Teknik', 'Kat koridor lambası yanmıyor'], ['Teknik', 'Asansör katta tam hizalanmıyor'], ['Teknik', 'Daire içine su sızıntısı'], ['Teknik', 'Otopark kapı kumandası çalışmıyor'],
        ['Temizlik', 'Merdiven temizliği yetersiz'], ['Temizlik', 'Çöp konteyneri taşmış'], ['Temizlik', 'Otopark zemini kirli'], ['Güvenlik', 'Giriş kapısı açık kalıyor'],
        ['Güvenlik', 'Kamera görüntüsü talebi'], ['Güvenlik', 'Yabancı araç park etmiş'], ['Bahçe', 'Çim sulama yürüyüş yoluna geliyor'], ['Bahçe', 'Ağaç dalları balkona değiyor'],
        ['Genel', 'Aidat borcu dökümü talebi'], ['Genel', 'Komşu gürültü şikayeti'], ['Genel', 'Yeni anahtar ve kart talebi'],
    ];

    private const TASKS = [
        ['Yangın tüpleri dolum tarihi kontrolü', 'security', 'high'], ['Sığınak havalandırma kontrolü', 'maintenance', 'medium'], ['Su deposu temizliği ve dezenfeksiyon', 'maintenance', 'high'], ['Çatı oluk temizliği', 'maintenance', 'medium'],
        ['Kamera kayıt cihazı disk kontrolü', 'security', 'medium'], ['Blok giriş camlarının silinmesi', 'cleaning', 'low'], ['Otopark derin temizlik', 'cleaning', 'medium'], ['Pano ve sigorta bakımı', 'maintenance', 'high'],
        ['Hidrofor basınç ayarı', 'maintenance', 'medium'], ['Bariyer kumanda kayıt güncellemesi', 'security', 'low'], ['Duyuru panolarının güncellenmesi', 'other', 'low'], ['Kış öncesi tesisat izolasyon kontrolü', 'maintenance', 'high'],
        ['Çöp odası ilaçlama', 'cleaning', 'medium'], ['Sayaç endekslerinin okunması', 'other', 'medium'],
    ];

    private const INVENTORY = [
        ['Endüstriyel çöp poşeti', 'Temizlik', 'rulo', 8], ['Yüzey temizleyici 5 L', 'Temizlik', 'bidon', 4], ['Yer paspası başlığı', 'Temizlik', 'adet', 6], ['Sıvı sabun 5 L', 'Temizlik', 'bidon', 3],
        ['LED ampul 12 W', 'Teknik', 'adet', 15], ['Sigorta 16 A', 'Teknik', 'adet', 10], ['Kablo 3x2,5', 'Teknik', 'metre', 30], ['Silikon', 'Teknik', 'adet', 5], ['Yangın tüpü 6 kg', 'Teknik', 'adet', 4],
        ['Çim tohumu', 'Bahçe', 'kg', 10], ['Gübre 25 kg', 'Bahçe', 'çuval', 2], ['Budama makası', 'Bahçe', 'adet', 2], ['A4 kağıt', 'Ofis', 'paket', 5], ['Makbuz koçanı', 'Ofis', 'adet', 3], ['Yazıcı toneri', 'Ofis', 'adet', 2],
    ];

    private const DOCUMENTS = [
        ['Site yönetim planı', 'Yönetim Planı', null], ['Asansör bakım sözleşmesi', 'Sözleşme', 45], ['Temizlik hizmet sözleşmesi', 'Sözleşme', 190], ['Bina sigorta poliçesi', 'Sigorta', 18], ['Deprem sigortası (DASK) poliçesi', 'Sigorta', -12],
        ['Genel kurul tutanağı', 'Karar / Tutanak', null], ['Asansör yıllık muayene raporu', 'Teknik', 120], ['Yangın algılama test raporu', 'Teknik', 60], ['Elektrik tesisatı periyodik kontrol raporu', 'Teknik', 240], ['Ortak alan elektrik faturası', 'Fatura', null],
    ];

    private FinanceService $finance;

    private ReservationService $reservations;

    private string $passwordHash;

    private User $admin;

    private CarbonImmutable $realNow;

    /** @var list<User> mobil uygulamaya bağlı sakin hesapları */
    private array $residentUsers = [];

    private int $accountNo = 0;

    public function run(FinanceService $finance, ReservationService $reservations): void
    {
        if (app()->isProduction() || User::exists()) {
            throw new RuntimeException('Gerçekçi demo verisi yalnızca canlı olmayan ortamda, boş veritabanına yüklenir.');
        }

        $this->finance = $finance;
        $this->reservations = $reservations;
        $this->realNow = CarbonImmutable::now();
        $this->passwordHash = Hash::make('password');
        mt_srand(20261110);

        try {
            $this->at($this->realNow->startOfMonth()->subMonths(self::MONTHS_OF_HISTORY + 1));
            $this->admin = $this->makeUser('Demo Yönetici', 'admin@demo.test', 'admin', true);
            $this->makeUser('Ayşe Yılmaz', 'sakin@demo.test', 'user');

            foreach (self::SITES as $site) {
                $this->command?->info("{$site['name']} hazırlanıyor...");
                $this->seedSite($site);
            }

            $this->at($this->realNow);
            $this->seedProperties();
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    // ------------------------------------------------------------------ SİTE

    private function seedSite(array $site): void
    {
        $start = $this->realNow->startOfMonth()->subMonths(self::MONTHS_OF_HISTORY);
        $this->at($start->subDays(3)->setTime(10, 0));

        $apartment = Apartment::provision(['name' => $site['name'], 'code' => $site['code'], 'city' => $site['city'], 'address' => $site['address'], 'blocks' => implode(',', array_keys($site['blocks']))], $this->admin);
        $apartment->update(['late_fee_enabled' => $site['late_fee'], 'late_fee_rate' => $site['late_fee'] ? 5 : 0, 'late_fee_grace_days' => $site['late_fee'] ? 5 : 0, 'dues_auto_run' => $site['auto'], 'late_fee_auto_run' => $site['auto'], 'tax_number' => (string) $this->between(1000000000, 9999999999)]);
        $this->addManagers($apartment, $site);

        $cash = $apartment->cashboxes()->first();
        $this->finance->recordOpeningBalance($cash, $site['opening']['cash'] * 100, $this->admin);
        $bank = null;
        if ($site['opening']['bank']) {
            $bank = $apartment->cashboxes()->create(['name' => $site['opening']['bank_name'], 'type' => 'bank', 'account_number' => 'TR'.$this->digits(2).' '.$this->digits(4).' '.$this->digits(4).' '.$this->digits(4).' '.$this->digits(4).' '.$this->digits(4).' '.$this->digits(2)]);
            $this->finance->recordOpeningBalance($bank, $site['opening']['bank'] * 100, $this->admin);
        }

        $profiles = $this->seedUnits($apartment, $site, $start);
        $units = $apartment->units()->with('block')->orderBy('id')->get();
        $definitions = $this->seedDues($apartment, $site);
        $vendors = $this->seedVendors($apartment, $site);
        $staff = $this->seedStaff($apartment, $site, $start);
        $areas = $this->seedCommonAreas($apartment, $site);
        $meters = $this->seedMeters($apartment, $site, $units);
        $this->seedBudget($apartment, $site);
        $this->seedAssets($apartment, $site, $vendors);
        $this->seedInventory($apartment, $site);

        for ($back = self::MONTHS_OF_HISTORY; $back >= 0; $back--) {
            $month = $this->realNow->startOfMonth()->subMonths($back);
            $this->seedMonth($apartment, $site, $month, $back, $units, $profiles, $definitions, $vendors, $staff, $areas, $meters, $cash, $bank ?? $cash);
        }

        $this->seedCorrections($apartment, $units, $profiles, $cash, $bank ?? $cash);
        $this->seedGovernance($apartment, $site, $units);
        $this->seedCommunication($apartment, $site, $units);
        $this->seedDocuments($apartment);
        if ($bank) {
            $this->seedBankLines($apartment, $bank, $units);
        }
    }

    private function addManagers(Apartment $apartment, array $site): void
    {
        foreach (['manager' => 'yonetici', 'accountant' => 'muhasebe', 'viewer' => 'denetci'] as $role => $slug) {
            if ($site['kind'] === 'small' && $role !== 'manager') {
                continue;
            }
            $user = $this->makeUser($this->personName(), $slug.'.'.mb_strtolower($site['code']).'@demo.test', 'admin');
            $apartment->managers()->attach($user, ['role' => $role]);
        }
    }

    /** @return array<int, string> daire id => ödeme alışkanlığı */
    private function seedUnits(Apartment $apartment, array $site, CarbonImmutable $start): array
    {
        $profiles = [];
        $blockSizes = array_values($site['blocks']);
        foreach ($apartment->blocks()->orderBy('id')->get() as $blockIndex => $block) {
            for ($no = 1; $no <= $blockSizes[$blockIndex]; $no++) {
                $usage = match ($site['kind']) {
                    'villa' => 'Konut',
                    'office' => $blockIndex === 1 && $no > 5 ? 'Depo' : 'İşyeri',
                    default => $no === 1 && $site['kind'] === 'residence' && $this->chance(0.5) ? 'İşyeri' : 'Konut',
                };
                $isVacant = $this->chance($site['vacant_rate']);
                $unit = $apartment->units()->create([
                    'block_id' => $block->id, 'unit_no' => (string) $no, 'floor' => $site['kind'] === 'villa' ? null : (string) (intdiv($no - 1, $site['kind'] === 'office' ? 2 : 4) + 1),
                    'usage_type' => $usage, 'status' => $isVacant ? 'Boş' : 'Dolu', 'gross_area' => $this->pick($site['areas']),
                    'land_share' => round(1 / array_sum($site['blocks']), 4),
                ]);
                $profiles[$unit->id] = $this->weighted($site['profiles']);
                $this->addMember($apartment, $unit->load('block'), $site, 'owner', $start);
                if (! $isVacant && $this->chance($site['tenant_rate'])) {
                    $this->addMember($apartment, $unit, $site, 'tenant', $start);
                }
                if ($this->chance(0.12)) {
                    $this->addFormerMember($apartment, $unit, $site, $start);
                }
            }
        }

        $apartment->unitGroups()->create(['name' => 'Yönetim kurulu üyeleri', 'details' => 'Kurul üyelerinin bağımsız bölümleri'])
            ->units()->sync($apartment->units()->inRandomOrder(mt_rand())->limit(min(5, $apartment->units()->count()))->pluck('id'));
        $apartment->unitGroups()->create(['name' => 'Geniş metrekareli bölümler', 'details' => 'Yakıt ve sulama paylaşımı için'])
            ->units()->sync($apartment->units()->where('gross_area', '>=', max($site['areas']))->pluck('id'));

        return $profiles;
    }

    private function addMember(Apartment $apartment, Unit $unit, array $site, string $type, CarbonImmutable $start): void
    {
        $isCompany = $site['kind'] === 'office' && ($type === 'tenant' || $this->chance(0.4));
        $name = $isCompany ? $this->pick(self::LAST_NAMES).' '.$this->pick(self::COMPANY_TYPES) : $this->personName();
        $member = $apartment->members()->create([
            'unit_id' => $unit->id, 'type' => $type, 'full_name' => $name, 'phone' => $this->chance(0.95) ? $this->phone() : null,
            'email' => $this->chance(0.7) ? $this->email($name) : null, 'emergency_phone' => $this->chance(0.3) ? $this->phone() : null,
            'moved_in_on' => $start->subDays($this->between(30, 1800))->toDateString(),
            'invite_code' => Member::generateInviteCode($apartment->code, $unit->label()),
        ]);

        if ($site['code'] === 'KORDON' && $unit->label() === 'A-1' && $type === 'owner') {
            $resident = User::where('email', 'sakin@demo.test')->first();
            $member->update(['user_id' => $resident->id, 'activated_at' => $start->addMonths(2)]);
            $this->residentUsers[] = $resident;
        } elseif (! $isCompany && $this->chance(0.25)) {
            $user = $this->makeUser($name, 'sakin'.(++$this->accountNo).'@demo.test', 'user');
            $user->forceFill(['last_login_at' => $this->realNow->subDays($this->between(0, 120))])->save();
            $member->update(['user_id' => $user->id, 'activated_at' => $start->addDays($this->between(5, 300))]);
            $this->residentUsers[] = $user;
        }
    }

    private function addFormerMember(Apartment $apartment, Unit $unit, array $site, CarbonImmutable $start): void
    {
        $name = $this->personName();
        $former = $apartment->members()->create([
            'unit_id' => $unit->id, 'type' => 'tenant', 'full_name' => $name, 'phone' => $this->phone(),
            'moved_in_on' => $start->subMonths($this->between(6, 30))->toDateString(),
            'moved_out_on' => $start->addMonths($this->between(1, self::MONTHS_OF_HISTORY - 1))->toDateString(),
            'move_out_reason' => $this->pick(['Sözleşme bitimi', 'Şehir değişikliği', 'Ev satın aldı', 'İş yeri değişikliği', 'Daire satıldı']),
        ]);
        $former->delete();
    }

    /** @return list<array{model: DuesDefinition, amounts: array<int, int>}> */
    private function seedDues(Apartment $apartment, array $site): array
    {
        $category = $apartment->categories()->where('name', 'Aidat')->first();

        return array_map(fn (array $dues) => ['amounts' => $dues['amounts'], 'model' => $apartment->duesDefinitions()->create([
            'name' => $dues['name'], 'amount' => $dues['amounts'][2025], 'calculation' => $dues['calc'], 'category_id' => $category->id, 'due_day' => $dues['due_day'],
        ])], $site['dues']);
    }

    /** @return array<string, int> firma adı => id */
    private function seedVendors(Apartment $apartment, array $site): array
    {
        $vendors = [];
        foreach ($site['expenses'] as [, $vendorName]) {
            if ($vendorName && ! isset($vendors[$vendorName])) {
                $vendors[$vendorName] = $apartment->vendors()->create(['name' => $vendorName, 'tax_number' => $this->digits(10), 'tax_office' => $site['city'].' VD', 'phone' => $this->phone(), 'email' => $this->email($vendorName)])->id;
            }
        }

        return $vendors;
    }

    /** @return Collection<int, Staff> */
    private function seedStaff(Apartment $apartment, array $site, CarbonImmutable $start): Collection
    {
        foreach ($site['staff'] as [$name, $role, $salary]) {
            $apartment->staff()->create(['name' => $name, 'role' => $role, 'phone' => $this->phone(), 'email' => $this->email($name), 'salary' => $salary, 'start_date' => $start->subMonths($this->between(1, 40))->toDateString(), 'status' => 'Aktif']);
        }

        return $apartment->staff()->get();
    }

    private function seedCommonAreas(Apartment $apartment, array $site): Collection
    {
        foreach ($site['common_areas'] as [$name, $fee, $open, $close, $capacity, $approval]) {
            $apartment->commonAreas()->create(['name' => $name, 'hourly_fee' => $fee, 'open_time' => $open, 'close_time' => $close, 'capacity' => $capacity, 'requires_approval' => $approval]);
        }

        return $apartment->commonAreas()->get();
    }

    private function seedMeters(Apartment $apartment, array $site, Collection $units): Collection
    {
        foreach ($site['meter_types'] as $type) {
            $apartment->meters()->create(['name' => 'Ortak alan '.$type, 'type' => $type, 'serial_no' => mb_substr($type, 0, 2).'-'.$this->digits(7), 'initial_value' => $this->between(1000, 9000), 'last_value' => 0]);
            foreach ($units as $unit) {
                $initial = $this->between(100, 4000);
                $apartment->meters()->create(['unit_id' => $unit->id, 'name' => $unit->label().' '.$type, 'type' => $type, 'serial_no' => mb_substr($type, 0, 2).'-'.$this->digits(7), 'initial_value' => $initial, 'last_value' => $initial]);
            }
        }

        return $apartment->meters()->get();
    }

    private function seedBudget(Apartment $apartment, array $site): void
    {
        foreach ([$this->realNow->year - 1, $this->realNow->year] as $year) {
            $inflation = $year === $this->realNow->year ? 1.32 : 1.0;
            $unitCount = array_sum($site['blocks']);
            $income = $apartment->categories()->where('name', 'Aidat')->first();
            $perUnit = array_sum(array_map(fn ($d) => $d['calc'] === 'sqm' ? $d['amounts'][2025] * (array_sum($site['areas']) / count($site['areas'])) : $d['amounts'][2025], $site['dues']));
            $apartment->budgetItems()->create(['year' => $year, 'category_id' => $income->id, 'amount' => round($perUnit * $unitCount * 12 * $inflation, -2)]);
            foreach ($site['expenses'] as [$categoryName, , , $base, , , $every]) {
                $category = $this->expenseCategory($apartment, $categoryName);
                if (! $apartment->budgetItems()->where('year', $year)->where('category_id', $category->id)->exists()) {
                    $apartment->budgetItems()->create(['year' => $year, 'category_id' => $category->id, 'amount' => round($base * (12 / $every) * $inflation * ($this->between(90, 115) / 100), -2)]);
                }
            }
        }
    }

    private function seedAssets(Apartment $apartment, array $site, array $vendors): void
    {
        foreach ($site['assets'] as [$name, $category, $monthsOld, $cost, $life, $location]) {
            $purchased = $this->realNow->subMonths($monthsOld);
            $isDisposed = str_starts_with($name, 'Eski');
            $apartment->assets()->create([
                'name' => $name, 'category' => $category, 'serial_no' => strtoupper(substr(md5($name.$site['code']), 0, 10)), 'location' => $location, 'purchase_date' => $purchased->toDateString(),
                'cost' => $cost, 'useful_life_years' => $life, 'salvage_value' => round($cost * 0.08, -2), 'vendor_id' => $vendors ? $this->pick(array_values($vendors)) : null,
                'status' => $isDisposed ? 'disposed' : 'active', 'disposed_on' => $isDisposed ? $this->realNow->subMonths(4)->toDateString() : null,
            ]);
        }
    }

    private function seedInventory(Apartment $apartment, array $site): void
    {
        $count = $site['kind'] === 'small' ? 4 : $this->between(9, count(self::INVENTORY));
        foreach (array_slice($this->shuffled(self::INVENTORY), 0, $count) as [$name, $category, $unit, $minimum]) {
            $apartment->inventoryItems()->create(['name' => $name, 'category' => $category, 'unit' => $unit, 'min_stock' => $minimum, 'quantity' => $this->pick([0, max(1, $minimum - 1), $minimum, $minimum * 2, $minimum * 3, $minimum * 4]), 'location' => $this->pick(['Depo', 'Teknik oda', 'Yönetim ofisi', 'Otopark deposu'])]);
        }
    }

    // ------------------------------------------------------------------ AY AKIŞI

    private function seedMonth(Apartment $apartment, array $site, CarbonImmutable $month, int $back, Collection $units, array $profiles, array $definitions, array $vendors, Collection $staff, Collection $areas, Collection $meters, Cashbox $cash, Cashbox $bank): void
    {
        $period = $month->format('Y-m');
        $events = [];

        $this->at($month->setTime(9, $this->between(0, 50)));
        foreach ($definitions as $definition) {
            $definition['model']->update(['amount' => $definition['amounts'][$month->year] ?? end($definition['amounts'])]);
        }
        $this->finance->generateDues($apartment, $period, $site['auto'] ? null : $this->admin);

        foreach ($units as $unit) {
            foreach ($this->paymentPlan($profiles[$unit->id], $back) as [$day, $share]) {
                $events[] = [$day, $this->between(9, 18), fn () => $this->payUnit($unit, $share, $site, $cash, $bank, $period)];
            }
        }

        foreach ($site['expenses'] as $expense) {
            if ($expense[6] === 1 || (int) $month->format('n') % $expense[6] === 0) {
                $events[] = [$this->between(4, 26), 15, fn () => $this->recordExpense($apartment, $expense, $month, $vendors, $back, $cash, $bank)];
            }
        }
        if ($this->chance(0.45)) {
            $events[] = [$this->between(3, 24), 11, fn () => $this->recordOneOffExpense($apartment, $vendors, $month, $back, $cash, $bank)];
        }

        foreach ($meters as $meter) {
            $events[] = [2, 8, fn () => $this->recordReading($meter, $month)];
        }
        if ($site['gas_bill'] && in_array((int) $month->format('n'), [11, 12, 1, 2, 3], true) && $back > 0) {
            $events[] = [12, 10, fn () => $this->distributeGasBill($apartment, $month, $units->count())];
        }
        if ($site['late_fee']) {
            $events[] = [17, 2, fn () => $this->finance->runLateFees($apartment->fresh(), null, $site['auto'] ? null : $this->admin)];
        }
        if ($staff->isNotEmpty() && $back > 0) {
            $events[] = [28, 16, fn () => $this->runPayroll($apartment, $period, $bank)];
        }
        if ($bank->isNot($cash)) {
            $events[] = [27, 17, fn () => $this->depositCash($cash, $bank)];
        }

        $ticketCount = max(1, (int) round($units->count() / 12)) + $this->between(0, 2);
        for ($i = 0; $i < $ticketCount; $i++) {
            $events[] = [$this->between(1, 27), $this->between(8, 21), fn () => $this->openTicket($apartment, $site, $units)];
        }
        for ($i = $this->between(1, 3); $i > 0; $i--) {
            $events[] = [$this->between(1, 27), 9, fn () => $this->createTask($apartment, $staff)];
        }
        if ($back <= 3) {
            for ($i = max(4, (int) round($units->count() / 3)); $i > 0; $i--) {
                $events[] = [$this->between(1, 27), $this->between(8, 22), fn () => $this->recordSecurityEntry($apartment, $site, $units)];
            }
        }
        foreach ($areas as $area) {
            for ($i = $this->between(1, 4); $i > 0; $i--) {
                $events[] = [$this->between(1, 26), 12, fn () => $this->bookArea($area, $units)];
            }
        }

        usort($events, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        foreach ($events as [$day, $hour, $event]) {
            $moment = $month->addDays(min($day, $month->daysInMonth) - 1)->setTime($hour, $this->between(0, 59));
            if ($moment->isAfter($this->realNow)) {
                continue;
            }
            $this->at($moment);
            try {
                $event();
            } catch (FinanceException) {
                // Kasa yetersizliği gibi iş kuralı engelleri gerçek hayatta da işlemi bekletir
            }
        }
    }

    /** @return list<array{0:int, 1:float}> [gün, açık borcun ne kadarı ödenecek] */
    private function paymentPlan(string $profile, int $back): array
    {
        return match ($profile) {
            'punctual' => $this->chance($back === 0 ? 0.65 : 0.97) ? [[$this->between(1, 12), 1.0]] : [],
            'late' => $this->chance($back <= 1 ? 0.45 : 0.75) ? [[$this->between(14, 28), 1.0]] : [],
            'partial' => $this->chance(0.85) ? [[$this->between(5, 25), $this->between(40, 80) / 100]] : [],
            'advance' => $back % 3 === 2 ? [[$this->between(1, 6), 3.0]] : [],
            default => $this->chance($back <= 5 ? 0.08 : 0.35) ? [[$this->between(10, 28), $this->between(30, 100) / 100]] : [],
        };
    }

    private function payUnit(Unit $unit, float $share, array $site, Cashbox $cash, Cashbox $bank, string $period): void
    {
        $open = Money::cents(Debt::where('unit_id', $unit->id)->where('status', Debt::PENDING)->sum('remaining_amount'));
        $monthly = max($open, Money::cents($unit->debts()->latest('id')->value('amount')));
        $amount = $share > 1 ? (int) ($monthly * $share) : (int) round($open * $share);
        if ($amount <= 0) {
            return;
        }
        if ($amount % 10000 !== 0 && $share < 1) {
            $amount = max(10000, (int) round($amount / 10000) * 10000);
        }

        $method = match (true) {
            $bank->is($cash) => 'collection',
            $site['kind'] === 'office' => 'collection',
            $this->chance(0.22) => 'pos',
            default => 'collection',
        };
        $box = $site['kind'] === 'office' || $this->chance(0.75) ? $bank : $cash;
        $description = $this->pick(["{$period} aidat ödemesi", "{$unit->label()} {$period} tahsilatı", "{$period} dönemi ödeme", 'Aidat ödemesi']);
        $this->finance->collect($unit, $box, $amount, $description, $this->admin, null, $method);
    }

    private function recordExpense(Apartment $apartment, array $definition, CarbonImmutable $month, array $vendors, int $back, Cashbox $cash, Cashbox $bank): void
    {
        [$categoryName, $vendorName, $description, $base, $vat, $seasonal] = $definition;
        $isWinter = in_array((int) $month->format('n'), [11, 12, 1, 2, 3], true);
        $seasonFactor = $seasonal < 1 ? ($isWinter ? $seasonal : 1.0) : ($this->isSeasonPeak($categoryName, $month) ? $seasonal : 1.0);
        $inflation = 1 + (self::MONTHS_OF_HISTORY - $back) * 0.022;
        $amount = round($base * $seasonFactor * $inflation * ($this->between(93, 108) / 100), 2);

        $this->payExpense($apartment, $categoryName, $vendorName ? $vendors[$vendorName] : null, $month->format('Y-m').' '.$description, $amount, $vat, $vendorName ? 1 : 0, $back, $cash, $bank);
    }

    private function isSeasonPeak(string $category, CarbonImmutable $month): bool
    {
        $monthNo = (int) $month->format('n');

        return in_array($category, ['Su', 'Havuz Bakımı', 'Bahçe ve Peyzaj'], true) ? $monthNo >= 6 && $monthNo <= 9 : $monthNo <= 2 || $monthNo === 12 || ($monthNo >= 7 && $monthNo <= 8);
    }

    private function recordOneOffExpense(Apartment $apartment, array $vendors, CarbonImmutable $month, int $back, Cashbox $cash, Cashbox $bank): void
    {
        [$description, $base] = $this->pick([['Kapı otomatiği yedek parça değişimi', 6800], ['Bina girişi seramik onarımı', 14500], ['Sınır anahtarı ve fotosel değişimi', 3900], ['Kırılan cam değişimi', 2800], ['Tıkanıklık açma hizmeti', 3200], ['Boya ve badana malzemesi', 11200], ['Yönetim ofisi kırtasiye gideri', 1900], ['Bilirkişi ve noter masrafı', 5600], ['Yangın dolabı hortum yenileme', 7300]]);
        $amount = round($base * (1 + (self::MONTHS_OF_HISTORY - $back) * 0.022) * ($this->between(90, 120) / 100), 2);
        $categoryName = str_contains($description, 'kırtasiye') || str_contains($description, 'noter') ? 'Diğer Gider' : 'Bakım Onarım';

        $this->payExpense($apartment, $categoryName, $vendors ? $this->pick(array_values($vendors)) : null, $description, $amount, 20, $this->chance(0.7) ? 1 : 0, $back, $cash, $bank);
    }

    private function payExpense(Apartment $apartment, string $categoryName, ?int $vendorId, string $description, float $amount, int $vat, int $hasInvoice, int $back, Cashbox $cash, Cashbox $bank): void
    {
        $expense = $apartment->expenses()->create([
            'vendor_id' => $vendorId, 'category_id' => $this->expenseCategory($apartment, $categoryName)->id, 'description' => $description,
            'amount' => $amount, 'vat_rate' => $vat, 'total_amount' => round($amount * (1 + $vat / 100), 2),
            'invoice_no' => $hasInvoice ? strtoupper(substr(md5($description.now()), 0, 3)).$this->digits(9) : null,
            'invoice_date' => today()->toDateString(), 'period' => now()->format('Y-m'), 'created_by' => $this->admin->id,
        ]);
        if ($back > 0 || $this->chance(0.45)) {
            $this->finance->approveExpense($expense, $this->chance(0.85) ? $bank : $cash, $this->admin);
        }
    }

    private function expenseCategory(Apartment $apartment, string $name)
    {
        return $apartment->categories()->firstOrCreate(['name' => $name, 'type' => 'expense']);
    }

    private function recordReading($meter, CarbonImmutable $month): void
    {
        if ($this->chance(0.04)) {
            return;
        }
        $isWinter = in_array((int) $month->format('n'), [11, 12, 1, 2, 3], true);
        $consumption = match ($meter->type) {
            'Doğalgaz' => $isWinter ? $this->between(90, 260) : $this->between(5, 35),
            'Isınma' => $isWinter ? $this->between(40, 160) : $this->between(0, 8),
            'Elektrik' => $this->between(350, 1800),
            default => $this->between(6, 22),
        } * ($meter->unit_id ? 1 : 6);
        $value = (float) ($meter->last_value ?: $meter->initial_value) + $consumption;
        $meter->readings()->create(['value' => $value, 'consumption' => $consumption, 'read_on' => today()->toDateString(), 'note' => $this->chance(0.08) ? 'Sayaç erişimi için kapı çalındı' : null]);
        $meter->update(['last_value' => $value]);
    }

    private function distributeGasBill(Apartment $apartment, CarbonImmutable $month, int $unitCount): void
    {
        app(MeterBillService::class)->distribute($apartment, [
            'meter_type' => 'Doğalgaz', 'method' => 'consumption', 'period_from' => $month->toDateString(), 'period_to' => $month->endOfMonth()->toDateString(),
            'total_amount' => $unitCount * $this->between(1400, 3100), 'due_date' => $month->addDays(24)->toDateString(), 'description' => $month->format('Y-m').' merkezi ısınma doğalgaz bedeli',
        ], $this->admin);
    }

    private function runPayroll(Apartment $apartment, string $period, Cashbox $bank): void
    {
        app(PayrollService::class)->generate($apartment, $period, $this->admin);
        foreach ($apartment->expenses()->where('period', $period)->whereNotNull('staff_id')->where('status', 'pending')->get() as $salary) {
            $this->finance->approveExpense($salary, $bank, $this->admin);
        }
    }

    private function depositCash(Cashbox $cash, Cashbox $bank): void
    {
        $balance = Money::cents($cash->fresh()->balance);
        if ($balance > 3000000) {
            $this->finance->transfer($cash->fresh(), $bank->fresh(), (int) floor(($balance - 1500000) / 100000) * 100000, 'Bankaya nakit yatırma', $this->admin);
        }
    }

    private function openTicket(Apartment $apartment, array $site, Collection $units): void
    {
        $unit = $units->random();
        [$category, $subject] = $this->chance(0.45)
            ? [$this->categoryFor($s = $this->pick($site['tickets'])), $s]
            : $this->pick(self::COMMON_TICKETS);
        $ageDays = (int) today()->diffInDays($this->realNow);
        $status = match (true) {
            $ageDays > 25 => $this->chance(0.75) ? 'closed' : 'resolved',
            $ageDays > 10 => $this->pick(['resolved', 'resolved', 'in_progress', 'closed']),
            default => $this->pick(['open', 'open', 'in_progress']),
        };
        $isClosed = in_array($status, ['resolved', 'closed'], true);
        $ticket = $apartment->tickets()->create([
            'unit_id' => $unit->id, 'unit_no' => $unit->label(), 'subject' => $subject, 'description' => $this->pick(['Birkaç gündür devam ediyor, ilgilenilmesini rica ederim.', 'Dün akşamdan beri bu sorunu yaşıyoruz.', 'Daha önce de bildirmiştim, tekrar kontrol edilebilir mi?', 'Uygun olduğunuzda bakılmasını rica ederim.']),
            'category' => $category, 'priority' => $this->pick(['low', 'normal', 'normal', 'normal', 'high']), 'status' => $status,
            'response' => $isClosed ? $this->pick(['Teknik ekip kontrol etti, sorun giderildi.', 'Yetkili servis çağrıldı ve onarım tamamlandı.', 'Gerekli bakım yapıldı, teşekkür ederiz.']) : ($status === 'in_progress' ? 'Talebiniz ilgili ekibe iletildi, parça bekleniyor.' : null),
        ]);
        if ($isClosed) {
            $ticket->timestamps = false;
            $ticket->forceFill(['updated_at' => min($this->realNow, now()->toImmutable()->addHours($this->between(3, 96)))])->save();
        }
    }

    private function categoryFor(string $subject): string
    {
        return match (true) {
            (bool) preg_match('/bariyer|turnike|güvenlik|park/iu', $subject) => 'Güvenlik',
            (bool) preg_match('/çim|sulama|palmiye|ağaç/iu', $subject) => 'Bahçe',
            (bool) preg_match('/kağıt|temiz|çöp/iu', $subject) => 'Temizlik',
            (bool) preg_match('/gürültü|şikayet/iu', $subject) => 'Genel',
            default => 'Teknik',
        };
    }

    private function createTask(Apartment $apartment, Collection $staff): void
    {
        [$title, $category, $priority] = $this->pick(self::TASKS);
        $due = today()->addDays($this->between(2, 18));
        $isDone = $due->lt($this->realNow) && $this->chance(0.88);
        $apartment->tasks()->create([
            'title' => $title, 'staff_id' => $staff->isNotEmpty() && $this->chance(0.85) ? $staff->random()->id : null, 'priority' => $priority, 'category' => $category, 'due_date' => $due->toDateString(),
            'status' => $isDone ? 'completed' : ($due->lt($this->realNow) || $this->chance(0.3) ? 'in_progress' : 'pending'),
            'completed_at' => $isDone ? min($this->realNow, $due->toImmutable()->subDays($this->between(0, 2))) : null, 'notes' => $this->chance(0.2) ? 'Malzeme depodan temin edildi.' : null,
        ]);
    }

    private function recordSecurityEntry(Apartment $apartment, array $site, Collection $units): void
    {
        $unit = $units->random();
        $kind = $this->pick(['cargo', 'cargo', 'cargo', 'visitor', 'visitor', 'parking']);
        $isRecent = today()->diffInDays($this->realNow) < 3;
        $person = $this->personName();
        $apartment->securityEntries()->create(match ($kind) {
            'cargo' => ['kind' => 'cargo', 'unit_no' => $unit->label(), 'full_name' => $person, 'company' => $this->pick(self::CARGO_COMPANIES), 'tracking_no' => strtoupper(substr(md5((string) mt_rand()), 0, 2)).$this->digits(10), 'status' => $isRecent && $this->chance(0.6) ? 'beklemede' : 'teslim-edildi', 'closed_at' => $isRecent ? null : now()->addHours($this->between(2, 48))],
            'parking' => ['kind' => 'parking', 'unit_no' => $unit->label(), 'full_name' => $person, 'plate' => $site['plate'].' '.chr($this->between(65, 90)).chr($this->between(65, 90)).' '.$this->between(100, 9999), 'status' => $isRecent ? 'aktif' : 'ayrildi', 'closed_at' => $isRecent ? null : now()->addHours($this->between(1, 30)), 'note' => 'Misafir otoparkı'],
            default => ['kind' => 'visitor', 'unit_no' => $unit->label(), 'full_name' => $person, 'company' => $this->pick([null, null, 'Klima servisi', 'Kombi servisi', 'Market teslimat', 'İnternet teknisyeni']), 'status' => $isRecent && $this->chance(0.5) ? 'aktif' : 'ayrildi', 'closed_at' => $isRecent ? null : now()->addHours($this->between(1, 5))],
        });
    }

    private function bookArea($area, Collection $units): void
    {
        $date = today()->addDays($this->between(1, 12));
        $startHour = (int) substr($area->open_time, 0, 2) + $this->between(0, 4);
        $reservation = $this->reservations->book($area, $units->random(), $date->toDateString(), sprintf('%02d:00', $startHour), sprintf('%02d:00', $startHour + $this->between(1, 3)), $this->admin, $this->pick([null, null, 'Aile etkinliği', 'Doğum günü', 'Firma toplantısı', 'Arkadaş buluşması']));
        if ($reservation->status === 'pending' && $date->lt($this->realNow)) {
            $this->chance(0.8) ? $this->reservations->approve($reservation, $this->admin) : $this->reservations->reject($reservation, $this->admin);
        } elseif ($reservation->status === 'approved' && $this->chance(0.08)) {
            $this->reservations->cancel($reservation, $this->admin);
        }
    }

    // ------------------------------------------------------------------ DÜZELTMELER

    private function seedCorrections(Apartment $apartment, Collection $units, array $profiles, Cashbox $cash, Cashbox $bank): void
    {
        $this->at($this->realNow->subDays(35)->setTime(11, 15));
        $apartment = $apartment->fresh();

        $repairCategory = $apartment->categories()->firstOrCreate(['name' => 'Ek Bütçe', 'type' => 'income']);
        $this->attempt(fn () => $this->finance->distribute($apartment, $units, [
            'description' => 'Olağanüstü onarım ek bütçe payı', 'amount' => $this->pick([35000, 75000, 120000]), 'due_date' => $this->realNow->addDays(10)->toDateString(),
            'period' => $this->realNow->format('Y-m'), 'category_id' => $repairCategory->id, 'category_name' => $repairCategory->name,
        ], $this->admin));

        $chronic = collect($profiles)->filter(fn ($profile) => $profile === 'chronic')->keys();
        foreach ($chronic->take(2) as $index => $unitId) {
            $debt = Debt::where('unit_id', $unitId)->where('status', Debt::PENDING)->where('kind', 'due')->orderBy('due_date')->first();
            if (! $debt) {
                continue;
            }
            $index === 0
                ? $this->finance->setLegal($debt, true, 'İcra dosya no 2026/'.$this->between(1000, 9999), $this->admin)
                : $this->attempt(fn () => $this->finance->installments($debt, 4, $this->realNow->addDays(15)->toDateString(), 1, $this->admin));
        }

        foreach ($apartment->cashTransactions()->where('sub_type', 'collection')->whereNull('reversed_at')->latest('id')->limit(40)->get()->random(min(2, $apartment->cashTransactions()->where('sub_type', 'collection')->count())) as $transaction) {
            $this->attempt(fn () => $this->finance->reverse($transaction, 'Yanlış daireye işlendi', $this->admin));
        }

        $advanced = $apartment->units()->where('credit_balance', '>=', 500)->first();
        if ($advanced) {
            $this->attempt(fn () => $this->finance->refundAdvance($advanced, $bank->fresh(), 50000, $this->admin));
        }
    }

    private function attempt(callable $operation): void
    {
        try {
            $operation();
        } catch (FinanceException) {
            // Örnek düzeltme iş kurallarına takılırsa atlanır
        }
    }

    // ------------------------------------------------------------------ KURUL VE İLETİŞİM

    private function seedGovernance(Apartment $apartment, array $site, Collection $units): void
    {
        $units = $units->load('members');
        $number = 0;

        foreach ([[-15, 'olagan', 'Olağan Genel Kurul'], [-9, 'yonetim', 'Yönetim Kurulu Toplantısı'], [-5, 'yonetim', 'Yönetim Kurulu Toplantısı'], [-2, 'olaganustu', 'Olağanüstü Genel Kurul']] as $index => [$monthsAgo, $type, $title]) {
            if ($site['kind'] === 'small' && $type === 'yonetim') {
                continue;
            }
            $meetsAt = $this->realNow->addMonths($monthsAgo)->startOfMonth()->addDays(9 + $index)->setTime(19, 30);
            $this->at($meetsAt->subDays(20));
            $meeting = $apartment->meetings()->create([
                'title' => $meetsAt->year.' '.$title, 'type' => $type, 'meets_at' => $meetsAt, 'location' => $site['common_areas'][0][0] ?? 'Bina girişi', 'status' => 'held',
                'agenda' => "1. Açılış ve yoklama\n2. Yönetim faaliyet raporu\n3. Bütçe ve aidat görüşmesi\n4. Dilek ve temenniler",
                'minutes' => 'Toplantı yeter sayısı sağlanarak açılmış, gündem maddeleri görüşülerek karara bağlanmıştır.',
            ]);
            foreach ($units->shuffle(mt_rand())->take((int) ceil($units->count() * $this->between(45, 85) / 100)) as $unit) {
                $meeting->attendances()->create(['unit_id' => $unit->id, 'attendee_name' => $unit->members->first()?->full_name ?? $this->personName(), 'is_proxy' => $this->chance(0.15)]);
            }
            if (isset($site['decisions'][$index])) {
                $this->at($meetsAt->addHours(2));
                [$subject, $content] = $site['decisions'][$index];
                $apartment->decisions()->create([
                    'decision_no' => $meetsAt->year.'/'.str_pad((string) (++$number), 3, '0', STR_PAD_LEFT), 'decided_on' => $meetsAt->toDateString(), 'subject' => $subject, 'content' => $content,
                    'attendees' => $units->take(3)->map(fn ($u) => $u->members->first()?->full_name)->filter()->implode(', '), 'meeting_id' => $meeting->id,
                ]);
            }
        }

        $this->at($this->realNow->subDays(2));
        $apartment->meetings()->create(['title' => ($this->realNow->year + 1).' Olağan Genel Kurul', 'type' => 'olagan', 'meets_at' => $this->realNow->addWeeks(4)->setTime(19, 0), 'location' => $site['common_areas'][0][0] ?? 'Bina girişi', 'status' => 'planned', 'agenda' => "1. Açılış\n2. Yıllık gelir gider raporu\n3. Yeni dönem işletme projesi\n4. Yönetim kurulu seçimi"]);
    }

    private function seedCommunication(Apartment $apartment, array $site, Collection $units): void
    {
        foreach ($site['announcements'] as $index => [$title, $content, $priority]) {
            $created = $this->realNow->subDays(3 + $index * $this->between(18, 45));
            $this->at($created);
            $apartment->announcements()->create(['title' => $title, 'content' => $content, 'priority' => $priority, 'created_by' => $this->admin->id, 'expires_on' => $index === 0 ? $this->realNow->addDays(20)->toDateString() : $created->addDays(21)->toDateString()]);
        }

        foreach ($site['polls'] as $index => [$question, $options]) {
            $created = $this->realNow->subDays(6 + $index * 75);
            $this->at($created);
            $poll = $apartment->polls()->create(['question' => $question, 'due_date' => $created->addDays(25)->toDateString(), 'is_active' => $index === 0]);
            $optionIds = array_map(fn (string $text) => $poll->options()->create(['text' => $text])->id, $options);
            foreach ($units->shuffle(mt_rand())->take((int) ceil($units->count() * $this->between(35, 80) / 100)) as $unit) {
                $poll->votes()->create(['poll_option_id' => $this->pick($optionIds), 'unit_id' => $unit->id]);
            }
        }

        foreach ([['Aidat ödeme hatırlatması', 'Bu ayın aidatını son ödeme tarihine kadar ödemenizi rica ederiz.', 'warning', true], ['Su kesintisi', 'Bakım nedeniyle yarın 10:00-15:00 arası su kesintisi olacaktır.', 'warning', true], ['Asansör bakımı tamamlandı', 'Periyodik bakım tamamlanmış olup asansörler hizmete açılmıştır.', 'info', true], ['Kargonuz güvenlikte', 'Adınıza gelen kargo güvenlik noktasında teslim alınmayı bekliyor.', 'info', false], ['Mali rapor yayınlandı', 'Yönetimin dönem gelir gider raporu yayınlanmıştır.', 'info', true], ['Ödemeniz alındı', 'Yaptığınız ödeme hesabınıza işlenmiştir.', 'success', false]] as $index => [$title, $body, $type, $toEveryone]) {
            $this->at($this->realNow->subDays(2 + $index * $this->between(6, 20)));
            $notification = $apartment->siteNotifications()->create(['unit_id' => $toEveryone ? null : $units->random()->id, 'title' => $title, 'body' => $body, 'type' => $type, 'created_by' => $this->admin->id]);
            $linked = $apartment->members()->whereNotNull('user_id')->pluck('user_id')->unique()->values();
            foreach ($linked->filter(fn () => $this->chance(0.6)) as $userId) {
                $notification->readers()->syncWithoutDetaching([$userId => ['read_at' => now()->addHours($this->between(1, 40))]]);
            }
        }
    }

    private function seedDocuments(Apartment $apartment): void
    {
        foreach (self::DOCUMENTS as $index => [$title, $category, $expiresInDays]) {
            $this->at($this->realNow->subDays(5 + $index * $this->between(12, 40)));
            $slug = str($title)->slug().'.txt';
            $path = "documents/{$apartment->id}/{$slug}";
            $content = "{$apartment->name}\n{$title}\n\nBu dosya demo amaçlı örnek bir belgedir.\n";
            Storage::disk('local')->put($path, $content);
            $apartment->documents()->create(['title' => $title, 'category' => $category, 'file_path' => $path, 'original_name' => $slug, 'mime' => 'text/plain', 'size' => strlen($content), 'expires_on' => $expiresInDays === null ? null : $this->realNow->addDays($expiresInDays)->toDateString(), 'uploaded_by' => $this->admin->id]);
        }
    }

    private function seedBankLines(Apartment $apartment, Cashbox $bank, Collection $units): void
    {
        $this->at($this->realNow->subDays(3)->setTime(9, 30));
        $units = $units->load('members');
        $rows = [];
        foreach ($units->shuffle(mt_rand())->take(min(10, $units->count())) as $index => $unit) {
            $name = mb_strtoupper($unit->members->first()?->full_name ?? '', 'UTF-8');
            $rows[] = ['date' => $this->realNow->subDays(1 + $index % 8)->toDateString(), 'reference' => 'BNK'.$this->digits(8), 'amount' => $this->between(9, 45) * 10000,
                'description' => $this->pick(["FAST {$name} {$unit->label()} AIDAT", "HAVALE {$name}", "EFT DAIRE {$unit->unit_no} ODEME", "{$name} KASIM AIDATI"])];
        }
        $rows[] = ['date' => $this->realNow->subDays(2)->toDateString(), 'reference' => 'BNK'.$this->digits(8), 'amount' => 125000, 'description' => 'GONDEREN BILINMIYOR FAST'];
        $rows[] = ['date' => $this->realNow->subDays(4)->toDateString(), 'reference' => 'BNK'.$this->digits(8), 'amount' => -18750, 'description' => 'HESAP ISLETIM UCRETI'];
        $rows[] = ['date' => $this->realNow->subDays(6)->toDateString(), 'reference' => 'BNK'.$this->digits(8), 'amount' => -412000, 'description' => 'OTOMATIK ODEME ELEKTRIK'];
        app(BankImportService::class)->import($apartment, $bank, $rows);

        $service = app(BankImportService::class);
        foreach ($apartment->bankLines()->where('amount', '>', 0)->whereNotNull('unit_id')->limit(3)->get() as $line) {
            $this->attempt(fn () => $service->post($line, null, $this->admin));
        }
        $apartment->bankLines()->where('description', 'HESAP ISLETIM UCRETI')->update(['status' => 'ignored']);
    }

    // ------------------------------------------------------------------ EMLAK

    private function seedProperties(): void
    {
        $listings = [
            ['Karşıyaka Bostanlı Deniz Manzaralı 3+1', 12400000, 'Karşıyaka / İzmir', 'Apartment', 'active', 3, 2, 145, ['Asansör', 'Otopark', 'Deniz Manzarası'], ['Batı']],
            ['Çamlıtepe Müstakil Bahçeli Villa', 41500000, 'Çankaya / Ankara', 'Villa', 'active', 5, 4, 420, ['Bahçe', 'Güvenlik', 'Otopark', 'Spor Salonu'], ['Güney', 'Doğu']],
            ['Levent Plaza 180 m² Ofis Katı', 28900000, 'Beşiktaş / İstanbul', 'Office', 'active', 0, 2, 180, ['Asansör', 'Güvenlik', 'Otopark', 'Klima'], ['Kuzey']],
            ['Lara Marin 1+1 Yatırımlık Rezidans', 7650000, 'Muratpaşa / Antalya', 'Apartment', 'active', 1, 1, 58, ['Havuz', 'Sauna', 'Spor Salonu', 'Güvenlik'], ['Güney']],
            ['Lara Marin 2+1 Havuz Manzaralı', 11300000, 'Muratpaşa / Antalya', 'Apartment', 'sold', 2, 1, 92, ['Havuz', 'Asansör', 'Güvenlik'], ['Batı']],
            ['Nilüfer Ertuğrul 3+1 Ara Kat', 5850000, 'Nilüfer / Bursa', 'Apartment', 'active', 3, 1, 118, ['Asansör', 'Doğalgaz'], ['Güney']],
            ['Ödemiş Bozdağ Yolu Yatırımlık Arsa', 3200000, 'Ödemiş / İzmir', 'Land', 'active', 0, 0, 1450, [], []],
            ['Bodrum Yalıkavak Deniz Manzaralı Villa', 96000000, 'Bodrum / Muğla', 'Villa', 'active', 6, 5, 510, ['Havuz', 'Deniz Manzarası', 'Bahçe', 'Güvenlik'], ['Batı', 'Güney']],
            ['Kadıköy Moda Caddesi Dükkan', 19750000, 'Kadıköy / İstanbul', 'Commercial', 'active', 0, 1, 76, [], ['Doğu']],
            ['Bornova Müstakil Ev', 8900000, 'Bornova / İzmir', 'House', 'passive', 4, 2, 190, ['Bahçe', 'Doğalgaz'], ['Güney']],
            ['Ataşehir Finans Merkezi Ofis', 34200000, 'Ataşehir / İstanbul', 'Office', 'sold', 0, 2, 240, ['Asansör', 'Klima', 'Otopark'], ['Kuzey', 'Doğu']],
            ['Fethiye Çalış Sahil Dairesi 2+1', 9400000, 'Fethiye / Muğla', 'Apartment', 'active', 2, 1, 98, ['Havuz', 'Deniz Manzarası', 'Otopark'], ['Batı']],
        ];
        foreach ($listings as [$title, $price, $location, $category, $status, $bedrooms, $bathrooms, $area, $features, $facades]) {
            $this->at($this->realNow->subDays($this->between(2, 240)));
            Property::create([
                'title' => $title, 'price' => $price, 'location' => $location, 'category' => $category, 'status' => $status, 'bedrooms' => $bedrooms ?: null, 'bathrooms' => $bathrooms ?: null,
                'area' => $area, 'features' => $features, 'facades' => $facades, 'is_furnished' => $this->chance(0.3), 'is_credit_eligible' => $this->chance(0.7),
                'agent_name' => $this->personName(), 'agent_phone' => $this->phone(), 'year_built' => $category === 'Land' ? null : $this->between(1998, 2025),
                'description' => 'Konumu ve ulaşım olanaklarıyla öne çıkan, bakımlı bir portföy.', 'distances' => ['Merkez' => $this->between(1, 15).' km', 'Hastane' => $this->between(1, 9).' km'], 'created_by' => $this->admin->id,
            ]);
        }
    }

    // ------------------------------------------------------------------ YARDIMCILAR

    private function makeUser(string $name, string $email, string $role, bool $isSuperAdmin = false): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $this->passwordHash, 'phone' => $this->phone()]);
        $user->forceFill(['role' => $role, 'is_super_admin' => $isSuperAdmin])->save();

        return $user;
    }

    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    private function personName(): string
    {
        return $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES);
    }

    private function phone(): string
    {
        return '05'.$this->pick(['30', '32', '33', '35', '36', '38', '42', '44', '46', '52', '53', '54', '55']).' '.$this->digits(3).' '.$this->digits(2).' '.$this->digits(2);
    }

    private function email(string $name): string
    {
        return str(strtr($name, ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c']))->slug('.').$this->between(1, 99).'@demo.test';
    }

    private function digits(int $count): string
    {
        $digits = '';
        for ($i = 0; $i < $count; $i++) {
            $digits .= $this->between(0, 9);
        }

        return $digits;
    }

    private function between(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function chance(float $probability): bool
    {
        return mt_rand(1, 10000) <= $probability * 10000;
    }

    private function pick(array $items): mixed
    {
        return array_values($items)[mt_rand(0, count($items) - 1)];
    }

    private function shuffled(array $items): array
    {
        shuffle($items);

        return $items;
    }

    /** @param array<string, int> $weights */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
