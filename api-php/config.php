<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => getenv('DB_PORT') ?: '1433',
        'database' => 'PharmaPro_v2',
        'username' => getenv('DB_USER') ?: 'sa',
        'password' => getenv('DB_PASS') ?: 'YourStrong@Passw0rd123', 
    ],
    'jwt' => [
        'secret' => 'PharmaPro_v2_Clinical_Secret_Key_2026_!@#',
        'expire' => 86400 // 24 giờ
    ],
    'ocr_service_url' => 'http://host.docker.internal:8000/ocr/prescription',
    'zalo' => [
        'app_id' => 'YOUR_ZALO_APP_ID',
        'oa_secret_key' => 'YOUR_ZALO_OA_SECRET'
    ]
];
