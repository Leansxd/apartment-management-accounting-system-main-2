<?php

return [
    // Kurulum bittiğinde yazılır; dosya varken /setup bir daha açılmaz
    'marker' => env('SETUP_MARKER', storage_path('app/installed')),

    // Doluysa /setup yalnızca bu kodu bilen kişi tarafından tamamlanabilir
    'token' => env('SETUP_TOKEN'),
];
