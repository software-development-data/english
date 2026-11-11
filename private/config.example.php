<?php
declare(strict_types=1);

/* Copy this file to private/config.php on the server. Never commit real secrets. */
return [
    'app_url' => 'https://english.soloworks.pro',
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'password' => 'SET_DATABASE_PASSWORD_HERE',
    ],
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite',
        'enabled' => false,
        'auto_publish' => false,
        'interval_hours' => 24,
    ],
    'mail' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'YOUR_HOSTINGER_MAILBOX',
        'password' => 'SET_SMTP_PASSWORD_HERE',
        'from_email' => 'YOUR_HOSTINGER_MAILBOX',
        'from_name' => 'ENGLISH QUEST',
    ],
];
