<?php
function rememberUser($username) {
    $token = bin2hex(random_bytes(32));
    setcookie('remember_token', $token, time() + (86400 * 7), "/", "", false, true); 
    file_put_contents(__DIR__ . '/remember.json', json_encode([
        'username' => $username,
        'token' => $token,
        'expires' => time() + (86400 * 7)
    ]));
}

function getRememberedUser() {
    if (!isset($_COOKIE['remember_token'])) return null;
    $file = __DIR__ . '/remember.json';
    if (!file_exists($file)) return null;

    $data = json_decode(file_get_contents($file), true);
    if ($data && $data['token'] === $_COOKIE['remember_token'] && $data['expires'] > time()) {
        return $data['username'];
    }
    return null;
}

function clearRememberedUser() {
    setcookie('remember_token', '', time() - 3600, "/");
    $file = __DIR__ . '/remember.json';
    if (file_exists($file)) unlink($file);
}
?>
