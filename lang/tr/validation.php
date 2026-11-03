<?php

// Uygulamada kullanılan doğrulama kurallarının Türkçe mesajları
return [
    'after_or_equal' => ':attribute, :date veya sonrası olmalıdır.',
    'array' => ':attribute bir liste olmalıdır.',
    'before_or_equal' => ':attribute, :date veya öncesi olmalıdır.',
    'between' => [
        'numeric' => ':attribute :min ile :max arasında olmalıdır.',
    ],
    'boolean' => ':attribute evet veya hayır olmalıdır.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'date_format' => ':attribute :format biçiminde olmalıdır.',
    'different' => ':attribute ile :other farklı olmalıdır.',
    'digits' => ':attribute :digits haneli olmalıdır.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'gte' => [
        'numeric' => ':attribute, :value veya daha büyük olmalıdır.',
    ],
    'image' => ':attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute tam sayı olmalıdır.',
    'max' => [
        'array' => ':attribute en fazla :max öğe içerebilir.',
        'file' => ':attribute en fazla :max kilobayt olabilir.',
        'numeric' => ':attribute en fazla :max olabilir.',
        'string' => ':attribute en fazla :max karakter olabilir.',
    ],
    'min' => [
        'numeric' => ':attribute en az :min olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
    ],
    'not_in' => 'Seçilen :attribute geçersiz.',
    'numeric' => ':attribute sayı olmalıdır.',
    'password' => [
        'min' => ':attribute en az :min karakter olmalıdır.',
    ],
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı zorunludur.',
    'required_if' => ':other :value olduğunda :attribute zorunludur.',
    'string' => ':attribute metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'url' => ':attribute geçerli bir bağlantı olmalıdır.',
    'alpha_dash' => ':attribute yalnızca harf, rakam, tire ve alt çizgi içerebilir.',

    'attributes' => [
        'name' => 'ad', 'code' => 'site kodu', 'email' => 'e-posta', 'password' => 'şifre', 'phone' => 'telefon',
        'amount' => 'tutar', 'description' => 'açıklama', 'due_date' => 'son ödeme tarihi', 'period' => 'dönem',
        'unit_id' => 'daire', 'unit_ids' => 'daireler', 'block_id' => 'blok', 'unit_group_id' => 'grup',
        'cashbox_id' => 'kasa', 'from_id' => 'kaynak kasa', 'to_id' => 'hedef kasa', 'category_id' => 'kategori',
        'vendor_id' => 'firma', 'unit_no' => 'daire no', 'full_name' => 'ad soyad', 'id_number' => 'TC kimlik no',
        'title' => 'başlık', 'content' => 'içerik', 'subject' => 'konu', 'body' => 'mesaj', 'target' => 'hedef',
        'question' => 'soru', 'options' => 'seçenekler', 'decision_no' => 'karar no', 'decided_on' => 'tarih',
        'gross_area' => 'm²', 'from' => 'ilk no', 'to' => 'son no', 'delta' => 'miktar değişimi', 'value' => 'okuma değeri',
        'read_on' => 'okuma tarihi', 'invite_code' => 'davet kodu', 'plate' => 'plaka', 'status' => 'durum',
        'confirm' => 'onay', 'opening_balance' => 'açılış bakiyesi', 'images.*' => 'görsel', 'due_day' => 'son ödeme günü',
    ],
];
