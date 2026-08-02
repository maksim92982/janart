<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/orders_log.txt');
error_reporting(E_ALL);

$ordersFilePath = __DIR__ . '/orders.json';
$logFilePath = __DIR__ . '/orders_log.txt';

function logMessage($message) {
    global $logFilePath;
    file_put_contents($logFilePath, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

function sendOrderNotification($order) {
    // Адрес получателя (ваша почта)
    $to = 'ma89spb@gmail.com';
    
    // Тема письма
    $subject = '🛒 Новый заказ #' . $order['orderNumber'] . ' | JANART STUDIO';
    
    // Формируем список товаров
    $itemsHtml = '';
    foreach ($order['items'] as $item) {
        $itemsHtml .= "• {$item['title']} - {$item['price']}\n";
    }
    
    // Тело письма
    $message = "Поступил новый заказ!\n\n";
    $message .= "📦 Номер заказа: {$order['orderNumber']}\n";
    $message .= "📅 Дата и время: {$order['timestamp']}\n";
    $message .= "👤 Контакт: {$order['contact']} ({$order['contactType']})\n";
    $message .= "💰 Сумма к оплате: {$order['total']} ₽\n\n";
    $message .= "🎨 Товары в заказе:\n{$itemsHtml}\n";
    $message .= "📊 Статус: {$order['status']}\n\n";
    $message .= "🌐 IP клиента: {$order['ip']}\n";
    $message .= "🔍 User Agent: {$order['userAgent']}\n\n";
    $message .= "Просмотр всех заказов: https://janart-studio.ru/view-orders.php?password=janart2025";
    
    // Заголовки для HTML письма
    $headers = "From: noreply@janart-studio.ru\r\n";
    $headers .= "Reply-To: {$order['contact']}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Отправляем письмо
    $sent = mail($to, $subject, $message, $headers);
    
    if ($sent) {
        logMessage('Email notification sent for order ' . $order['orderNumber']);
    } else {
        logMessage('Failed to send email notification for order ' . $order['orderNumber']);
    }
    
    return $sent;
}

function validateContact($contact) {
    // Проверяем телефон (российский формат)
    if (preg_match('/^(\+7|7|8)?[\s\-]?\(?[489][0-9]{2}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $contact)) {
        return 'phone';
    }
    
    // Проверяем email
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    }
    
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON received', 'json_error' => json_last_error_msg()]);
        logMessage('Error: Invalid JSON received - ' . json_last_error_msg());
        exit;
    }

    // Защита от дубликатов: проверяем, не был ли заказ уже создан
    $sessionId = session_id();
    if (empty($sessionId)) {
        session_start();
    }
    
    // Создаем уникальный ключ для этого заказа
    $uniqueKey = md5($data['contact'] . serialize($data['items']) . (string)$data['total']);
    $sessionKey = 'order_' . $uniqueKey;
    
    // Проверяем, не создавали ли мы уже этот заказ
    if (isset($_SESSION[$sessionKey])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Order already saved',
            'orderNumber' => $_SESSION[$sessionKey],
            'duplicate' => true
        ]);
        logMessage('Warning: Duplicate order prevented - key: ' . $uniqueKey);
        exit;
    }
    
    // Отмечаем заказ как созданный
    $_SESSION[$sessionKey] = 'processing';

    // Проверяем обязательные поля
    if (!isset($data['contact']) || !isset($data['items']) || !isset($data['total'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: contact, items, or total']);
        logMessage('Error: Missing required fields in order data');
        exit;
    }

    // Валидируем контактные данные
    $contactType = validateContact($data['contact']);
    if (!$contactType) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid contact format. Please provide valid phone or email.']);
        logMessage('Error: Invalid contact format - ' . $data['contact']);
        exit;
    }

    // Проверяем товары
    if (!is_array($data['items']) || empty($data['items'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No items in order']);
        logMessage('Error: No items in order');
        exit;
    }

    // Проверяем сумму
    if (!is_numeric($data['total']) || $data['total'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid total amount']);
        logMessage('Error: Invalid total amount - ' . $data['total']);
        exit;
    }

    // Загружаем существующие заказы, чтобы корректно проверить уникальность номера
    $orders = [];
    if (file_exists($ordersFilePath)) {
        $ordersContent = file_get_contents($ordersFilePath);
        if ($ordersContent !== false) {
            $orders = json_decode($ordersContent, true) ?: [];
        }
    }

    // Используем номер заказа от фронтенда, если он корректен
    $orderNumber = '';
    if (isset($data['orderNumber']) && is_string($data['orderNumber'])) {
        $candidate = trim($data['orderNumber']);
        if (preg_match('/^JS\d{14}$/', $candidate)) {
            $orderNumber = $candidate;
        }
    }
    if ($orderNumber === '') {
        $orderNumber = 'JS' . date('YmdHis');
    }

    // Проверяем уникальность номера заказа
    $existingNumbers = array_column($orders, 'orderNumber');
    if (in_array($orderNumber, $existingNumbers, true)) {
        $orderNumber .= '-' . bin2hex(random_bytes(3));
    }
    
    // Создаем объект заказа
    $order = [
        'orderNumber' => $orderNumber,
        'contact' => trim($data['contact']),
        'contactType' => $contactType,
        'items' => $data['items'],
        'total' => (float)$data['total'],
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'status' => 'pending'
    ];

    // Добавляем новый заказ
    $orders[] = $order;

    // Сохраняем заказы
    $jsonContent = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($jsonContent === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode orders', 'json_error' => json_last_error_msg()]);
        logMessage('Error: Failed to encode orders - ' . json_last_error_msg());
        exit;
    }

    if (file_put_contents($ordersFilePath, $jsonContent) !== false) {
        // Отправляем email уведомление о новом заказе
        sendOrderNotification($order);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Order saved successfully',
            'orderNumber' => $orderNumber,
            'totalOrders' => count($orders)
        ]);
        logMessage('Success: Order ' . $orderNumber . ' saved. Contact: ' . $order['contact'] . ' (' . $order['contactType'] . '), Total: ' . $order['total'] . ' ₽, Items: ' . count($order['items']));
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save order', 'message' => 'Could not write to orders file. Check file permissions.']);
        logMessage('Error: Failed to save order to file');
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed', 'message' => 'Only POST requests are allowed.']);
    logMessage('Error: Method Not Allowed - ' . $_SERVER['REQUEST_METHOD']);
}
?>

