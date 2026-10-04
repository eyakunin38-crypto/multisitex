<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

require '/var/www/multisitex.com/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile   = '/var/www/multisitex.com/config/db.php';
$mailCfgFile = '/var/www/multisitex.com/config/mail.php';

if (!file_exists($dbCfgFile) || !file_exists($mailCfgFile)) {
    error_log('monitor: config not found');
    exit(1);
}

$dbCfg      = require $dbCfgFile;
$mailConfig = require $mailCfgFile;

$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    error_log('monitor: DB error: ' . $db->connect_error);
    exit(1);
}
$db->set_charset('utf8mb4');

// ============================================
// СТАТИСТИКА
// ============================================
$stats = [];

// --- Users ---
$stats['users_total']        = (int)$db->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$stats['users_with_email']   = (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE email IS NOT NULL")->fetch_assoc()['c'];
$stats['users_without_email']= $stats['users_total'] - $stats['users_with_email'];
$stats['users_new_24h']      = (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch_assoc()['c'];

// --- Sites ---
$stats['sites_total'] = (int)$db->query("SELECT COUNT(*) AS c FROM sites")->fetch_assoc()['c'];

$r = $db->query("SELECT tariff, COUNT(*) AS c FROM sites GROUP BY tariff");
$stats['sites_by_tariff'] = [];
while ($row = $r->fetch_assoc()) {
    $stats['sites_by_tariff'][$row['tariff']] = (int)$row['c'];
}
$stats['sites_test']     = $stats['sites_by_tariff']['test']     ?? 0;
$stats['sites_start']    = $stats['sites_by_tariff']['start']    ?? 0;
$stats['sites_business'] = $stats['sites_by_tariff']['business'] ?? 0;
$stats['sites_new_24h']  = (int)$db->query("SELECT COUNT(*) AS c FROM sites WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch_assoc()['c'];

// --- Payments ---
$row = $db->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS s FROM payments")->fetch_assoc();
$stats['payments_count']  = (int)$row['c'];
$stats['payments_sum']    = (float)$row['s'];
$stats['payments_new_24h']= (int)$db->query("SELECT COUNT(*) AS c FROM payments WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch_assoc()['c'];

// --- Leads (платформа) ---
$stats['leads_total'] = (int)$db->query("SELECT COUNT(*) AS c FROM leads")->fetch_assoc()['c'];
$stats['leads_new_24h'] = (int)$db->query("SELECT COUNT(*) AS c FROM leads WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch_assoc()['c'];

// --- Таблицы в БД ---
$row = $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = 'multisitex_db'")->fetch_assoc();
$stats['tables_total'] = (int)$row['c'];

$row = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size FROM information_schema.tables WHERE table_schema = 'multisitex_db'")->fetch_assoc();
$stats['db_size_mb'] = (float)$row['size'];

$db->close();

// --- Диск ---
$diskTotal = disk_total_space('/');
$diskFree  = disk_free_space('/');
$diskUsed  = $diskTotal - $diskFree;

$stats['disk_total_gb']  = round($diskTotal / 1024 / 1024 / 1024, 1);
$stats['disk_free_gb']   = round($diskFree  / 1024 / 1024 / 1024, 1);
$stats['disk_used_gb']   = round($diskUsed  / 1024 / 1024 / 1024, 1);
$stats['disk_used_pct']  = round($diskUsed / $diskTotal * 100, 1);

// --- Папка sites/ ---
$sitesSize = 0;
$sitesPath = '/var/www/multisitex.com/sites';
if (is_dir($sitesPath)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sitesPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile()) {
            $sitesSize += $file->getSize();
        }
    }
}
$stats['sites_size_mb'] = round($sitesSize / 1024 / 1024, 1);

// --- Load average ---
$load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
$stats['load_1']  = round($load[0], 2);
$stats['load_5']  = round($load[1], 2);
$stats['load_15'] = round($load[2], 2);

// --- Память ---
$memInfo = @file_get_contents('/proc/meminfo');
if ($memInfo) {
    preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
    if ($mt && $ma) {
        $memTotal = (int)$mt[1] / 1024 / 1024;
        $memAvail = (int)$ma[1] / 1024 / 1024;
        $stats['mem_total_gb'] = round($memTotal, 1);
        $stats['mem_free_gb']  = round($memAvail, 1);
        $stats['mem_used_pct'] = round(($memTotal - $memAvail) / $memTotal * 100, 1);
    }
}

// ============================================
// ПИСЬМО
// ============================================
$date = date('Y-m-d H:i:s');

$body = "
<h2>📊 MultisiteX — отчёт</h2>
<p><strong>Дата:</strong> {$date} (MSK)</p>

<h3>👥 Пользователи</h3>
<ul>
    <li>Всего: <strong>{$stats['users_total']}</strong></li>
    <li>С email: <strong>{$stats['users_with_email']}</strong></li>
    <li>Без email: <strong>{$stats['users_without_email']}</strong></li>
    <li>Новых за 24ч: <strong>{$stats['users_new_24h']}</strong></li>
</ul>

<h3>🌐 Сайты</h3>
<ul>
    <li>Всего: <strong>{$stats['sites_total']}</strong></li>
    <li>test: <strong>{$stats['sites_test']}</strong></li>
    <li>start: <strong>{$stats['sites_start']}</strong></li>
    <li>business: <strong>{$stats['sites_business']}</strong></li>
    <li>Новых за 24ч: <strong>{$stats['sites_new_24h']}</strong></li>
</ul>

<h3>💰 Оплаты</h3>
<ul>
    <li>Всего: <strong>{$stats['payments_count']}</strong></li>
    <li>Сумма: <strong>{$stats['payments_sum']} ₽</strong></li>
    <li>За 24ч: <strong>{$stats['payments_new_24h']}</strong></li>
</ul>

<h3>📬 Лиды (платформа)</h3>
<ul>
    <li>Всего: <strong>{$stats['leads_total']}</strong></li>
    <li>За 24ч: <strong>{$stats['leads_new_24h']}</strong></li>
</ul>

<h3>🗄 База данных</h3>
<ul>
    <li>Таблиц: <strong>{$stats['tables_total']}</strong></li>
    <li>Размер: <strong>{$stats['db_size_mb']} МБ</strong></li>
</ul>

<h3>💾 Диск</h3>
<ul>
    <li>Всего: <strong>{$stats['disk_total_gb']} ГБ</strong></li>
    <li>Свободно: <strong>{$stats['disk_free_gb']} ГБ</strong></li>
    <li>Использовано: <strong>{$stats['disk_used_pct']}%</strong></li>
    <li>Папка sites/: <strong>{$stats['sites_size_mb']} МБ</strong></li>
</ul>

<h3>⚙️ Нагрузка</h3>
<ul>
    <li>Load 1/5/15: <strong>{$stats['load_1']} / {$stats['load_5']} / {$stats['load_15']}</strong></li>
    <li>RAM: <strong>{$stats['mem_used_pct']}%</strong> (свободно {$stats['mem_free_gb']} ГБ из {$stats['mem_total_gb']} ГБ)</li>
</ul>

<p style='color:#888;font-size:12px;'>MultisiteX monitor</p>
";

// ============================================
// ОТПРАВКА
// ============================================
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $mailConfig['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $mailConfig['username'];
    $mail->Password   = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $mailConfig['port'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
    $mail->addAddress('yesmarketing@yandex.ru');
    $mail->isHTML(true);
    $mail->Subject = '📊 MultisiteX — отчёт ' . date('Y-m-d H:i');
    $mail->Body    = $body;
    $mail->send();

    echo "✅ Отчёт отправлен\n";
    error_log('monitor: OK ' . $date);

} catch (Exception $e) {
    echo "❌ Ошибка: " . $mail->ErrorInfo . "\n";
    error_log('monitor: mail error: ' . $mail->ErrorInfo);
    exit(1);
}
