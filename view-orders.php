<?php
error_reporting(0);
ini_set('display_errors', 'Off');

$ordersFilePath = 'orders.json';

// Простая защита от несанкционированного доступа
$adminPassword = 'janart2025'; // Измените пароль на свой

if (!isset($_GET['password']) || $_GET['password'] !== $adminPassword) {
    http_response_code(401);
    echo '<h1>Доступ запрещен</h1><p>Неверный пароль</p>';
    exit;
}

// Загружаем заказы
$orders = [];
if (file_exists($ordersFilePath)) {
    $ordersContent = file_get_contents($ordersFilePath);
    if ($ordersContent !== false) {
        $orders = json_decode($ordersContent, true) ?: [];
    }
}

// Сортируем заказы по дате (новые сверху)
usort($orders, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказы JANART STUDIO</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-family: 'Cormorant Garamond', serif;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .order {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .order-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-number {
            font-weight: bold;
            color: #007bff;
            font-size: 1.1rem;
        }
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        .order-body {
            padding: 20px;
        }
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .info-value {
            color: #666;
        }
        .items-list {
            margin-top: 15px;
        }
        .item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: white;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        .item-details {
            flex: 1;
        }
        .item-title {
            font-weight: bold;
            color: #333;
        }
        .item-price {
            color: #007bff;
            font-weight: bold;
        }
        .total-amount {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin-top: 15px;
        }
        .total-label {
            color: #666;
            font-size: 0.9rem;
        }
        .total-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .no-orders {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Заказы JANART STUDIO</h1>
        
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <h2>Заказов пока нет</h2>
                <p>Как только покупатели начнут делать заказы, они появятся здесь.</p>
            </div>
        <?php else: ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($orders) ?></div>
                    <div class="stat-label">Всего заказов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum(array_column($orders, 'total')) ?> ₽</div>
                    <div class="stat-label">Общая сумма</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($orders, function($order) { return $order['status'] === 'pending'; })) ?></div>
                    <div class="stat-label">Ожидают обработки</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_unique(array_column($orders, 'contact'))) ?></div>
                    <div class="stat-label">Уникальных покупателей</div>
                </div>
            </div>

            <?php foreach ($orders as $order): ?>
                <div class="order">
                    <div class="order-header">
                        <div>
                            <div class="order-number">Заказ №<?= htmlspecialchars($order['orderNumber']) ?></div>
                            <div class="order-date"><?= htmlspecialchars($order['timestamp']) ?></div>
                        </div>
                        <span class="status-badge status-<?= $order['status'] ?>"><?= $order['status'] ?></span>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-info">
                            <div class="info-group">
                                <div class="info-label">📞 Контактные данные</div>
                                <div class="info-value">
                                    <?= htmlspecialchars($order['contact']) ?>
                                    <small>(<?= $order['contactType'] === 'phone' ? 'Телефон' : 'Email' ?>)</small>
                                </div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">🌐 Техническая информация</div>
                                <div class="info-value">
                                    IP: <?= htmlspecialchars($order['ip']) ?><br>
                                    <small><?= htmlspecialchars(substr($order['userAgent'], 0, 50)) ?>...</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="items-list">
                            <div class="info-label">🎨 Товары в заказе:</div>
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="item">
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image" onerror="this.style.display='none'">
                                    <div class="item-details">
                                        <div class="item-title"><?= htmlspecialchars($item['title']) ?></div>
                                        <div class="item-price"><?= htmlspecialchars($item['price']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="total-amount">
                            <div class="total-label">Сумма к оплате</div>
                            <div class="total-value"><?= number_format($order['total'], 0, ',', ' ') ?> ₽</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
