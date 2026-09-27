<?php
header('Content-Type: text/html; charset=utf-8');

$site = $_GET['site'] ?? '';
if (!$site) {
    die('Сайт не указан');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оплата прошла</title>
    <style>
        body { font-family: Inter, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f7fa; margin: 0; }
        .card { background: white; padding: 40px 50px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); text-align: center; }
        h1 { font-size: 28px; font-weight: 600; color: #1a1a1a; margin-bottom: 8px; }
        p { font-size: 16px; color: #555; margin-bottom: 24px; }
        .btn { background: #1a1a1a; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="card">
        <h1>✅ Оплата прошла!</h1>
        <p>Сайт <strong><?= htmlspecialchars($site) ?></strong> активирован на тарифе «Старт».</p>
        <a href="https://multisitex.com/sites/<?= urlencode($site) ?>/admin.php" class="btn">Перейти в админку</a>
    </div>
</body>
</html>
