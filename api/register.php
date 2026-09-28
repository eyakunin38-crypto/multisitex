<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();

require '/var/www/multisitex.com/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===== Конфиг =====
$dbCfgFile   = '/var/www/multisitex.com/config/db.php';
$mailCfgFile = '/var/www/multisitex.com/config/mail.php';

if (!file_exists($dbCfgFile) || !file_exists($mailCfgFile)) {
    echo json_encode(['success' => false, 'error' => 'Config not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dbCfg      = require $dbCfgFile;
$mailConfig = require $mailCfgFile;

// ===== Приём данных =====
$niche   = trim($_POST['niche']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$product = trim($_POST['product'] ?? '');
$lang    = trim($_POST['lang']    ?? 'ru');

// ===== Валидация =====
if ($niche === '') {
    echo json_encode(['success' => false, 'error' => 'Выберите нишу'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Некорректный email'], JSON_UNESCAPED_UNICODE);
    exit;
}
$phoneClean = preg_replace('/\D/', '', $phone);
if (!preg_match('/^(7|8)\d{10}$/', $phoneClean)) {
    echo json_encode(['success' => false, 'error' => 'Некорректный телефон'], JSON_UNESCAPED_UNICODE);
    exit;
}
$phoneClean = '7' . substr($phoneClean, 1);

// ===== БД =====
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД'], JSON_UNESCAPED_UNICODE);
    exit;
}
$db->set_charset('utf8mb4');

// ===== Получаем user_id из сессии / куки =====
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0 && !empty($_COOKIE['visitor_id'])) {
    $stmt = $db->prepare("SELECT id FROM users WHERE visitor_id = ?");
    $stmt->bind_param('s', $_COOKIE['visitor_id']);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($u) {
        $user_id = (int)$u['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

// ===== Проверка: email занят ДРУГИМ users? =====
$stmt = $db->prepare("SELECT id, email_verified FROM users WHERE email = ? AND id != ? LIMIT 1");
$stmt->bind_param('si', $email, $user_id);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($other) {
    // Email занят другим пользователем
    echo json_encode([
        'success'  => false,
        'redirect' => '/login.php?email=' . urlencode($email) . '&reason=exists',
        'error'    => 'Этот email уже зарегистрирован. Войдите.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Токен =====
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 86400); // 24 часа

// ===== Логика =====
if ($user_id > 0) {
    // Есть текущий users — привязываем email к нему
    $stmt = $db->prepare("
        UPDATE users
        SET email = ?, phone = ?, email_token = ?, token_expires = ?,
            pending_niche = ?, pending_product = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ssssssi', $email, $phoneClean, $token, $expires, $niche, $product, $user_id);
    $stmt->execute();
    $stmt->close();
} else {
    // Fallback — нет сессии, нет куки
    // Создаём нового users (не должно происходить, если deploy.php работал)
    $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO users (email, phone, password, email_verified, email_token, token_expires, pending_niche, pending_product)
        VALUES (?, ?, ?, 0, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssss', $email, $phoneClean, $tempHash, $token, $expires, $niche, $product);
    $stmt->execute();
    $user_id = (int)$db->insert_id;
    $stmt->close();

    $_SESSION['user_id'] = $user_id;
}

$db->close();

// ===== Письмо активации =====
$link = "https://multisitex.com/activate.php?token=" . urlencode($token);
$body = "Здравствуйте!\n\n"
      . "Вы начали создание сайта в Multisitex.\n"
      . "Чтобы завершить — перейдите по ссылке:\n\n"
      . "$link\n\n"
      . "Ссылка действует 24 часа.\n\n"
      . "Если вы не запрашивали — просто проигнорируйте это письмо.\n\n"
      . "Multisitex";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $mailConfig['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $mailConfig['username'];
    $mail->Password   = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $mailConfig['port'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
    $mail->addAddress($email);
    $mail->Subject = 'Активация сайта в Multisitex';
    $mail->Body    = $body;
    $mail->send();
} catch (Exception $e) {
    error_log('Mail error: ' . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'error' => 'Не удалось отправить письмо'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Проверьте почту'], JSON_UNESCAPED_UNICODE);
