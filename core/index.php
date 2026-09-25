<?php
// ============================================
// ЯДРО МУЛЬТИСАЙТА — С ШАБЛОНИЗАТОРОМ tc.php
// ============================================
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$connect = mysqli_connect('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if (!$connect) {
    die("Ошибка БД: " . mysqli_connect_error());
}
mysqli_set_charset($connect, "utf8mb4");

// ============================================
// 2. ОПРЕДЕЛЯЕМ РЕГИОН ПО IP
// ============================================
include __DIR__ . '/region.php';

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$region = function_exists('return_region')
    ? return_region($ip)
    : '';

// ============================================
// 3. ОПРЕДЕЛЯЕМ САЙТ ПО URL (поддержка /sites/)
// ============================================
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#/sites/([^/]+)/#', $uri, $match)) {
    $domain = $match[1];
} else {
    $domain = $_SERVER['HTTP_HOST'];
}

$result = mysqli_query($connect, "
    SELECT
        s.content_table,
        s.niche,
        s.template_id,
        t.template_name,
        st.phone,
        st.whatsapp,
        s.tariff
    FROM sites s
    LEFT JOIN settings st ON s.domain = st.domain
    LEFT JOIN templates t ON s.template_id = t.id
    WHERE s.domain = '$domain' AND s.status = 'active'
");
$site = mysqli_fetch_assoc($result);

if (!$site) {
    http_response_code(404);
    die("❌ Сайт не найден: $domain");
}

// ============================================
// 4. ПОЛУЧАЕМ КОНТЕНТ
// ============================================
$table = $site['content_table'];
$content = [];
$res = mysqli_query($connect, "
    SELECT id, type, code, content, image, video, keyword
    FROM `$table`
    WHERE active = 1
    ORDER BY type, code
");
while ($row = mysqli_fetch_assoc($res)) {
    $content[$row['type']][$row['code']] = $row;
}

// ============================================
// 5. ПАРАМЕТРЫ URL
// ============================================
$header = $content[1][1]['content'] ?? 'Заголовок';
$image = $content[4][1]['image'] ?? '';
$video = $content[5][1]['video'] ?? '';
$button = $content[3][1]['content'] ?? 'Кнопка';

// ============================================
// КЛЮЧЕВОЕ СЛОВО — ТОЛЬКО ИЗ utm_term (с urldecode)
// ============================================
if (isset($_GET['utm_term']) && !empty($_GET['utm_term'])) {
    $keyword = urldecode($_GET['utm_term']);
} else {
    $keyword = '';
}

// --- НОВАЯ ЛОГИКА: Поиск по utm_term (создание новой записи, если нет) ---
if (isset($_GET['utm_term']) && !empty($_GET['utm_term'])) {
    $utm_term = urldecode($_GET['utm_term']);
    $utm_term_escaped = mysqli_real_escape_string($connect, $utm_term);

    $stmt = mysqli_query($connect, "SELECT * FROM `$table` WHERE keyword = '$utm_term_escaped' AND type = 1 LIMIT 1");
    if ($row = mysqli_fetch_assoc($stmt)) {
        $header = $row['content'];
        $keyword = $row['keyword'];
        $btn_stmt = mysqli_query($connect, "SELECT content FROM `$table` WHERE keyword = '$utm_term_escaped' AND type = 3 LIMIT 1");
        if ($btn_row = mysqli_fetch_assoc($btn_stmt)) {
            $button = $btn_row['content'];
        }
    } else {
        $maxCode = mysqli_fetch_assoc(mysqli_query($connect, "SELECT MAX(code) as max FROM `$table` WHERE type = 1"))['max'] ?? 0;
        $newCode = $maxCode + 1;
        mysqli_query($connect, "INSERT INTO `$table` (keyword, type, code, content) VALUES ('$utm_term_escaped', 1, $newCode, '$utm_term_escaped')");
        $header = $utm_term;
        $keyword = $utm_term;
    }
}

// --- Старая логика: поиск по h, b, i, v ---
if (isset($_GET['h'])) {
    $code = (int)$_GET['h'];
    if (isset($content[1][$code])) {
        $header = $content[1][$code]['content'];
        $keyword = $content[1][$code]['keyword'] ?? '';
    }
}
if (isset($_GET['b']) && isset($content[3][(int)$_GET['b']])) {
    $button = $content[3][(int)$_GET['b']]['content'];
}
if (isset($_GET['i']) && isset($content[4][(int)$_GET['i']])) {
    $image = $content[4][(int)$_GET['i']]['image'];
}
if (isset($_GET['v']) && isset($content[5][(int)$_GET['v']])) {
    $video = $content[5][(int)$_GET['v']]['video'];
}

// ============================================
// 6. ТЕГИ ДЛЯ ШАБЛОНИЗАТОРА
// ============================================
$tags = [
    'Region' => $region,
    'Header' => $header,
    'Image' => $image,
    'button' => $button,
    'video' => $video,
    'Keyword' => $keyword,
    'Phone' => $site['phone'] ?? '79882447774',
    'Whatsapp' => $site['whatsapp'] ?? '79531107773',
    'Source' => $_GET['utm_source'] ?? 'прямой заход',
    'Url' => "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    'Codeheader' => '1',
    'header_1' => $header,
    'phone' => $site['phone'] ?? '79882447774',
    'whatsapp' => $site['whatsapp'] ?? '79531107773',
    'image_1' => $image,
    'video_1' => $video,
    'button_1' => $button,
];

// ============================================
// 7. ПОДКЛЮЧАЕМ ШАБЛОНИЗАТОР
// ============================================
require_once __DIR__ . '/tc.php';

$tc = new tc();
$tc->domain = $domain;
$tc->templateName = 'main';
$tc->content = $tags;

$html = $tc->Fill();

// ============================================
// 8. ПЛАШКА — автоматически перед </body>, только на тарифе test
// ============================================
if (($site['tariff'] ?? '') === 'test') {
    $site_domain = $domain; // домен клиентского сайта (не multisitex.com)
    $utm_url = 'https://multisitex.com/?utm_source=' . urlencode($site_domain)
             . '&utm_medium=badge&utm_campaign=free_site';

    $badge = '<div class="msx-badge-wrap">'
           . '<a href="' . htmlspecialchars($utm_url) . '" target="_blank" rel="noopener" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#555;">'
           . '<span>Сайт создан на</span>'
           . '<img src="https://multisitex.com/assets/img/logo.png" alt="Multisitex" style="height:22px;width:auto;">'
           . '</a>'
           . '</div>'
           . '<style>.msx-badge-wrap{text-align:center;padding:10px 0;}'
           . '.msx-badge-wrap img{display:inline-block;vertical-align:middle;}'
           . '@media(max-width:600px){.msx-badge-wrap img{height:16px;}}</style>';

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $badge . '</body>', $html, 1);
    } else {
        $html .= $badge;
    }
}

echo $html;

mysqli_close($connect);
?>
