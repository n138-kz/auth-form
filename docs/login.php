<?php
session_name('SID');
session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
]);

header('Content-Type: application/json; charset=UTF-8');
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
    $url = 'https://discord.com/api/webhooks/1535164470857441301/0zbvwxDyrPCIo-3067PQ2sBp7wxindDS5DBorb4cX4P2CF-NrS4K2D7IeyfZsKDxRV6f?wait=true';
    $content = "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```";
    $embed = [
        'title' => 'ユーザー名: ' . ($input['formdata']['dlym6tweiywa2taq']['value'] ?? null),
        'description' => 'IPアドレス: ' . ($_SERVER['REMOTE_ADDR'] ?? null) . "\n" .
                         'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\n" .
                         'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? null) . "\n" .
                         'Timestamp: ' . date('Y-m-d H:i:s') . "\n" .
                         'Cookie: ' . ($_COOKIE['SID'] ?? null),
        'color' => hexdec('333333'),
        'timestamp' => date('c'),
    ];
    $curl_req = curl_init($url);
    curl_setopt($curl_req, CURLOPT_POST, true);
    curl_setopt($curl_req, CURLOPT_POSTFIELDS, json_encode(['content' => $content, 'embeds' => [$embed]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($curl_req, CURLOPT_RETURNTRANSFER, true);
    $curl_result = json_decode(curl_exec($curl_req), true);
    echo json_encode($curl_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $curl_req = curl_init(explode('?', $url)[0].'/messages/'.$curl_result['id']);
    curl_setopt($curl_req, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($curl_req, CURLOPT_RETURNTRANSFER, true);
    $curl_result = json_decode(curl_exec($curl_req), true);
    echo json_encode($curl_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

}
