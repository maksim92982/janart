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

if (!isset($_FILES['images'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded']);
    exit;
}

$paintingId = isset($_POST['paintingId']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['paintingId']) : '';
$galleryType = isset($_POST['galleryType']) ? $_POST['galleryType'] : 'gallery';
$galleryType = $galleryType === 'gallery2' ? 'masterclass' : 'gallery';
if ($paintingId === '') {
    $paintingId = 'misc';
}

$baseDir = realpath(__DIR__ . '/..');
$uploadDir = $baseDir . '/images/' . $galleryType . '/' . $paintingId . '/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$uploadedFiles = [];
$errors = [];

$allowedExtensions = ['jpg','jpeg','png','gif','webp','bmp','heic','heif','tiff'];

foreach ($_FILES['images']['name'] as $key => $name) {
    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['images']['tmp_name'][$key];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $ext = 'jpg';
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '', $base);
        if ($safeBase === '' || file_exists($uploadDir . $safeBase . '.' . $ext)) {
            $safeBase = uniqid();
        }
        $fileName = $safeBase . '.' . $ext;
        $filePath = $uploadDir . $fileName;

        if (@move_uploaded_file($tmpName, $filePath)) {
        $relative = 'images/' . $galleryType . '/' . $paintingId . '/' . $fileName;
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
        $errors[] = "Error uploading $name: " . $_FILES['images']['error'][$key];
    }
}

echo json_encode([
    'success' => count($uploadedFiles) > 0,
    'uploadedFiles' => $uploadedFiles,
    'errors' => $errors,
]);
?>



