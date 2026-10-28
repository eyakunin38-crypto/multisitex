<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// ============================================
// АВТОРИЗАЦИЯ
// ============================================
$authCfgFile = '/var/www/multisitex.com/config/auth.php';
if (!file_exists($authCfgFile)) {
    http_response_code(500);
    exit('Config not found');
}
$authCfg = require $authCfgFile;

if (!isset($_SESSION['admin_logged'])) {
    $login    = $_POST['login']    ?? '';
    $password = $_POST['password'] ?? '';

    if ($login === $authCfg['sites_login'] && $password === $authCfg['sites_password']) {
        $_SESSION['admin_logged'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Вход — Управление сайтами</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .login { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); width: 350px; }
                .login h2 { margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="login">
                <h2 class="text-center">🔐 Вход в сайты</h2>
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
}

// ============================================
// БД
// ============================================
$dbCfg = require '/var/www/multisitex.com/config/db.php';
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    die("Ошибка БД");
}
$db->set_charset("utf8mb4");

// ============================================
// УДАЛЕНИЕ САЙТА
// ============================================
if (isset($_GET['delete']) && isset($_GET['domain'])) {
    $domain = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['domain']);

    // 1. Папка
    $path = "/var/www/multisitex.com/sites/$domain";
    if (is_dir($path)) {
        exec('rm -rf ' . escapeshellarg($path));
    }

    // 2. Таблицы
    $db->query("DROP TABLE IF EXISTS `$domain`");
    $db->query("DROP TABLE IF EXISTS `leads_$domain`");

    // 3. Записи
    $stmt = $db->prepare("DELETE FROM sites WHERE domain = ?");
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM settings WHERE domain = ?");
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM leads WHERE domain = ?");
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->close();

    header("Location: sites.php?deleted=1");
    exit;
}

// ============================================
// ВЫХОД
// ============================================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: sites.php");
    exit;
}

// ============================================
// СПИСОК САЙТОВ
// ============================================
$result = $db->query("
    SELECT
        s.domain,
        s.niche,
        st.phone
    FROM sites s
    LEFT JOIN settings st ON s.domain = st.domain
    ORDER BY s.id DESC
");
$sites = $result->fetch_all(MYSQLI_ASSOC);
$total = count($sites);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление сайтами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .header h1 { margin: 0; font-size: 1.8rem; }
        .badge-custom { background: #e9ecef; color: #495057; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; }
        .table th { background: #f0f2f5; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>🌐 Управление сайтами</h1>
            <span class="badge-custom">Всего: <?= $total ?></span>
        </div>
        <div class="actions">
            <a href="sites.php" class="btn btn-outline-secondary btn-sm">🔄 Обновить</a>
            <a href="?logout=1" class="btn btn-outline-danger btn-sm">🚪 Выйти</a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✅ Сайт удалён!</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>Домен</th>
                    <th>Ниша</th>
                    <th>Телефон</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">Нет созданных сайтов</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($sites as $site): ?>
                <tr>
                    <td>
                        <a href="https://multisitex.com/sites/<?= urlencode($site['domain']) ?>/" target="_blank">
                            <?= htmlspecialchars($site['domain']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($site['niche']) ?></td>
                    <td>
                        <?php if (!empty($site['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($site['phone']) ?>" class="text-decoration-none"><?= htmlspecialchars($site['phone']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?delete=1&domain=<?= urlencode($site['domain']) ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Удалить сайт <?= htmlspecialchars($site['domain'], ENT_QUOTES) ?> безвозвратно?')">
                            🗑️
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $db->close(); ?>
