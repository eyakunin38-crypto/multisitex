<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ============================================
// ЛОГ
// ============================================
const LOG_FILE = '/var/www/multisitex.com/yookassa.log';

function log_msg(string $msg): void
{
    @file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// ============================================
// ПРОВЕРКА IP YOOMONEY / ЮKASSA
// ============================================
$allowed_ips = [
    '185.32.252.0/22',   // ЮKassa (старый)
    '77.75.153.78',      // YooMoney
];

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

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
    log_msg("❌ bad IP: $client_ip");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

log_msg("✅ IP OK: $client_ip");

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile  = '/var/www/multisitex.com/config/db.php';
$tariffFile = '/var/www/multisitex.com/config/tariffs.php';

if (!file_exists($dbCfgFile) || !file_exists($tariffFile)) {
    log_msg("❌ Config not found");
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
log_msg("RAW: " . $source);

$data = json_decode($source, true);

if (!is_array($data) || !isset($data['event'])) {
    log_msg("❌ Invalid JSON");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if ($data['event'] !== 'payment.succeeded') {
    log_msg("⏭ Skipped event: " . $data['event']);
    echo json_encode(['success' => true, 'skipped' => $data['event']]);
    exit;
}

$site      = trim($data['object']['metadata']['site']   ?? '');
$tariffKey = trim($data['object']['metadata']['tariff'] ?? '');
$amount    = (float)($data['object']['amount']['value'] ?? 0);
$paymentId = trim($data['object']['id'] ?? '');

if ($site === '' || $tariffKey === '' || $paymentId === '') {
    log_msg("❌ No metadata: site=$site, tariff=$tariffKey, paymentId=$paymentId");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No metadata']);
    exit;
}

if (!isset($tariffs[$tariffKey])) {
    log_msg("❌ Unknown tariff: $tariffKey");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown tariff']);
    exit;
}

$expectedPrice = (float)$tariffs[$tariffKey]['price'];

if ($amount < $expectedPrice) {
    log_msg("❌ Amount too low: $amount < $expectedPrice (site=$site)");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount too low']);
    exit;
}

log_msg("💰 Payment: site=$site, tariff=$tariffKey, amount=$amount, paymentId=$paymentId");

// ============================================
// БД
// ============================================
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    log_msg("❌ DB: " . $db->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}
$db->set_charset('utf8mb4');

// payments
$stmt = $db->prepare("
    INSERT INTO payments (site, payment_id, amount, status)
    VALUES (?, ?, ?, 'succeeded')
    ON DUPLICATE KEY UPDATE status = 'succeeded'
");
if ($stmt) {
    $stmt->bind_param('ssd', $site, $paymentId, $amount);
    $stmt->execute();
    $stmt->close();
    log_msg("✅ payments: INSERT/UPDATE $paymentId");
} else {
    log_msg("⚠️ payments insert failed: " . $db->error);
}

// sites.tariff
$stmt = $db->prepare("UPDATE sites SET tariff = ? WHERE domain = ?");
$stmt->bind_param('ss', $tariffKey, $site);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    log_msg("⚠️ sites UPDATE 0 rows: $site");
} else {
    log_msg("✅ sites.tariff = $tariffKey для $site");
}

$db->close();

log_msg("✅ DONE: $site, тариф $tariffKey, $amount ₽, $paymentId");

echo json_encode(['success' => true]);
