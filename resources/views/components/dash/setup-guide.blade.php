@if ($user->is_super_admin)
    @php
        $field = fn (string $id) => "div:has(> #{$id})";
        $stop = fn (string $target, string $title, string $text, ?string $example = null, ?string $open = null, ?string $fill = null, bool $locked = false, ?string $typing = null) => compact('target', 'title', 'text', 'example', 'open', 'fill', 'locked', 'typing');

        $guideGroups = [
            [
                'key' => 'site', 'section' => 'Konut & Siteler', 'url' => route('sites.index'), 'done' => (bool) $currentSite,
                'stops' => [
                    $stop('main h1', 'Kurulum buradan başlar', 'Yönettiğiniz her apartman, site veya rezidans bu sayfada tutulur. Daire, sakin, aidat ve kasa gibi bütün kayıtlar bir konuta bağlı olduğu için ilk iş konutu tanımlamaktır.'),
                    $stop('main a.card', 'Kayıtlı konutlarınız', 'Eklediğiniz her konut burada bir kart olarak görünür. Kartta blok, daire ve sakin sayılarını görürsünüz, karta tıklayınca o konutun panosu açılır.'),
                    $stop('details:has(#name) > summary', 'Yeni konut formu', 'Yeni bir apartman veya site eklemek için bu başlığa tıklanır. İleri’ye bastığınızda formu sizin için açıp alanları tek tek anlatacağım.'),
                    $stop($field('name'), 'Site adı', 'Konutun tam adını yazın. Bu ad menülerde, raporlarda ve makbuzlarda görünür. Daha sonra Site Ayarları’ndan değiştirebilirsiniz.', 'Örnek: Çınar Apartmanı', 'details:has(#name)', fill: 'name'),
                    $stop($field('code'), 'Site kodu', 'Konuta özel, kısa ve benzersiz bir koddur. Harf, rakam, tire ve alt çizgi kullanılabilir, boşluk olamaz. Sakin davet kodlarında kullanılır, silme ve sıfırlama gibi kritik işlemlerde de onay olarak sizden istenir.', 'Örnek: CINAR', 'details:has(#name)', fill: 'code'),
                    $stop($field('city'), 'Şehir', 'Konutun bulunduğu şehirdir. Konut kartında görünür ve birden çok konut yönetiyorsanız ayırt etmenizi kolaylaştırır. Boş bırakılabilir.', 'Örnek: Antalya', 'details:has(#name)'),
                    $stop($field('blocks'), 'Bloklar', 'Binaların adlarını aralarına virgül koyarak yazın. Her biri ayrı blok olarak açılır. Tek binanız varsa A olarak bırakabilirsiniz, sonradan blok eklemek de mümkündür.', 'Örnek: A,B,C', 'details:has(#name)'),
                    $stop($field('address'), 'Adres', 'Konutun açık adresidir. Resmi yazışmalarda ve kayıtlarda işinize yarar, boş bırakılabilir.', 'Örnek: Atatürk Cad. No:12 Muratpaşa', 'details:has(#name)'),
                    $stop('details:has(#name) .btn-primary', 'Konutu oluşturun', 'Oluştur’a bastığınızda konut açılır, ayrıca otomatik olarak bir Merkez Kasa ile Aidat, Elektrik, Su gibi hazır gelir ve gider kategorileri eklenir. Konut olmadan sonraki bölümlere geçilemez.', null, 'details:has(#name)', locked: true),
                ],
            ],
            [
                'key' => 'blocks', 'section' => 'Bloklar', 'url' => $siteRoute('site.units.index'), 'done' => (bool) $currentSite?->blocks()->exists(),
                'stops' => [
                    $stop('section.card:has(#block-name) ul', 'Blok listesi', 'Konutunuzdaki binalar burada sıralanır ve her bloğun kaç dairesi olduğu yanında yazar. İçinde daire bulunan blok silinemez, önce dairelerinin silinmesi gerekir.'),
                    $stop('form:has(> #block-name)', 'Yeni blok ekleyin', 'Listede olmayan bir bina varsa adını yazıp Ekle’ye basın. Aynı konutta aynı adla iki blok açılamaz. Her daire bir bloğa bağlı olduğu için daire eklemeden önce bloklar tamamlanmalıdır. En az bir blok olmadan devam edilemez.', 'Örnek: B', locked: true, typing: 'block-name'),
                ],
            ],
            [
                'key' => 'units', 'section' => 'Daireler', 'url' => $siteRoute('site.units.index'), 'done' => (bool) $currentSite?->units()->exists(),
                'stops' => [
                    $stop('section.card:has(#u-no)', 'Daire ekleme alanı', 'Aidat ve borçlar dairelere yazılır, sakinler de dairelere bağlanır. Daireleri buradan tek tek veya aşağıdaki bölümden toplu olarak ekleyebilirsiniz.'),
                    $stop($field('u-block'), 'Blok', 'Dairenin bulunduğu binayı seçin. Liste, tanımladığınız bloklardan gelir.'),
                    $stop($field('u-no'), 'Daire no', 'Dairenin kapı numarasıdır. Aynı blokta aynı numara iki kez kullanılamaz, farklı bloklarda kullanılabilir.', 'Örnek: 12'),
                    $stop($field('u-floor'), 'Kat', 'Dairenin bulunduğu kat. Listelerde görünür, boş bırakılabilir.', 'Örnek: 3'),
                    $stop($field('u-area'), 'Brüt m²', 'Dairenin brüt alanıdır. Aidatı m² başına hesaplayacaksanız mutlaka doldurun, çünkü borç bu değerle çarpılarak bulunur.', 'Örnek: 120'),
                    $stop($field('u-usage'), 'Kullanım', 'Bağımsız bölümün türünü seçin: Konut, İşyeri, Depo veya Otopark.'),
                    $stop($field('u-status'), 'Durum', 'Dairede oturan biri varsa Dolu, kullanılmıyorsa Boş seçin.'),
                    $stop($field('u-share'), 'Arsa payı', 'Tapuda yazan arsa payıdır. Bilmiyorsanız boş bırakıp sonra daireyi düzenleyerek ekleyebilirsiniz.', 'Örnek: 12'),
                    $stop('section.card:has(#u-no)', 'Toplu daire ekleme', 'Çok daireniz varsa en hızlı yol budur. Blok seçin, ilk ve son numarayı yazın, sistem aradaki bütün daireleri açar. Tek seferde en fazla 500 daire eklenir, zaten var olan numaralar atlanır. Yukarıdaki formdan veya buradan en az bir daire eklemeden devam edilemez.', 'Örnek: İlk no 1, son no 24 yazarsanız 24 daire açılır', locked: true),
                    $stop('section.card:has(table)', 'Daire listesi', 'Eklediğiniz bütün daireler burada görünür. Kat, kullanım, durum, m², sakinler ve varsa avans bakiyesi tek satırda izlenir.'),
                    $stop('section.card:has(#g-name)', 'Daire grupları', 'Dükkanlar veya çatı katları gibi daireleri bir ad altında toplayabilirsiniz. Gruplar borç dağıtımında hedef olarak kullanılır. Gerekmiyorsa bu bölümü geçebilirsiniz.', 'Örnek: Dükkanlar', typing: 'g-name'),
                ],
            ],
            [
                'key' => 'members', 'section' => 'Üyeler / Sakinler', 'url' => $siteRoute('site.members.index'), 'done' => (bool) $currentSite?->members()->exists(),
                'stops' => [
                    $stop('main h1', 'Sakinler sayfası', 'Dairelerde oturan veya daireye sahip olan kişiler burada tutulur. Borç hatırlatmaları, ekstreler ve iletişim bu kayıtlar üzerinden yürür.'),
                    $stop('details:has(#m-name) > summary', 'Yeni üye formu', 'Yeni sakin eklemek için bu başlık kullanılır. İleri’ye bastığınızda formu açıp alanları anlatacağım.'),
                    $stop($field('m-unit'), 'Daire', 'Kişinin bağlı olduğu daireyi seçin. Liste, eklediğiniz dairelerden gelir.', null, 'details:has(#m-name)'),
                    $stop($field('m-type'), 'Tür', 'Kişi dairenin sahibiyse Mülk Sahibi, kirada oturuyorsa Kiracı seçin. Bir dairede ikisi de kayıtlı olabilir.', null, 'details:has(#m-name)'),
                    $stop($field('m-name'), 'Ad soyad', 'Kişinin tam adını yazın. Makbuz ve ekstrelerde bu ad görünür.', 'Örnek: Ayşe Yılmaz', 'details:has(#m-name)', fill: 'm-name'),
                    $stop($field('m-phone'), 'Telefon', 'İletişim ve borç hatırlatmaları için kullanılır.', 'Örnek: 0532 100 20 30', 'details:has(#m-name)'),
                    $stop($field('m-email'), 'E-posta', 'Kişinin e-posta adresidir. Boş bırakılabilir.', 'Örnek: ayse@ornek.com', 'details:has(#m-name)'),
                    $stop($field('m-in'), 'Daireye giriş tarihi', 'Kişinin daireye taşındığı tarihtir ve taşınma geçmişinde görünür.', null, 'details:has(#m-name)'),
                    $stop($field('m-tc'), 'TC kimlik no', '11 haneli kimlik numarasıdır. Boş bırakılabilir.', null, 'details:has(#m-name)'),
                    $stop($field('m-em'), 'Acil durum telefonu', 'Kişiye ulaşılamadığında aranacak yakınının numarasıdır.', 'Örnek: 0532 400 50 60', 'details:has(#m-name)'),
                    $stop('details:has(#m-name) .btn-primary', 'Üyeyi kaydedin', 'Kaydettiğinizde kişi daireye bağlanır ve mobil uygulamaya katılabilmesi için ona özel bir davet kodu üretilir. Sakinlerin hepsini bu şekilde ekleyebilirsiniz. En az bir üye eklemeden devam edilemez.', null, 'details:has(#m-name)', locked: true),
                ],
            ],
            [
                'key' => 'users', 'section' => 'Sistem Kullanıcıları', 'url' => route('users.index'), 'done' => \App\Models\User::whereKeyNot($user->id)->exists(),
                'stops' => [
                    $stop('main h1', 'Kullanıcılar sayfası', 'Sisteme giriş yapabilen hesaplar burada listelenir. Üye kaydı bir sakini tanımlar, kullanıcı hesabı ise panele veya mobil uygulamaya girişi sağlar.'),
                    $stop('details:has(#password) > summary', 'Kullanıcı oluşturma', 'Yanınızda çalışacak kişiler için buradan hesap açılır. İleri’ye bastığınızda formu açacağım.'),
                    $stop($field('name'), 'Ad soyad', 'Hesabı kullanacak kişinin adı.', 'Örnek: Ahmet Yılmaz', 'details:has(#password)'),
                    $stop($field('email'), 'E-posta', 'Kişi sisteme bu adresle giriş yapar, her hesap için farklı olmalıdır.', 'Örnek: ahmet@ornek.com', 'details:has(#password)'),
                    $stop($field('password'), 'Şifre', 'En az 8 karakterlik bir şifre belirleyin ve kişiye güvenli bir yoldan iletin.', null, 'details:has(#password)'),
                    $stop($field('role'), 'Rol', 'Panel yöneticisi web paneline girer. Sakin (mobil) yalnızca mobil uygulamayı kullanır, onu seçince hangi bloktaki daireye ait olduğunu da seçersiniz. Bir panel yöneticisinin bir konutu görebilmesi için onu o konutun Site Ayarları sayfasından yönetici olarak eklemeyi unutmayın.', null, 'details:has(#password)'),
                ],
            ],
            [
                'key' => 'cashboxes', 'section' => 'Kasa & Banka', 'url' => $siteRoute('site.cashboxes.index'), 'done' => (bool) $currentSite?->cashboxes()->exists(),
                'stops' => [
                    $stop('main h1', 'Kasa ve banka hesapları', 'Konutun parasının durduğu nakit kasalar ve banka hesapları burada izlenir. Her tahsilat ve gider bir kasaya işlenir. Merkez Kasa konutla birlikte hazır gelir.'),
                    $stop('.card:has(#c-name)', 'Yeni hesap ekleme', 'Konutun banka hesabı veya ikinci bir nakit kasası varsa bu bölümden eklenir.'),
                    $stop($field('c-name'), 'Ad', 'Hesabı ekranda tanıyacağınız isim.', 'Örnek: Ziraat Bankası'),
                    $stop($field('c-type'), 'Tür', 'Elde tutulan para için Nakit Kasa, bankadaki para için Banka Hesabı seçin.'),
                    $stop($field('c-acc'), 'IBAN / hesap no', 'Banka hesabının numarası. Nakit kasalarda boş bırakılır.', 'Örnek: TR12 0001 0000 0000 0000 0000 01'),
                    $stop($field('c-open'), 'Açılış bakiyesi', 'Sisteme geçtiğiniz gün bu hesapta bulunan paradır. Doğru girerseniz kasa bakiyeleriniz gerçek durumla baştan uyumlu olur.', 'Örnek: 25000'),
                    $stop('.card:has(#t-amount)', 'Gelir / gider kaydı', 'Aidat dışındaki gelirleri ve kasadan yapılan ödemeleri buradan elle işleyebilirsiniz. Tür, kasa, kategori, tutar ve açıklama girilir.'),
                    $stop('.card:has(#v-amount)', 'Virman', 'Kasadan bankaya para yatırmak gibi hesaplar arası aktarımlar içindir. Toplam para değişmez, yalnızca hesap değiştirir.'),
                ],
            ],
            [
                'key' => 'dues', 'section' => 'Aidat ve Borçlandırma', 'url' => $siteRoute('site.dues.index'), 'done' => (bool) $currentSite?->duesDefinitions()->exists(),
                'stops' => [
                    $stop('main h1', 'Aidat motoru', 'Aidat gibi düzenli ödemeler burada bir kez tanımlanır. Sistem her dönem için hedef dairelere borcu kendisi yazar, tek tek borç girmeniz gerekmez.'),
                    $stop('details:has(#calculation) > summary', 'Yeni aidat tanımı', 'Aidatınızı tanımlamak için bu başlık kullanılır. İleri’ye bastığınızda formu açacağım.'),
                    $stop($field('name'), 'Ad', 'Borcun sakinin ekstresinde görünecek adıdır.', 'Örnek: Aylık Aidat', 'details:has(#calculation)', fill: 'name'),
                    $stop($field('calculation'), 'Hesaplama', 'Sabit tutar seçerseniz her daireye aynı borç yazılır. m² başına seçerseniz tutar dairenin brüt alanıyla çarpılır.', null, 'details:has(#calculation)'),
                    $stop($field('amount'), 'Tutar', 'Daire başına veya m² başına uygulanacak tutar.', 'Örnek: 750', 'details:has(#calculation)', fill: 'amount'),
                    $stop($field('due_day'), 'Son ödeme günü', 'Ödemenin yapılması gereken gün. 31 yazarsanız her ayın son günü kabul edilir.', null, 'details:has(#calculation)'),
                    $stop($field('target_type'), 'Hedef', 'Borcun kimlere yazılacağını seçin: Tüm site, tek bir blok veya tek bir daire.', null, 'details:has(#calculation)'),
                    $stop($field('category_id'), 'Kategori', 'Bu gelirin raporlarda hangi başlıkta görüneceğini belirler.', 'Örnek: Aidat', 'details:has(#calculation)'),
                    $stop('details:has(#calculation) .btn-primary', 'Tanımı kaydedin', 'Kaydetmek henüz borç yazmaz, yalnızca kuralı oluşturur. En az bir aidat tanımı olmadan devam edilemez.', null, 'details:has(#calculation)', locked: true),
                    $stop('.card:has(#period)', 'Dönem borçlarını üretin', 'Ayı seçip çalıştırdığınızda aktif tanımlar o dönemin borçlarını dairelere yazar. Aynı dönemi yeniden çalıştırmak mükerrer borç oluşturmaz.'),
                ],
            ],
        ];

        $guideStops = [];
        if ($currentSite?->units()->withTrashed()->exists()) {
            $guideStops[] = ['key' => 'check', 'section' => 'Setup', 'title' => $currentSite->name.' zaten kurulu', 'text' => 'Tekrar kurmak istiyor musunuz? Rehber sayfaları sırayla gezer ve hiçbir kaydınızı silmez. Verileri sıfırlamak isterseniz Site Ayarları sayfasını kullanın.', 'nextLabel' => 'Evet', 'skipLabel' => 'Hayır'];
        }
        foreach ($guideGroups as $group) {
            foreach ($group['stops'] as $stopIndex => $guideStop) {
                preg_match('/^div:has\(> #(.+)\)$/', $guideStop['target'], $fieldMatch);
                $guideStops[] = array_merge($guideStop, [
                    'typing' => $guideStop['example'] === null ? null : ($guideStop['typing'] ?? $fieldMatch[1] ?? null),
                    'sample' => \Illuminate\Support\Str::after((string) $guideStop['example'], 'Örnek: '),
                    'key' => $group['key'].':'.$stopIndex,
                    'section' => $group['section'],
                    'url' => $group['url'],
                    'done' => $group['done'],
                ]);
            }
        }
        $guideStops[] = ['key' => 'finish', 'section' => 'Setup', 'title' => 'Kurulum tamam', 'text' => 'Temel yapı hazır. Günlük işleriniz için sol menüdeki Tahsilat, Gider ve Rapor bölümlerini kullanabilirsiniz. Bu rehberi sağ üstteki Setup butonuyla istediğiniz zaman yeniden açabilirsiniz.'];
    @endphp

    <div id="setup-guide-spot" class="guide-spot no-print hidden"></div>
    <aside id="setup-guide" class="guide-card no-print hidden" aria-label="Setup rehberi" aria-live="polite">
        <div class="guide-progress"><span data-guide-progress class="guide-progress-bar"></span></div>
        <div class="p-5">
            <div data-guide-content class="guide-content">
                <div class="flex items-center justify-between gap-3">
                    <p data-guide-section class="guide-section"></p>
                    <p data-guide-count class="guide-count"></p>
                </div>
                <h3 data-guide-title class="guide-heading"></h3>
                <p data-guide-text class="guide-text"></p>
                <p data-guide-example class="guide-example hidden"></p>
                <p data-guide-hint class="guide-hint hidden" role="status"></p>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3">
                <button type="button" data-guide-skip class="guide-skip"></button>
                <div class="flex gap-1.5">
                    <button type="button" data-guide-back class="guide-back">Geri</button>
                    <button type="button" data-guide-next class="guide-next"></button>
                </div>
            </div>
        </div>
    </aside>
    <script type="application/json" id="setup-guide-data">@json($guideStops)</script>
@endif
