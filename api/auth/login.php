<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

auth_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    auth_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = auth_read_json_body();
$email = auth_normalize_email((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if (!$email || !auth_is_valid_email($email)) {
    auth_send_json(['ok' => false, 'error' => 'invalid_email'], 400);
}
if ($password === '') {
    auth_send_json(['ok' => false, 'error' => 'missing_password'], 400);
}

try {
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT email, password_hash, created_at, updated_at, last_login FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        auth_send_json(['ok' => false, 'error' => 'user_not_found'], 404);
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        auth_send_json(['ok' => false, 'error' => 'invalid_password'], 401);
    }

    $now = date('c');
    $upd = $pdo->prepare('UPDATE users SET last_login = ? WHERE email = ?');
    $upd->execute([$now, $email]);

    $user['last_login'] = $now;
    unset($user['password_hash']);

    // Сохраняем email в сессию для проверки доступа к лотам
    session_start();
    $_SESSION['user_email'] = $email;

    auth_log('LOGIN ok', ['email' => $email]);
    auth_send_json(['ok' => true, 'user' => $user]);
} catch (Throwable $e) {
    auth_log('LOGIN exception', ['message' => $e->getMessage()]);
    auth_send_json(['ok' => false, 'error' => 'server_error'], 500);
}


