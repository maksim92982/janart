<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

auth_handle_preflight();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Уничтожаем сессию
if (isset($_SESSION['user_email'])) {
    $email = $_SESSION['user_email'];
    auth_log('LOGOUT', ['email' => $email]);
}

// Очищаем все данные сессии
$_SESSION = [];
session_destroy();

// Удаляем cookie сессии, если она существует
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

echo json_encode([
    'ok' => true,
    'message' => 'Вы успешно вышли из системы'
]);
