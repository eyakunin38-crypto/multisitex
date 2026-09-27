<?php
header('Content-Type: application/json');

$source = file_get_contents('php://input');
$data = json_decode($source, true);

file_put_contents('/tmp/yookassa.log', date('Y-m-d H:i:s') . ' ' . $source . "\n", FILE_APPEND);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request']));
}

if ($data['event'] !== 'payment.succeeded') {
    exit(json_encode(['success' => true, 'skipped' => $data['event']]));
}

$site      = $data['object']['metadata']['site'] ?? '';
$tariffKey = $data['object']['metadata']['tariff'] ?? 'start';
$amount    = (float)($data['object']['amount']['value'] ?? 0);
$paymentId = $data['object']['id'] ?? '';

if (!$site || !$paymentId) {
    file_put_contents('/tmp/yookassa.log', "❌ Нет site или paymentId\n", FILE_APPEND);
    exit(json_encode(['success' => false, 'error' => 'No site or payment_id']));
}

// Проверка суммы по тарифу из config/tariffs.php
$tariffs = require '/var/www/multisitex.com/config/tariffs.php';

if (!isset($tariffs[$tariffKey])) {
    file_put_contents('/tmp/yookassa.log', "❌ Неизвестный тариф: $tariffKey\n", FILE_APPEND);
    exit(json_encode(['success' => false, 'error' => 'Unknown tariff']));
}

$expectedPrice = $tariffs[$tariffKey]['price'];

if ($amount < $expectedPrice) {
    file_put_contents('/tmp/yookassa.log', "❌ Мало денег: $amount за $site (нужно $expectedPrice)\n", FILE_APPEND);
    exit(json_encode(['success' => false, 'error' => 'Amount too low']));
}

$db = new mysqli('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if ($db->connect_error) {
    file_put_contents('/tmp/yookassa.log', "❌ БД: " . $db->connect_error . "\n", FILE_APPEND);
    exit(json_encode(['success' => false, 'error' => 'DB error']));
}
$db->set_charset('utf8mb4');

$stmt = $db->prepare("
    INSERT INTO payments (site, payment_id, amount, status)
    VALUES (?, ?, ?, 'succeeded')
    ON DUPLICATE KEY UPDATE status = 'succeeded'
");
if ($stmt) {
    $stmt->bind_param('ssd', $site, $paymentId, $amount);
    $stmt->execute();
    $stmt->close();
}

$stmt = $db->prepare("UPDATE sites SET tariff = ? WHERE domain = ?");
$stmt->bind_param('ss', $tariffKey, $site);
$stmt->execute();
$stmt->close();

$db->close();

file_put_contents('/tmp/yookassa.log', "✅ Активирован: $site, тариф $tariffKey, $amount ₽, $paymentId\n", FILE_APPEND);

echo json_encode(['success' => true]);
