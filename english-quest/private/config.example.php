<?php
declare(strict_types=1);

return [
    'app_url' => getenv('EQ_APP_URL') ?: 'https://english.soloworks.pro',
    'db' => [
        'host' => getenv('EQ_DB_HOST') ?: 'localhost',
        'name' => getenv('EQ_DB_NAME') ?: '',
        'user' => getenv('EQ_DB_USER') ?: '',
        'password' => getenv('EQ_DB_PASSWORD') ?: '',
    ],
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => getenv('GEMINI_MODEL') ?: '',
        'enabled' => (bool) getenv('GEMINI_API_KEY') && (bool) getenv('GEMINI_MODEL'),
        'auto_publish' => false,
        'interval_hours' => 24,
    ],
    'mail' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('SMTP_USERNAME') ?: '',
        'from_name' => 'SOLOWORKS',
    ],
];
