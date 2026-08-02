<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

auth_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    auth_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = auth_read_json_body();
$email = auth_normalize_email((string)($body['email'] ?? ''));
$oldPassword = (string)($body['oldPassword'] ?? '');
$newPassword = (string)($body['newPassword'] ?? '');

if (!$email || !auth_is_valid_email($email)) {
    auth_send_json(['ok' => false, 'error' => 'invalid_email'], 400);
}
if ($oldPassword === '' || $newPassword === '') {
    auth_send_json(['ok' => false, 'error' => 'missing_passwords'], 400);
}
if (strlen($newPassword) < 8) {
    auth_send_json(['ok' => false, 'error' => 'weak_password'], 400);
}

try {
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        auth_send_json(['ok' => false, 'error' => 'user_not_found'], 404);
    }

    if (!password_verify($oldPassword, (string)$user['password_hash'])) {
        auth_send_json(['ok' => false, 'error' => 'invalid_password'], 401);
    }

    $now = date('c');
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE email = ?');
    $upd->execute([$hash, $now, $email]);

    auth_log('CHANGE_PASSWORD ok', ['email' => $email]);
    auth_send_json(['ok' => true]);
} catch (Throwable $e) {
    auth_log('CHANGE_PASSWORD exception', ['message' => $e->getMessage()]);
    auth_send_json(['ok' => false, 'error' => 'server_error'], 500);
}


