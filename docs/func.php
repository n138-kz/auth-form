<?php
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
