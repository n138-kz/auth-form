<?php
ini_set('display_errors', 0);
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
        ];
    } catch (\Exception $th) {
        return [
            'code' => 1,
            'summary' => '送信失敗',
            'description' => $mail->ErrorInfo,
        ];
        return false;
    }
}
