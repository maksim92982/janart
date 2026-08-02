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

$body = auth_read_json_body();
$email = auth_normalize_email((string)($body['email'] ?? ''));
$lotId = trim((string)($body['lotId'] ?? ''));

if (!$email || !auth_is_valid_email($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный email']);
    exit;
}

if ($lotId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID лота']);
    exit;
}

try {
    $pdo = auth_db();
    
    // Создаем таблицы, если их нет
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
    
    $pdo->beginTransaction();
    
    // Проверяем, существует ли пользователь
    $stmt = $pdo->prepare('SELECT email, created_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    $isNewUser = !$user;
    $userCreated = false;
    
    if ($isNewUser) {
        // Регистрируем нового пользователя
        $plainPassword = auth_generate_password(8);
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $now = date('c');
        
        $insert = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $insert->execute([$email, $hash, $now]);
        
        // Отправляем пароль на email
        $subject = 'Регистрация и покупка — JANART STUDIO';
        $text = "Здравствуйте!\n\n"
            . "Вы зарегистрировались на сайте JANART STUDIO и приобрели доступ к лоту.\n\n"
            . "Ваши данные для входа:\n"
            . "- Email: {$email}\n"
            . "- Пароль: {$plainPassword}\n\n"
            . "Если вы не регистрировались — просто проигнорируйте это письмо.\n\n"
            . "С уважением,\nJANART STUDIO\n";
        
        $sent = auth_send_password_email($email, $subject, $text);
        if (!$sent) {
            $pdo->rollBack();
            auth_log('PURCHASE_LOT_WITH_EMAIL email_send_failed', ['email' => $email, 'lot_id' => $lotId]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Не удалось отправить письмо']);
            exit;
        }
        
        $userCreated = true;
        auth_log('PURCHASE_LOT_WITH_EMAIL new_user_created', ['email' => $email]);
    }
    
    // Проверяем, не куплен ли уже этот лот
    $stmt = $pdo->prepare('SELECT id FROM lots_access WHERE user_email = ? AND lot_id = ?');
    $stmt->execute([$email, $lotId]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Лот уже куплен']);
        exit;
    }
    
    // Добавляем доступ к лоту
    $purchasedAt = date('c');
    $stmt = $pdo->prepare('INSERT INTO lots_access (user_email, lot_id, purchased_at, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $lotId, $purchasedAt, null]);
    
    $pdo->commit();
    
    // Авторизуем пользователя (создаем сессию)
    session_start();
    $_SESSION['user_email'] = $email;
    
    // Получаем данные пользователя для возврата
    $stmt = $pdo->prepare('SELECT email, created_at, last_login FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userData = $stmt->fetch();
    
    auth_log('PURCHASE_LOT_WITH_EMAIL ok', [
        'email' => $email,
        'lot_id' => $lotId,
        'is_new_user' => $isNewUser
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Лот успешно куплен',
        'lotId' => $lotId,
        'user' => [
            'email' => $userData['email'],
            'created_at' => $userData['created_at'],
            'last_login' => $userData['last_login']
        ],
        'isNewUser' => $isNewUser
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_log('PURCHASE_LOT_WITH_EMAIL exception', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
