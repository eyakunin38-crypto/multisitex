<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ============================================
// ПРОВЕРКА IP ЮKASSA
// ============================================
$allowed_ips = [
    '185.32.252.0/22',
];

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// Если несколько IP через запятую — берём первый
if (strpos($client_ip, ',') !== false) {
    $client_ip = trim(explode(',', $client_ip)[0]);
}

function ip_in_range(string $ip, string $range): bool
{
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    [$subnet, $mask] = explode('/', $range);
    $mask = (int)$mask;
    $ip_long     = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long   = -1 << (32 - $mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

$ip_ok = false;
foreach ($allowed_ips as $range) {
    if (ip_in_range($client_ip, $range)) {
        $ip_ok = true;
        break;
    }
}

if (!$ip_ok) {
    error_log("webhook: bad IP: $client_ip");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile = '/var/www/multisitex.com/config/db.php';
$tariffFile = '/var/www/multisitex.com/config/tariffs.php';

if (!file_exists($dbCfgFile) || !file_exists($tariffFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

$dbCfg   = require $dbCfgFile;
$tariffs = require $tariffFile;

// ============================================
// ВХОДЯЩИЕ ДАННЫЕ
// ============================================
$source = file_get_contents('php://input');
$data = json_decode($source, true);

file_put_contents('/tmp/yookassa.log', date('Y-m-d H:i:s') . ' ' . $source . "\n", FILE_APPEND);

if (!is_array($data) || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if ($data['event'] !== 'payment.succeeded') {
    echo json_encode(['success' => true, 'skipped' => $data['event']]);
    exit;
}

$site      = trim($data['object']['metadata']['site']   ?? '');
$tariffKey = trim($data['object']['metadata']['tariff'] ?? '');
$amount    = (float)($data['object']['amount']['value'] ?? 0);
$paymentId = trim($data['object']['id'] ?? '');

if ($site === '' || $tariffKey === '' || $paymentId === '') {
    file_put_contents('/tmp/yookassa.log', "❌ Нет site / tariff / paymentId\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No metadata']);
    exit;
}

if (!isset($tariffs[$tariffKey])) {
    file_put_contents('/tmp/yookassa.log', "❌ Неизвестный тариф: $tariffKey\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown tariff']);
    exit;
}

$expectedPrice = (float)$tariffs[$tariffKey]['price'];

if ($amount < $expectedPrice) {
    file_put_contents('/tmp/yookassa.log', "❌ Мало денег: $amount за $site (нужно $expectedPrice)\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount too low']);
    exit;
}

// ============================================
// БД
// ============================================
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    file_put_contents('/tmp/yookassa.log', "❌ БД: " . $db->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
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
} else {
    file_put_contents('/tmp/yookassa.log', "⚠️ payments insert failed: " . $db->error . "\n", FILE_APPEND);
}

$stmt = $db->prepare("UPDATE sites SET tariff = ? WHERE domain = ?");
$stmt->bind_param('ss', $tariffKey, $site);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

$db->close();

if ($affected === 0) {
    file_put_contents('/tmp/yookassa.log', "⚠️ sites UPDATE 0 rows: $site\n", FILE_APPEND);
}

file_put_contents('/tmp/yookassa.log', "✅ Активирован: $site, тариф $tariffKey, $amount ₽, $paymentId\n", FILE_APPEND);

echo json_encode(['success' => true]);
