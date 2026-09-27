<?php
// ============================================
// АДМИНКА ДЛЯ КОНКРЕТНОГО САЙТА (новая архитектура)
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаемся к БД
$connect = mysqli_connect('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if (!$connect) {
    die("Ошибка БД: " . mysqli_connect_error());
}
mysqli_set_charset($connect, "utf8mb4");

// ============================================
// 1. ОПРЕДЕЛЯЕМ ДОМЕН ИЗ URL
// ============================================
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#/sites/([^/]+)/admin\.php#', $uri, $match)) {
    $domain = $match[1];
} else {
    die('❌ Сайт не найден');
}

// ============================================
// 2. ПОЛУЧАЕМ ДАННЫЕ О САЙТЕ
// ============================================
$result = mysqli_query($connect, "SELECT * FROM sites WHERE domain = '$domain'");
$site = mysqli_fetch_assoc($result);

if (!$site) {
    die("❌ Сайт $domain не найден в БД");
}

$table = $site['content_table']; // таблица контента
$leadsTable = 'leads_' . $domain; // таблица лидов

// ============================================
// 3. ОБРАБОТКА ДОБАВЛЕНИЯ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_element'])) {
    $keyword = mysqli_real_escape_string($connect, $_POST['new_keyword']);
    $type = (int)$_POST['new_type'];
    $code = (int)$_POST['new_code'];
    $content = mysqli_real_escape_string($connect, $_POST['new_content']);
    $image = mysqli_real_escape_string($connect, $_POST['new_image']);
    $video = mysqli_real_escape_string($connect, $_POST['new_video']);
    $button = (int)$_POST['new_button'];
    $link = mysqli_real_escape_string($connect, $_POST['new_link']);
    $sort = (int)$_POST['new_sort'];
    $active = (int)$_POST['new_active'];

    $query = "INSERT INTO `$table` (keyword, type, code, content, image, video, button, link, sort, active)
              VALUES ('$keyword', $type, $code, '$content', '$image', '$video', $button, '$link', $sort, $active)";
    mysqli_query($connect, $query);
    $success = "✅ Элемент добавлен!";
}

// ============================================
// 4. ОБРАБОТКА РЕДАКТИРОВАНИЯ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_element'])) {
    $id = (int)$_POST['edit_id'];
    $keyword = mysqli_real_escape_string($connect, $_POST['edit_keyword']);
    $type = (int)$_POST['edit_type'];
    $code = (int)$_POST['edit_code'];
    $content = mysqli_real_escape_string($connect, $_POST['edit_content']);
    $image = mysqli_real_escape_string($connect, $_POST['edit_image']);
    $video = mysqli_real_escape_string($connect, $_POST['edit_video']);
    $button = (int)$_POST['edit_button'];
    $link = mysqli_real_escape_string($connect, $_POST['edit_link']);
    $sort = (int)$_POST['edit_sort'];
    $active = (int)$_POST['edit_active'];

    $query = "UPDATE `$table` SET
                keyword='$keyword',
                type=$type,
                code=$code,
                content='$content',
                image='$image',
                video='$video',
                button=$button,
                link='$link',
                sort=$sort,
                active=$active
              WHERE id=$id";
    mysqli_query($connect, $query);
    $success = "✅ Элемент обновлён!";
}

// ============================================
// 5. ОБРАБОТКА УДАЛЕНИЯ
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($connect, "DELETE FROM `$table` WHERE id=$id");
    $success = "🗑️ Элемент удалён!";
}

// ============================================
// 6. ПОЛУЧАЕМ КОНТЕНТ
// ============================================
$content = [];
$res = mysqli_query($connect, "SELECT * FROM `$table` ORDER BY type, code");
while ($row = mysqli_fetch_assoc($res)) {
    $content[] = $row;
}

// ============================================
// 7. ПОЛУЧАЕМ ЛИДЫ
// ============================================
$leads = [];
$resLeads = mysqli_query($connect, "SELECT * FROM `$leadsTable` ORDER BY id DESC LIMIT 50");
while ($row = mysqli_fetch_assoc($resLeads)) {
    $leads[] = $row;
}

// ============================================
// 8. ССЫЛКА НА САЙТ
// ============================================
$siteUrl = "https://" . $_SERVER['HTTP_HOST'] . "/sites/$domain/";

mysqli_close($connect);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка — <?= htmlspecialchars($domain) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; padding: 20px; font-family: 'Inter', sans-serif; }
        .container { max-width: 1400px; margin: 0 auto; }
        .card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-bottom: 24px; }
        .card-header { background: transparent; padding: 20px 24px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 18px; }
        .card-body { padding: 24px; }
        .table th { font-weight: 600; font-size: 13px; color: #555; }
        .btn-save { background: #111; color: #fff; border: none; padding: 10px 30px; border-radius: 8px; }
        .btn-save:hover { background: #333; }
        .tariff-banner { background: #fff8e1; padding: 16px; border-radius: 12px; border-left: 4px solid #f5a623; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .tariff-banner .btn { background: #111; color: #fff; border: none; padding: 10px 24px; border-radius: 60px; text-decoration: none; }
        .tariff-banner .btn:hover { background: #333; }
        .modal-content { border-radius: 16px; }
        .nav-tabs .nav-link { color: #555; }
        .nav-tabs .nav-link.active { color: #111; font-weight: 600; }
    </style>
</head>
<body>
<div class="container">

    <!-- Шапка -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <span>🔧 Админка: <strong><?= htmlspecialchars($domain) ?></strong></span>
                <div>
                    <a href="<?= $siteUrl ?>" target="_blank" class="btn btn-outline-dark btn-sm">🌐 Открыть сайт</a>
                    <a href="<?= $siteUrl ?>admin.php?show=leads" class="btn btn-outline-secondary btn-sm ms-2">📋 Заявки</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (($site['tariff'] ?? '') === 'test'): ?>
        <!-- Баннер тарифа — только на бесплатном -->
        <div class="tariff-banner">
            <div>
                <strong>🚀 Активируйте тариф «Старт»</strong> — 4 990 ₽/мес<br>
                <span class="text-muted small">Убирайте клеймо, загружайте свои картинки, смотрите аналитику</span>
            </div>
            <a href="#" class="btn" data-bs-toggle="modal" data-bs-target="#videoModal">Посмотреть видео</a>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['show']) && $_GET['show'] == 'leads'): ?>
        <!-- ===== ЛИДЫ ===== -->
        <div class="card">
            <div class="card-header">📞 Заявки с сайта</div>
            <div class="card-body">
                <?php if (count($leads) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Телефон</th>
                                <th>Email</th>
                                <th>Регион</th>
                                <th>Ключевое слово</th>
                                <th>Источник</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leads as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
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
                <a href="<?= $siteUrl ?>admin.php" class="btn btn-secondary btn-sm">← Назад к контенту</a>
            </div>
        </div>
    <?php else: ?>
        <!-- ===== КОНТЕНТ ===== -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
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
                    echo "<li class='nav-item'><a class='nav-link $active' href='?type=$t'>$label</a></li>";
                }
                echo '</ul>';
                ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Ключевое слово</th>
                                <th>Тип</th>
                                <th>Код</th>
                                <th>Контент</th>
                                <th>Картинка</th>
                                <th>Видео</th>
                                <th>Кнопка</th>
                                <th>Сортировка</th>
                                <th>Активен</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($content as $row):
                                if ($type != 'all' && $row['type'] != $type) continue;
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
                                        <a href="?delete=<?= $row['id'] ?>"
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

</div>

<!-- ===== МОДАЛЬНОЕ ОКНО ДОБАВЛЕНИЯ ===== -->
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
                        <div class="col-md-6">
                            <label class="form-label">Ключевое слово</label>
                            <input type="text" name="new_keyword" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Тип</label>
                            <select name="new_type" class="form-select">
                                <option value="1">Заголовок</option>
                                <option value="2">Регион</option>
                                <option value="3">Кнопка</option>
                                <option value="4">Изображение</option>
                                <option value="5">Видео</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Код</label>
                            <input type="number" name="new_code" class="form-control" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Сортировка</label>
                            <input type="number" name="new_sort" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Активен</label>
                            <select name="new_active" class="form-select">
                                <option value="1">Да</option>
                                <option value="0">Нет</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Контент (текст)</label>
                            <input type="text" name="new_content" class="form-control" placeholder="Текст">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Картинка (URL)</label>
                            <input type="text" name="new_image" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Видео (ID)</label>
                            <input type="text" name="new_video" class="form-control" placeholder="ID видео">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Кнопка (0/1)</label>
                            <input type="number" name="new_button" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ссылка</label>
                            <input type="text" name="new_link" class="form-control" placeholder="URL">
                        </div>
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

<!-- ===== МОДАЛЬНОЕ ОКНО РЕДАКТИРОВАНИЯ ===== -->
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
                        <div class="col-md-6">
                            <label class="form-label">Ключевое слово</label>
                            <input type="text" name="edit_keyword" id="edit_keyword" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Тип</label>
                            <select name="edit_type" id="edit_type" class="form-select">
                                <option value="1">Заголовок</option>
                                <option value="2">Регион</option>
                                <option value="3">Кнопка</option>
                                <option value="4">Изображение</option>
                                <option value="5">Видео</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Код</label>
                            <input type="number" name="edit_code" id="edit_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Сортировка</label>
                            <input type="number" name="edit_sort" id="edit_sort" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Активен</label>
                            <select name="edit_active" id="edit_active" class="form-select">
                                <option value="1">Да</option>
                                <option value="0">Нет</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Контент (текст)</label>
                            <input type="text" name="edit_content" id="edit_content" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Картинка (URL)</label>
                            <input type="text" name="edit_image" id="edit_image" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Видео (ID)</label>
                            <input type="text" name="edit_video" id="edit_video" class="form-control" placeholder="ID видео">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Кнопка (0/1)</label>
                            <input type="number" name="edit_button" id="edit_button" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ссылка</label>
                            <input type="text" name="edit_link" id="edit_link" class="form-control" placeholder="URL">
                        </div>
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

<!-- ===== МОДАЛЬНОЕ ОКНО С ВИДЕО ===== -->
<div class="modal fade" id="videoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Как активировать сайт</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-16x9">
                    <iframe
                        src="https://rutube.ru/play/embed/d3b0f75aaff8496e2f6fd595783e2a0a/"
                        style="border: none;"
                        allow="clipboard-write; autoplay"
                        allowFullScreen></iframe>
                </div>
                <p class="mt-3 mb-0 text-muted small">
                    После просмотра выберите тариф и завершите активацию.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <a href="/activate.php?site=<?= urlencode($domain) ?>" class="btn btn-dark">Перейти к оплате</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Заполнение модального окна редактирования
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
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
<script src="//code.jivo.ru/widget/jmf4AkbuEc" async></script>
</body>
</html>
