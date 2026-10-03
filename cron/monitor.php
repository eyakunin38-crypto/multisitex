<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');

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
// СБОР СТАТИСТИКИ
// ============================================
$stats = [];

// Users
$r = $db->query("SELECT COUNT(*) AS cnt FROM users");
$stats['users_total'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE email IS NOT NULL");
$stats['users_with_email'] = (int)$r->fetch_assoc()['cnt'];

$stats['users_without_email'] = $stats['users_total'] - $stats['users_with_email'];

// Sites
$r = $db->query("SELECT COUNT(*) AS cnt FROM sites");
$stats['sites_total'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT tariff, COUNT(*) AS cnt FROM sites GROUP BY tariff");
$stats['sites_by_tariff'] = [];
while ($row = $r->fetch_assoc()) {
    $stats['sites_by_tariff'][$row['tariff']] = (int)$row['cnt'];
}

$stats['sites_test']     = $stats['sites_by_tariff']['test']     ?? 0;
$stats['sites_start']    = $stats['sites_by_tariff']['start']    ?? 0;
$stats['sites_business'] = $stats['sites_by_tariff']['business'] ?? 0;

// Payments
$r = $db->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS sum FROM payments");
$row = $r->fetch_assoc();
$stats['payments_count'] = (int)$row['cnt'];
$stats['payments_sum']   = (float)$row['sum'];

// За сутки
$r = $db->query("SELECT COUNT(*) AS cnt FROM sites WHERE created_at > NOW() - INTERVAL 1 DAY");
$stats['sites_new_24h'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE created_at > NOW() - INTERVAL 1 DAY");
$stats['users_new_24h'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM payments WHERE created_at > NOW() - INTERVAL 1 DAY");
$stats['payments_new_24h'] = (int)$r->fetch_assoc()['cnt'];

$db->close();

// ============================================
// ФОРМИРУЕМ ПИСЬМО
// ============================================
$date = date('Y-m-d H:i:s');

$body = "
<h2>📊 MultisiteX — отчёт</h2>
<p><strong>Дата:</strong> {$date}</p>

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
