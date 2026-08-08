<?php
session_name('SID');
session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
]);

header('Content-Type: application/json; charset=UTF-8');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$config = [
    'google' => [
        'recaptcha' => [
            'site_key' => getenv('google_recaptcha_site_key') ?: null,
        ],
    ],
    'discord' => [
        'webhook_url' => getenv('discord_webhook_url') ?: null,
    ],
];
