<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '/var/www/multisitex.com/vendor/autoload.php';

use YooKassa\Client;

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile   = '/var/www/multisitex.com/config/db.php';
$ykCfgFile   = '/var/www/multisitex.com/config/yookassa.php';
$tariffFile  = '/var/www/multisitex.com/config/tariffs.php';

if (!file_exists($dbCfgFile) || !file_exists($ykCfgFile) || !file_exists($tariffFile)) {
    http_response_code(500);
    exit('Config not found');
}

$dbCfg   = require $dbCfgFile;
$ykCfg   = require $ykCfgFile;
$tariffs = require $tariffFile;

// ============================================
// ПАРАМЕТРЫ
// ============================================
$site = trim($_GET['site'] ?? '');
if ($site === '') {
    die('Сайт не указан');
}
if (!preg_match('/^[a-z0-9_-]+$/i', $site)) {
    die('Некорректный сайт');
}

$tariffKey = trim($_GET['tariff'] ?? 'start');
if (!isset($tariffs[$tariffKey])) {
    die('Неизвестный тариф');
}
$price = $tariffs[$tariffKey]['price'];
$title = $tariffs[$tariffKey]['title'];

// ============================================
// БД
// ============================================
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    error_log('pay.php: DB error: ' . $db->connect_error);
    die('Временная ошибка. Попробуйте позже.');
}
$db->set_charset('utf8mb4');

$stmt = $db->prepare("SELECT id, domain, tariff FROM sites WHERE domain = ?");
$stmt->bind_param('s', $site);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    die('Сайт не найден');
}

if ($result['tariff'] === 'start' || $result['tariff'] === 'business') {
    die('Сайт уже оплачен');
}

$db->close();

// ============================================
// ЮKASSA
// ============================================
$client = new Client();
$client->setAuth($ykCfg['shop_id'], $ykCfg['secret_key']);

try {
    $payment = $client->createPayment([
        'amount' => [
            'value'    => number_format((float)$price, 2, '.', ''),
            'currency' => 'RUB',
        ],
        'confirmation' => [
            'type'       => 'redirect',
            'return_url' => 'https://multisitex.com/pay-success.php?site=' . urlencode($site),
        ],
        'capture'     => true,
        'description' => 'Тариф ' . $title . ' для ' . $site,
        'metadata'    => [
            'site'   => $site,
            'tariff' => $tariffKey,
        ],
    ], uniqid('', true));

    header('Location: ' . $payment->getConfirmation()->getConfirmationUrl());
    exit;

} catch (Exception $e) {
    error_log('pay.php: ' . $e->getMessage());
    die('Ошибка создания платежа. Попробуйте позже.');
}
