# Leans kullanım rehberi: HTML üretir, Chrome ile PDF'e çevrilir.
import html, pathlib

ROOT = pathlib.Path(__file__).parent
S = "/sites/{id}"


def esc(s):
    return html.escape(s, quote=False)


def ul(items):
    return "<ul>" + "".join(f"<li>{i}</li>" for i in items) + "</ul>"


def ol(items):
    return "<ol>" + "".join(f"<li>{i}</li>" for i in items) + "</ol>"


def page(title, menu, url, purpose, steps=None, actions=None, rules=None, links=None, img=None, tip=None):
    out = [f'<section class="page"><div class="head"><h3>{title}</h3>']
    out.append(f'<div class="meta"><span><b>Menü:</b> {menu}</span><span><b>Adres:</b> <code>{esc(url)}</code></span></div>')
    out.append(f'<p class="purpose">{purpose}</p>')
    if img:
        out.append(f'<figure><img src="img/{img}"><figcaption>{title} ekranı (demo veri)</figcaption></figure>')
    out.append("</div>")
    if actions:
        out.append("<h4>Bu sayfada ne yapılır?</h4>" + ul(actions))
    if steps:
        out.append("<h4>Adım adım</h4>" + ol(steps))
    if rules:
        out.append('<h4>Kurallar ve dikkat edilecekler</h4><div class="rules">' + ul(rules) + "</div>")
    if links:
        out.append('<h4>Bağlantılı sayfalar</h4><p class="links">' + " · ".join(links) + "</p>")
    if tip:
        out.append(f'<div class="tip"><b>Patrona söyle:</b> {tip}</div>')
    out.append("</section>")
    return "\n".join(out)


def chapter(num, title, intro, body):
    return f'<div class="chapter"><h2><span>{num}</span>{title}</h2><p class="intro">{intro}</p>{body}</div>'


def table(head, rows):
    h = "".join(f"<th>{c}</th>" for c in head)
    r = "".join("<tr>" + "".join(f"<td>{c}</td>" for c in row) + "</tr>" for row in rows)
    return f"<table><thead><tr>{h}</tr></thead><tbody>{r}</tbody></table>"


parts = []

# ------------------------------------------------------------------ KAPAK + İÇİNDEKİLER
parts.append("""
<div class="cover">
  <div class="logo">⚡</div>
  <h1>Leans</h1>
  <p class="sub">Apartman ve Site Yönetimi · Muhasebe Sistemi</p>
  <p class="sub2">Eksiksiz Kullanım ve Tanıtım Rehberi</p>
  <p class="date">Kasım 2026 · Her sayfa, her buton, her bağlantı</p>
</div>
<div class="toc">
  <h2>İçindekiler</h2>
  <ol>
    <li>Sistem nedir, kimler kullanır?</li>
    <li>Temel kavramlar (5 dakikada muhasebe)</li>
    <li>Ekran düzeni ve menü haritası</li>
    <li>Giriş, hesap ve panolar</li>
    <li>Yapı, Daire &amp; Sakinler</li>
    <li>Tahakkuk, Aidat &amp; Sayaç</li>
    <li>Tahsilat, Kasa &amp; Banka</li>
    <li>Muhasebe, Defter &amp; Bütçe</li>
    <li>Gider, Tedarikçi &amp; İK</li>
    <li>Saha, Envanter &amp; Güvenlik</li>
    <li>Kurul, İletişim &amp; Arşiv</li>
    <li>Rapor, Denetim &amp; Ayarlar</li>
    <li>Sistem yönetimi (süper admin)</li>
    <li>Sakin portalı ve mobil uygulama</li>
    <li>Otomatik (gece) işleri</li>
    <li>Günlük, aylık ve yıllık iş akışları</li>
    <li>Güvenlik ve veri bütünlüğü</li>
    <li>Patron sunumu: senaryo ve olası sorular</li>
    <li>Bilinen eksikler ve yol haritası</li>
    <li>Sözlük</li>
  </ol>
</div>
""")

# ------------------------------------------------------------------ 1
parts.append(chapter(1, "Sistem nedir, kimler kullanır?",
    "Leans; birden fazla apartmanı, siteyi veya iş merkezini tek panelden yöneten web tabanlı bir yönetim ve muhasebe sistemidir.",
    """
<p>Sistem iki yüzden oluşur:</p>
""" + ul([
    "<b>Yönetim paneli:</b> yönetici, muhasebeci ve izleyicinin kullandığı ana ekranlar. Aidat yazma, tahsilat, gider, kasa, banka, raporlar, toplantı, arıza, rezervasyon vb. her şey buradan yapılır.",
    "<b>Sakin portalı ve mobil uygulama:</b> sakinin kendi borcunu gördüğü, arıza talebi açtığı, ankete oy verdiği, rezervasyon yaptığı ve duyuruları okuduğu bölüm. Web portalı hazır, mobil uygulama için API hazır.",
]) + """
<h4>Kim ne yapabilir?</h4>
""" + table(["Rol", "Nerede", "Yetkisi"], [
    ["Süper admin", "Tüm sistem", "Bütün siteler, kullanıcı oluşturma ve rol verme, sistem denetim kayıtları, yedek alma ve geri yükleme."],
    ["Site sahibi", "Kendi sitesi", "Siteyi oluşturan kişi. Yönetici yetkilerine ek olarak dönem ve yıl kapatma, dönemi geri açma, siteyi sıfırlama ve silme yalnızca ondadır."],
    ["Yönetici", "Atandığı siteler", "Sitede her modülde tam yetki."],
    ["Muhasebeci", "Atandığı siteler", "Modül modül ayarlanır: erişim yok / görüntüleme / görüntüleme + işlem. Varsayılan: finans modüllerinde işlem, diğerlerinde görüntüleme."],
    ["İzleyici", "Atandığı siteler", "Varsayılan olarak her modülde yalnızca görüntüleme (ör. denetçi, yönetim kurulu üyesi)."],
    ["Sakin", "Portal / mobil", "Yalnızca bağlı olduğu dairenin borcu, bildirimleri, anketleri, talepleri, rezervasyonları ve toplantıları."],
]) + """
<div class="tip"><b>Önemli:</b> Yetki yalnızca menüde gizlenmez, sunucu tarafında zorlanır. Muhasebecinin yetkisi olmayan bir sayfanın adresini elle yazsa bile "yetkiniz yok" (403) hatası alır; yetkisi olmayan butonlar sayfadan otomatik çıkarılır.</div>
"""))

# ------------------------------------------------------------------ 2
parts.append(chapter(2, "Temel kavramlar (5 dakikada muhasebe)",
    "Sistemi anlatabilmek için bilmen gereken 8 kavram. Hepsi bir dairenin hesabı etrafında döner.",
    table(["Kavram", "Ne demek?", "Örnek"], [
        ["Tahakkuk (borç yazma)", "Daireye borç kaydı açmak. Para henüz gelmedi ama gelir doğdu.", "A-5'e Kasım aidatı 1.000 TL yazıldı."],
        ["Tahsilat", "Daireden para almak. Kasa artar, borç düşer.", "A-5 1.000 TL ödedi, borç kapandı."],
        ["Avans", "Dairenin borcundan fazla ödediği para; daire bizde alacaklıdır.", "1.500 TL ödedi: 1.000 borca, 500 avansa."],
        ["Mahsup", "Avansın yeni bir borca otomatik düşülmesi.", "Aralık aidatı yazılınca 500 TL avans düşüldü, kalan borç 500."],
        ["Kasa", "Paranın durduğu yer: nakit kasa, banka hesabı veya döviz kasası.", "Merkez Kasa, Ziraat Bankası, USD Kasa."],
        ["Yevmiye (çift taraflı kayıt)", "Her para hareketi iki satır yazar: bir borç, bir alacak. İkisi hep eşittir.", "Tahsilat: Kasa (borç) 1.000 / Daire hesabı (alacak) 1.000."],
        ["Ters kayıt (iptal)", "Hiçbir kayıt silinmez; hata zıt yönlü yeni kayıtla düzeltilir.", "Yanlış tahsilat iptal edilince kasadan 1.000 çıkar, borç yeniden açılır."],
        ["Dönem kapatma", "Belli bir tarihe kadarki kayıtları kilitlemek.", "31.12.2025'e kadar kapatıldı; o tarihe borç yazılamaz, kayıt iptal edilemez."],
    ]) + """
<h4>Bir dairenin hesabı nasıl işler? (Tam örnek)</h4>
""" + ol([
    "<b>Borç yazılır:</b> A-5'e 1.000 TL aidat. Daire borcu 1.000.",
    "<b>Sakin 1.500 TL öder:</b> Sistem parayı en eski vadeli borca yatırır. Borç kapanır, kalan 500 TL avans olur.",
    "<b>Ertesi ay 1.000 TL aidat yazılır:</b> 500 TL avans otomatik düşülür. Kalan borç 500. Sakinin bir şey yapmasına gerek yoktur.",
    "<b>İlk ödeme yanlışsa iptal edilir:</b> Ama avansı başka borçta kullanılmışsa sistem önce o borcun iptal edilmesini ister. Böylece bakiye hiçbir zaman bozulmaz.",
]) + """
<div class="tip"><b>Patrona söyle:</b> "Her daire bir banka hesabı gibi. Aidat borç yazar, ödeme düşer, fazlası avans olur ve sonraki borca kendiliğinden düşer. Hiçbir kayıt silinmez, düzeltme ters kayıtla yapılır; o yüzden her kuruşun izi var."</div>
"""))

# ------------------------------------------------------------------ 3
parts.append(chapter(3, "Ekran düzeni ve menü haritası",
    "Her sayfa aynı iskeleti kullanır. Bu düzeni bir kez öğrenince bütün sistemde rahat gezersin.",
    """
<h4>Ekranın bölümleri</h4>
""" + ul([
    "<b>Sol dar menü (ikonlar):</b> En üstte ⚡ logo (Genel Portföy Panosu), altında + (yeni kayıt kısayolu) ve ana pano ikonları, sonra 8 menü grubu. Bir ikonun üzerine gelince alt sayfaları açılır. En altta ayarlar ve hesabın (baş harfin) var.",
    "<b>Üst çubuk (sağ üst):</b> <i>Setup</i> (kurulum rehberi), <i>Koyu/Açık Tema</i>, <b>aktif site seçici</b> (hangi sitedeysen onun adı yazar, tıklayıp başka siteye geçersin), site ayarları, son işlemler, <b>arama</b> (daire, sakin, borç, talep, gider arar), bildirim zili ve hesap menüsü (şifre, iki adımlı doğrulama, çıkış).",
    "<b>Sayfa başlığı:</b> Sayfanın adı ve altında hangi site için açık olduğu.",
    "<b>Özet kartları:</b> Çoğu sayfanın üstünde 4-6 kutu (KPI) ve grafikler bulunur. Ör. tahsilat sayfasında 'Bu ay tahsilat', 'Ödeme yapan daire'.",
    "<b>İşlem alanı:</b> '+ Yeni …' başlıklı açılır form kartları. Tıklayınca form açılır.",
    "<b>Liste / tablo:</b> Filtreler, sekmeler (ör. Bekleyen / İşlenen), satır sonunda Düzenle, Sil, Detay, Makbuz gibi düğmeler.",
    "<b>Excel'e aktar ve Yazdır / PDF:</b> Muhasebe ve rapor sayfalarının sağ üstünde.",
]) + """
<h4>8 menü grubu: ne yapacaksan nereye gidersin?</h4>
""" + table(["Menü grubu", "Alt sayfalar", "Burada ne yapılır"], [
    ["Yapı, Daire &amp; Sakinler", "Konut &amp; Siteler · Blok, Daire &amp; Gruplar · Sakin &amp; Üye Listesi · Sakin &amp; Taşınma Geçmişi · Sistem Kullanıcıları · Emlak Portföyü", "Site, blok, daire ve sakin kaydı; kullanıcılar; emlak ilanları."],
    ["Tahakkuk, Aidat &amp; Sayaç", "Aidat &amp; Otomatik Tahakkuk · Borçlandırma &amp; Taksit / İcra · Gecikme Zammı Motoru · Sayaç &amp; Endeks Okuma · Sayaç Faturası Dağıtımı", "Dairelere borç yazmanın bütün yolları."],
    ["Tahsilat, Kasa &amp; Banka", "Tahsilat &amp; Makbuz Kesimi · Kasa &amp; Banka Hesapları · Banka Ekstresi Eşleştirme · Avans &amp; Kredi Mahsup · Finansal Özet &amp; Virman", "Para almak, kasaları yönetmek, bankayla eşleştirmek."],
    ["Muhasebe, Defter &amp; Bütçe", "Gelir - Gider &amp; Kategoriler · Daire Hesap Ekstreleri · Yevmiye Defteri · Kasa Defteri · Mizan Tablosu · Mali Durum / Bilanço · Alacak Yaşlandırma · Bütçe &amp; Gerçekleşen", "Mali tablolar ve muhasebe defterleri."],
    ["Gider, Tedarikçi &amp; İK", "Gider &amp; Fatura Onayı · Tedarikçi &amp; Cari Hesaplar · Personel &amp; Görevliler · Personel Maaş &amp; Bordro", "Faturalar, firmalar, personel ve maaş."],
    ["Saha, Envanter &amp; Güvenlik", "Görev &amp; Bakım Takibi · Stok &amp; Sarf Malzeme · Demirbaş &amp; Zimmet Kaydı · Ortak Alan Rezervasyonu · Güvenlik, Ziyaretçi &amp; Kargo · Arıza &amp; Destek Talepleri", "Günlük işletme ve saha işleri."],
    ["Kurul, İletişim &amp; Arşiv", "Genel Kurul &amp; Toplantılar · Karar Defteri · Duyuru Panosu · Sakin Anketleri &amp; Oylama · Toplu Bildirim Gönderimi · Otomatik Borç Hatırlatıcı · Dijital Belge Arşivi", "Sakinlerle iletişim, kurul ve belgeler."],
    ["Rapor, Denetim &amp; Ayarlar", "Rapor Merkezi (PDF / CSV) · İşlem &amp; Denetim Kayıtları · Site Ayarları &amp; Yöneticiler", "Raporlar, kim ne yaptı kaydı, site ayarları ve izinler."],
]) + """
<div class="tip"><b>İpucu:</b> Menü kişiye göre filtrelenir. Muhasebecinin "erişim yok" olduğu modül menüde hiç görünmez. Sayfaların çoğu birbirine bağlantı verir: bir borç satırından tahsilata, bir yaşlandırma satırından daire ekstresine, bir tahsilattan makbuza tek tıkla geçilir.</div>
"""))

# ------------------------------------------------------------------ 4
c = []
c.append(page("Giriş ekranı", "—", "/login",
    "Sisteme e-posta ve şifreyle girilen ekran. Yönetici de sakin de buradan girer; sistem kullanıcının rolüne göre paneli veya sakin portalını açar.",
    actions=[
        "E-posta ve şifreyle giriş (Göster düğmesi şifreyi gösterir).",
        "<b>Şifremi unuttum:</b> e-postaya sıfırlama bağlantısı gönderir (/forgot-password).",
        "<b>İki adımlı doğrulama açıksa:</b> şifreden sonra telefondaki 6 haneli kod veya kurtarma kodu istenir.",
        "<b>Hesap aç:</b> sakinler davet koduyla kendi hesabını açar (/portal/join).",
        "Demo kurulumda 'Demo yönetici' ve 'Demo sakin' hızlı giriş düğmeleri bulunur.",
    ],
    rules=["Çok sayıda hatalı giriş denemesi geçici olarak engellenir (hız sınırı).", "Demo hesapları canlı ortamda bırakılmamalıdır; üretim kontrolü bunu engel olarak raporlar."]))
c.append(page("Genel Portföy Panosu", "⚡ logo / Genel Portföy Panosu", "/",
    "Yöneticinin eriştiği tüm sitelerin tek bakışta özeti. Patrona ilk göstereceğin ekran budur.",
    img="portfoy.jpg",
    actions=[
        "<b>Özet kartları:</b> Toplam tahakkuk, tahsil edilen, bekleyen alacak (gecikmiş dahil), toplam kasa ve banka, ödenen kayıtlar, portföy ve yapı (site, sakin, kullanıcı sayısı).",
        "<b>Portföy Gelir ve Tahakkuk Özeti grafiği:</b> Tüm sitelerde günlük tahsilat, tahakkuk ve gider. Sağ üstten 30 gün / 12 ay seçilir.",
        "<b>Portföy Operasyonel Uyarılar:</b> Onay bekleyen gider, eşleşmemiş banka satırı, icra takibindeki borç, onay bekleyen rezervasyon, kritik stok kalemi, açık destek talepleri. Her satır ilgili sayfaya götürür.",
        "Alt bölümde: Yönetilen Siteler, Son İşlemler ve Kullanıcılar sekmeleri.",
        "<b>Konut Seç / Ekle</b> ile siteye geç, <b>Yeni Tahakkuk</b> ile borç yazma ekranına git.",
    ],
    links=["Konut &amp; Siteler", "Site Finans Panosu", "Sistem Kullanıcıları"],
    tip="\"Beş sitenin toplam tahsilatını, borcunu, kasasını ve bekleyen işlerini tek ekranda görüyorum.\""))
c.append(page("Site Finans Panosu", "Sol menü / Site Finans Panosu", S + "",
    "Seçili tek sitenin yönetim ve muhasebe panosu. Bir siteye girince ilk açılan sayfadır.",
    img="site-pano.jpg",
    actions=[
        "<b>Kartlar:</b> Toplam tahakkuk (tahsil oranıyla), tahsil edilen, bekleyen alacak (geciken kısmı), kasa ve banka mevcudu (avans bilgisiyle), dönem giderleri (onay bekleyen sayısı), daire ve doluluk.",
        "<b>Gelir ve Tahakkuk Özeti:</b> Günlük tahsilat, tahakkuk ve gider grafiği; altında dönem tahsilatı, dönem gideri, net nakit akışı ve 7 gün içinde vadesi gelen borç.",
        "<b>Tahsilat Hunisi &amp; Kasa:</b> Borç kaydı → vadesi gelen → ödemesi başlayan → tamamen kapanan; altında kasa ve banka hesapları ve 'Virman / Ekle' kısayolu.",
        "<b>Son Hareketler</b> ve <b>Borçlu Daireler</b> listeleri.",
        "Üstte hızlı düğmeler: <b>Tahsilat Al</b>, <b>Gider Gir</b>, <b>Yeni Tahakkuk</b>.",
    ],
    links=["Tahsilat", "Gider Yönetimi", "Borçlandırma", "Kasa &amp; Banka"]))
c.append(page("Hesabım: şifre ve iki adımlı doğrulama", "Sağ üst hesap menüsü", "/account/password · /account/two-factor",
    "Kullanıcının kendi güvenlik ayarları.",
    actions=[
        "<b>Şifremi Değiştir:</b> mevcut şifre, yeni şifre (en az 8 karakter) ve tekrarı.",
        "<b>İki Adımlı Doğrulama:</b> Kuruluma başla → telefonda Google Authenticator vb. ile QR kodu okut (okutamazsan anahtarı elle gir) → 6 haneli kodla onayla. Açıldıktan sonra kurtarma kodları gösterilir; yenilenebilir. Kapatmak için mevcut şifre istenir.",
    ],
    rules=["Telefonunu ve kurtarma kodlarını kaybeden yöneticinin iki adımlı doğrulaması sunucudan komutla kapatılabilir (<code>php artisan app:disable-two-factor e-posta</code>)."]))
parts.append(chapter(4, "Giriş, hesap ve panolar", "Sisteme giriş ve ilk karşılaşılan ekranlar.", "".join(c)))

# ------------------------------------------------------------------ 5
c = []
c.append(page("Konut &amp; Siteler", "Yapı, Daire &amp; Sakinler → Konut &amp; Siteler", "/sites",
    "Erişebildiğin bütün siteler kart olarak listelenir; yeni site buradan açılır.",
    img="siteler.jpg",
    actions=[
        "Her kartta: site kodu, adı, şehri, blok / daire / sakin sayısı, tahsilat oranı, gecikmiş borç, kasa ve açık talep sayısı. Karta tıklayınca Site Finans Panosu açılır.",
        "<b>+ Yeni site oluştur:</b> Site adı, site kodu (davet kodlarında kullanılır, ör. YVADI), şehir, bloklar (virgülle: A,B,C) ve adres.",
    ],
    rules=["Site oluşunca 'Merkez Kasa' ve standart gelir / gider kategorileri otomatik eklenir.", "Oluşturan kişi sitenin sahibi ve yöneticisi olur."]))
c.append(page("Blok, Daire &amp; Gruplar", "Yapı, Daire &amp; Sakinler → Blok, Daire &amp; Gruplar", S + "/units",
    "Sitenin fiziksel yapısı: bloklar, daireler ve daire grupları. Sistemin merkezi dairedir; borç kişiye değil daireye yazılır.",
    img="daireler.jpg",
    actions=[
        "<b>Özet:</b> toplam daire, doluluk, toplam alan (m²), toplam arsa payı, borçlu daire, avanslı daire; bloklara ve kullanım türüne göre dağılım; en borçlu daireler.",
        "<b>Bloklar:</b> blok ekle, adını düzenle, sil.",
        "<b>Daire Ekle:</b> blok, daire no, kat, brüt m², kullanım (konut, işyeri…), durum (dolu / boş), arsa payı.",
        "<b>Toplu ekle:</b> blok, ilk no ve son no girip tek seferde onlarca daire oluştur (m² isteğe bağlı).",
        "<b>Daireler listesi:</b> her daireyi düzenle veya sil; daire adına tıklayınca daire detayı açılır.",
        "<b>Daire Grupları:</b> ör. 'Dükkanlar', 'Çatı katları'. Borç dağıtırken hedef olarak seçilebilir.",
    ],
    rules=[
        "Açık borcu, avans bakiyesi veya kayıtlı sakini olan daire silinemez.",
        "m² ve arsa payı, m² başına ve arsa payına göre aidat hesabında kullanılır; boş bırakılan daire o tanımda atlanır ve raporlanır.",
    ],
    links=["Daire detayı (sakinler, borçlar, ödemeler)", "Daire Hesap Ekstresi", "Tahsilat"]))
c.append(page("Daire detayı", "Daire listesinde daireye tıkla", S + "/units/{daire}",
    "Tek dairenin kimlik kartı.",
    actions=["Dairedeki sakinler, dairenin bütün borçları ve ödemeleri.", "Kısayollar: bu daireden tahsilat al, hesap ekstresini aç."]))
c.append(page("Sakin &amp; Üye Listesi", "Yapı, Daire &amp; Sakinler → Sakin &amp; Üye Listesi", S + "/members",
    "Dairelerde oturan mülk sahipleri ve kiracılar.",
    img="sakinler.jpg",
    actions=[
        "<b>Özet:</b> toplam sakin, mülk sahibi, kiracı, mobil bağlı oranı, iletişimsiz (telefon ve e-postası olmayan), bu ay eklenen.",
        "<b>+ Yeni üye ekle:</b> daire, tür (Mülk Sahibi / Kiracı), ad soyad, telefon, e-posta, daireye giriş tarihi, TC kimlik no, acil durum telefonu, adres, notlar.",
        "Eklenen her üyeye otomatik <b>davet kodu</b> üretilir (ör. YVADI-A1-SJYAQ2F3). Sakin bu kodla portalda veya mobil uygulamada kendi hesabını bağlar.",
        "Filtre: tür, blok, mobil durumu; ad, telefon, e-posta ile arama. Satırda <b>Detay</b>.",
    ],
    links=["Üye detayı", "Sakin &amp; Taşınma Geçmişi"]))
c.append(page("Üye detayı", "Sakin listesinde Detay", S + "/members/{üye}",
    "Tek sakinin bilgileri ve mobil erişimi.",
    actions=[
        "Bilgileri düzenle ve kaydet.",
        "<b>Mobil erişim:</b> 'Yeni davet kodu üret' (eski kod geçersiz olur) ve 'Mobil bağlantıyı kaldır' (sakinin hesapla bağı kopar, yeni kod üretilir).",
        "<b>Geçmişe taşı (daireden ayrıldı / devir):</b> ayrılış tarihi ve neden. Üye silinmez, geçmişe taşınır; mobil erişimi hemen biter.",
        "<b>Kalıcı olarak sil:</b> yalnızca hiçbir tahsilatta 'ödeyen' olarak geçmiyorsa.",
    ],
    rules=["Sakin taşınsa bile eski borçlar dairede kalır; yeni sakin dairenin hesabını devralır."]))
c.append(page("Sakin &amp; Taşınma Geçmişi", "Yapı, Daire &amp; Sakinler → Sakin &amp; Taşınma Geçmişi", S + "/member-history",
    "Daireden ayrılan veya devreden eski sakinlerin listesi.",
    actions=["<b>Geri al:</b> yanlışlıkla geçmişe taşınan üyeyi geri getirir.", "<b>Kalıcı sil:</b> tahsilatta ödeyen olarak geçmiyorsa tamamen siler."]))
c.append(page("Sistem Kullanıcıları", "Yapı, Daire &amp; Sakinler → Sistem Kullanıcıları", "/users",
    "Sisteme giriş yapan hesaplar. Yönetici yalnızca kendi sitelerinin sakinlerini ve yöneticilerini görür; süper admin herkesi görür.",
    actions=[
        "Filtrele ve listele; kullanıcıya tıklayınca detay: yetki, yönettiği siteler, daireleri, borçları.",
        "<b>Yalnızca süper admin:</b> kullanıcı oluştur (ad, e-posta, şifre, rol, sakinse blok/daire ve sakin türü), rolünü değiştir, bilgilerini güncelle, kullanıcıyı sil.",
    ]))
c.append(page("Emlak Portföyü", "Yapı, Daire &amp; Sakinler → Emlak Portföyü", "/properties",
    "Satılık / kiralık emlak ilanlarının kaydı. Muhasebeden bağımsız ek bir modüldür.",
    actions=["İlan ekle: başlık, kategori, durum, fiyat, konum, video bağlantısı, açıklama, özellikler, en fazla 10 görsel (her biri 5 MB), danışman ve telefonu.", "Filtrele, düzenle, sil."]))
parts.append(chapter(5, "Yapı, Daire &amp; Sakinler", "Her şeyin temeli: site → blok → daire → sakin.", "".join(c)))

# ------------------------------------------------------------------ 6
c = []
c.append(page("Aidat &amp; Otomatik Tahakkuk", "Tahakkuk, Aidat &amp; Sayaç → Aidat &amp; Otomatik Tahakkuk", S + "/dues",
    "Aidat motoru. Bir kez tanım yaparsın, sistem her ay ilgili dairelere borcu kendisi yazar.",
    img="aidat.jpg",
    actions=[
        "<b>Özet:</b> aktif tanım sayısı, aylık beklenen, bu dönem tahakkuk ve tahsilat, son çalıştırma dönemi, otomatik üretim açık/kapalı; son 6 dönem tahakkuk-tahsilat grafiği.",
        "<b>Aidat / borç tanımı:</b> ad (ör. Aidat, Yakıt Avansı), <b>hesaplama</b> (Sabit tutar daire başı · m² başına · Arsa payına göre: toplam tutar dairelere bölünür), sorumlu (mülk sahibi / kiracı / daire geneli), tutar, son ödeme günü (ayın kaçı), hedef (tüm site / blok / tek daire), kategori.",
        "Tanımı düzenle, aktif/pasif yap, sil.",
        "<b>Dönem borçlarını üret:</b> dönemi (ay) seç → <b>Aktif tanımları çalıştır</b>. 'Bu dönemde silinen borçları da yeniden yaz' işaretlenmezse silinmiş borçlar geri gelmez.",
        "<b>Aidatları her gece otomatik üret:</b> açılırsa sistem her gece o ayın borçlarını yazar; ay içinde eklenen daireler de borçlanır.",
    ],
    steps=["Tanımı oluştur (ör. Aidat, Sabit, 1.200 TL, vade günü 20, hedef tüm site).", "Dönemi seç ve 'Aktif tanımları çalıştır'.", "Sonuç mesajında kaç borç yazıldığını, kaçının zaten var olduğu için atlandığını gör.", "İstersen otomatik üretimi aç; bir daha elle çalıştırmana gerek kalmaz."],
    rules=[
        "<b>Mükerrer olmaz:</b> Aynı dönemi tekrar çalıştırmak ikinci borç yazmaz (her borcun dönem + tanım + daire anahtarı vardır).",
        "Tanımda yapılan değişiklik sonraki üretimlerde geçerlidir; yazılmış borçlar değişmez.",
        "Kayıtlı ayın günü kısa aya denk gelirse (ör. 31 Şubat) ayın son gününe çekilir.",
        "Dairenin avansı varsa yeni borçtan otomatik düşülür.",
        "Kapalı döneme aidat üretilemez.",
    ],
    tip="\"Aidatı her ay elle yazmıyoruz; tanımı bir kez yapıyoruz, gece kendiliğinden yazılıyor. İki kez çalışsa bile mükerrer borç oluşmuyor.\""))
c.append(page("Borçlandırma &amp; Taksit / İcra", "Tahakkuk, Aidat &amp; Sayaç → Borçlandırma &amp; Taksit / İcra", S + "/debts",
    "Aidat dışı tek seferlik borçlar (ek bütçe, tamirat payı, demirbaş katkısı) ve bütün borç kayıtlarının yönetimi.",
    img="borclar.jpg",
    actions=[
        "<b>Özet:</b> açık borç, gecikmiş, 30 gün içinde vadeli, tahsil oranı, icra takibinde, toplam tahakkuk; kategoriye ve bloğa göre açık borç; en borçlu daireler.",
        "<b>+ Borç dağıt:</b> hedef (tüm daireler / blok / grup / tek daire), kategori, açıklama, daire başı tutar, sorumlu, son ödeme tarihi → <b>Borçları oluştur</b>. Dairenin avansı varsa borçtan otomatik düşülür.",
        "<b>Borç kayıtları:</b> daire, durum, açıklama, blok, tür, kategori, tarih aralığı ve 'İcradakiler' ile filtre.",
        "Satır işlemleri: <b>Düzenle</b> (açıklama, vade, tutar, kategori, sorumlu), <b>Sil</b>, <b>Taksitlendir</b> (taksit sayısı 2-36, ilk taksit tarihi, aralık 1-12 ay), <b>İcra</b> işaretle / kaldır (not ile).",
    ],
    rules=[
        "Ödeme alınmış borcun yalnızca açıklaması ve vadesi değişir; tutar ve kategori değiştirilemez.",
        "Tahsilat yapılmış borç silinemez; önce tahsilat iptal edilmelidir.",
        "Silinen borç için ters yevmiye kaydı atılır. Borca bağlı gecikme zamları ve taksitler de birlikte silinir.",
        "Aidat borcu silinirse o dönem + daire işaretlenir; aidat motoru onu yeniden üretmez.",
        "Taksitlendirmede ödenmemiş kısım kapatılır, eşit taksitler (kuruş kaybı olmadan) yeni borç olarak yazılır.",
        "Gecikme zammı taksitlendirilemez ve düzenlenemez (gerekirse silinir).",
    ],
    links=["Tahsilat", "Gider Yönetimi (daireye yansıtılan giderler)", "Alacak Yaşlandırma"]))
c.append(page("Gecikme Zammı Motoru", "Tahakkuk, Aidat &amp; Sayaç → Gecikme Zammı Motoru", S + "/late-fees",
    "Vadesi geçen borçlara aylık gecikme tazminatı hesaplar ve borç olarak yazar.",
    img="gecikme.jpg",
    actions=[
        "<b>Ayarlar:</b> 'Gecikme zammı uygulansın' (kapalıyken hiç hesaplanmaz), 'Her gece otomatik yazılsın', aylık oran (%), ek süre (gün). Not: Kat Mülkiyeti Kanunu m.20'ye göre aylık %5.",
        "<b>Yazılacak gecikme zammı:</b> hesap tarihi seç → <b>Önizle</b> → daire daire yazılacak kalemleri gör → <b>Hesapla ve borç olarak yaz</b>.",
        "Özet: yazılan zam, tahsil edilen, kalan, bu ay yazılan, zam alan daire, otomatik yazım durumu.",
    ],
    rules=[
        "Vade + ek süre geçtikten sonra tamamlanan her ay için bir zam yazılır: tutar = o anki kalan borç × aylık oran.",
        "Aynı borcun aynı ayı için ikinci kez zam yazılmaz.",
        "Zamma zam işlemez; yalnızca aidat ve taksit borçlarına uygulanır.",
        "Silinen bir zam kalemi bir daha yazılmaz (affedilmiş sayılır).",
    ]))
c.append(page("Sayaç &amp; Endeks Okuma", "Tahakkuk, Aidat &amp; Sayaç → Sayaç &amp; Endeks Okuma", S + "/meters",
    "Su, elektrik, doğalgaz, ısınma sayaçları ve dönemsel okumalar.",
    actions=[
        "Sayaç ekle: ad, tür, bağlı daire (boşsa ortak alan), seri no, ilk endeks.",
        "Okuma gir: yeni endeks, tarih, not. Tüketim önceki okumadan otomatik hesaplanır.",
        "Okumayı düzenle / sil, sayacı düzenle / sil.",
    ],
    links=["Sayaç Faturası Dağıtımı (tüketime göre dağıtım bu okumaları kullanır)"]))
c.append(page("Sayaç Faturası Dağıtımı", "Tahakkuk, Aidat &amp; Sayaç → Sayaç Faturası Dağıtımı", S + "/meter-bills",
    "Ortak su, ısınma veya doğalgaz faturasını dairelere paylaştırıp her payı borç olarak yazar.",
    img="sayac-fatura.jpg",
    steps=[
        "Sayaç türü, dağıtım yöntemi (<b>tüketime göre</b> / <b>m²'ye göre</b> / <b>eşit</b>), dönem başlangıcı ve bitişi, fatura tutarı, açıklama ve son ödeme tarihini gir.",
        "<b>Önizle:</b> her dairenin ağırlığını ve payını gör.",
        "<b>Dağıt ve borç yaz:</b> paylar dairelere borç olarak yazılır.",
        "Hata varsa 'Geçmiş dağıtımlar'dan <b>İptal et</b>.",
    ],
    rules=[
        "Paylar kuruşu kuruşuna fatura tutarına eşittir (yuvarlama kaybı yoktur).",
        "'Tüketime göre' için sayaçların daireye bağlı olması ve dönem içinde okuma girilmiş olması gerekir.",
        "Dairelerden biri bile ödeme yaptıysa dağıtım iptal edilemez (hiçbir pay silinmez).",
    ]))
parts.append(chapter(6, "Tahakkuk, Aidat &amp; Sayaç", "Dairelere borç yazmanın bütün yolları: düzenli aidat, tek seferlik borç, gecikme zammı, sayaç faturası, ayrıca giderin daireye yansıtılması (Bölüm 9) ve ücretli rezervasyon (Bölüm 10).", "".join(c)))

# ------------------------------------------------------------------ 7
c = []
c.append(page("Tahsilat &amp; Makbuz Kesimi", "Tahsilat, Kasa &amp; Banka → Tahsilat &amp; Makbuz Kesimi", S + "/collections",
    "Sakinden para alma ekranı. En çok kullanılan sayfadır.",
    img="tahsilat.jpg",
    steps=[
        "<b>1. Daire seçin:</b> listeden daireyi seç. Açık borçları listelenir.",
        "<b>2. Tahsilatı kaydedin:</b> İstersen <b>Ödeyen</b> sakini seç, <b>öncelikli borcu</b> seç veya borçların yanına tutar yaz ('Tamamı' düğmesi kalanın tamamını yazar).",
        "Ödeme parçasını gir: kasa / banka, tutar, döviz kasasıysa kur, ödeme şekli (nakit, havale, POS…). Farklı kasalara bölünmüş ödeme için <b>+ Ödeme parçası ekle</b>.",
        "Açıklama yaz → <b>Tahsilatı kaydet</b>. Makbuz otomatik oluşur.",
    ],
    actions=[
        "<b>Özet:</b> bu ay tahsilat (geçen aya göre), bugün, işlem sayısı, ortalama tahsilat, ödeme yapan daire, bu ay avansa giden; son 6 ay grafiği; kasaya göre dağılım.",
        "<b>Son tahsilatlar:</b> tarih, kasa, yöntem filtresi; her satırda makbuz ve <b>Hareketi iptal et</b> (iptal nedeni zorunlu).",
    ],
    rules=[
        "Dağıtım sırası: önce elle tutar yazılan borçlar, sonra seçilen öncelikli borç, sonra en eski vadeli borçlar. Artan para TL avans olur.",
        "Ödeyen sakin seçilirse otomatik dağıtım yalnızca onun sorumlu olduğu ve daire geneli borçlara yapılır (kiracı ödüyorsa mülk sahibinin borcuna yazılmaz).",
        "Parçalardan biri hatalıysa hiçbiri kaydedilmez.",
        "Döviz kasasına giren para, girilen kurla TL karşılığıyla borca yazılır.",
        "İptalde ödenen borçlar yeniden açılır, avans geri alınır; avans başka borçta kullanılmışsa önce o borç iptal edilmelidir.",
    ],
    links=["Makbuz", "Daire Hesap Ekstresi", "Kasa &amp; Banka"],
    tip="\"Tahsilatı daireyi seçip tutarı yazarak tek ekrandan alıyoruz; sistem parayı en eski borçtan kapatıyor, fazlasını avansa atıyor, makbuzu kendisi kesiyor.\""))
c.append(page("Makbuz", "Tahsilat listesinde, ekstrede, kasa hareketinde 'Makbuz'", S + "/transactions/{hareket}/receipt",
    "Tahsilat makbuzu. Numarası M-000123 biçimindedir.",
    actions=["<b>Yazdır / PDF:</b> tarayıcıdan yazdırılır veya PDF olarak kaydedilir.", "Makbuzda tutar yazıyla, düzenleyen kişi ve site adı yer alır."]))
c.append(page("Kasa &amp; Banka Hesapları", "Tahsilat, Kasa &amp; Banka → Kasa &amp; Banka Hesapları", S + "/cashboxes",
    "Sitenin bütün kasaları ve banka hesapları; borçla ilişkisiz gelir-gider kaydı, virman ve kategoriler.",
    img="kasa.jpg",
    actions=[
        "<b>Özet:</b> toplam likidite, nakit, banka, bu ay giren / çıkan / net akış; son 12 ay nakit akışı grafiği; kasa bakiyeleri.",
        "<b>Kasa kartları:</b> her kasada bakiye, Düzenle, Pasife al, Sil.",
        "<b>Gelir / gider kaydı:</b> borçla ilgisi olmayan hareketler (ör. otopark kirası geliri, banka masrafı): tür, kasa, kategori, tutar, kur, açıklama.",
        "<b>Virman (kasalar arası):</b> kaynak kasa, hedef kasa, çıkan tutar; para birimleri farklıysa hedef kasaya giren tutar (döviz bozdurma / alma).",
        "<b>Yeni kasa / banka hesabı:</b> ad, tür (nakit / banka), para birimi (TRY, USD, EUR…), IBAN / hesap no, açılış bakiyesi ve döviz için açılış kuru.",
        "<b>Kategoriler:</b> gelir / gider kategorisi ekle, adını değiştir, kullanılmamışsa sil.",
        "<b>Kasa hareketleri:</b> tüm giriş-çıkışlar; satırdan makbuz ve iptal.",
    ],
    rules=[
        "Kasa eksiye düşemez (Site Ayarları'ndan 'Kasa bakiyesi eksiye düşebilsin' açılmadıkça). Döviz kasası hiçbir zaman eksiye düşmez.",
        "Bakiyesi olan kasa pasife alınamaz; son aktif kasa kapatılamaz; hareket görmüş kasa silinemez (pasife alınır).",
        "Virman iptal edilemez; ters yönde yeni virman yapılır.",
        "Döviz kasasının TL değeri ağırlıklı ortalama maliyetle tutulur; bozdururken oluşan fark 'kur farkı' olarak ayrıca kaydedilir.",
        "Kullanılmış kategori silinemez, yalnızca adı değiştirilir.",
    ]))
c.append(page("Banka Ekstresi Eşleştirme", "Tahsilat, Kasa &amp; Banka → Banka Ekstresi Eşleştirme", S + "/bank",
    "Bankadan indirilen ekstreyi (CSV) yükleyip satırları tahsilata, gelire veya gidere dönüştürür; banka ile kasa defterini karşılaştırır.",
    img="banka.jpg",
    steps=[
        "Bankanın internet şubesinden hesap hareketlerini Excel olarak indir, 'CSV olarak kaydet'.",
        "<b>Banka hesabını</b> ve <b>CSV dosyasını</b> seç → <b>Yükle</b>. Başlık satırında Tarih, Açıklama ve Tutar (veya Giriş/Çıkış) sütunları olmalıdır.",
        "Açıklamasında daire (A-5, Daire 5) veya sakin adı geçen gelen satırlar otomatik o daireyle eşleşir.",
        "<b>Bekleyen</b> sekmesinde her satır için: gelen paraysa daire seç → <b>Tahsil et</b> (daire yoksa kategori seç → <b>Gelir yaz</b>); giden paraysa kategori seç → <b>Gider yaz</b> veya <b>Mevcut gidere bağla</b> ile bekleyen bir faturayı öde; ilgisizse <b>Yok say</b>.",
        "Eşleşen bütün gelen satırları tek seferde işlemek için <b>Eşleşenleri toplu işle</b>.",
    ],
    actions=[
        "<b>Durum kutusu:</b> bekleyen, işlenen, yok sayılan satır sayısı ve tutarı.",
        "<b>Ekstrede karşılığı olmayan kasa hareketleri:</b> ekstre tarihlerinde kasada kayıtlı ama bankada görünmeyen hareketler; eksik veya hatalı giriş olabilir.",
        "İşlenen satırdan makbuza gidilir.",
    ],
    rules=[
        "Aynı ekstreyi tekrar yüklemek mükerrer satır oluşturmaz.",
        "Satır, bankadaki kendi tarihiyle kaydedilir; kapalı döneme düşen satır işlenmez.",
        "Mevcut gidere bağlanan satır yeni gider açmaz, faturayı öder (çift gider olmaz).",
        "İşlenmiş bir hareket iptal edilirse banka satırı yeniden 'bekleyen' olur.",
    ]))
c.append(page("Avans &amp; Kredi Mahsup", "Tahsilat, Kasa &amp; Banka → Avans &amp; Kredi Mahsup", S + "/advances",
    "Fazla ödeme yapmış (avansı olan) daireler.",
    img="avans.jpg",
    actions=[
        "Özet: toplam avans, avanslı daire, ortalama, mahsup edilebilir (açık borcu da olan) daire.",
        "<b>Borca mahsup et:</b> dairenin avansını açık borçlarına en eski vadeden başlayarak düşer.",
        "<b>İade et:</b> avansı TL kasadan sakine geri öder.",
    ],
    rules=["Yeni borç yazılınca avans zaten otomatik düşülür; bu sayfa elle mahsup ve iade içindir.", "İade avansı aşamaz ve yalnızca TL kasadan yapılır."]))
c.append(page("Finansal Özet &amp; Virman", "Tahsilat, Kasa &amp; Banka → Finansal Özet &amp; Virman", S + "/finance",
    "Finansın tek sayfalık özeti.",
    actions=["Kasalar ve bakiyeleri, tahsilat performansı, onay bekleyen giderler ve son hareketler.", "Kartlardan Kasa &amp; Banka (virman burada yapılır), Tahsilat, Borçlandırma ve Gider sayfalarına geçilir."]))
parts.append(chapter(7, "Tahsilat, Kasa &amp; Banka", "Paranın girdiği ve durduğu yerler.", "".join(c)))

# ------------------------------------------------------------------ 8
c = []
c.append(page("Gelir - Gider Tablosu &amp; Kategoriler", "Muhasebe, Defter &amp; Bütçe → Gelir - Gider &amp; Kategoriler", S + "/income-expense",
    "Seçilen tarih aralığında toplam gelir, toplam gider ve net sonuç.",
    img="gelir-gider.jpg",
    actions=[
        "Tarih aralığı veya hızlı seçim: Bu ay, Geçen ay, Bu yıl, Geçen yıl.",
        "Kartlar: toplam gelir, toplam gider, net sonuç, gider / gelir oranı.",
        "Gelir kaynakları ve gider kalemleri (kategoriye göre), önceki dönemle karşılaştırma, aylara göre dağılım.",
        "<b>Excel'e aktar</b> ve <b>Yazdır / PDF</b>.",
    ]))
c.append(page("Daire Hesap Ekstresi", "Muhasebe, Defter &amp; Bütçe → Daire Hesap Ekstreleri", S + "/statements",
    "Tek dairenin banka ekstresi gibi tüm hareketleri.",
    actions=["Daire seç → <b>Göster</b>. Borçlar, ödemeler, avans hareketleri tarih sırasıyla ve yürüyen bakiyeyle.", "Satırlardan makbuza; üstten tahsilat ekranına geçilir. Sakin de aynı ekstreyi portalda görür."]))
c.append(page("Yevmiye Defteri", "Muhasebe, Defter &amp; Bütçe → Yevmiye Defteri", S + "/ledger",
    "Çift taraflı muhasebe kayıtlarının tamamı. Kayıtlar yalnızca eklenir, değiştirilemez.",
    img="yevmiye.jpg",
    actions=[
        "Filtre: hesap (Kasa/Banka, Üye/Daire Hesabı, Üye Avans Hesabı, Gelir, Gider, Açılış, Kur Farkı), işlem türü, arama, tarih aralığı.",
        "Kartlar: kayıt sayısı, bu ay borç ve alacak, kullanılan hesap; <b>Denge</b> kutusu toplam borç = toplam alacak ise 'Dengede ✓' gösterir.",
        "<b>+ Elle yevmiye fişi:</b> tarih, açıklama ve satırlar (gelir / gider kategorisi, kur farkı, açılış hesapları; her satırda yalnızca borç veya yalnızca alacak). Fiş dengede değilse kaydedilmez.",
        "Elle fişi iptal et (neden zorunlu). Excel / PDF çıktısı.",
    ],
    rules=["Kasa ve daire hesaplarına elle fiş kesilemez; onlar kendi ekranlarındaki işlemlerle oluşur.", "Elle fiş mizan ve yevmiyede görünür; kasa bakiyesini değiştirmez."]))
c.append(page("Kasa Defteri", "Muhasebe, Defter &amp; Bütçe → Kasa Defteri", S + "/accounting/cash-book",
    "Seçilen kasanın açılış bakiyesi, dönem içi giriş-çıkışları ve kapanış bakiyesi.",
    actions=["Kasa / banka ve tarih seç; her satırdan makbuza git; yazdır ve Excel."]))
c.append(page("Mizan Tablosu", "Muhasebe, Defter &amp; Bütçe → Mizan Tablosu", S + "/accounting/trial-balance",
    "Hesap bazında borç ve alacak toplamları ile bakiyeler. Muhasebecinin 'defter dengede mi' kontrolü.",
    links=["Yevmiye Defteri"]))
c.append(page("Mali Durum Tablosu (Bilanço)", "Muhasebe, Defter &amp; Bütçe → Mali Durum / Bilanço", S + "/accounting/balance-sheet",
    "Seçilen günün sonu itibarıyla sitenin varlıkları, yükümlülükleri ve net varlığı.",
    img="bilanco.jpg",
    actions=[
        "Tarih itibarıyla ve karşılaştırma tarihi seç → <b>Göster</b>.",
        "<b>Varlıklar:</b> kasa ve banka, daire alacakları (gecikmiş kısmıyla), demirbaşların net değeri.",
        "<b>Yükümlülükler:</b> daire avansları (iade veya mahsup edilecek), ödenecek giderler (girilmiş ama ödenmemiş faturalar).",
        "Net varlık, cari oran, gecikmiş alacak payı, nakit / varlık oranı.",
    ],
    rules=["Kasa, alacak ve avans yevmiyeden hesaplanır; bu yüzden geçmiş bir tarih için de doğrudur."]))
c.append(page("Alacak Yaşlandırma", "Muhasebe, Defter &amp; Bütçe → Alacak Yaşlandırma", S + "/accounting/aging",
    "Kim, ne kadar süredir, ne kadar borçlu?",
    img="yaslandirma.jpg",
    actions=[
        "Filtre: blok, 'Yalnızca icradakiler', tarih.",
        "Dilimler: vadesi gelmemiş, 1-30 gün, 31-60, 61-90, 90+ gün. Her daire için dilim tutarları ve toplam.",
        "Satırdan <b>Ekstre</b> ve <b>Tahsil et</b> kısayolları.",
    ],
    tip="\"Hangi dairenin kaç gündür borçlu olduğunu tek tabloda görüyoruz; 90 günü geçenler icraya aday.\""))
c.append(page("Bütçe &amp; Gerçekleşen", "Muhasebe, Defter &amp; Bütçe → Bütçe &amp; Gerçekleşen", S + "/budget",
    "Yıllık bütçe planı ve gerçekleşme karşılaştırması.",
    img="butce.jpg",
    actions=[
        "Yıl seç; geçmiş yıllarla karşılaştırmak için yılları işaretle.",
        "Kategori bazında planlanan tutarları gir → <b>Bütçeyi kaydet</b>. <b>Önceki yılı kopyala</b> ile hızlı başla.",
        "Gelir / gider gerçekleşme oranları, planlanan ve gerçekleşen net, bütçeyi aşan kalemler, aylık gelir-gider, yıllara göre özet ve değerlendirme.",
    ],
    rules=["Boş bırakılan veya 0 girilen kategori bütçeden çıkarılır.", "Gelirde gerçekleşen = borçlara yapılan tahsilat + avanstan mahsup edilen tutar."]))
parts.append(chapter(8, "Muhasebe, Defter &amp; Bütçe", "Mali tablolar. Bu sayfalarda veri girilmez (elle fiş ve bütçe hariç); diğer modüllerde yapılan işlemler buraya kendiliğinden yansır.", "".join(c)))

# ------------------------------------------------------------------ 9
c = []
c.append(page("Gider &amp; Fatura Onayı", "Gider, Tedarikçi &amp; İK → Gider &amp; Fatura Onayı", S + "/expenses",
    "Apartmanın ödediği faturalar: asansör bakımı, elektrik, temizlik, tamirat, maaş.",
    img="gider.jpg",
    steps=[
        "<b>+ Gider / fatura gir:</b> firma, kategori, açıklama, fatura no, tutar (KDV hariç), KDV oranı (%0, 1, 10, 20), fatura tarihi.",
        "Hemen ödendiyse <b>Hemen öde</b> ile kasayı seç; değilse gider 'ödeme bekleyen' olarak kalır.",
        "Fatura bir daireden alınacaksa (ör. o dairenin kırdığı cam) <b>daireye yansıt</b>: daire, daireden alınacak tutar, sorumlu, son ödeme tarihi. Daireye borç yazılır.",
        "Bekleyen gideri ödemek için satırdaki <b>Öde</b>: kasa, tutar (boşsa kalanın tamamı; daha azı kısmi ödeme), döviz kasasında kur.",
    ],
    actions=[
        "<b>Özet:</b> onay bekleyen, bu ay ödenen, bu yıl ödenen, bu yıl KDV, aylık ortalama, fatura sayısı; kategoriye ve firmaya göre dağılım.",
        "Filtre: firma, kategori, tarih, arama; sekmeler: Tümü, Ödeme bekleyen, Kısmi ödenen, Ödendi.",
    ],
    rules=[
        "Ödenmiş veya kısmi ödenmiş gider düzenlenemez ve silinemez; önce ödeme iptal edilir.",
        "Ödeme kasadan düşer; kasada para yoksa reddedilir.",
        "Döviz kasasından ödemede, ortalama maliyet ile güncel kur arasındaki fark kur farkı olarak yazılır.",
        "Daireye yansıtılmış gider silinirse, ödenmemiş yansıtma borcu da silinir.",
    ],
    links=["Banka Ekstresi (giden satırı faturaya bağlama)", "Tedarikçiler", "Personel Bordrosu"]))
c.append(page("Tedarikçi &amp; Cari Hesaplar", "Gider, Tedarikçi &amp; İK → Tedarikçi &amp; Cari Hesaplar", S + "/vendors",
    "Çalışılan firmalar.",
    actions=["Firma ekle / düzenle: ad, telefon, vergi dairesi, vergi no, e-posta, adres.", "Her firmaya ödenen toplam görünür.", "Sil = pasife alma; geçmiş giderlerdeki bağlantı korunur."]))
c.append(page("Personel &amp; Görevliler", "Gider, Tedarikçi &amp; İK → Personel &amp; Görevliler", S + "/staff",
    "Kapıcı, güvenlik, temizlik görevlisi vb.",
    actions=["Ekle / düzenle / sil: ad soyad, görev, durum (Aktif / Pasif), telefon, e-posta, maaş, başlangıç tarihi."]))
c.append(page("Personel Maaş &amp; Bordro", "Gider, Tedarikçi &amp; İK → Personel Maaş &amp; Bordro", S + "/payroll",
    "Aylık maaşları gider olarak üretir ve öder.",
    steps=["Dönemi seç → <b>… bordrosunu oluştur</b>. Aktif ve maaşı girilmiş her personel için 'Personel' kategorisinde bir gider oluşur.", "Her satırda <b>Öde</b> ile kasayı seçip maaşı öde."],
    rules=["Aynı personel ve dönem için ikinci kayıt yazılmaz.", "Kapalı döneme bordro oluşturulamaz."]))
parts.append(chapter(9, "Gider, Tedarikçi &amp; İK", "Paranın çıktığı yerler.", "".join(c)))

# ------------------------------------------------------------------ 10
c = []
c.append(page("Görev &amp; Bakım Takibi", "Saha, Envanter &amp; Güvenlik → Görev &amp; Bakım Takibi", S + "/tasks",
    "Yönetimin yapılacaklar listesi.",
    actions=["Görev oluştur: başlık, sorumlu, son tarih, öncelik, kategori, açıklama.", "Durumunu güncelle (Bekliyor / Devam Ediyor / Tamamlandı), sil. Öncelik: Düşük / Orta / Yüksek; kategori: Bakım, Temizlik, Güvenlik, Diğer. Geciken görevler raporlarda görünür."]))
c.append(page("Stok &amp; Sarf Malzeme", "Saha, Envanter &amp; Güvenlik → Stok &amp; Sarf Malzeme", S + "/inventory",
    "Temizlik malzemesi, ampul, yedek parça stoğu.",
    actions=["Kalem ekle: ad, kategori, miktar, birim, kritik seviye, konum.", "Stok giriş / çıkış uygula; düzenle, sil.", "Kritik seviyenin altına düşen kalemler panodaki uyarılarda görünür."]))
c.append(page("Demirbaş &amp; Zimmet Kaydı", "Saha, Envanter &amp; Güvenlik → Demirbaş &amp; Zimmet Kaydı", S + "/assets",
    "Jeneratör, hidrofor, kamera sistemi gibi demirbaşlar.",
    actions=["Ekle: ad, kategori, satıcı firma, alış tarihi, maliyet, hurda değeri, faydalı ömür (yıl), seri no, konum.", "Doğrusal amortismanla net değer otomatik hesaplanır; bilançoda varlık olarak görünür.", "<b>Elden çıkar</b> (satış / hurda), düzenle, sil."]))
c.append(page("Ortak Alan Rezervasyonu", "Saha, Envanter &amp; Güvenlik → Ortak Alan Rezervasyonu", S + "/reservations",
    "Toplantı salonu, havuz, tenis kortu, mangal alanı gibi ortak alanların kullanımı.",
    img="rezervasyon.jpg",
    actions=[
        "<b>Ortak alanlar:</b> alan adı, açıklama, kapasite, saatlik ücret, açılış / kapanış saati.",
        "<b>Rezervasyon oluştur:</b> alan, daire, tarih, başlangıç-bitiş saati, not.",
        "Sakinlerin portaldan yaptığı talepler 'Onay bekliyor' olarak gelir: <b>Onayla</b>, <b>Reddet</b>, <b>İptal</b>.",
        "Filtre: alan, tarih; sekmeler: Tümü, Onay bekliyor, Onaylandı, Reddedildi, İptal.",
    ],
    rules=["Ücretli alanlarda ücret, onayla birlikte daireye borç olarak yazılır.", "Aynı alanda çakışan saatlere ve alanın çalışma saatleri dışına rezervasyon yapılamaz."]))
c.append(page("Güvenlik, Ziyaretçi &amp; Kargo", "Saha, Envanter &amp; Güvenlik → Güvenlik, Ziyaretçi &amp; Kargo", S + "/security",
    "Güvenlik kulübesinin kayıt defteri.",
    actions=["Kayıt türünü seç: Kargo (Beklemede / Teslim Edildi / İptal), Otopark (Otoparkta / Ayrıldı), Ziyaretçi (İçeride / Ayrıldı): daire, kişi / açıklama, kargo firması, takip no, plaka, not.", "Durumu güncelle (ör. kargo teslim edildi), sil."]))
c.append(page("Arıza &amp; Destek Talepleri", "Saha, Envanter &amp; Güvenlik → Arıza &amp; Destek Talepleri", S + "/tickets",
    "Sakinlerden gelen arıza ve şikâyetler. Mobilden ve portaldan açılan talepler de burada görünür.",
    img="talepler.jpg",
    actions=[
        "<b>Özet:</b> açık talep, acil açık, bu ay açılan, çözülme oranı, ortalama çözüm süresi; kategoriye ve önceliğe göre dağılım.",
        "<b>+ Talep aç:</b> konu, daire (boşsa ortak alan), kategori, öncelik, açıklama.",
        "Talep satırında yazışma: sakine yanıt yaz ve fotoğraf ekle; durum (Açık / İşlemde / Çözüldü / Kapandı) ve yanıtı güncelle; talebi sil.",
        "Sekmeler: Tümü, Açık, İşlemde, Çözüldü, Kapandı.",
    ],
    tip="\"Sakin arızayı telefondan fotoğrafla bildiriyor, biz buradan yanıtlıyoruz; çözüm sürelerimizi de ölçüyoruz.\""))
parts.append(chapter(10, "Saha, Envanter &amp; Güvenlik", "Sitenin günlük işletmesi.", "".join(c)))

# ------------------------------------------------------------------ 11
c = []
c.append(page("Genel Kurul &amp; Toplantılar", "Kurul, İletişim &amp; Arşiv → Genel Kurul &amp; Toplantılar", S + "/meetings",
    "Olağan / olağanüstü genel kurul ve yönetim kurulu toplantıları.",
    img="toplanti.jpg",
    actions=[
        "<b>+ Toplantı planla:</b> başlık, tür, tarih ve saat, yer, gündem.",
        "Liste: tarih, toplantı, tür, yer, katılan daire, durum (Planlandı / Yapıldı); <b>Aç</b> ile detay.",
        "<b>Toplantı detayı:</b> bilgileri ve tutanağı düzenle; <b>Hazirun (katılım) cetveli</b> (hangi daire katıldı işaretle → Hazirunu kaydet → Hazirun cetveli yazdır); <b>Alınan kararlar</b> (karar no, konu, metin → Karar ekle); toplantıyı sil.",
    ],
    links=["Karar Defteri", "Rapor Merkezi → Hazirun Cetveli, Toplantı Katılımı"]))
c.append(page("Karar Defteri", "Kurul, İletişim &amp; Arşiv → Karar Defteri", S + "/decisions",
    "Yönetim kararlarının resmi kaydı.",
    actions=["Karar ekle: karar no, tarih, konu, karar metni, katılımcılar.", "Düzenle, sil, <b>Yazdır / PDF</b>.", "Toplantıdan eklenen kararlar da burada listelenir."]))
c.append(page("Duyuru Panosu", "Kurul, İletişim &amp; Arşiv → Duyuru Panosu", S + "/announcements",
    "Sakinlere yönelik duyurular. Aktif duyurular portalda ve mobil uygulamada görünür.",
    actions=["Yeni duyuru: başlık, öncelik, yayın bitiş tarihi, içerik → <b>Yayınla</b>.", "Düzenle, yayından kaldır / yayınla, sil. Bitiş tarihi geçen duyuru otomatik gizlenir."]))
c.append(page("Sakin Anketleri &amp; Oylama", "Kurul, İletişim &amp; Arşiv → Sakin Anketleri &amp; Oylama", S + "/polls",
    "Sakinlere soru sorup oylama yaptırma.",
    img="anket.jpg",
    actions=["Yeni anket: soru, bitiş tarihi, seçenekler (her satıra bir tane, 2-10 adet) → <b>Anketi başlat</b>.", "Her anket kartında katılım (kaç daire oy kullandı) ve sonuçlar; aç / kapat, sil."],
    rules=["Her daire bir oy kullanır.", "Oy kullanıldıktan sonra yalnızca bitiş tarihi değiştirilebilir."]))
c.append(page("Toplu Bildirim Gönderimi", "Kurul, İletişim &amp; Arşiv → Toplu Bildirim Gönderimi", S + "/notifications",
    "Seçilen dairelere uygulama içi bildirim gönderir.",
    actions=["Başlık, hedef (tüm site / blok / seçili daireler), mesaj → <b>Gönder</b>.", "Gönderilenler listesi."],
    rules=["Bildirimler portal ve mobil uygulamanın bildirim kutusunda görünür. Telefona anlık (push) bildirim için ayrıca bir servis entegrasyonu gerekir (henüz yok)."]))
c.append(page("Otomatik Borç Hatırlatıcı", "Kurul, İletişim &amp; Arşiv → Otomatik Borç Hatırlatıcı", S + "/reminders",
    "Vadesi geçmiş borcu olan dairelere hatırlatma.",
    img="hatirlatma.jpg",
    actions=[
        "Özet: gecikmiş daire, gecikmiş toplam, daire başı ortalama, e-postayla ulaşılabilir daire, bu ay hatırlatma.",
        "Liste: daire, sakin, telefon, borç sayısı, en eski vade, gecikmiş tutar; satırdan <b>Tahsil et</b>.",
        "Daireleri işaretle → isteğe bağlı 'E-posta da gönder' → <b>Seçilenlere hatırlatma gönder</b>.",
        "<b>Otomatik hatırlatma:</b> 'Gecikmiş dairelere otomatik hatırlat', 'E-posta da gönder', kaç günde bir → Kaydet. Her sabah 09:00'da kontrol edilir.",
    ]))
c.append(page("Dijital Belge Arşivi", "Kurul, İletişim &amp; Arşiv → Dijital Belge Arşivi", S + "/documents",
    "Sözleşme, fatura, tutanak, yönetim planı, sigorta poliçesi ve teknik belgeler.",
    img="belge.jpg",
    actions=[
        "Belge yükle: başlık, kategori (Sözleşme, Fatura, Karar / Tutanak, Yönetim Planı, Sigorta, Teknik, Diğer), bitiş / geçerlilik tarihi, dosya (PDF, görsel, Word / Excel, CSV, ZIP; en çok 10 MB).",
        "Süresi dolan ve 30 gün içinde dolacak belgeler sarı uyarı bandında gösterilir (ör. DASK poliçesi).",
        "Kategoriye göre filtrele, ara; tarayıcıda görüntüle, indir, sil.",
    ],
    rules=["Dosyalar herkese açık bir klasörde değil, korumalı alanda saklanır; yalnızca yetkili kullanıcı indirebilir."]))
parts.append(chapter(11, "Kurul, İletişim &amp; Arşiv", "Sakinlerle iletişim ve yönetimin resmi kayıtları.", "".join(c)))

# ------------------------------------------------------------------ 12
c = []
c.append(page("Rapor Merkezi", "Rapor, Denetim &amp; Ayarlar → Rapor Merkezi (PDF / CSV)", S + "/reports",
    "Hazır raporlar. Her rapor yazdırılabilir (PDF olarak kaydedilebilir) ve Excel'e aktarılabilir.",
    img="raporlar.jpg",
    actions=["Rapor kartında <b>Görüntüle</b> → filtrele (blok, başlangıç, bitiş) → <b>Uygula</b> → <b>Yazdır / PDF</b> veya Excel."]) +
    table(["Grup", "Raporlar"], [
        ["Listeler", "Sakin Listesi · Hazirun Cetveli (imzalı katılım listesi) · Bakiye Durum Listesi"],
        ["Tahsilat ve Borç", "Detaylı Borç Raporu · Vadesi Geçmiş Borçlar · Tahsilat Makbuzları · En Borçlu Daireler · Avans Listesi · Gecikme Zammı Raporu · İcra Takibindeki Borçlar"],
        ["Finans Analizi", "Aylık Tahsilat Performansı · Aylık Nakit Akışı · Kategori Bazında Gider · Firma Bazında Harcama · Blok Karşılaştırması"],
        ["Operasyon", "Destek Talepleri · Görev Raporu · Rezervasyon Raporu · Güvenlik Kayıtları · Stok Durumu · Demirbaş Listesi · Sayaç Tüketimi · Personel ve Bordro"],
        ["İletişim ve Yönetim", "Anket Sonuçları · Toplantı Katılımı"],
    ]))
c.append(page("İşlem &amp; Denetim Kayıtları", "Rapor, Denetim &amp; Ayarlar → İşlem &amp; Denetim Kayıtları", S + "/audit",
    "Kim, ne zaman, ne yaptı? Finansal ve yönetimsel her işlemin silinemez izi.",
    img="denetim.jpg",
    actions=[
        "Özet: toplam kayıt, son 24 saat, son 30 gün, işlem yapan kullanıcı; modüle göre ve en sık işlemler.",
        "Filtre: modül, kullanıcı, tarih, arama. Her satırda tarih, kullanıcı, işlem (ör. COLLECTION, REVERSE_TRANSACTION, APPROVE_EXPENSE), modül, referans ve detay (tutar, neden, değişen alanların eski / yeni değeri).",
    ],
    tip="\"Her tahsilat, iptal, silme ve ayar değişikliği kimin yaptığıyla kayıtlı; bu kayıt silinemiyor.\""))
c.append(page("Site Ayarları &amp; Yöneticiler", "Rapor, Denetim &amp; Ayarlar → Site Ayarları &amp; Yöneticiler", S + "/settings",
    "Sitenin kimlik bilgileri, dönem / yıl kapatma, yöneticiler ve rol izinleri.",
    img="ayarlar.jpg",
    actions=[
        "<b>Site bilgileri:</b> ad, kod, şehir, vergi no, adres; 'Kasa bakiyesi eksiye düşebilsin' seçeneği.",
        "<b>Dönem kapatma</b> (yalnızca site sahibi): kapanış tarihi seç → o tarih dahil öncesine borç eklenemez, kayıt silinemez / iptal edilemez. Geri açmak için gerekçe zorunlu ve denetim kaydına yazılır.",
        "<b>Yıl sonu kapanışı</b> (yalnızca site sahibi): tamamlanmış yılı seç → <b>Yılı kapat</b>. Yılın gelir-gider sonucu ve 31 Aralık bakiyeleri kaydedilir, dönem 31 Aralık'a kadar kapanır. Defter bakiyelerle tutmuyorsa kapanış yapılmaz. Kapatılan yıllar kartta listelenir.",
        "<b>Yöneticiler:</b> siteye kullanıcı ekle, rolünü seç (Yönetici / Muhasebeci / İzleyici), çıkar.",
        "<b>Rol izinleri:</b> muhasebeci ve izleyici için her modülde Erişim yok / Görüntüleme / Görüntüleme + işlem → İzinleri kaydet.",
        "<b>Tehlikeli işlemler:</b> Siteyi sıfırla (tüm hareketleri siler, yapı kalır) ve Siteyi sil. Onay için site kodu yazılır.",
    ],
    rules=["Dönem en geç dünden önceki bir tarihe kadar kapatılabilir.", "Kapalı dönem olsa bile bugünkü tahsilatlar eski borçlara yazılmaya devam eder."]))
c.append(page("Site içi arama", "Üst çubuk → büyüteç", S + "/search",
    "Sitede hızlı arama.",
    actions=["Daire, sakin, borç, destek talebi ve gider sonuçları ayrı başlıklar altında; her sonuç ilgili sayfaya götürür."]))
parts.append(chapter(12, "Rapor, Denetim &amp; Ayarlar", "Çıktılar, denetim izi ve sitenin ayarları.", "".join(c)))

# ------------------------------------------------------------------ 13
c = []
c.append(page("Sistem Kayıtları", "Süper admin menüsü", "/system/audit",
    "Bütün sitelerin ve sistemin denetim kaydı (giriş, kullanıcı ve rol değişikliği, yedek geri yükleme dahil)."))
c.append(page("Yedekler", "Süper admin menüsü", "/system/backups",
    "Veritabanı yedekleri.",
    actions=["<b>Şimdi yedek al</b>.", "<b>Yedeği geri yükle:</b> yedeği seç, şifreni ve onay ifadesini yaz. Geri yüklemeden önce mevcut durumun güvenlik yedeği otomatik alınır."],
    rules=["Her gece 03:00'te otomatik yedek alınır, eski yedekler temizlenir.", "Yedekler şu an aynı sunucuda durur; dış depoya kopyalanmaz (yol haritasında)."]))
c.append(page("Kurulum sihirbazı", "İlk açılış / Setup", "/setup",
    "Sistemi ilk kez kuran ekran.",
    actions=["Kurulum türü (temiz / demo), kurulum kodu, veritabanı bilgileri, ilk yöneticinin adı-e-postası-şifresi, ilk site adı, kodu ve blokları. Her adımda 'Bu adımı anlat' açıklaması vardır."]))
parts.append(chapter(13, "Sistem yönetimi (süper admin)", "Yalnızca süper adminin gördüğü bölümler ve sunucu komutları.", "".join(c) + """
<h4>Sunucu komutları (teknik ekip için)</h4>
""" + table(["Komut", "Ne yapar"], [
    ["<code>php artisan app:setup</code>", "Sıfırdan kurulum (ortam, veritabanı, ilk yönetici, ilk site). Tekrar çalıştırmak güvenlidir."],
    ["<code>php artisan app:make-admin e-posta --create --super</code>", "Yönetici veya süper admin oluşturur / atar."],
    ["<code>php artisan app:run-billing</code>", "Otomatik aidat ve gecikme zammı (her gece 02:00)."],
    ["<code>php artisan app:backup</code> / <code>app:restore</code>", "Yedek al / yedekten geri yükle."],
    ["<code>php artisan app:reconcile</code>", "Yevmiye defterini kasa, daire borcu ve avans bakiyeleriyle karşılaştırır; sapma varsa raporlar (her gece 04:00)."],
    ["<code>php artisan app:send-reminders</code>", "Otomatik borç hatırlatmaları (her gün 09:00)."],
    ["<code>php artisan app:check-production</code>", "Canlıya çıkış kontrolü: hata ekranı, HTTPS, e-posta, kuyruk, yedek, iki adımlı doğrulama vb."],
    ["<code>php artisan app:disable-two-factor e-posta</code>", "Telefonunu kaybeden kullanıcının iki adımlı doğrulamasını kapatır."],
])))

# ------------------------------------------------------------------ 14
parts.append(chapter(14, "Sakin portalı ve mobil uygulama", "Sakinlerin kullandığı bölüm. Web portalı hazırdır; mobil uygulama için aynı işlevleri sunan API hazırdır.",
    """
<h4>Sakin nasıl katılır?</h4>
""" + ol([
    "Yönetici sakini <b>Sakin &amp; Üye Listesi</b>'ne ekler; sistem davet kodu üretir (ör. YVADI-A1-SJYAQ2F3).",
    "Kod sakine iletilir (SMS, WhatsApp veya kâğıtla).",
    "Sakin giriş ekranında <b>Hesap aç</b> (/portal/join) ile hesabını açar veya mevcut hesabıyla girip <b>Davet koduyla daire ekle</b> alanına kodu yazar.",
    "Bir hesap birden fazla daireye / siteye bağlanabilir.",
]) + table(["Portal sayfası", "Adres", "Sakin ne yapar?"], [
    ["Sitelerim", "/portal", "Bağlı olduğu siteleri görür, davet koduyla yeni daire ekler."],
    ["Genel bakış", "/portal/{site}", "Yaklaşan ve geciken borçlar, duyurular, son bildirimler."],
    ["Borçlar", "/portal/{site}/debts", "Dairesinin borçları ve hesap ekstresi (birden fazla dairesi varsa daire seçer)."],
    ["Bildirimler", "/portal/{site}/notifications", "Bildirimler (okundu işaretle) ve duyurular."],
    ["Anketler", "/portal/{site}/polls", "Daire adına oy verir (daire başı tek oy)."],
    ["Talepler", "/portal/{site}/tickets", "Arıza / şikâyet açar (konu, kategori, öncelik, açıklama, fotoğraf), yönetimle yazışır."],
    ["Rezervasyonlar", "/portal/{site}/reservations", "Ortak alan rezervasyonu ister ve iptal eder; yönetim onaylar."],
    ["Toplantılar", "/portal/{site}/meetings", "Toplantıları ve alınan kararları görür."],
    ["Hesabım", "/portal/account", "Şifresini değiştirir."],
]) + """
<div class="tip"><b>Güvenlik:</b> Sakin yalnızca kendi dairesini görür. Daireden ayrılan veya bağlantısı kaldırılan sakinin erişimi anında biter. Sakin, başka bir sitenin adresini yazsa bile 403 hatası alır.</div>
<div class="tip"><b>Henüz yok:</b> Sakinin portaldan online ödeme yapması veya dekont yüklemesi bilinçli olarak ertelendi.</div>
"""))

# ------------------------------------------------------------------ 15
parts.append(chapter(15, "Otomatik (gece) işleri", "Sistem kimse dokunmadan şunları yapar. Sunucuda her dakika çalışan tek bir zamanlayıcı (cron) satırı gerekir.",
    table(["Saat", "İş", "Açıklama"], [
        ["02:00", "Aidat ve gecikme zammı", "Otomatik seçeneği açık sitelerde o ayın aidatlarını ve vadesi geçen borçların zamlarını yazar."],
        ["03:00", "Yedek", "Veritabanını sıkıştırılmış olarak yedekler, eskileri temizler."],
        ["04:00", "Uzlaştırma", "Yevmiye defterini kasa, daire borcu ve avans bakiyeleriyle karşılaştırır. Tutmazsa hata verir."],
        ["09:00", "Borç hatırlatma", "Otomatik hatırlatması açık sitelerde gecikmiş dairelere hatırlatma (ve istenirse e-posta) gönderir."],
        ["Her dakika", "E-posta kuyruğu", "Bekleyen e-postaları gönderir."],
    ]) + ul([
        "<b>Tekrar çalışsa zarar vermez:</b> aynı ayın aidatı ikinci kez yazılmaz.",
        "<b>Bir site hata verirse diğerleri durmaz;</b> hatalı site ertesi gece yeniden denenir.",
        "Her site otomatik aidat, zam ve hatırlatmayı kendi ayar sayfasından açar / kapatır.",
    ])))

# ------------------------------------------------------------------ 16
parts.append(chapter(16, "Günlük, aylık ve yıllık iş akışları", "Hangi iş hangi sırayla, hangi sayfadan yapılır?",
    """
<h4>A. Yeni bir site kurmak</h4>
""" + ol([
    "<b>Konut &amp; Siteler</b> → + Yeni site oluştur (Merkez Kasa ve kategoriler otomatik gelir).",
    "<b>Blok, Daire &amp; Gruplar</b> → blokları ve daireleri ekle (Toplu ekle ile hızlıca), m² ve arsa payını gir.",
    "<b>Sakin &amp; Üye Listesi</b> → sakinleri ekle, davet kodlarını dağıt.",
    "<b>Kasa &amp; Banka Hesapları</b> → banka hesaplarını aç, açılış bakiyelerini gir.",
    "<b>Aidat &amp; Otomatik Tahakkuk</b> → aidat tanımını yap, otomatik üretimi aç.",
    "<b>Gecikme Zammı</b> ve <b>Borç Hatırlatıcı</b> ayarlarını yap.",
    "<b>Site Ayarları</b> → yöneticileri ekle, muhasebeci izinlerini ayarla.",
]) + """
<h4>B. Her gün</h4>
""" + ol([
    "<b>Site Finans Panosu</b>'na bak: uyarılar, bugün vadesi gelenler.",
    "Elden veya havaleyle gelen ödemeleri <b>Tahsilat</b>'tan gir.",
    "Bankadan ekstreyi indir, <b>Banka Ekstresi Eşleştirme</b>'ye yükle, eşleşenleri toplu işle.",
    "Gelen faturaları <b>Gider &amp; Fatura Onayı</b>'na gir, ödenecekleri öde.",
    "<b>Arıza &amp; Destek Talepleri</b> ve <b>Rezervasyon</b> onaylarını yanıtla.",
]) + """
<h4>C. Her ay</h4>
""" + ol([
    "Aidatlar gece otomatik yazılır (kapalıysa <b>Aidat</b> sayfasından dönemi çalıştır).",
    "Sayaç okumalarını gir, <b>Sayaç Faturası Dağıtımı</b> ile ortak faturayı paylaştır.",
    "<b>Personel Bordrosu</b> → bordroyu oluştur ve öde.",
    "<b>Alacak Yaşlandırma</b> → geciken dairelere <b>Borç Hatırlatıcı</b>'dan hatırlatma gönder.",
    "<b>Gelir - Gider Tablosu</b> ve <b>Bütçe</b> ile ayı değerlendir.",
    "Ay sonu kontrolü bittiğinde <b>Site Ayarları → Dönem kapatma</b> ile ayı kilitle.",
]) + """
<h4>D. Yıl sonu ve genel kurul</h4>
""" + ol([
    "<b>Bütçe &amp; Gerçekleşen</b> ve <b>Mali Durum Tablosu</b> ile yılı raporla.",
    "<b>Site Ayarları → Yıl sonu kapanışı</b> ile yılı kapat.",
    "<b>Genel Kurul &amp; Toplantılar</b>'da toplantıyı planla, <b>Duyuru</b> ile sakinlere bildir.",
    "Toplantı günü hazirun cetvelini doldur ve yazdır, kararları ekle; kararlar <b>Karar Defteri</b>'ne düşer.",
    "Yeni yılın bütçesini <b>Önceki yılı kopyala</b> ile hazırla.",
]) + """
<h4>E. Sık karşılaşılan durumlar</h4>
""" + table(["Durum", "Ne yapılır, nereden?"], [
    ["Yanlış daireye tahsilat girildi", "Tahsilat listesinde hareketi <b>iptal et</b> (neden yaz), doğru daireye yeniden gir."],
    ["Yanlış tutarlı borç yazıldı", "Ödeme alınmadıysa <b>Borçlandırma</b>'da düzenle; alındıysa önce tahsilatı iptal et."],
    ["Sakin borcunu taksitle ödemek istiyor", "<b>Borçlandırma</b>'da borcu <b>Taksitlendir</b>."],
    ["Sakin uzun süredir ödemiyor", "<b>Borçlandırma</b>'da borca <b>İcra</b> işareti koy; <b>Rapor Merkezi → İcra Takibindeki Borçlar</b>."],
    ["Sakin fazla ödedi, parasını istiyor", "<b>Avans &amp; Kredi Mahsup</b> → <b>İade et</b>."],
    ["Daire satıldı", "Eski sakini <b>Geçmişe taşı</b>, yeni sakini ekle. Borçlar dairede kalır."],
    ["Fatura bir dairenin kusurundan doğdu", "Gideri girerken <b>daireye yansıt</b>."],
    ["Bankadan dövizi TL'ye çevirdik", "<b>Kasa &amp; Banka → Virman</b>; hedefe giren TL tutarını yaz, kur farkı otomatik."],
])))

# ------------------------------------------------------------------ 17
parts.append(chapter(17, "Güvenlik ve veri bütünlüğü", "Sistemin \"neden güvenilir\" olduğunu anlatan maddeler.",
    table(["Konu", "Nasıl sağlanıyor?"], [
        ["Para hassasiyeti", "Tutarlar kuruş (tam sayı) olarak hesaplanır; bölüştürmelerde artan kuruş kaybolmaz."],
        ["Yarım kayıt olmaz", "Her işlem tek bir veritabanı işlemidir: ya tamamı olur ya hiçbiri."],
        ["Aynı anda iki işlem", "İşlem sırasında ilgili daire, kasa ve borç kilitlenir; iki kişi aynı anda tahsilat girse bakiye bozulmaz."],
        ["Çift taraflı kayıt", "Her hareket borç = alacak iki satır yazar; yevmiye her an dengededir."],
        ["Silme yok, ters kayıt var", "Muhasebe kayıtları değiştirilmez; düzeltme zıt kayıtla yapılır."],
        ["Mükerrer koruması", "Aidat, gecikme zammı, sayaç payı, banka satırı aynı anahtarla ikinci kez yazılmaz."],
        ["Gece uzlaştırma", "Defter ile kasa / borç / avans bakiyeleri her gece karşılaştırılır."],
        ["Dönem kilidi", "Kapatılan döneme kayıt girilemez, oradaki kayıt iptal edilemez; geri açma gerekçeli ve kayıtlı."],
        ["Denetim izi", "Her işlem kullanıcı, zaman ve detayla kayıtlı; değişikliklerde eski / yeni değer tutulur."],
        ["Erişim", "Rol ve modül izinleri sunucuda zorlanır; her site yalnızca kendi verisini görür."],
        ["Giriş güvenliği", "Şifre sıfırlama, iki adımlı doğrulama, hatalı giriş sınırı, davet kodu deneme sınırı."],
        ["Belgeler", "Yüklenen dosyalar korumalı alanda; yalnızca yetkili indirir."],
        ["Yedek", "Her gece otomatik yedek; geri yüklemeden önce güvenlik yedeği."],
        ["Test", "Sistemin davranışları 580'den fazla otomatik testle doğrulanıyor."],
    ])))

# ------------------------------------------------------------------ 18
parts.append(chapter(18, "Patron sunumu: senaryo ve olası sorular", "15 dakikalık canlı demo akışı ve hazır cevaplar.",
    """
<h4>Sunum akışı (15 dakika)</h4>
""" + table(["Dakika", "Ekran", "Ne göster, ne söyle"], [
    ["0-2", "Genel Portföy Panosu", "\"Beş sitenin tahsilatı, borcu, kasası ve bekleyen işleri tek ekranda.\" Uyarılar kutusunu göster."],
    ["2-4", "Siteler → bir site → Site Finans Panosu", "Tahsilat hunisi, kasa, borçlu daireler."],
    ["4-6", "Aidat &amp; Otomatik Tahakkuk", "\"Tanımı bir kez yapıyoruz, her gece kendiliğinden yazılıyor, mükerrer olmuyor.\""],
    ["6-8", "Tahsilat", "Daire seç, 1.500 TL gir: borç kapanır, 500 avans olur, makbuz çıkar."],
    ["8-9", "Banka Ekstresi", "\"Ekstreyi yüklüyoruz, daireler otomatik eşleşiyor.\""],
    ["9-10", "Gider &amp; Fatura Onayı", "Fatura gir, kasadan öde."],
    ["10-12", "Alacak Yaşlandırma → Borç Hatırlatıcı", "\"Kim kaç gündür borçlu; tek tuşla hatırlatma.\""],
    ["12-13", "Mali Durum Tablosu / Yevmiye", "\"Defter dengede; bilanço her tarih için doğru.\""],
    ["13-14", "Arıza Talepleri ve Sakin portalı", "\"Sakin telefondan arıza bildiriyor, borcunu görüyor, oy veriyor.\""],
    ["14-15", "Denetim Kayıtları + Site Ayarları", "\"Her işlem kimin yaptığıyla kayıtlı; dönem kapatılınca kimse eski kaydı değiştiremez.\""],
]) + """
<h4>Olası sorular ve cevaplar</h4>
""" + table(["Soru", "Cevap"], [
    ["Muhasebeci para çıkarabilir mi, kontrol var mı?", "Varsayılan olarak finans modüllerinde işlem yapabilir; yönetici Site Ayarları → Rol izinleri'nden modül modül kısıtlar. Kasa eksiye düşmez, her işlem denetim kaydında. İki kişi onayı şu an yok."],
    ["Yanlış girilen kaydı biri silip izini kaybettirebilir mi?", "Hayır. Muhasebe kaydı silinmez, ters kayıtla düzeltilir; işlem denetim kaydına yazılır. Kapalı dönemde iptal de yapılamaz."],
    ["Aynı aidat iki kez yazılabilir mi?", "Hayır, her borcun dönem + tanım + daire anahtarı var; ikinci çalıştırma atlanır."],
    ["Sakin taşınınca borç ne olur?", "Borç dairede kalır; eski sakin geçmişe taşınır, yeni sakin dairenin hesabını devralır."],
    ["Banka ile kasa tutuyor mu nasıl anlarız?", "Banka ekstresi sayfası, ekstrede karşılığı olmayan kasa hareketlerini listeler; gece uzlaştırma defterle bakiyeleri karşılaştırır."],
    ["Döviz tutabiliyor muyuz?", "Evet; USD / EUR kasası açılır, tahsilat ve ödeme kurla yapılır, kur farkı otomatik hesaplanır."],
    ["Raporları dışarı verebiliyor muyuz?", "25 hazır rapor ve tüm mali tablolar yazdırılır / PDF olarak kaydedilir ve Excel'e aktarılır."],
    ["Veriler kaybolursa?", "Her gece otomatik yedek alınır, tek tıkla geri yüklenir. Yedeğin buluta kopyalanması yol haritasında."],
    ["Sakin online ödeyebilir mi?", "Şu an hayır, bilinçli olarak ertelendi. Tahsilat yönetim panelinden veya banka ekstresinden giriliyor."],
    ["SMS gönderiyor mu?", "Şu an e-posta ve uygulama içi bildirim var; SMS / WhatsApp sağlayıcı entegrasyonu yol haritasında."],
]) + """
<div class="tip"><b>Altın kural:</b> Bilmediğin bir soruda "o kısmı kontrol edip size dönerim" de. Eksikleri saklama; "yol haritasında" diye sun.</div>
"""))

# ------------------------------------------------------------------ 19
parts.append(chapter(19, "Bilinen eksikler ve yol haritası", "Dürüst olunması gereken konular. Patron sorarsa \"planlı\" diye sunulur.",
    table(["Konu", "Durum"], [
        ["Sakin online ödeme / dekont yükleme", "Bilinçli olarak ertelendi."],
        ["SMS / WhatsApp", "Yok; e-posta ve uygulama içi bildirim var."],
        ["Mobil push bildirim", "Bildirimler uygulama içinde; telefona anlık bildirim için servis entegrasyonu gerekli."],
        ["e-Fatura / e-Arşiv / e-Defter", "Yok."],
        ["Banka API entegrasyonu", "Yok; banka ekstresi CSV ile yükleniyor."],
        ["Yedeğin dış depoya kopyalanması", "Yok; yedekler aynı sunucuda."],
        ["İki kişi onayı (yüksek tutarlı gider)", "Yok."],
        ["Toplu aidat artışı, boş daire muafiyeti, ay ortası taşınmada orantılama", "Yok."],
        ["Daire / sakin Excel'den toplu içe aktarma", "Yok; daireler 'Toplu ekle' ile numara aralığından eklenebilir."],
        ["Gerçek PDF üretimi", "Tarayıcının yazdır → PDF olarak kaydet özelliği kullanılıyor."],
        ["Periyodik kontrol takvimi (asansör, yangın tüpü) hatırlatması", "Belge arşivinde bitiş tarihi uyarısı var; tekrarlayan takvim yok."],
        ["KVKK modülü (aydınlatma, rıza, veri silme talebi)", "Yok."],
        ["Çok dilli arayüz", "Yalnızca Türkçe."],
    ])))

# ------------------------------------------------------------------ 20
parts.append(chapter(20, "Sözlük", "Sistemde geçen terimler.",
    table(["Terim", "Anlamı"], [
        ["Tahakkuk", "Borcun kayda geçmesi (para henüz gelmemiş olabilir)."],
        ["Tahsilat", "Daireden para alınması."],
        ["Avans", "Dairenin fazla ödediği, sonraki borçlara sayılacak para."],
        ["Mahsup", "Avansın borca düşülmesi."],
        ["Virman", "İki kasa / banka hesabı arasında para aktarımı."],
        ["Yevmiye", "Bütün muhasebe kayıtlarının tarih sırasıyla tutulduğu defter."],
        ["Mizan", "Hesapların borç-alacak toplamlarını gösteren kontrol tablosu."],
        ["Mali durum tablosu (bilanço)", "Belli bir gündeki varlık ve yükümlülükler."],
        ["Yaşlandırma", "Borçların kaç gündür gecikmede olduğuna göre gruplanması."],
        ["Arsa payı", "Dairenin arsadaki payı; aidat bölüşümünde kullanılabilir."],
        ["Hazirun cetveli", "Genel kurula katılanların imza listesi."],
        ["Kur farkı", "Döviz alım maliyeti ile bozdurma / ödeme anındaki değer arasındaki fark."],
        ["Ters kayıt", "Hatalı kaydı silmeden, zıt yönde yeni kayıtla düzeltme."],
        ["Dönem kapatma", "Belirli bir tarihe kadarki kayıtların kilitlenmesi."],
        ["Davet kodu", "Sakinin kendi hesabını daireye bağlamasını sağlayan tek kullanımlık kod."],
        ["Denetim kaydı", "Kim, ne zaman, hangi işlemi yaptı bilgisinin silinemez kaydı."],
    ])))

CSS = """
@page { size: A4; margin: 16mm 15mm 18mm 15mm; @bottom-center { content: counter(page); } }
@page :first { margin: 0; @bottom-center { content: none; } }
* { box-sizing: border-box; }
body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; color: #18181b; font-size: 10.5pt; line-height: 1.5; margin: 0; }
h1,h2,h3,h4 { line-height: 1.25; }
.cover { height: 296mm; width: 210mm; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 10mm; page-break-after: always; background: linear-gradient(160deg,#09090b 0%,#1e293b 100%); color: #fff; margin: 0; padding-left: 25mm; }
.cover .logo { width: 22mm; height: 22mm; border-radius: 5mm; background: #fff; color: #09090b; display:flex; align-items:center; justify-content:center; font-size: 34pt; }
.cover h1 { font-size: 46pt; margin: 8mm 0 2mm; letter-spacing: -1px; }
.cover .sub { font-size: 16pt; margin: 0; color: #cbd5e1; }
.cover .sub2 { font-size: 20pt; margin: 10mm 0 0; font-weight: 600; }
.cover .date { margin-top: 30mm; color: #94a3b8; }
.toc { page-break-after: always; }
.toc h2 { font-size: 22pt; border-bottom: 2px solid #18181b; padding-bottom: 3mm; }
.toc ol { font-size: 12.5pt; line-height: 2; padding-left: 12mm; }
.chapter { page-break-before: always; }
.chapter > h2 { font-size: 21pt; margin: 0 0 2mm; display: flex; align-items: center; gap: 4mm; border-bottom: 2px solid #18181b; padding-bottom: 3mm; }
.chapter > h2 span { background: #18181b; color: #fff; border-radius: 3mm; min-width: 11mm; height: 11mm; display: inline-flex; align-items: center; justify-content: center; font-size: 14pt; }
.intro { color: #52525b; font-size: 11.5pt; margin: 3mm 0 6mm; }
.page { border: 1px solid #e4e4e7; border-radius: 3mm; padding: 5mm 6mm; margin: 0 0 6mm; break-inside: auto; }
.page h3 { margin: 0 0 2mm; font-size: 14.5pt; color: #0f172a; }
.page h4 { margin: 4mm 0 1.5mm; font-size: 11pt; color: #334155; text-transform: uppercase; letter-spacing: .3px; }
h4 { font-size: 12pt; margin: 6mm 0 2mm; }
.meta { display: flex; flex-wrap: wrap; gap: 2mm 8mm; font-size: 9pt; color: #52525b; background: #f4f4f5; border-radius: 2mm; padding: 2mm 3mm; }
code { font-family: 'SF Mono', Menlo, monospace; font-size: 8.8pt; background: #f4f4f5; padding: 0 1mm; border-radius: 1mm; }
.meta code { background: #fff; }
.purpose { font-size: 11pt; margin: 3mm 0; }
.head { break-inside: avoid; }
h4 { break-after: avoid; }
figure { margin: 3mm 0; break-inside: avoid; }
figure img { width: 92%; display: block; margin: 0 auto; border: 1px solid #d4d4d8; border-radius: 2mm; }
figcaption { font-size: 8.5pt; color: #71717a; text-align: center; margin-top: 1mm; }
ul, ol { margin: 1mm 0 2mm; padding-left: 6mm; }
li { margin: .8mm 0; }
.rules { background: #fff7ed; border-left: 3px solid #f59e0b; padding: 1mm 3mm; border-radius: 1mm; }
.links { color: #1d4ed8; font-size: 10pt; }
.tip { background: #eff6ff; border-left: 3px solid #2563eb; padding: 2.5mm 3.5mm; margin: 3mm 0; border-radius: 1mm; break-inside: avoid; }
table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; font-size: 9.6pt; }
th { background: #18181b; color: #fff; text-align: left; padding: 2mm; }
td { border-bottom: 1px solid #e4e4e7; padding: 1.8mm 2mm; vertical-align: top; }
tr { break-inside: avoid; }
tr:nth-child(even) td { background: #fafafa; }
"""

doc = f'<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Leans Kullanım Rehberi</title><style>{CSS}</style></head><body>{"".join(parts)}</body></html>'
(ROOT / "rehber.html").write_text(doc, encoding="utf-8")
print("ok", len(doc))
