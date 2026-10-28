<?php
declare(strict_types=1);

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile = '/var/www/multisitex.com/config/db.php';
$authCfgFile = '/var/www/multisitex.com/config/auth.php';

if (!file_exists($dbCfgFile) || !file_exists($authCfgFile)) {
    http_response_code(500);
    exit('Config not found');
}
$dbCfg = require $dbCfgFile;
$authCfg = require $authCfgFile;

// ============================================
// HTTP BASIC
// ============================================
$basicLogin = $authCfg['templates_login'] ?? 'admin';
$basicPass  = $authCfg['templates_password'] ?? 'Mbx7gt8123!';

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $basicLogin ||
    $_SERVER['PHP_AUTH_PW'] !== $basicPass) {
    header('WWW-Authenticate: Basic realm="Templates Admin"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Требуется авторизация');
}

// ============================================
// БД
// ============================================
$connect = mysqli_connect($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if (!$connect) {
    http_response_code(500);
    exit('Ошибка БД');
}
mysqli_set_charset($connect, 'utf8mb4');

// ============================================
// СПИСОК НИШ
// ============================================
$niches = [];
$res = mysqli_query($connect, "SELECT * FROM niches ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $niches[] = $row;
}

if (empty($niches)) {
    exit('Ниши не найдены');
}

// ============================================
// ТЕКУЩАЯ НИША
// ============================================
$nicheName = $_GET['niche'] ?? $niches[0]['name'];

$niche = null;
foreach ($niches as $n) {
    if ($n['name'] === $nicheName) {
        $niche = $n;
        break;
    }
}

if (!$niche) {
    $niche = $niches[0];
    $nicheName = $niche['name'];
}

$table = 'template_' . $nicheName;

// ============================================
// ДОБАВЛЕНИЕ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_element'])) {
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
// РЕДАКТИРОВАНИЕ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_element'])) {
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
// УДАЛЕНИЕ
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($connect, "DELETE FROM `$table` WHERE id=$id");
    $success = "🗑️ Элемент удалён!";
}

// ============================================
// КОНТЕНТ
// ============================================
$content = [];
$res = mysqli_query($connect, "SELECT * FROM `$table` ORDER BY type, code");
while ($row = mysqli_fetch_assoc($res)) {
    $content[] = $row;
}

mysqli_close($connect);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка шаблонов — <?= htmlspecialchars($nicheName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; padding: 12px; font-family: 'Inter', sans-serif; }
        .layout { display: flex; gap: 16px; max-width: 1500px; margin: 0 auto; }
        .sidebar {
            width: 220px;
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
        .sidebar .niche-item {
            display: block;
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }
        .sidebar .niche-item:hover { background: #f5f5f5; }
        .sidebar .niche-item.active { background: #111; color: #fff; font-weight: 500; }
        .main { flex: 1; min-width: 0; }
        .card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-bottom: 16px; }
        .card-header { background: transparent; padding: 16px 20px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 16px; }
        .card-body { padding: 20px; }
        .table th { font-weight: 600; font-size: 13px; color: #555; }
        @media (max-width: 768px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; max-height: none; position: static; }
        }
    </style>
</head>
<body>

<div class="layout">

    <!-- ЛЕВАЯ КОЛОНКА -->
    <aside class="sidebar">
        <h4>Ниши</h4>
        <?php foreach ($niches as $n): ?>
            <a href="?niche=<?= urlencode($n['name']) ?>"
               class="niche-item <?= $n['name'] === $nicheName ? 'active' : '' ?>">
                <?= htmlspecialchars($n['name']) ?>
            </a>
        <?php endforeach; ?>
        <hr>
        <a href="/templates.php" class="niche-item">← Управление нишами</a>
    </aside>

    <!-- ПРАВАЯ КОЛОНКА -->
    <main class="main">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Шапка -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>📄 Контент шаблона: <strong><?= htmlspecialchars($nicheName) ?></strong></span>
                    <a href="?niche=<?= urlencode($nicheName) ?>&preview=1" class="btn btn-outline-dark btn-sm" target="_blank">👁️ Предпросмотр</a>
                </div>
            </div>
        </div>

        <!-- КОНТЕНТ -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>📄 Элементы</span>
                    <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">➕ Добавить элемент</button>
                </div>
            </div>
            <div class="card-body">
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
                            <?php foreach ($content as $row): ?>
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
                                        <a href="?niche=<?= urlencode($nicheName) ?>&delete=<?= $row['id'] ?>"
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
