<?php
// ============================================
// АДМИНКА ЛИДОВ — ДЛЯ ТЕБЯ И ПАРТНЁРА
// ============================================

// ============================================
// АВТОРИЗАЦИЯ
// ============================================
$authCfgFile = '/var/www/multisitex.com/config/auth.php';
if (!file_exists($authCfgFile)) {
    http_response_code(500);
    exit('Config not found');
}
$authCfg = require $authCfgFile;

$login    = $_POST['login']    ?? '';
$password = $_POST['password'] ?? '';

if ($login !== $authCfg['leads_login'] || $password !== $authCfg['leads_password']) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Вход — Лиды MultisiteX</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); width: 350px; }
            .login h2 { margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="login">
            <h2 class="text-center">🔐 Вход в лиды</h2>
            <form method="post">
                <div class="mb-3">
                    <input type="text" name="login" class="form-control" placeholder="Логин" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Пароль" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// ПОДКЛЮЧЕНИЕ К БД
// ============================================
$dbCfg = require '/var/www/multisitex.com/config/db.php';
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    die("Ошибка БД");
}
$db->set_charset("utf8mb4");

// ============================================
// ЭКСПОРТ CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Телефон', 'Продукт', 'Ниша', 'Домен', 'Дата']);
    $res = $db->query("SELECT * FROM leads ORDER BY id DESC LIMIT 5000");
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['phone'],
            $row['product'],
            $row['niche'],
            $row['domain'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ============================================
// ПОЛУЧАЕМ ЛИДЫ (лимит 1000)
// ============================================
$result = $db->query("SELECT * FROM leads ORDER BY id DESC LIMIT 1000");
$leads = $result->fetch_all(MYSQLI_ASSOC);
$total = count($leads);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лиды MultisiteX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .header h1 { margin: 0; font-size: 1.8rem; }
        .table th { background: #f0f2f5; }
        .badge-custom { background: #e9ecef; color: #495057; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>📋 Лиды MultisiteX</h1>
            <span class="badge-custom">Всего: <?= $total ?></span>
            <?php if ($total >= 1000): ?>
                <span class="badge-custom" style="background:#fff3cd;color:#856404;">Показаны последние 1000</span>
            <?php endif; ?>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-success btn-sm">📥 Экспорт CSV</a>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">🚪 Выйти</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Телефон</th>
                    <th>Что продаёт</th>
                    <th>Ниша</th>
                    <th>Домен</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td><?= (int)$lead['id'] ?></td>
                    <td><a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="text-decoration-none"><?= htmlspecialchars($lead['phone']) ?></a></td>
                    <td><?= htmlspecialchars($lead['product']) ?></td>
                    <td><?= htmlspecialchars($lead['niche']) ?></td>
                    <td>
                        <?php if (!empty($lead['domain'])): ?>
                            <a href="https://multisitex.com/sites/<?= htmlspecialchars($lead['domain']) ?>/" target="_blank"><?= htmlspecialchars($lead['domain']) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $lead['created_at'] ? date('d.m.Y H:i', strtotime($lead['created_at'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $db->close(); ?>
