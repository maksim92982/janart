<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

auth_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    auth_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = auth_read_json_body();
$email = auth_normalize_email((string)($body['email'] ?? ''));

if (!$email || !auth_is_valid_email($email)) {
    auth_send_json(['ok' => false, 'error' => 'invalid_email'], 400);
}

try {
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        auth_send_json(['ok' => false, 'error' => 'user_not_found'], 404);
    }

    $oldHash = (string)$user['password_hash'];
    $newPassword = auth_generate_password(8);
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $now = date('c');

    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE email = ?');
    $upd->execute([$newHash, $now, $email]);

    $subject = 'Восстановление пароля — JANART STUDIO';
    $text = "Здравствуйте!\n\n"
        . "Вы запросили восстановление пароля на сайте JANART STUDIO.\n\n"
        . "Ваш новый пароль:\n"
        . "{$newPassword}\n\n"
        . "Email: {$email}\n\n"
        . "Если вы не запрашивали восстановление — просто проигнорируйте это письмо.\n\n"
        . "С уважением,\nJANART STUDIO\n";

    $sent = auth_send_password_email($email, $subject, $text);
    if (!$sent) {
        // откатываем пароль
        $rollback = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
        $rollback->execute([$oldHash, $email]);
        $pdo->commit();
        auth_log('RECOVER email_send_failed', ['email' => $email]);
        auth_send_json(['ok' => false, 'error' => 'email_send_failed'], 500);
    }

    $pdo->commit();
    auth_log('RECOVER ok', ['email' => $email]);
    auth_send_json(['ok' => true]);
} catch (Throwable $e) {
    auth_log('RECOVER exception', ['message' => $e->getMessage()]);
    auth_send_json(['ok' => false, 'error' => 'server_error'], 500);
}


