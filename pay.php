<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '/var/www/multisitex.com/vendor/autoload.php';

use YooKassa\Client;

$site = $_GET['site'] ?? '';
if (!$site) {
    die('Сайт не указан');
}

$db = new mysqli('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if ($db->connect_error) {
    die('Ошибка БД: ' . $db->connect_error);
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

$client = new Client();
$client->setAuth('1474368', 'live_0DkCGxqRtIIhz3MtgCqat4NgEFWCfZhXY8QXVm-vfYQ');

try {
    $payment = $client->createPayment([
        'amount' => [
            'value' => '1.00',
            'currency' => 'RUB',
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://multisitex.com/pay-success.php?site=' . urlencode($site),
        ],
        'capture' => true,
        'description' => 'Тестовый платёж 1 ₽ для ' . $site,
        'metadata' => [
            'site' => $site,
        ],
    ], uniqid('', true));

    header('Location: ' . $payment->getConfirmation()->getConfirmationUrl());
    exit;

} catch (Exception $e) {
    die('Ошибка создания платежа: ' . $e->getMessage());
}
