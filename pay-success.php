<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ============================================
// КОНФИГ
// ============================================
$cfgFile = '/var/www/multisitex.com/config/db.php';
if (!file_exists($cfgFile)) {
    http_response_code(500);
    exit('Config not found');
}
$dbCfg = require $cfgFile;

// ============================================
// ПАРАМЕТРЫ
// ============================================
$site = trim($_GET['site'] ?? '');
if ($site === '') {
    http_response_code(400);
    $error = 'Сайт не указан';
} elseif (!preg_match('/^[a-z0-9_-]+$/i', $site)) {
    http_response_code(400);
    $error = 'Некорректный сайт';
} else {
    // ============================================
    // ПРОВЕРКА ТАРИФА
    // ============================================
    $db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
    if ($db->connect_error) {
        http_response_code(500);
        exit('Ошибка БД');
    }
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare("SELECT tariff FROM sites WHERE domain = ?");
    $stmt->bind_param('s', $site);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$row) {
        http_response_code(404);
        $error = 'Сайт не найден';
    } elseif ($row['tariff'] !== 'start' && $row['tariff'] !== 'business') {
        // Не оплачен — ждём webhook
        $pending = true;
    } else {
        $paid = true;
        $tariff = $row['tariff'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата — Multisitex</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f7fa;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .icon { font-size: 48px; display: block; margin-bottom: 12px; }
        h1 { font-size: 26px; font-weight: 500; color: #1a1a1a; margin-bottom: 8px; }
        p { font-size: 15px; color: #555; margin-bottom: 24px; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: #1a1a1a;
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
        }
        .btn:hover { background: #333; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (!empty($paid)): ?>
            <span class="icon">✅</span>
            <h1>Оплата прошла!</h1>
            <p>Сайт <strong><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8') ?></strong><br>
               активирован на тарифе «<?= htmlspecialchars($tariff, ENT_QUOTES, 'UTF-8') ?>».</p>
            <a href="https://multisitex.com/sites/<?= urlencode($site) ?>/admin.php" class="btn">Перейти в админку</a>

        <?php elseif (!empty($pending)): ?>
            <span class="icon">⏳</span>
            <h1>Платёж в обработке</h1>
            <p>Оплата принята. Тариф активируется автоматически в течение минуты.<br>
               Обновите страницу через 30 секунд.</p>
            <a href="https://multisitex.com/sites/<?= urlencode($site) ?>/admin.php" class="btn">Перейти в админку</a>

        <?php else: ?>
            <span class="icon">⚠️</span>
            <h1>Ошибка</h1>
            <p><?= htmlspecialchars($error ?? 'Неизвестная ошибка', ENT_QUOTES, 'UTF-8') ?></p>
            <a href="https://multisitex.com/" class="btn">На главную</a>
        <?php endif; ?>
    </div>
</body>
</html>
