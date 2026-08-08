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
    /*
    PHPでユーザーのログインパスワードを保存・認証する場合は、hash_hmac() ではなく、password_hash() と password_verify() を使用するのが現在のベストプラクティス（標準仕様）です。 [1, 2] 
    hash_hmac() は処理速度が速すぎるため、万が一ハッシュ値が漏洩した際に、高速な総当たり攻撃（ブルートフォース攻撃）で元のパスワードを割り出されてしまうリスクがあります。 [3] 
    それぞれの用途と、どうしても hash_hmac() をパスワード処理に組み合わせたい場合の正しい使い方を解説します。
    ------------------------------
    ## 1. 最も安全なパスワードハッシュ化（推奨）
    PHP標準の [password_hash](https://www.php.net/manual/ja/function.password-hash.php) は、内部で bcrypt や Argon2 といった意図的に計算負荷を高くした（重い）アルゴリズムを使用するため、総当たり攻撃に対して非常に強固です。 [1] 
    ## パスワード登録時（ハッシュ化して保存） [4] 

    $password = $_POST['password'];

    // 自動的に安全なソルトが生成され、安全なハッシュが作られます
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // この $hashed_password（約60〜255文字）をデータベースに保存します

    ## ログイン認証時（一致チェック）

    $user_input = $_POST['password']; // ユーザーが入力したパスワード
    $db_hash = $user['password'];     // DBから取得したハッシュ値

    // password_verifyがソルトの解析から一致チェックまで自動で行います
    if (password_verify($user_input, $db_hash)) {
        echo "ログイン成功";
    } else {
        echo "パスワードが間違っています";
    }

    ------------------------------
    ## 2. hash_hmac() をパスワードに使うケース
    [hash_hmac](https://www.php.net/manual/ja/function.hash-hmac.php) をパスワード処理に使うべき、あるいは使わざるを得ないのは主に以下の2つのケースです。 [5, 6] 

    * APIの署名（トークン）生成や、システム間での共通鍵認証
    * bcrypt の最大72文字制限を回避するための前処理 [7, 8, 9] 

    ## 応用：bcryptの72文字制限を回避するテクニック [7, 10] 
    password_hash() のデフォルトである bcrypt には、「入力されたパスワードの72文字目までしか認識しない（73文字目以降を無視する）」という仕様上の制限があります。
    これを回避するために、あらかじめパスワードを hash_hmac() で固定長のハッシュ（例：SHA-256であれば常に64文字）に変換してから password_hash() に渡す手法があります。 [7, 11, 12, 13, 14] 

    $password = $_POST['password'];
    $secret_key = 'your-system-secret-key'; // システム固有の秘密鍵

    // 1. パスワードを一度HMACで固定長にする（72文字制限対策）
    $pre_hash = hash_hmac('sha256', $password, $secret_key);

    // 2. 固定長になったハッシュを、安全なパスワードハッシュ関数に通す
    $final_hash = password_hash($pre_hash, PASSWORD_DEFAULT);

    // データベースには $final_hash を保存する

    ------------------------------
    ## 3. hash_hmac() の基本構文（参考）
    データ改ざん検知やトークン生成に hash_hmac 単体を使う場合の書き方です。 [9] 

    // hash_hmac('アルゴリズム', 'ハッシュ化したい文字列', '秘密の鍵')
    $hmac = hash_hmac('sha256', 'my_password_or_data', 'secret_shared_key');

    echo $hmac; // 64文字の16進数文字列が出力される

    ------------------------------
    どのような用途で hash_hmac を検討されていますか？

    * 新規サイトのユーザーログイン機能を作っている
    * 外部APIとの認証（署名）を実装したい
    * 既存の古いシステムからパスワードデータを移行したい

    状況に合わせて最適なセキュリティ実装をご案内します。 [15] 

    [1] [https://www.php.net](https://www.php.net/manual/ja/function.password-hash.php)
    [2] [https://riv-sol.com](https://riv-sol.com/archives/5302)
    [3] [https://www.php.net](https://www.php.net/manual/ja/faq.passwords.php)
    [4] [https://qiita.com](https://qiita.com/rana_kualu/items/3ef57485be1103362f56)
    [5] [https://tech.innovator.jp.net](https://tech.innovator.jp.net/entry/laravel-custom-hasher)
    [6] [https://blog.ohgaki.net](https://blog.ohgaki.net/5505)
    [7] [https://blog.tokumaru.org](https://blog.tokumaru.org/2019/02/caution-bcrypt-with-sha512.html)
    [8] [https://tech.gootablog.com](https://tech.gootablog.com/article/php-hash/)
    [9] [https://app.engr-sng.com](https://app.engr-sng.com/programming-language/detail/php-8-extension-function-hash-hash-hmac-5b859a39)
    [10] [https://riv-sol.com](https://riv-sol.com/archives/5302)
    [11] [https://micmap.org](http://micmap.org/php-by-example/manual/ja/function.crypt.html)
    [12] [https://riv-sol.com](https://riv-sol.com/archives/5302)
    [13] [https://www.webdesignleaves.com](https://www.webdesignleaves.com/pr/plugins/password_hash_generator.html)
    [14] [https://qiita.com](https://qiita.com/kurodariuto/items/676ef655869656b2f168)
    [15] [https://www.php.net](https://www.php.net/manual/ja/function.hash-hmac.php)
    */
    {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo -> beginTransaction();
            {
                /* *User exist check* */
                $stm = $pdo -> prepare('SELECT count(userid) FROM accounts_view WHERE userid = :username');
                $stm -> execute([':username' => $input['formdata']['dlym6tweiywa2taq']['value']]);
                /* docker compose exec db psql -U postgres -d myapp -c 'SELECT * FROM accounts_view' */
                $res = $stm -> fetch(PDO::FETCH_ASSOC);
                if($res['count']>0) {
                    http_response_code(200);
                    die(json_encode([
                        'code' => 200,
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
            {
                /* *User add* */
                $stm = $pdo -> prepare('INSERT INTO accounts(userid, password_hash) VALUES (:userid, :password) RETURNING id;');
                $stm -> execute([
                    ':userid' => $input['formdata']['dlym6tweiywa2taq']['value'],
                    ':password' => (
                        password_hash(
                        hash_hmac('sha256', 
                            microtime(true) . $input['formdata']['dlym6tweiywa2taq']['value'],
                            $input['formdata']['dlym6tweiywa2taq']['value']
                        ), PASSWORD_DEFAULT)
                    ),
                ]);
                $accountId = $stm->fetchColumn();

                $stm = $pdo -> prepare('INSERT INTO accounts_otp(account_id) VALUES (:account_id)');
                $stm -> execute([':account_id' => $accountId]);

                $stm = $pdo -> prepare('INSERT INTO accounts_attr(account_id) VALUES (:account_id)');
                $stm -> execute([':account_id' => $accountId]);

                $stm = $pdo -> prepare('INSERT INTO accounts_attr_thirdparty_accounts(account_id) VALUES (:account_id)');
                $stm -> execute([':account_id' => $accountId]);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
    http_response_code(503);
    die('not ready');
    {
    }
}
