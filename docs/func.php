<?php
ini_set('display_errors', 0);
function sendDiscordWebhook(string $webhookUrl = '', array $payload = ['content'=>'Hello-Discord-World'], array $databaseConfiguration = []) {
    if (empty($payload)) {
        return;
    }

    $url = $webhookUrl . '?wait=true';
    $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    $tmpfile = null;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if(mb_strlen($payload)<=2000) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
    } else {
        $tmpfile = tmpfile();
        fwrite($tmpfile, $payload);
        $tmpfilepath = stream_get_meta_data($tmpfile)['uri'];

        $cfile = new \CURLFile($tmpfilepath, 'application/json', 'payload.json');
        $payload = [
            'files[0]' => $cfile,
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, []);
    }
    $curl_result = json_decode(curl_exec($ch), true);

    if (isset($tmpfile) && is_resource($tmpfile)) {
        fclose($tmpfile);
    }

    if(! empty($databaseConfiguration)){
        $config = $databaseConfiguration;
        if(
            isset($config['host']) && isset($config['port']) && isset($config['db'])
            &&
            isset($config['user']) && isset($config['pass'])
        ) {
            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['db']}";
            try {
                $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $stm = $pdo -> prepare('INSERT INTO webhook_discord(m_response) VALUES (:response)');
                $stm -> execute([':response' => json_encode($curl_result)]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        } else {
            error_log('Database parametors has empty.');
        }
    }

    {
        /* *https://zenn.dev/niisan/articles/cb3cedeeaf3ed7* */
        $pid = pcntl_fork();
        if ($pid === 0) {
            posix_setsid();

            sleep(300);

            $ch = curl_init(explode('?', $url)[0].'/messages/'.$curl_result['id']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $curl_result = json_decode(curl_exec($ch), true);
            exit(0);
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function sendMail(array $mailattr = [], array $mailbody = []) {
    $mailattr['host'] = $mailattr['host'] ?? 'mail';
    $mailattr['port'] = $mailattr['port'] ?? 25;
    $mailattr['smtp'] = $mailattr['smtp'] ?? [];
    $mailattr['smtp']['auth'] = $mailattr['smtp']['auth'] ?? true;
    $mailattr['smtp']['secure'] = $mailattr['smtp']['secure'] ?? true;
    $mailattr['charset'] = $mailattr['charset'] ?? 'UTF-8';
    $mailattr['delivery'] = $mailattr['delivery'] ?? [];
    $mailattr['delivery']['from'] = $mailattr['delivery']['from'] ?? [];
    $mailattr['delivery']['from']['address'] = $mailattr['delivery']['from']['address'] ?? '';
    $mailattr['delivery']['from']['name'] = $mailattr['delivery']['from']['name'] ?? $mailattr['delivery']['from']['address'];
    $mailattr['delivery']['to'] = $mailattr['delivery']['to'] ?? [];
    $mailattr['delivery']['to']['address'] = $mailattr['delivery']['to']['address'] ?? '';
    $mailattr['delivery']['to']['name'] = $mailattr['delivery']['to']['name'] ?? $mailattr['delivery']['to']['address'];
    
    $mailbody['subject'] = $mailbody['subject'] ?? 'テストメール: ' . date('c');
    $mailbody['content'] = $mailbody['content'] ?? "テストメール\nHELLO WORLD!!\n" . date('c') . '';
    $mailbody['ishtml'] = $mailbody['ishtml'] ?? false;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $mailattr['host'];
        $mail->Port       = $mailattr['port'];
        $mail->SMTPAuth   = $mailattr['smtp']['auth'];
        $mail->SMTPSecure = $mailattr['smtp']['secure'];
        $mail->CharSet    = $mailattr['charset'];

        $mail->setFrom($mailattr['delivery']['from']['address'], $mailattr['delivery']['from']['name']);
        $mail->addAddress($mailattr['delivery']['to']['address'], $mailattr['delivery']['to']['name']);

        $mail->isHTML($mailbody['ishtml']);
        $mail->Subject = $mailbody['subject'];
        $mail->Body    = $mailbody['content'];

        $mail->send();
        return [
            'code' => 0,
            'summary' => '送信成功',
            'description' => 'メールが送信されました',
            'sendto' => "{$mailattr['delivery']['to']['name']} <{$mailattr['delivery']['to']['address']}>",
        ];
    } catch (\Exception $th) {
        return [
            'code' => 1,
            'summary' => '送信失敗',
            'description' => $mail->ErrorInfo,
            'sendto' => "{$mailattr['delivery']['to']['name']} <{$mailattr['delivery']['to']['address']}>",
        ];
        return false;
    }
}
