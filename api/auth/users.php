<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

/**
 * Простой интерфейс просмотра пользователей через браузер.
 * Доступ: GET /api/auth/users.php?key=VIEW_USERS
 */

define('USER_VIEW_KEY', 'VIEW_USERS');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$key = $_GET['key'] ?? '';
if ($key !== USER_VIEW_KEY) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $pdo = auth_db();
    $stmt = $pdo->query('SELECT email, created_at, updated_at, last_login FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB error';
    exit;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список пользователей</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f6f6f6; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #272727; color: #fff; }
        tr:nth-child(even) { background: #fafafa; }
        tr:hover { background: #f1f1f1; }
    </style>
</head>
<body>
    <h1>Пользователи JANART STUDIO</h1>
    <p>Всего: <?php echo count($users); ?></p>
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Создан</th>
                <th>Обновлён</th>
                <th>Последний вход</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($user['updated_at'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($user['last_login'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>

