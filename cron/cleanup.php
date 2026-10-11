<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile = '/var/www/multisitex.com/config/db.php';
if (!file_exists($dbCfgFile)) {
    error_log('cleanup: config not found');
    exit(1);
}
$dbCfg = require $dbCfgFile;

const SITES_PATH = '/var/www/multisitex.com/sites';
const CLEANUP_DAYS = 7;

$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    error_log('cleanup: DB error: ' . $db->connect_error);
    exit(1);
}
$db->set_charset('utf8mb4');

$log = [];
$deleted = 0;

// ============================================
// 1. НАЙТИ САЙТЫ-КАНДИДАТЫ
// ============================================
$sql = "
    SELECT s.id, s.domain, s.user_id
    FROM sites s
    JOIN users u ON u.id = s.user_id
    WHERE u.email IS NULL
      AND s.tariff = 'test'
      AND s.created_at < NOW() - INTERVAL " . CLEANUP_DAYS . " DAY
";

$result = $db->query($sql);
if (!$result) {
    error_log('cleanup: query error: ' . $db->error);
    exit(1);
}

$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

$log[] = "Found " . count($candidates) . " candidates (older than " . CLEANUP_DAYS . " days)";

// ============================================
// 2. УДАЛИТЬ КАЖДЫЙ
// ============================================
foreach ($candidates as $site) {
    $domain  = $site['domain'];
    $siteId  = (int)$site['id'];
    $userId  = (int)$site['user_id'];

    // Защита от path traversal
    if (!preg_match('/^[a-z0-9_-]+$/i', $domain)) {
        $log[] = "SKIP bad domain: $domain";
        continue;
    }

    // Удаляем таблицы
    $db->query("DROP TABLE IF EXISTS `$domain`");
    $db->query("DROP TABLE IF EXISTS `leads_$domain`");

    // Удаляем папку
    $path = SITES_PATH . '/' . $domain;
    if (is_dir($path)) {
        exec('rm -rf ' . escapeshellarg($path));
    }

    // Удаляем settings
    $stmt = $db->prepare("DELETE FROM settings WHERE domain = ?");
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->close();

    // Удаляем leads (платформа)
    $stmt = $db->prepare("DELETE FROM leads WHERE domain = ?");
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->close();

    // Удаляем sites
    $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
    $stmt->bind_param('i', $siteId);
    $stmt->execute();
    $stmt->close();

    $log[] = "DELETED: $domain (site_id=$siteId, user_id=$userId)";
    $deleted++;
}

// ============================================
// USERS НЕ УДАЛЯЕМ
// ============================================

$db->close();

// ============================================
// 4. ЛОГ
// ============================================
$logText = date('Y-m-d H:i:s') . " — deleted $deleted sites\n";
$logText .= implode("\n", $log) . "\n\n";

file_put_contents('/var/log/multisitex-cleanup.log', $logText, FILE_APPEND);

echo "✅ Deleted: $deleted sites\n";
