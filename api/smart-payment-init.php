<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/payment-config.php';

smart_payment_log('Incoming smart payment request', ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed', 'message' => 'Only POST allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    smart_payment_log('Invalid JSON payload', ['body' => $rawBody]);
    exit;
}

$orderNumber = trim((string)($payload['orderNumber'] ?? ''));
$contact = trim((string)($payload['contact'] ?? ''));
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

if ($contact === '' || $items === []) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Empty contact or items']);
    smart_payment_log('Validation failed', ['contact' => $contact, 'items_count' => count($items)]);
    exit;
}

try {
    smart_payment_require_configuration();
} catch (RuntimeException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    smart_payment_log('Config error', ['message' => $e->getMessage()]);
    exit;
}

/**
 * Преобразуем строку вроде "3 450 ₽" или "2,000" в сумму в копейках.
 */
function parse_money_to_kopecks(string $value): int
{
    $normalized = str_replace([',', ' ', '’', '\''], ['.', '', '', ''], $value);
    $digitsOnly = preg_replace('/[^\d\.]/u', '', $normalized);
    $floatValue = (float) $digitsOnly;
    return (int) round($floatValue * 100);
}

$infoItems = [];
foreach ($items as $index => $item) {
    if (count($infoItems) >= 6) {
        break;
    }

    $name = trim((string)($item['title'] ?? $item['name'] ?? ''));
    $quantity = max(1, (int)($item['quantity'] ?? 1));
    $amountKopecks = parse_money_to_kopecks((string)($item['price'] ?? $item['amount'] ?? '0'));

    if ($name === '' || $amountKopecks <= 0) {
        continue;
    }

    $infoItems[] = [
        'name' => $name,
        'quantity' => $quantity,
        'amount' => $amountKopecks,
    ];
}

if ($infoItems === []) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'No valid items for payment']);
    smart_payment_log('No items for payment', ['items' => $items]);
    exit;
}

$totalKopecks = array_reduce($infoItems, static function ($carry, $item) {
    return $carry + ($item['amount'] * max(1, $item['quantity']));
}, 0);

$orderId = $orderNumber !== '' ? preg_replace('/[^A-Za-z0-9_\-]/', '', $orderNumber) : '';
if ($orderId === '') {
    $orderId = 'JS' . bin2hex(random_bytes(5));
}

$signatureBase = $orderId . $totalKopecks;
foreach ($infoItems as $item) {
    $signatureBase .= $item['name'] . $item['quantity'] . $item['amount'];
}
$signatureBase .= SELFWORK_SECRET_KEY;
$signature = hash('sha256', $signatureBase);

$postFields = [
    'order_id' => $orderId,
    'amount' => (string)$totalKopecks,
    'signature' => $signature,
];

foreach ($infoItems as $index => $item) {
    $postFields["info[{$index}][name]"] = $item['name'];
    $postFields["info[{$index}][quantity]"] = (string)$item['quantity'];
    $postFields["info[{$index}][amount]"] = (string)$item['amount'];
}

$curl = curl_init(SELFWORK_SMART_PAYMENT_ENDPOINT);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: ' . SELFWORK_STORE_DOMAIN,
        'Referer: ' . SELFWORK_STORE_DOMAIN,
    ],
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_TIMEOUT => 30,
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $httpCode >= 500) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(502);
    echo json_encode([
        'error' => 'Сам.Эквайринг недоступен',
        'details' => $curlError ?: 'Response code ' . $httpCode,
    ]);
    smart_payment_log('Selfwork init failed', ['http_code' => $httpCode, 'curl_error' => $curlError]);
    exit;
}

if ($httpCode >= 400) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'error' => 'Сам.Эквайринг отклонил запрос',
        'details' => substr($responseBody ?? '', 0, 1024),
    ]);
    smart_payment_log('Selfwork init rejected', ['http_code' => $httpCode, 'response' => substr($responseBody ?? '', 0, 1024)]);
    exit;
}

smart_payment_log('Smart payment initialized', [
    'order_id' => $orderId,
    'total' => $totalKopecks,
    'items' => count($infoItems),
]);

header('Content-Type: text/html; charset=utf-8');
echo $responseBody;
