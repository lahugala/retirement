<?php
// Application configuration
defined('JWT_SECRET') || define('JWT_SECRET', 'r3t1r3m3nt_s0c13ty_s3cr3t_k3y_2024');
defined('JWT_EXPIRY') || define('JWT_EXPIRY', 86400); // 24 hours in seconds
defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/../uploads/');
defined('MAX_FILE_SIZE') || define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
defined('APPROVAL_THRESHOLD') || define('APPROVAL_THRESHOLD', 100.00);
defined('BUDGET_APPROVAL_THRESHOLD') || define('BUDGET_APPROVAL_THRESHOLD', 1000.00);
defined('CORS_ORIGIN') || define('CORS_ORIGIN', '*');

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'dbname' => getenv('DB_NAME') ?: 'retirement_society',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '505974',
    ]
];
