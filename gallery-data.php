<?php
// Отключаем вывод ошибок для чистого JSON
error_reporting(0);
ini_set('display_errors', 0);

// Устанавливаем правильные заголовки
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Проверяем существование файла
$jsonFile = 'gallery-data.json';

if (!file_exists($jsonFile)) {
    // Если файл не существует, возвращаем пустые данные
    echo json_encode([
        'paintings' => [],
        'error' => 'File not found'
    ]);
    exit;
}

// Проверяем права доступа
if (!is_readable($jsonFile)) {
    echo json_encode([
        'paintings' => [],
        'error' => 'File not readable'
    ]);
    exit;
}

// Читаем содержимое файла
$content = file_get_contents($jsonFile);

if ($content === false) {
    echo json_encode([
        'paintings' => [],
        'error' => 'Failed to read file'
    ]);
    exit;
}

// Проверяем, что это валидный JSON
$data = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'paintings' => [],
        'error' => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Возвращаем данные
echo json_encode($data);
?>