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
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = (bool)$stmt->fetchColumn();

    if ($exists) {
        $pdo->rollBack();
        auth_send_json(['ok' => false, 'error' => 'email_exists'], 409);
    }

    $plainPassword = auth_generate_password(8);
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $now = date('c');

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
    $insert->execute([$email, $hash, $now]);

    $subject = 'Регистрация — JANART STUDIO';
    $text = "Здравствуйте!\n\n"
        . "Вы зарегистрировались на сайте JANART STUDIO.\n\n"
        . "Ваши данные для входа:\n"
        . "- Email: {$email}\n"
        . "- Пароль: {$plainPassword}\n\n"
        . "Если вы не регистрировались — просто проигнорируйте это письмо.\n\n"
        . "С уважением,\nJANART STUDIO\n";

    $sent = auth_send_password_email($email, $subject, $text);
    if (!$sent) {
        $pdo->rollBack();
        auth_log('REGISTER email_send_failed', ['email' => $email]);
        auth_send_json(['ok' => false, 'error' => 'email_send_failed'], 500);
    }

    $pdo->commit();
    auth_log('REGISTER ok', ['email' => $email]);
    auth_send_json(['ok' => true]);
} catch (Throwable $e) {
    auth_log('REGISTER exception', ['message' => $e->getMessage()]);
    auth_send_json(['ok' => false, 'error' => 'server_error'], 500);
}


