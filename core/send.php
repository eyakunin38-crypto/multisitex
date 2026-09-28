<?php
// ============================================
// send.php — обработчик заявок + страница спасибо
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

$phone = $_POST['phone'] ?? '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$region = $_POST['region'] ?? '';
$keyword = $_POST['keyword'] ?? '';
$source = $_POST['source'] ?? '';
$product = $_POST['product'] ?? '';

$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#/sites/([^/]+)/#', $uri, $match)) {
    $domain = $match[1];
    $table = 'leads_' . $domain;
} else {
    $domain = $_SERVER['HTTP_HOST'];
    $table = 'leads';
}

if (empty($phone)) {
    die("❌ Телефон обязателен");
}

$db = new mysqli('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if ($db->connect_error) {
    die("❌ Ошибка БД: " . $db->connect_error);
}
$db->set_charset("utf8mb4");

// Сохраняем лид
$stmt = $db->prepare("
    INSERT INTO `$table` (phone, name, email, region, keyword, source, product, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("sssssss", $phone, $name, $email, $region, $keyword, $source, $product);
$stmt->execute();
$stmt->close();

// Обновляем счётчик лидов
if (!empty($keyword)) {
    $contentTable = $domain;
    $db->query("UPDATE `$contentTable` SET lead = lead + 1 WHERE keyword = '$keyword'");
}

$db->close();

// ============================================
// СТРАНИЦА БЛАГОДАРНОСТИ (вшита в код)
// ============================================
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Спасибо за заявку!</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .card {
            background: white;
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            max-width: 500px;
        }
        h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        p {
            font-size: 16px;
            color: #555;
            margin-bottom: 24px;
        }
        .btn {
            background: #1a1a1a;
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #333;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>✅ Спасибо!</h1>
        <p>Ваша заявка принята. Мы свяжемся с вами в ближайшее время.</p>
        <a href="/sites/<?= $domain ?>/" class="btn">Вернуться на сайт</a>
    </div>
</body>
</html>
<?php
exit;
?>
