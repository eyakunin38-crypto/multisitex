<?php
declare(strict_types=1);
session_start();

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile = '/var/www/multisitex.com/config/db.php';
if (!file_exists($dbCfgFile)) {
    http_response_code(500);
    exit('Config not found');
}
$dbCfg = require $dbCfgFile;

$connect = mysqli_connect($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if (!$connect) {
    http_response_code(500);
    exit('Ошибка БД');
}
mysqli_set_charset($connect, 'utf8mb4');

// ============================================
// 1. ПОЛЬЗОВАТЕЛЬ
// ============================================
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0 && !empty($_COOKIE['visitor_id'])) {
    $visitor_id = $_COOKIE['visitor_id'];
    $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE visitor_id = ?");
    mysqli_stmt_bind_param($stmt, 's', $visitor_id);
    mysqli_stmt_execute($stmt);
    $u = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($u) {
        $user_id = (int)$u['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if ($user_id <= 0) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header("Location: /login.php?redirect=$redirect");
    exit;
}

// ============================================
// 2. EMAIL ПОЛЬЗОВАТЕЛЯ
// ============================================
$userEmail = null;
$stmt = mysqli_prepare($connect, "SELECT email FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$u = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
$userEmail = $u['email'] ?? null;

// ============================================
// 3. СПИСОК САЙТОВ ПОЛЬЗОВАТЕЛЯ
// ============================================
$sites = [];
$stmt = mysqli_prepare($connect, "SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = $res->fetch_assoc()) {
    $sites[] = $row;
}
mysqli_stmt_close($stmt);

if (empty($sites)) {
    header('Location: /');
    exit;
}

// ============================================
// 4. ТЕКУЩИЙ САЙТ
// ============================================
$urlDomain = null;
if (preg_match('#/sites/([^/]+)/#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $urlDomain = $m[1];
}

$domain = $_GET['site'] ?? $urlDomain ?? $sites[0]['domain'];

$site = null;
foreach ($sites as $s) {
    if ($s['domain'] === $domain) {
        $site = $s;
        break;
    }
}

$siteNotFound = false;
$sitePhone = '';
$table = '';
$leadsTable = '';

if (!$site) {
    $siteNotFound = true;
} else {
    $table = $site['content_table'];
    $leadsTable = 'leads_' . $domain;

    // Телефон сайта (для формы email)
    $stmt = mysqli_prepare($connect, "SELECT phone FROM settings WHERE domain = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $domain);
    mysqli_stmt_execute($stmt);
    $s = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    $sitePhone = $s['phone'] ?? '';
}

// ============================================
// 5. ОБРАБОТКА ДОБАВЛЕНИЯ
// ============================================
if ($site && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_element'])) {
    $keyword = mysqli_real_escape_string($connect, $_POST['new_keyword'] ?? '');
    $type    = (int)($_POST['new_type'] ?? 1);
    $code    = (int)($_POST['new_code'] ?? 1);
    $content = mysqli_real_escape_string($connect, $_POST['new_content'] ?? '');
    $image   = mysqli_real_escape_string($connect, $_POST['new_image'] ?? '');
    $video   = mysqli_real_escape_string($connect, $_POST['new_video'] ?? '');
    $button  = (int)($_POST['new_button'] ?? 0);
    $link    = mysqli_real_escape_string($connect, $_POST['new_link'] ?? '');
    $sort    = (int)($_POST['new_sort'] ?? 0);
    $active  = (int)($_POST['new_active'] ?? 1);

    $query = "INSERT INTO `$table` (keyword, type, code, content, image, video, button, link, sort, active)
              VALUES ('$keyword', $type, $code, '$content', '$image', '$video', $button, '$link', $sort, $active)";
    mysqli_query($connect, $query);
    $success = "✅ Элемент добавлен!";
}

// ============================================
// 6. ОБРАБОТКА РЕДАКТИРОВАНИЯ
// ============================================
if ($site && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_element'])) {
    $id      = (int)($_POST['edit_id'] ?? 0);
    $keyword = mysqli_real_escape_string($connect, $_POST['edit_keyword'] ?? '');
    $type    = (int)($_POST['edit_type'] ?? 1);
    $code    = (int)($_POST['edit_code'] ?? 1);
    $content = mysqli_real_escape_string($connect, $_POST['edit_content'] ?? '');
    $image   = mysqli_real_escape_string($connect, $_POST['edit_image'] ?? '');
    $video   = mysqli_real_escape_string($connect, $_POST['edit_video'] ?? '');
    $button  = (int)($_POST['edit_button'] ?? 0);
    $link    = mysqli_real_escape_string($connect, $_POST['edit_link'] ?? '');
    $sort    = (int)($_POST['edit_sort'] ?? 0);
    $active  = (int)($_POST['edit_active'] ?? 1);

    $query = "UPDATE `$table` SET
                keyword='$keyword', type=$type, code=$code, content='$content',
                image='$image', video='$video', button=$button, link='$link',
                sort=$sort, active=$active
              WHERE id=$id";
    mysqli_query($connect, $query);
    $success = "✅ Элемент обновлён!";
}

// ============================================
// 7. ОБРАБОТКА УДАЛЕНИЯ
// ============================================
if ($site && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($connect, "DELETE FROM `$table` WHERE id=$id");
    $success = "🗑️ Элемент удалён!";
}

// ============================================
// 8. КОНТЕНТ И ЛИДЫ
// ============================================
$content = [];
$leads = [];

if ($site) {
    $res = mysqli_query($connect, "SELECT * FROM `$table` ORDER BY type, code");
    while ($row = mysqli_fetch_assoc($res)) {
        $content[] = $row;
    }

    $resLeads = mysqli_query($connect, "SELECT * FROM `$leadsTable` ORDER BY id DESC LIMIT 50");
    while ($row = mysqli_fetch_assoc($resLeads)) {
        $leads[] = $row;
    }
}

$siteUrl = "https://" . $_SERVER['HTTP_HOST'] . "/sites/" . ($site['domain'] ?? '');

// ============================================
// 9. ОФФЕРЫ — РОТАЦИЯ
// ============================================
$offer = null;
if ($site) {
    $offerStmt = mysqli_prepare($connect, "
        SELECT * FROM offers
        WHERE active = 1
          AND (tariff_show = ? OR tariff_show = 'all')
        ORDER BY RAND() * weight DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($offerStmt, 's', $site['tariff']);
    mysqli_stmt_execute($offerStmt);
    $offer = mysqli_stmt_get_result($offerStmt)->fetch_assoc();
    mysqli_stmt_close($offerStmt);
}

mysqli_close($connect);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка — <?= htmlspecialchars($site['domain'] ?? 'Сайт не найден') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; padding: 12px; font-family: 'Inter', sans-serif; }
        .layout { display: flex; gap: 16px; max-width: 1500px; margin: 0 auto; }
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            max-height: calc(100vh - 24px);
            overflow-y: auto;
            position: sticky;
            top: 12px;
        }
        .sidebar h4 { font-size: 14px; color: #888; text-transform: uppercase; margin-bottom: 12px; }
        .sidebar .site-item {
            display: block;
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            word-break: break-all;
        }
        .sidebar .site-item:hover { background: #f5f5f5; }
        .sidebar .site-item.active { background: #111; color: #fff; font-weight: 500; }
        .sidebar .site-item .badge { font-size: 10px; float: right; margin-top: 2px; }
        .sidebar .menu-link {
            display: block;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: #555;
            font-size: 14px;
            margin-top: 4px;
        }
        .sidebar .menu-link:hover { background: #f5f5f5; color: #111; }
        .sidebar .btn-create {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 16px;
            background: #111;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
        }
        .sidebar .btn-create:hover { background: #333; }
        .main { flex: 1; min-width: 0; }
        .burger-btn {
            display: none;
            background: #111;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 18px;
            margin-bottom: 12px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .layout { flex-direction: column; }
            .sidebar { display: none; width: 100%; max-height: none; position: static; }
            .sidebar.open { display: block; }
            .burger-btn { display: inline-block; }
        }
        .card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-bottom: 16px; }
        .card-header { background: transparent; padding: 16px 20px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 16px; }
        .card-body { padding: 20px; }
        .table th { font-weight: 600; font-size: 13px; color: #555; }
        .tariff-banner { background: #fff8e1; padding: 14px; border-radius: 12px; border-left: 4px solid #f5a623; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 14px; }
        .tariff-banner .btn { background: #111; color: #fff; border: none; padding: 8px 20px; border-radius: 60px; text-decoration: none; font-size: 13px; }
        .email-banner { background: #eff6ff; padding: 14px; border-radius: 12px; border-left: 4px solid #1e40af; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 14px; }
        .email-banner .btn { background: #1e40af; color: #fff; border: none; padding: 8px 20px; border-radius: 60px; text-decoration: none; font-size: 13px; }
        .not-found { text-align: center; padding: 60px 20px; }
        .not-found .icon { font-size: 64px; margin-bottom: 16px; display: block; }
        .not-found h2 { font-size: 28px; font-weight: 300; }
        .not-found p { color: #888; margin-bottom: 24px; }
        .not-found .btn { background: #111; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 0; display: inline-block; }
    </style>
</head>
<body>

<div class="layout">

    <!-- ЛЕВАЯ КОЛОНКА -->
    <aside class="sidebar" id="sidebar">
        <h4>Мои сайты</h4>
        <?php foreach ($sites as $s): ?>
            <a href="/sites/<?= urlencode($s['domain']) ?>/admin.php"
               class="site-item <?= $s['domain'] === $domain ? 'active' : '' ?>">
                <?= htmlspecialchars($s['domain']) ?>
                <span class="badge bg-<?= $s['tariff'] === 'test' ? 'secondary' : 'success' ?>"><?= htmlspecialchars($s['tariff']) ?></span>
            </a>
        <?php endforeach; ?>

        <a href="/" class="btn-create">➕ Создать сайт</a>

        <hr style="margin: 20px 0; border-color: #f0f0f0;">

        <a href="/sites/<?= urlencode($domain) ?>/admin.php?show=leads" class="menu-link">📬 Заявки</a>
        <a href="/sites/<?= urlencode($domain) ?>/admin.php" class="menu-link">📄 Контент</a>
        <a href="/pricing.php" class="menu-link">💎 Тарифы</a>
        <a href="/logout.php" class="menu-link">🚪 Выйти</a>
    </aside>

    <!-- ПРАВАЯ КОЛОНКА -->
    <main class="main">

        <button class="burger-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰ Меню</button>

        <?php if (!empty($siteNotFound)): ?>
            <div class="card">
                <div class="card-body not-found">
                    <span class="icon">⚠️</span>
                    <h2>Сайт не найден</h2>
                    <p>Возможно, он был удалён.</p>
                    <a href="/" class="btn">Создать свой сайт</a>
                </div>
            </div>
        <?php else: ?>

            <!-- Шапка -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>🔧 Админка: <strong><?= htmlspecialchars($site['domain']) ?></strong></span>
                        <div>
                            <a href="<?= $siteUrl ?>" target="_blank" class="btn btn-outline-dark btn-sm">🌐 Открыть сайт</a>
                            <a href="/logout.php" class="btn btn-outline-danger btn-sm">Выйти</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email-баннер (если email нет) -->
            <?php if (empty($userEmail)): ?>
                <div class="email-banner">
                    <div>
                        <strong>📩 Укажите email — иначе сайт удалится через 7 дней</strong><br>
                        <span class="text-muted small">Без email вы не сможете получать заявки и войти с другого устройства.</span>
                    </div>
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#emailModal">Указать email</button>
                </div>
            <?php endif; ?>

            <!-- Оффер (ротация) -->
            <?php if ($offer): ?>
                <?php
                $offerUrl = $offer['url'];
                if ($offer['type'] === 'internal') {
                    $offerUrl = str_replace('{site}', urlencode($site['domain']), $offerUrl);
                }
                ?>
                <div class="tariff-banner">
                    <div>
                        <strong><?= htmlspecialchars($offer['title']) ?></strong><br>
                        <span class="text-muted small"><?= htmlspecialchars($offer['description']) ?></span>
                    </div>
                    <a href="<?= htmlspecialchars($offerUrl) ?>"
                       class="btn"
                       target="<?= $offer['type'] === 'partner' ? '_blank' : '_self' ?>"
                       rel="noopener">
                        Перейти
                    </a>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['show']) && $_GET['show'] === 'leads'): ?>
                <!-- ЛИДЫ -->
                <div class="card">
                    <div class="card-header">📞 Заявки с сайта</div>
                    <div class="card-body">
                        <?php if (count($leads) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th><th>Имя</th><th>Телефон</th><th>Email</th>
                                        <th>Регион</th><th>Ключевое слово</th><th>Источник</th><th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($leads as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($row['id'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['region'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['keyword'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['source'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-info">Заявок пока нет</div>
                        <?php endif; ?>
                        <a href="/sites/<?= urlencode($domain) ?>/admin.php" class="btn btn-secondary btn-sm">← Назад к контенту</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- КОНТЕНТ -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span>📄 Контент сайта</span>
                            <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">➕ Добавить элемент</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <?php
                        $type = $_GET['type'] ?? 'all';
                        $types = ['all' => 'Все', 1 => 'Заголовки', 2 => 'Регионы', 3 => 'Кнопки', 4 => 'Изображения', 5 => 'Видео'];
                        echo '<ul class="nav nav-tabs mb-3">';
                        foreach ($types as $t => $label) {
                            $active = ($type == $t) ? 'active' : '';
                            echo "<li class='nav-item'><a class='nav-link $active' href='/sites/" . urlencode($domain) . "/admin.php?type=$t'>$label</a></li>";
                        }
                        echo '</ul>';
                        ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th><th>Ключевое слово</th><th>Тип</th><th>Код</th>
                                        <th>Контент</th><th>Картинка</th><th>Видео</th><th>Кнопка</th>
                                        <th>Сортировка</th><th>Активен</th><th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($content as $row):
                                        if ($type !== 'all' && $row['type'] != $type) continue;
                                    ?>
                                        <tr>
                                            <td><?= $row['id'] ?></td>
                                            <td><?= htmlspecialchars($row['keyword']) ?></td>
                                            <td><?= $row['type'] ?></td>
                                            <td><?= $row['code'] ?></td>
                                            <td><?= htmlspecialchars($row['content']) ?></td>
                                            <td><?= htmlspecialchars($row['image']) ?></td>
                                            <td><?= htmlspecialchars($row['video']) ?></td>
                                            <td><?= $row['button'] ?></td>
                                            <td><?= $row['sort'] ?></td>
                                            <td><?= $row['active'] ? '✅' : '❌' ?></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm edit-btn"
                                                    data-id="<?= $row['id'] ?>"
                                                    data-keyword="<?= htmlspecialchars($row['keyword']) ?>"
                                                    data-type="<?= $row['type'] ?>"
                                                    data-code="<?= $row['code'] ?>"
                                                    data-content="<?= htmlspecialchars($row['content']) ?>"
                                                    data-image="<?= htmlspecialchars($row['image']) ?>"
                                                    data-video="<?= htmlspecialchars($row['video']) ?>"
                                                    data-button="<?= $row['button'] ?>"
                                                    data-link="<?= htmlspecialchars($row['link']) ?>"
                                                    data-sort="<?= $row['sort'] ?>"
                                                    data-active="<?= $row['active'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal">✏️</button>
                                                <a href="/sites/<?= urlencode($domain) ?>/admin.php?delete=<?= $row['id'] ?>"
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Удалить элемент?')">🗑️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<!-- Модалка добавления -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">➕ Добавить элемент</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_element" value="1">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Ключевое слово</label><input type="text" name="new_keyword" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Тип</label>
                            <select name="new_type" class="form-select">
                                <option value="1">Заголовок</option><option value="2">Регион</option>
                                <option value="3">Кнопка</option><option value="4">Изображение</option><option value="5">Видео</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Код</label><input type="number" name="new_code" class="form-control" value="1"></div>
                        <div class="col-md-4"><label class="form-label">Сортировка</label><input type="number" name="new_sort" class="form-control" value="0"></div>
                        <div class="col-md-4"><label class="form-label">Активен</label>
                            <select name="new_active" class="form-select"><option value="1">Да</option><option value="0">Нет</option></select>
                        </div>
                        <div class="col-md-12"><label class="form-label">Контент (текст)</label><input type="text" name="new_content" class="form-control" placeholder="Текст"></div>
                        <div class="col-md-6"><label class="form-label">Картинка (URL)</label><input type="text" name="new_image" class="form-control" placeholder="https://..."></div>
                        <div class="col-md-6"><label class="form-label">Видео (ID)</label><input type="text" name="new_video" class="form-control" placeholder="ID видео"></div>
                        <div class="col-md-6"><label class="form-label">Кнопка (0/1)</label><input type="number" name="new_button" class="form-control" value="0"></div>
                        <div class="col-md-6"><label class="form-label">Ссылка</label><input type="text" name="new_link" class="form-control" placeholder="URL"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-dark">💾 Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка редактирования -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Редактирование элемента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_element" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Ключевое слово</label><input type="text" name="edit_keyword" id="edit_keyword" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Тип</label>
                            <select name="edit_type" id="edit_type" class="form-select">
                                <option value="1">Заголовок</option><option value="2">Регион</option>
                                <option value="3">Кнопка</option><option value="4">Изображение</option><option value="5">Видео</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Код</label><input type="number" name="edit_code" id="edit_code" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Сортировка</label><input type="number" name="edit_sort" id="edit_sort" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Активен</label>
                            <select name="edit_active" id="edit_active" class="form-select"><option value="1">Да</option><option value="0">Нет</option></select>
                        </div>
                        <div class="col-md-12"><label class="form-label">Контент (текст)</label><input type="text" name="edit_content" id="edit_content" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Картинка (URL)</label><input type="text" name="edit_image" id="edit_image" class="form-control" placeholder="https://..."></div>
                        <div class="col-md-6"><label class="form-label">Видео (ID)</label><input type="text" name="edit_video" id="edit_video" class="form-control" placeholder="ID видео"></div>
                        <div class="col-md-6"><label class="form-label">Кнопка (0/1)</label><input type="number" name="edit_button" id="edit_button" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Ссылка</label><input type="text" name="edit_link" id="edit_link" class="form-control" placeholder="URL"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-dark">💾 Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка email -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📩 Указать email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="emailForm">
                <div class="modal-body">
                    <p class="text-muted small">Мы отправим ссылку для активации. Без email сайт удалится через 7 дней.</p>
                    <input type="email" id="newEmail" class="form-control" placeholder="you@example.com" required>
                    <div class="text-danger small mt-2" id="emailError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-dark">Отправить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_keyword').value = this.dataset.keyword;
            document.getElementById('edit_type').value = this.dataset.type;
            document.getElementById('edit_code').value = this.dataset.code;
            document.getElementById('edit_content').value = this.dataset.content;
            document.getElementById('edit_image').value = this.dataset.image;
            document.getElementById('edit_video').value = this.dataset.video;
            document.getElementById('edit_button').value = this.dataset.button;
            document.getElementById('edit_link').value = this.dataset.link || '';
            document.getElementById('edit_sort').value = this.dataset.sort || 0;
            document.getElementById('edit_active').value = this.dataset.active || 1;
        });
    });

    document.getElementById('emailForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const email = document.getElementById('newEmail').value.trim();
        const err = document.getElementById('emailError');
        err.textContent = '';

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            err.textContent = 'Введите корректный email';
            return;
        }

        const data = new FormData();
        data.append('email', email);
        data.append('niche', '<?= htmlspecialchars($site['niche'] ?? '') ?>');
        data.append('phone', '<?= htmlspecialchars($sitePhone) ?>');
        data.append('product', '');

        try {
            const res = await fetch('/api/register.php', { method: 'POST', body: data });
            const result = await res.json();

            if (result.success) {
                window.location.href = '/?check=1&email=' + encodeURIComponent(email);
            } else {
                if (result.redirect) {
                    window.location.href = result.redirect;
                    return;
                }
                err.textContent = result.error || 'Ошибка';
            }
        } catch (e) {
            err.textContent = 'Ошибка сети';
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
