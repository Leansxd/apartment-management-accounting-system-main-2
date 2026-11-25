<?php

return [
    // mysqldump PATH üzerinde değilse tam yolu (ör. C:\xampp\mysql\bin\mysqldump.exe)
    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    // mysql istemcisi; boşsa mysqldump ile aynı klasördeki mysql kullanılır
    'mysql' => env('BACKUP_MYSQL_PATH'),

    // Saklanacak son yedek sayısı
    'keep' => (int) env('BACKUP_KEEP', 14),
];
