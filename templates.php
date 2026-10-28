<?php
// ============================================
// УПРАВЛЕНИЕ ШАБЛОНАМИ (НИШАМИ) MULTISITEX
// ============================================

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

if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    // Уже авторизован
} else {
    if (($_POST['password'] ?? '') === $authCfg['templates_password']) {
        $_SESSION['admin_logged'] = true;
        header("Location: templates.php");
        exit;
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Вход — Управление шаблонами</title>
            <style>
                body { font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5; margin: 0; }
                .login { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
                .login h2 { margin-top: 0; }
                .login input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
                .login button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
                .login .error { color: red; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="login">
                <h2>🔐 Вход в Templates</h2>
                <form method="post">
                    <input type="password" name="password" placeholder="Введите пароль" required>
                    <button type="submit">Войти</button>
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

$action = $_GET['action'] ?? '';

// ============================================
// ДОБАВЛЕНИЕ НИШИ
// ============================================
if ($action === 'add' && !empty($_POST['name'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_POST['name']);
    $title = $db->real_escape_string($_POST['title'] ?? $name);
    $html = $_POST['html_template'] ?? '<h1>Новый шаблон</h1><p>Замените этот текст</p>';

    $templatePath = "/var/www/multisitex.com/templates/$name/";
    $fullPath = "/var/www/multisitex.com/templates/$name";

    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
    file_put_contents("$fullPath/main.html", $html);
    chown($fullPath, 'nginx');
    chgrp($fullPath, 'nginx');
    chmod("$fullPath/main.html", 644);

    $stmt = $db->prepare("INSERT INTO niches (name, title, template_path) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $title, $templatePath);
    $stmt->execute();
    $stmt->close();

    header("Location: templates.php?added=" . urlencode($name));
    exit;
}

// ============================================
// ОБНОВЛЕНИЕ ШАБЛОНА
// ============================================
if ($action === 'update' && !empty($_POST['name']) && isset($_POST['html_template'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_POST['name']);
    $html = $_POST['html_template'];
    $file = "/var/www/multisitex.com/templates/$name/main.html";
    file_put_contents($file, $html);
    header("Location: templates.php?updated=" . urlencode($name));
    exit;
}

// ============================================
// УДАЛЕНИЕ НИШИ
// ============================================
if ($action === 'delete' && !empty($_GET['name'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['name']);
    $path = "/var/www/multisitex.com/templates/$name";
    if (is_dir($path)) {
        exec('rm -rf ' . escapeshellarg($path));
    }
    $stmt = $db->prepare("DELETE FROM niches WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
    header("Location: templates.php?deleted=" . urlencode($name));
    exit;
}

// ============================================
// ПРЕДПРОСМОТР
// ============================================
if ($action === 'preview' && !empty($_GET['name'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['name']);
    $file = "/var/www/multisitex.com/templates/$name/main.html";
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo "Шаблон не найден";
    }
    exit;
}

// ============================================
// РЕДАКТИРОВАНИЕ
// ============================================
if ($action === 'edit' && !empty($_GET['name'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['name']);
    $file = "/var/www/multisitex.com/templates/$name/main.html";
    $content = file_exists($file) ? file_get_contents($file) : '';
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Редактирование</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        textarea { width: 100%; height: 400px; font-family: monospace; font-size: 14px; padding: 10px; }
        .btn { padding: 10px 20px; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background: #28a745; }
        .btn-secondary { background: #6c757d; }
        .btn-info { background: #17a2b8; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        iframe { width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
    </head>
    <body>
    <div class="container">
        <h1>✏️ Редактирование: <?= htmlspecialchars($name) ?></h1>
        <div class="row">
            <div class="col">
                <form method="post" action="templates.php?action=update">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>">
                    <textarea name="html_template"><?= htmlspecialchars($content) ?></textarea>
                    <button type="submit" class="btn btn-success">💾 Сохранить</button>
                    <a href="templates.php" class="btn btn-secondary">← Назад</a>
                    <a href="templates.php?action=preview&name=<?= htmlspecialchars($name) ?>" target="_blank" class="btn btn-info">👁️ Предпросмотр</a>
                </form>
            </div>
            <div class="col">
                <h3>Предпросмотр</h3>
                <iframe srcdoc="<?= htmlspecialchars($content) ?>"></iframe>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    $db->close();
    exit;
}

// ============================================
// ВЫХОД
// ============================================
if ($action === 'logout') {
    session_destroy();
    header("Location: templates.php");
    exit;
}

// ============================================
// ОСНОВНАЯ СТРАНИЦА — СПИСОК НИШ
// ============================================
$result = $db->query("SELECT * FROM niches ORDER BY name");
$niches = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Управление шаблонами</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; }
        .btn { padding: 6px 12px; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .btn-info { background: #17a2b8; }
        .btn-secondary { background: #6c757d; }
        .add-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .add-form input, .add-form textarea { width: 100%; padding: 8px; margin: 5px 0; }
        .add-form textarea { height: 150px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📁 Управление шаблонами (нишами)</h1>
    <p><a href="templates.php?action=logout" class="btn btn-secondary">Выйти</a></p>

    <div class="add-form">
        <h3>➕ Добавить новую нишу</h3>
        <form method="post" action="templates.php?action=add">
            <input type="text" name="name" placeholder="Системное имя (например: remont)" required>
            <input type="text" name="title" placeholder="Отображаемое имя (например: Ремонт квартир)" required>
            <textarea name="html_template" placeholder="Вставьте HTML-код шаблона (main.html)"></textarea>
            <button type="submit" class="btn btn-success">➕ Создать нишу</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>ID</th><th>Системное имя</th><th>Отображаемое имя</th><th>Путь</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($niches as $niche): ?>
            <tr>
                <td><?= $niche['id'] ?></td>
                <td><?= htmlspecialchars($niche['name']) ?></td>
                <td><?= htmlspecialchars($niche['title'] ?? $niche['name']) ?></td>
                <td><code><?= htmlspecialchars($niche['template_path']) ?></code></td>
                <td>
                    <a href="templates.php?action=edit&name=<?= htmlspecialchars($niche['name']) ?>" class="btn btn-primary">✏️</a>
                    <a href="templates.php?action=preview&name=<?= htmlspecialchars($niche['name']) ?>" target="_blank" class="btn btn-info">👁️</a>
                    <a href="templates.php?action=delete&name=<?= htmlspecialchars($niche['name']) ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (isset($_GET['added'])): ?>
        <p style="color:green;">✅ Добавлено: <?= htmlspecialchars($_GET['added']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <p style="color:green;">✅ Обновлено: <?= htmlspecialchars($_GET['updated']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <p style="color:green;">🗑️ Удалено: <?= htmlspecialchars($_GET['deleted']) ?></p>
    <?php endif; ?>
</div>
</body>
</html>
<?php $db->close(); ?>
