<?php
// Файл для обновления данных галереи на хостинге
// Этот файл будет вызываться из админ-панели для синхронизации изменений

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Получаем данные из POST запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Проверяем, что пришли данные хотя бы для одной галереи
$hasGallery1Data = isset($data['gallery']) || isset($data['paintings']);
$hasGallery2Data = isset($data['gallery2']);
if (!$hasGallery1Data && !$hasGallery2Data) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing gallery payload']);
    exit;
}

$galleryPaintings = [];
if (isset($data['gallery']['paintings']) && is_array($data['gallery']['paintings'])) {
    $galleryPaintings = $data['gallery']['paintings'];
} elseif (isset($data['paintings']) && is_array($data['paintings'])) {
    $galleryPaintings = $data['paintings'];
}

$gallery2Paintings = [];
if (isset($data['gallery2']['paintings']) && is_array($data['gallery2']['paintings'])) {
    $gallery2Paintings = $data['gallery2']['paintings'];
}

$now = date('c');
$galleryMeta = $data['gallery'] ?? [];
$gallery2Meta = $data['gallery2'] ?? [];

$galleryPayload = [
    'paintings' => $galleryPaintings,
    'lastUpdated' => $galleryMeta['lastUpdated'] ?? $now,
    'version' => $galleryMeta['version'] ?? '3.0'
];

$gallery2Payload = [
    'paintings' => $gallery2Paintings,
    'lastUpdated' => $gallery2Meta['lastUpdated'] ?? $now,
    'version' => $gallery2Meta['version'] ?? '3.0'
];

// Собираем новый формат
$galleryData = [
    'gallery' => $galleryPayload,
    'gallery2' => $gallery2Payload,
    'version' => '3.0',
    'updatedBy' => 'admin-panel',
    'hostingUpdated' => true
];

// Сохраняем данные в файл
$jsonFile = 'gallery-data.json';
$result = file_put_contents($jsonFile, json_encode($galleryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save data to file']);
    exit;
}

// Логируем обновление обеих галерей
$galleryCount = count($galleryPaintings);
$gallery2Count = count($gallery2Paintings);
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'cli';
$logEntry = sprintf(
    "[%s] [%s] Gallery 1: %d paintings, Gallery 2: %d paintings, fileSize: %d bytes\n",
    date('Y-m-d H:i:s'),
    $remoteIp,
    $galleryCount,
    $gallery2Count,
    $result
);
file_put_contents('gallery-update.log', $logEntry, FILE_APPEND | LOCK_EX);

// Возвращаем успешный ответ
echo json_encode([
    'success' => true,
    'message' => 'Gallery data updated successfully',
    'galleryCount' => $galleryCount,
    'gallery2Count' => $gallery2Count,
    'timestamp' => date('c'),
    'fileSize' => $result
]);
?>
