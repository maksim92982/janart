<?php

declare(strict_types=1);

function auth_send_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_handle_preflight(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        auth_send_json(['ok' => true], 200);
    }
}

function auth_read_json_body(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function auth_storage_dir(): string {
    return __DIR__ . '/../storage';
}

function auth_log_path(): string {
    return auth_storage_dir() . '/auth.log';
}

function auth_log(string $message, array $context = []): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $time = date('Y-m-d H:i:s');
    $ctx = $context ? (' ' . json_encode($context, JSON_UNESCAPED_UNICODE)) : '';
    $line = sprintf("[%s] [%s] %s%s\n", $time, $ip, $message, $ctx);
    @file_put_contents(auth_log_path(), $line, FILE_APPEND | LOCK_EX);
}

function auth_db_path(): string {
    return auth_storage_dir() . '/janart.sqlite';
}

function auth_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = auth_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . auth_db_path(), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            email TEXT PRIMARY KEY,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL,
            last_login TEXT NULL
        )'
    );

    return $pdo;
}

function auth_normalize_email(string $email): string {
    return strtolower(trim($email));
}

function auth_is_valid_email(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function auth_generate_password(int $length = 8): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function auth_mail_from(): string {
    // Важно: чтобы письма не попадали в спам, на хостинге лучше настроить реальный почтовый ящик домена.
    return 'JANART STUDIO <noreply@janart-studio.ru>';
}

function auth_send_password_email(string $to, string $subject, string $body): bool {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'From: ' . auth_mail_from();
    $headers[] = 'Reply-To: ' . auth_mail_from();
    $headers[] = 'X-Mailer: JANART STUDIO';
    $headers[] = 'Message-ID: <' . time() . '.' . uniqid('', true) . '@janart-studio.ru>';
    $headers[] = 'Date: ' . date('r');

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return (bool)@mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}


