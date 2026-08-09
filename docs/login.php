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
if (file_exists(__DIR__ . '/func.php')) {
    require_once __DIR__ . '/func.php';
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
            'site_key' => getenv('GOOGLE_RECAPTCHA_SITE_KEY') ?? null,
            'secret_key' => getenv('GOOGLE_RECAPTCHA_SECRET_KEY') ?? null,
        ],
    ],
    'ipinfo' => [
        'token' => getenv('IPINFO_TOKEN') ?? null,
    ],
    'discord' => [
        'webhook_url' => getenv('DISCORD_WEBHOOK_URL') ?? null,
        'client_id' => getenv('DISCORD_AUTHN_BOT_CLIENT_ID') ?? null,
        'client_secret' => getenv('DISCORD_AUTHN_BOT_CLIENT_SECRET') ?? null,
        'redirect_uri' => getenv('DISCORD_AUTHN_BOT_REDIRECT_URI') ?? null,
        'scope' => getenv('DISCORD_AUTHN_BOT_SCOPE') ?? null,
    ],
];
{
    /* *DB CONNECTION TEST* */
    $host = getenv('INTERNAL_DB_HOST') ?? 'db';
    $port = getenv('INTERNAL_DB_PORT') ?? '5432';
    $db   = getenv('INTERNAL_DB_DATABASE') ?? 'myapp';
    $user = getenv('INTERNAL_DB_USERNAME') ?? 'postgres';
    $pass = getenv('INTERNAL_DB_PASSWORD') ?? 'password';
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
    $tables = [
        'accounts',
        'accounts_otp',
        'accounts_view',
        'accounts_view_unsafe',
    ];
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
$input['formdata'] = $input['formdata'] ?? null;

{
    $input['formdata']['g-recaptcha-response'] = $input['formdata']['g-recaptcha-response'] ?? null;
    $input['formdata']['g-recaptcha-response']['token'] = $input['formdata']['g-recaptcha-response']['token'] ?? null;
    $input['formdata']['g-recaptcha-response']['expire_at'] = $input['formdata']['g-recaptcha-response']['expire_at'] ?? null;
    $input['formdata']['g-recaptcha-response']['issued_at'] = $input['formdata']['g-recaptcha-response']['issued_at'] ?? null;
}
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

    if($result['success']!==true) {
        http_response_code(400);
        die(json_encode([
            'code' => 400,
            'error' => 'Bad Request (400): Invalid or expired reCAPTCHA token.',
        ]));
    }
}

{
    $input['provider'] = $input['provider'] ?? null;
    $input['formdata']['dlym6tweiywa2taq'] = $input['formdata']['dlym6tweiywa2taq'] ?? [];
    $input['formdata']['dlym6tweiywa2taq']['value'] = $input['formdata']['dlym6tweiywa2taq']['value'] ?? null;
}
if ($input['provider'] != 'internal') {
    http_response_code(400);
    die(json_encode([
        'code' => 400,
        'error' => 'Bad Request (400): Missing request parametor.',
    ]));
}

if(false) {
} elseif(isset($input['formdata']['dlym6tweiywa2taq']['value'])) {
    {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo -> prepare('SELECT userid FROM accounts_view WHERE userid = :username')
             -> execute([':username' => $input['formdata']['dlym6tweiywa2taq']['value']]);
             /* docker compose exec db psql -U postgres -d myapp -c 'SELECT * FROM accounts_view' */
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
}
