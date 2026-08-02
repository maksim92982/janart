<?php
require_once __DIR__ . '/../lib/auth-bootstrap.php';

auth_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    auth_send_json(['success' => false, 'error' => 'Только POST запросы'], 405);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Получаем email из сессии
session_start();
$userEmail = $_SESSION['user_email'] ?? null;

if (!$userEmail) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$body = auth_read_json_body();
$lotId = trim((string)($body['lotId'] ?? ''));

if ($lotId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID лота']);
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
    
    // Проверяем, не куплен ли уже этот лот
    $stmt = $pdo->prepare('SELECT id FROM lots_access WHERE user_email = ? AND lot_id = ?');
    $stmt->execute([$userEmail, $lotId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Лот уже куплен']);
        exit;
    }
    
    // Добавляем доступ к лоту (бессрочный доступ)
    $purchasedAt = date('c');
    $stmt = $pdo->prepare('INSERT INTO lots_access (user_email, lot_id, purchased_at, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userEmail, $lotId, $purchasedAt, null]);
    
    auth_log('LOT_PURCHASED', ['user' => $userEmail, 'lot_id' => $lotId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Лот успешно куплен',
        'lotId' => $lotId,
    ]);
} catch (Throwable $e) {
    auth_log('PURCHASE_LOT exception', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
