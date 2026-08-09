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

echo json_encode([
    'google' => [
        'recaptcha' => [
            'site_key' => getenv('GOOGLE_RECAPTCHA_SITE_KEY') ?? null,
        ],
    ],
    'discord' => [
        'webhook_url' => getenv('DISCORD_WEBHOOK_URL') ?? null,
        'client_id' => getenv('DISCORD_AUTHN_BOT_CLIENT_ID') ?? null,
        'redirect_uri' => getenv('DISCORD_AUTHN_BOT_REDIRECT_URI') ?? null,
        'scope' => getenv('DISCORD_AUTHN_BOT_SCOPE') ?? null,
    ],
]);
