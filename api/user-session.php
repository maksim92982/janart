<?php
require_once __DIR__ . '/../lib/auth-bootstrap.php';

auth_handle_preflight();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Получаем email из сессии или заголовков
session_start();
$userEmail = $_SESSION['user_email'] ?? null;

if (!$userEmail) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

try {
    $pdo = auth_db();
    
    // Создаем таблицу для доступа к лотам, если её нет
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS lots_access (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_email TEXT NOT NULL,
            lot_id TEXT NOT NULL,
            purchased_at TEXT NOT NULL,
            expires_at TEXT NULL,
            UNIQUE(user_email, lot_id)
        )'
    );
    
    // Получаем информацию о пользователе
    $stmt = $pdo->prepare('SELECT email, created_at, last_login FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }
    
    // Получаем доступ к лотам
    $stmt = $pdo->prepare('SELECT lot_id, purchased_at, expires_at FROM lots_access WHERE user_email = ?');
    $stmt->execute([$userEmail]);
    $lotsAccess = [];
    
    $now = time();
    while ($row = $stmt->fetch()) {
        $expiresAt = $row['expires_at'];
        if ($expiresAt) {
            $expiresTimestamp = strtotime($expiresAt);
            if ($expiresTimestamp !== false && $expiresTimestamp < $now) {
                // Доступ истёк
                continue;
            }
        }
        
        // Доступ активен
        $lotsAccess[$row['lot_id']] = [
            'expiresAt' => $expiresAt,
            'grantedAt' => $row['purchased_at'],
            'purchasedAt' => $row['purchased_at'],
        ];
    }
    
    echo json_encode([
        'success' => true,
        'login' => $user['email'],
        'lotsAccess' => $lotsAccess,
    ]);
} catch (Throwable $e) {
    auth_log('USER_SESSION exception', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
