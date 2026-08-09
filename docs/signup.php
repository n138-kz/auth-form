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
        'authn_bot' => [
            'client_id' => getenv('DISCORD_AUTHN_BOT_CLIENT_ID') ?? null,
            'client_secret' => getenv('DISCORD_AUTHN_BOT_CLIENT_SECRET') ?? null,
            'redirect_uri' => getenv('DISCORD_AUTHN_BOT_REDIRECT_URI') ?? null,
            'scope' => getenv('DISCORD_AUTHN_BOT_SCOPE') ?? null,
        ],
    ],
    'internal' => [
        'database' => [
            'host' => getenv('INTERNAL_DB_HOST') ?? 'db',
            'port' => getenv('INTERNAL_DB_PORT') ?? '5432',
            'db'   => getenv('INTERNAL_DB_DATABASE') ?? 'myapp',
            'user' => getenv('INTERNAL_DB_USERNAME') ?? 'postgres',
            'pass' => getenv('INTERNAL_DB_PASSWORD') ?? 'password',
        ],
        'mail' => [
            'host' => getenv('INTERNAL_MAIL_HOST') ?? 'mail',
            'port' => getenv('INTERNAL_MAIL_PORT') ?? '25',
            'fromuser' => [
                'address' => getenv('INTERNAL_MAIL_SENDER_ADDR') ?? 'localhost.localnet.net',
                'name' => getenv('INTERNAL_MAIL_SENDER_NAME') ?? 'localhost',
            ],
        ],
    ],
];
{
    /* *DB CONNECTION TEST* */
    $host = $config['internal']['database']['host'];
    $port = $config['internal']['database']['port'];
    $db   = $config['internal']['database']['db'];
    $user = $config['internal']['database']['user'];
    $pass = $config['internal']['database']['pass'];
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
        {
            $payload = [
                'content' => "```json\n" . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
                'embeds' => [[
                    'title' => 'Invalid or expired reCAPTCHA token',
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

{
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $pdo -> beginTransaction();

        $stm = $pdo -> query('SELECT * FROM accounts_view_candidate');
        $res = $stm -> fetchAll(PDO::FETCH_ASSOC);

        $stm[0] = $pdo -> prepare('DELETE FROM accounts_attr WHERE account_id = :account_id');
        $stm[1] = $pdo -> prepare('DELETE FROM accounts WHERE userid = :userid');
        foreach ($res as $k => $v) {
            if (! isset($v['id'])) {
                continue;
            }
            if (! isset($v['userid'])) {
                continue;
            }

            $stm[0] -> execute([':account_id' => $v['id']]);
            $stm[1] -> execute([':userid' => $v['userid']]);
        }

        $pdo->commit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        die(json_encode([
            'code' => 500,
            'error' => 'Internal server error',
        ]));
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        die(json_encode([
            'code' => 500,
            'error' => 'Internal server error',
        ]));
    }
}
if(false) {
} elseif(isset($input['formdata']['dlym6tweiywa2taq']['value'])) {
    {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            {
                /* *User exist check* */
                $stm = $pdo -> prepare('SELECT count(userid) FROM accounts_view WHERE userid = :username');
                $stm -> execute([':username' => $input['formdata']['dlym6tweiywa2taq']['value']]);
                /* docker compose exec db psql -U postgres -d myapp -c 'SELECT * FROM accounts_view' */
                $res = $stm -> fetch(PDO::FETCH_ASSOC);
                if($res['count']>0) {
                    http_response_code(409);
                    die(json_encode([
                        'code' => 409,
                        'error' => 'User has already registed.',
                    ]));
                } elseif($res['count']<0) {
                    http_response_code(500);
                    die(json_encode([
                        'code' => 500,
                        'error' => 'Internal server error',
                    ]));
                }
            }
            $pdo -> beginTransaction();
            {
                {
                    /* *User add* */
                    $stm = $pdo -> prepare('INSERT INTO accounts(userid, password_hash) VALUES (:userid, :password) RETURNING id;');
                    $stm -> execute([
                        ':userid' => $input['formdata']['dlym6tweiywa2taq']['value'],
                        ':password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    ]);
                    $accountId = $stm->fetchColumn();
                }

                {
                    $stm = $pdo -> prepare('INSERT INTO accounts_attr(account_id) VALUES (:account_id)');
                    $stm -> execute([':account_id' => $accountId]);
                }
            }
            $pdo->commit();

            {
                $mailattr = [
                    'host' => $config['internal']['mail']['host'],
                    'port' => $config['internal']['mail']['port'],
                    'smtp' => [
                        'auth' => false,
                        'secure' => false,
                    ],
                    'charset' => 'UTF-8',
                    'delivery' => [
                        'from' => [
                            'address' => $config['internal']['mail']['fromuser']['address'],
                            'name' => $config['internal']['mail']['fromuser']['name'],
                        ],
                        'to' => [
                            'address' => $input['formdata']['dlym6tweiywa2taq']['value'],
                            'name' => $input['formdata']['dlym6tweiywa2taq']['value'],
                        ],
                    ],
                ];
                $mailbody['subject'] = 'n138.jp: アカウント本登録のお願い';
                $mailbody['content'] = '' .
                    "こんにちは！n138.jpです。\n" .
                    "このメールはアカウント仮登録が完了したことをお知らせするメールです。\n\n" .
                    "まだ本登録は完了していません。\n" .
                    "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['DOCUMENT_URI']) . '#passwordreset' . "\n\n" .
                    "6時間以内に本登録が完了しない場合、仮登録は削除されます。\n" .
                    date('c') . '';
                $mailbody['ishtml'] = false;

                $mail = sendMail($mailattr, $mailbody);
                {
                    $payload = [
                        'content' => "```json\n" . json_encode($mail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
                        'embeds' => [[
                            'title' => 'mail send result: ' . $mail['summary'],
                            'description' => '' .
                                'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                                'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                                'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                                'Timestamp: ' . '<t:' . time() . ':F> <t:' . time() . ':R>' . "\n" .
                                'Mail message: ' . ($mail['description'] ?? null),
                            'color' => $mail['code']===1 ? hexdec('FF0000') : hexdec('006400'),
                            'timestamp' => date('c'),
                        ]],
                    ];
                    sendDiscordWebhook($config['discord']['webhook_url'], $payload);
                }
            }

            http_response_code(201);
            echo json_encode([
                'code' => 201,
                'message' => 'Created',
            ]);
            $_SESSION = [];
            $_SESSION['userid'] = $input['formdata']['dlym6tweiywa2taq']['value'];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            /* *レースコンディション等で UNIQUE 制約違反(23505)が発生した場合のハンドリング* */
            if ($e->getCode() === '23505') {
                http_response_code(409);
                die(json_encode([
                    'code' => 409,
                    'error' => 'User has already registered.',
                ]));
            }

            http_response_code(500);
            die(json_encode([
                'code' => 500,
                'error' => 'Internal server error',
            ]));
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            die(json_encode([
                'code' => 500,
                'error' => 'Internal server error',
            ]));
        }
    }
}
