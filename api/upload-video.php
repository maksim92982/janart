<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['videos'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No video files uploaded']);
    exit;
}

$paintingId = isset($_POST['paintingId']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['paintingId']) : '';
$galleryType = isset($_POST['galleryType']) ? $_POST['galleryType'] : 'gallery';
$galleryType = $galleryType === 'gallery2' ? 'masterclass' : 'gallery';
if ($paintingId === '') {
    $paintingId = 'misc';
}

$baseDir = realpath(__DIR__ . '/..');
$uploadDir = $baseDir . '/images/' . $galleryType . '/' . $paintingId . '/videos/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$uploadedFiles = [];
$errors = [];

$allowedExtensions = ['mp4','mov','webm','ogg','avi','mkv','flv','wmv','m4v'];

foreach ($_FILES['videos']['name'] as $key => $name) {
    if ($_FILES['videos']['error'][$key] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['videos']['tmp_name'][$key];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $ext = 'mp4';
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '', $base);
        if ($safeBase === '' || file_exists($uploadDir . $safeBase . '.' . $ext)) {
            $safeBase = uniqid('video_');
        }
        $fileName = $safeBase . '.' . $ext;
        $filePath = $uploadDir . $fileName;

        if (@move_uploaded_file($tmpName, $filePath)) {
            $relative = 'images/' . $galleryType . '/' . $paintingId . '/videos/' . $fileName;
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $absolute = ($host !== '') ? ($scheme . '://' . $host . '/' . $relative) : $relative;
            $uploadedFiles[] = [
                'originalName' => $name,
                'fileName' => $fileName,
                'path' => $relative,
                'url' => $absolute,
            ];
        } else {
            $errors[] = "Failed to upload $name";
        }
    } else {
        $errors[] = "Error uploading $name: " . $_FILES['videos']['error'][$key];
    }
}

echo json_encode([
    'success' => count($uploadedFiles) > 0,
    'uploadedFiles' => $uploadedFiles,
    'errors' => $errors,
]);
?>
