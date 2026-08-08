<?php
session_name('SID');
session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
]);

function sendDiscordWebhook($webhookUrl, $payload) {
    $url = $webhookUrl . '?wait=true';

    $curl_req = curl_init($url);
    curl_setopt($curl_req, CURLOPT_POST, true);
    curl_setopt($curl_req, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    curl_setopt($curl_req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    $curl_result = json_decode(curl_exec($curl_req), true);

    {
        /* *https://zenn.dev/niisan/articles/cb3cedeeaf3ed7* */
        $pid = pcntl_fork();
        if ($pid === 0) {
            sleep(300);
            $curl_req = curl_init(explode('?', $url)[0].'/messages/'.$curl_result['id']);
            curl_setopt($curl_req, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($curl_req, CURLOPT_RETURNTRANSFER, true);
            $curl_result = json_decode(curl_exec($curl_req), true);
        } else if ($pid === -1) {
            throw new RuntimeException('プロセスの作成に失敗した模様');
        }
    }

    return null;
}

header('Content-Type: application/json; charset=UTF-8');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$config = [
    'google' => [
        'recaptcha' => [
            'site_key' => getenv('google_recaptcha_site_key') ?: null,
            'secret_key' => getenv('google_recaptcha_secret_key') ?: null,
        ],
    ],
    'ipinfo' => [
        'token' => getenv('ipinfo_token') ?: null,
    ],
    'discord' => [
        'webhook_url' => getenv('DISCORD_WEBHOOK_URL') ?: null,
        'client_id' => getenv('DISCORD_AUTHN_BOT_CLIENT_ID') ?: null,
    ],
];
{
    /* *DB CONNECTION TEST* */
    $host = getenv('INTERNAL_DB_HOST') ?: 'db';
    $port = getenv('INTERNAL_DB_PORT') ?: '5432';
    $db   = getenv('INTERNAL_DB_DATABASE') ?: 'myapp';
    $user = getenv('INTERNAL_DB_USERNAME') ?: 'postgres';
    $pass = getenv('INTERNAL_DB_PASSWORD') ?: 'password';
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        {
            $payload = [
                'content' => "```json\n" . json_encode([
                    'error' => $e->getMessage(),
                    'dsn' => $dsn,
                    'user' => $user,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
                'embeds' => [[
                    'title' => 'DB CONNECTION ERROR',
                    'description' => 'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                                    'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                                    'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                                    'Timestamp: ' . date('Y-m-d H:i:s') . "\n" .
                                    'Cookie: ' . ($_COOKIE['SID'] ?? null),
                    'color' => hexdec('FF0000'),
                    'timestamp' => date('c'),
                ]],
            ];
            sendDiscordWebhook($config['discord']['webhook_url'], $payload);
        }

        http_response_code(503);
        die('System Error (503): Database connection failed.');
    }
}

$input = json_decode(file_get_contents('php://input'), true);

{
    $input['formdata'] = $input['formdata'] ?? null;
    $input['formdata']['dlym6tweiywa2taq'] = $input['formdata']['dlym6tweiywa2taq'] ?? null;
    $input['formdata']['dlym6tweiywa2taq']['value'] = $input['formdata']['dlym6tweiywa2taq']['value'] ?? null;
    $input['formdata']['g-recaptcha-response'] = $input['formdata']['g-recaptcha-response'] ?? null;
    $input['formdata']['g-recaptcha-response']['token'] = $input['formdata']['g-recaptcha-response']['token'] ?? null;
    $input['formdata']['g-recaptcha-response']['expire_at'] = $input['formdata']['g-recaptcha-response']['expire_at'] ?? null;
    $input['formdata']['g-recaptcha-response']['issued_at'] = $input['formdata']['g-recaptcha-response']['issued_at'] ?? null;
}
{
    $payload = [
        'content' => "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
        'embeds' => [[
            'title' => 'ユーザー名: ' . ($input['formdata']['dlym6tweiywa2taq']['value'] ?? null),
            'description' => 'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                            'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                            'Timestamp: ' . date('Y-m-d H:i:s') . "\n" .
                            'Cookie: ' . ($_COOKIE['SID'] ?? null),
            'color' => hexdec('333333'),
            'timestamp' => date('c'),
        ]],
    ];
    sendDiscordWebhook($config['discord']['webhook_url'], $payload);
}
