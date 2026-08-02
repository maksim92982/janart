<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$ordersFile = __DIR__ . '/../orders.json';
$galleryFile = __DIR__ . '/../gallery-data.json';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$orderId = trim((string)($data['order_id'] ?? $data['orderId'] ?? $data['order_id'] ?? $data['orderNumber'] ?? ''));
$status = strtolower(trim((string)($data['status'] ?? $data['event'] ?? $data['type'] ?? '')));

$paidEvents = ['payment.succeeded', 'succeeded', 'paid', 'success', 'completed'];
$isPaid = $status === '' || in_array($status, $paidEvents, true);

if ($orderId === '') {
    http_response_code(200);
    echo json_encode(['success' => false, 'reason' => 'missing_order_id']);
    exit;
}

if (!$isPaid) {
    http_response_code(200);
    echo json_encode(['success' => true, 'note' => 'payment_not_successful', 'status' => $status]);
    exit;
}

$orders = [];
if (file_exists($ordersFile)) {
    $orders = json_decode(file_get_contents($ordersFile), true) ?: [];
}

$foundOrderKey = null;
foreach ($orders as $key => $order) {
    if (isset($order['orderNumber']) && $order['orderNumber'] === $orderId) {
        $foundOrderKey = $key;
        break;
    }
}

if ($foundOrderKey === null) {
    http_response_code(200);
    echo json_encode(['success' => false, 'reason' => 'order_not_found', 'orderId' => $orderId]);
    exit;
}

$order = $orders[$foundOrderKey];
if (isset($order['status']) && $order['status'] === 'paid') {
    http_response_code(200);
    echo json_encode(['success' => true, 'note' => 'already_paid']);
    exit;
}

$orders[$foundOrderKey]['status'] = 'paid';

$galleryDataRaw = [];
if (file_exists($galleryFile)) {
    $galleryDataRaw = json_decode(file_get_contents($galleryFile), true) ?: [];
}

function &findPaintingById(array &$data, string $itemId)
{
    static $null = null;
    foreach (['gallery', 'gallery2'] as $key) {
        if (isset($data[$key]['paintings']) && is_array($data[$key]['paintings'])) {
            foreach ($data[$key]['paintings'] as &$painting) {
                if (!is_array($painting)) {
                    continue;
                }
                if ((isset($painting['id']) && $painting['id'] === $itemId)
                    || (isset($painting['lotId']) && $painting['lotId'] === $itemId)) {
                    return $painting;
                }
            }
            unset($painting);
        }
    }
    return $null;
}

$inventoryChanged = false;
if (isset($order['items']) && is_array($order['items'])) {
    foreach ($order['items'] as $item) {
        $itemId = trim((string)($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }

        $quantity = max(1, intval($item['quantity'] ?? 1));
        $painting =& findPaintingById($galleryDataRaw, $itemId);
        if (!is_array($painting)) {
            continue;
        }

        if (!isset($painting['quantity'])) {
            $painting['quantity'] = 1;
        }
        $painting['quantity'] = max(0, intval($painting['quantity']) - $quantity);
        if ($painting['quantity'] <= 0) {
            $painting['soldOut'] = true;
        } else {
            if (isset($painting['soldOut'])) {
                unset($painting['soldOut']);
            }
        }

        $inventoryChanged = true;
        unset($painting);
    }
}

if ($inventoryChanged && !empty($galleryDataRaw)) {
    file_put_contents($galleryFile, json_encode($galleryDataRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'reason' => 'failed_save_order']);
    exit;
}

http_response_code(200);
echo json_encode(['success' => true, 'orderId' => $orderId, 'inventoryChanged' => $inventoryChanged]);
