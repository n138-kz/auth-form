<?php
$username = 'admin';
$password = 'gXPPj8yn'; // 任意のパスワードに変更してください

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    !isset($_SERVER['PHP_AUTH_PW']) || 
    $_SERVER['PHP_AUTH_USER'] !== $username || 
    $_SERVER['PHP_AUTH_PW'] !== $password) {

    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo '認証が必要です。';
    exit;
}

// 認証成功時に phpinfo を表示
phpinfo();
