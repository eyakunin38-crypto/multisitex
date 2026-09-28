<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/deploy_errors.log');

// ============================================
// КОНФИГ
// ============================================
$cfgFile = '/var/www/multisitex.com/config/db.php';
if (!file_exists($cfgFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}
$dbCfg = require $cfgFile;

const SITES_PATH     = '/var/www/multisitex.com/sites';
const CORE_PATH      = '/var/www/multisitex.com/core';
const TEMPLATES_PATH = '/var/www/multisitex.com/templates';

// ============================================
// ПОЛЬЗОВАТЕЛЬ
// ============================================
function resolveUserId(mysqli $db): int
{
    // 1. Сессия
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    // 2. Куки
    if (!empty($_COOKIE['visitor_id'])) {
        $visitor_id = $_COOKIE['visitor_id'];
        $stmt = $db->prepare("SELECT id FROM users WHERE visitor_id = ?");
        $stmt->bind_param('s', $visitor_id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            $_SESSION['user_id'] = (int)$u['id'];
            return (int)$u['id'];
        }
    }

    // 3. Новый
    $visitor_id = bin2hex(random_bytes(16));
    $stmt = $db->prepare("INSERT INTO users (visitor_id) VALUES (?)");
    $stmt->bind_param('s', $visitor_id);
    $stmt->execute();
    $user_id = (int)$db->insert_id;
    $stmt->close();

    if (!headers_sent()) {
        setcookie('visitor_id', $visitor_id, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $_SESSION['user_id'] = $user_id;
    return $user_id;
}

// ============================================
// ФУНКЦИЯ СОЗДАНИЯ САЙТА
// ============================================
function createSite(array $data): array
{
    global $dbCfg;

    $niche   = trim($data['niche']   ?? '');
    $phone   = trim($data['phone']   ?? '');
    $product = trim($data['product'] ?? '');
    $user_id = (int)($data['user_id'] ?? 0);

    if ($niche === '') {
        return ['success' => false, 'error' => 'Выберите нишу'];
    }

    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if ($phone === '') {
        $phone = '79882447774';
    }

    $db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
    if ($db->connect_error) {
        return ['success' => false, 'error' => 'Ошибка БД'];
    }
    $db->set_charset('utf8mb4');

    // Если user_id не передан — создаём/находим
    if ($user_id <= 0) {
        $user_id = resolveUserId($db);
    }

    // ============================================
    // ВЕТКА CUSTOM
    // ============================================
    if ($niche === 'custom') {
        $domain = 'custom' . uniqid();
        $clientPath = SITES_PATH . '/' . $domain;

        if (!mkdir($clientPath, 0755, true)) {
            $db->close();
            return ['success' => false, 'error' => "Не удалось создать папку: $clientPath"];
        }

        $templatesDir = $clientPath . '/templates';
        mkdir($templatesDir, 0755, true);
        copy(TEMPLATES_PATH . '/default/main.html', $templatesDir . '/main.html');

        symlink(CORE_PATH . '/index.php', $clientPath . '/index.php');
        symlink(CORE_PATH . '/send.php',  $clientPath . '/send.php');
        symlink(CORE_PATH . '/admin.php', $clientPath . '/admin.php');

        if (!createContentTable($db, $domain)) {
            $db->close();
            return ['success' => false, 'error' => 'Ошибка создания таблицы контента: ' . $db->error];
        }

        if (!createLeadsTable($db, $domain)) {
            $db->close();
            return ['success' => false, 'error' => 'Ошибка создания таблицы лидов: ' . $db->error];
        }

        $stmt = $db->prepare("INSERT INTO sites (user_id, domain, niche, content_table, status) VALUES (?, ?, 'custom', ?, 'active')");
        $stmt->bind_param('iss', $user_id, $domain, $domain);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("INSERT INTO leads (phone, niche, domain, product, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssss', $phone, $niche, $domain, $product);
        $stmt->execute();
        $stmt->close();

        $db->close();

        return [
            'success'    => true,
            'domain'     => $domain,
            'url'        => "https://multisitex.com/sites/$domain/",
            'admin_url'  => "https://multisitex.com/sites/$domain/admin.php",
            'niche'      => $niche,
            'user_id'    => $user_id,
            'message'    => "✅ Сайт $domain создан (custom)",
        ];
    }

    // ============================================
    // ВЕТКА ОБЫЧНОЙ НИШИ
    // ============================================
    $stmt = $db->prepare("SELECT template_path FROM niches WHERE name = ?");
    $stmt->bind_param('s', $niche);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $db->close();
        return ['success' => false, 'error' => "Ниша '$niche' не найдена"];
    }

    $templatePath = rtrim($row['template_path'], '/') . '/';
    if (!is_dir($templatePath)) {
        $db->close();
        return ['success' => false, 'error' => "Папка шаблона не найдена: $templatePath"];
    }

    // Поиск свободного номера
    $existingDomains = [];
    $stmt = $db->prepare("SELECT domain FROM sites WHERE niche = ?");
    $stmt->bind_param('s', $niche);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $existingDomains[] = $r['domain'];
    }
    $stmt->close();

    $maxNumber = 0;
    foreach ($existingDomains as $d) {
        if (strpos($d, $niche) === 0) {
            $n = (int)substr($d, strlen($niche));
            if ($n > $maxNumber) $maxNumber = $n;
        }
    }

    $nextNumber = $maxNumber + 1;
    $domain = $niche . $nextNumber;
    $clientPath = SITES_PATH . '/' . $domain;

    $attempts = 0;
    while (file_exists($clientPath) || in_array($domain, $existingDomains, true)) {
        $attempts++;
        if ($attempts > 100) {
            $db->close();
            return ['success' => false, 'error' => 'Не удалось сгенерировать уникальное имя'];
        }
        $nextNumber++;
        $domain = $niche . $nextNumber;
        $clientPath = SITES_PATH . '/' . $domain;
    }

    if (!mkdir($clientPath, 0755, true)) {
        $db->close();
        return ['success' => false, 'error' => "Не удалось создать папку: $clientPath"];
    }

    if (!copyDir($templatePath, $clientPath . '/templates')) {
        $db->close();
        return ['success' => false, 'error' => 'Ошибка копирования шаблона'];
    }

    foreach (['index.php', 'send.php', 'admin.php'] as $f) {
        $target = CORE_PATH . '/' . $f;
        $link   = $clientPath . '/' . $f;
        if (file_exists($link)) unlink($link);
        if (!@symlink($target, $link)) {
            file_put_contents($link, '<?php require_once __DIR__ . "/../../core/' . $f . '"; ?>');
        }
    }

    $settings = "<?php\n"
        . "define('SITE_DOMAIN', '" . $domain . "');\n"
        . "define('SITE_NICHE', '" . $niche . "');\n"
        . "define('CONTENT_TABLE', '" . $domain . "');\n"
        . "return [\n"
        . "    'domain' => '" . $domain . "',\n"
        . "    'niche' => '" . $niche . "',\n"
        . "    'content_table' => '" . $domain . "',\n"
        . "];\n";
    file_put_contents($clientPath . '/settings.php', $settings);

    if (!createContentTable($db, $domain)) {
        $db->close();
        return ['success' => false, 'error' => 'Ошибка создания таблицы контента: ' . $db->error];
    }

    $templateTable = 'template_' . $niche;
    if (!$db->query("INSERT INTO `$domain` SELECT * FROM `$templateTable`")) {
        $db->close();
        return ['success' => false, 'error' => 'Ошибка копирования шаблона контента: ' . $db->error];
    }

    $stmt = $db->prepare("INSERT INTO settings (domain, phone, whatsapp) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $domain, $phone, $phone);
    $stmt->execute();
    $stmt->close();

    $templateId = 1;
    $status = 'active';
    $stmt = $db->prepare("INSERT INTO sites (user_id, domain, niche, template_id, content_table, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ississ', $user_id, $domain, $niche, $templateId, $domain, $status);
    $stmt->execute();
    $stmt->close();

    if (!createLeadsTable($db, $domain)) {
        $db->close();
        return ['success' => false, 'error' => 'Ошибка создания таблицы лидов: ' . $db->error];
    }

    $stmt = $db->prepare("INSERT INTO leads (phone, niche, domain, product, created_at) VALUES (?, ?, ?, '', NOW())");
    $stmt->bind_param('sss', $phone, $niche, $domain);
    $stmt->execute();
    $stmt->close();

    $db->close();

    exec('chown -R nginx:nginx ' . escapeshellarg($clientPath));
    exec('chmod -R 755 ' . escapeshellarg($clientPath));

    return [
        'success'    => true,
        'domain'     => $domain,
        'url'        => "https://multisitex.com/sites/$domain/",
        'admin_url'  => "https://multisitex.com/sites/$domain/admin.php",
        'niche'      => $niche,
        'user_id'    => $user_id,
        'message'    => "✅ Сайт $domain создан для ниши $niche",
    ];
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================
function createContentTable(mysqli $db, string $table): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `$table` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            keyword VARCHAR(255),
            type TINYINT DEFAULT 1,
            code TINYINT DEFAULT 1,
            content TEXT,
            image VARCHAR(255) DEFAULT '',
            video VARCHAR(255) DEFAULT '',
            button TINYINT DEFAULT 0,
            link VARCHAR(255) DEFAULT '',
            visit INT DEFAULT 0,
            lead INT DEFAULT 0,
            conv DECIMAL(5,2) DEFAULT 0,
            sort INT DEFAULT 0,
            active TINYINT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    return (bool)$db->query($sql);
}

function createLeadsTable(mysqli $db, string $domain): bool
{
    $table = 'leads_' . $domain;
    $sql = "
        CREATE TABLE IF NOT EXISTS `$table` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20),
            name VARCHAR(255),
            email VARCHAR(255),
            region VARCHAR(100),
            keyword VARCHAR(255),
            source VARCHAR(100),
            product VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    return (bool)$db->query($sql);
}

function copyDir(string $src, string $dst): bool
{
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;
        $s = $src . '/' . $file;
        $d = $dst . '/' . $file;
        if (is_dir($s)) {
            if (!copyDir($s, $d)) return false;
        } else {
            if (!copy($s, $d)) return false;
        }
    }
    return true;
}

// ============================================
// ТОЧКА ВХОДА (HTTP)
// ============================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();

    $db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
    if ($db->connect_error) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
        exit;
    }
    $db->set_charset('utf8mb4');

    $user_id = resolveUserId($db);
    $db->close();

    $data = [
        'niche'   => $_POST['niche']   ?? '',
        'phone'   => $_POST['phone']   ?? '',
        'product' => $_POST['product'] ?? '',
        'user_id' => $user_id,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(createSite($data), JSON_UNESCAPED_UNICODE);
}
