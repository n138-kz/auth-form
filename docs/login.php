<?php
session_name('SID');
session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
]);

function sendDiscordWebhook(string $webhookUrl, array $payload) {
    $url = $webhookUrl . '?wait=true';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    $curl_result = json_decode(curl_exec($ch), true);

    {
        /* *https://zenn.dev/niisan/articles/cb3cedeeaf3ed7* */
        $pid = pcntl_fork();
        if ($pid === 0) {
            sleep(300);
            $ch = curl_init(explode('?', $url)[0].'/messages/'.$curl_result['id']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $curl_result = json_decode(curl_exec($ch), true);
        } else if ($pid === -1) {
            throw new RuntimeException('プロセスの作成に失敗した模様');
        }
    }

    return null;
}
function verifyRecaptcha(string $token, string $secretKey) {
    /**
     * reCAPTCHA v3 トークンを検証する関数
     *
     * @param string $token クライアントから送信された g-recaptcha-response
     * @param string $secretKey reCAPTCHA の Secret Key
     * @return array|false 検証結果の連想配列、または失敗時 false
     */
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = [
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null // 任意（セキュリティ向上）
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error || !$response) {
        return false;
    }

    return json_decode($response, true);
}

header('Content-Type: application/json; charset=UTF-8');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if(!isset($_SERVER['HTTP_USER_AGENT']) || !isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(400);
    die(json_encode([
        'code' => 400,
        'error' => 'Bad Request (400): Missing required headers.',
    ]));
}
if(('POST' !== $_SERVER['REQUEST_METHOD'])) {
    http_response_code(400);
    die(json_encode([
        'code' => 400,
        'error' => 'Bad Request (400): Invalid request method.',
    ]));
}

$config = [
    'google' => [
        'recaptcha' => [
            'site_key' => getenv('GOOGLE_RECAPTCHA_SITE_KEY') ?: null,
            'secret_key' => getenv('GOOGLE_RECAPTCHA_SECRET_KEY') ?: null,
        ],
    ],
    'ipinfo' => [
        'token' => getenv('IPINFO_TOKEN') ?: null,
    ],
    'discord' => [
        'webhook_url' => getenv('DISCORD_WEBHOOK_URL') ?: null,
        'client_id' => getenv('DISCORD_AUTHN_BOT_CLIENT_ID') ?: null,
        'client_secret' => getenv('DISCORD_AUTHN_BOT_CLIENT_SECRET') ?: null,
        'redirect_uri' => getenv('DISCORD_AUTHN_BOT_REDIRECT_URI') ?: null,
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
                    'description' => '' .
                        'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                        'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                        'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                        'Timestamp: ' . '<t:' . time() . ':F> <t:' . time() . ':R>' . "\n" .
                        'Cookie: ' . ($_COOKIE['SID'] ?? null),
                    'color' => hexdec('FF0000'),
                    'timestamp' => date('c'),
                ]],
            ];
            sendDiscordWebhook($config['discord']['webhook_url'], $payload);
        }

        http_response_code(503);
        die(json_encode([
            'code' => 503,
            'error' => 'System Error (503): Database connection failed.',
        ]));
    }
}

$input = json_decode(file_get_contents('php://input'), true);

if (! isset($input['formdata']['g-recaptcha-response']['token'])) {
    {
        $payload = [
            'content' => "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
            'embeds' => [[
                'title' => 'Missing reCAPTCHA response',
                'description' => '' .
                    'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                    'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                    'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                    'Timestamp: ' . '<t:' . time() . ':F> <t:' . time() . ':R>' . "\n" .
                    'Cookie: ' . ($_COOKIE['SID'] ?? null),
                'color' => hexdec('FF0000'),
                'timestamp' => date('c'),
            ]],
        ];
        sendDiscordWebhook($config['discord']['webhook_url'], $payload);
    }
    http_response_code(400);
    die(json_encode([
        'code' => 400,
        'error' => 'Bad Request (400): Missing reCAPTCHA response.',
    ]));
}
{
    $result = verifyRecaptcha($input['formdata']['g-recaptcha-response']['token'], $config['google']['recaptcha']['secret_key']);
    $result['success'] = $result['success'] ?? false;
    $result['score'] = $result['score'] ?? (float) 0.0;
    $result['action'] = $result['action'] ?? null;
    $result['challenge_ts'] = $result['challenge_ts'] ?? null;
    $result['hostname'] = $result['hostname'] ?? null;
    $result['error-codes'] = $result['error-codes'] ?? [];

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

{
    $input['formdata'] = $input['formdata'] ?? null;
    $input['formdata']['g-recaptcha-response'] = $input['formdata']['g-recaptcha-response'] ?? null;
    $input['formdata']['g-recaptcha-response']['token'] = $input['formdata']['g-recaptcha-response']['token'] ?? null;
    $input['formdata']['g-recaptcha-response']['expire_at'] = $input['formdata']['g-recaptcha-response']['expire_at'] ?? null;
    $input['formdata']['g-recaptcha-response']['issued_at'] = $input['formdata']['g-recaptcha-response']['issued_at'] ?? null;
    $input['formdata']['dlym6tweiywa2taq'] = $input['formdata']['dlym6tweiywa2taq'] ?? null;
    $input['formdata']['dlym6tweiywa2taq']['value'] = $input['formdata']['dlym6tweiywa2taq']['value'] ?? null;
}
{
    $payload = [
        'content' => "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
        'embeds' => [[
            'title' => 'ユーザー名: ' . ($input['formdata']['dlym6tweiywa2taq']['value'] ?? null),
            'description' => '' .
                'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                'Timestamp: ' . '<t:' . time() . ':F> <t:' . time() . ':R>' . "\n" .
                'Cookie: ' . ($_COOKIE['SID'] ?? null),
            'color' => hexdec('333333'),
            'timestamp' => date('c'),
        ]],
    ];
    sendDiscordWebhook($config['discord']['webhook_url'], $payload);
}
