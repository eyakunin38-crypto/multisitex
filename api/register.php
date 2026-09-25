<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

require '/var/www/multisitex.com/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===== Конфиг =====
$mailConfig = require '/var/www/multisitex.com/config/mail.php';

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
// Приводим к формату 7XXXXXXXXXX
$phoneClean = '7' . substr($phoneClean, 1);

// ===== БД =====
$db = new mysqli('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
if ($db->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД'], JSON_UNESCAPED_UNICODE);
    exit;
}
$db->set_charset('utf8mb4');

// ===== Проверка юзера по email =====
$stmt = $db->prepare("SELECT id, email_verified FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 86400); // 24 часа

if ($user) {
    if ((int)$user['email_verified'] === 1) {
        // Email уже подтверждён — уведомляем владельца
        $stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->bind_param('si', $phoneClean, $user['id']);
        $stmt->execute();

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
            $mail->Subject = 'Кто-то пытается использовать ваш email в Multisitex';
            $mail->Body    = "Здравствуйте!\n\n"
                . "Кто-то пытается создать сайт, используя ваш email.\n"
                . "Если это вы — войдите в свой кабинет.\n"
                . "Если нет — просто проигнорируйте это письмо.\n\n"
                . "Multisitex";
            $mail->send();
        } catch (Exception $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
        }

        echo json_encode(['success' => true, 'message' => 'Проверьте почту'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Не подтверждён — обновляем токен и pending-данные
    $stmt = $db->prepare("UPDATE users SET phone = ?, email_token = ?, token_expires = ?, pending_niche = ?, pending_product = ? WHERE id = ?");
    $stmt->bind_param('sssssi', $phoneClean, $token, $expires, $niche, $product, $user['id']);
    $stmt->execute();
} else {
    // Новый юзер — временный хэш пароля (NOT NULL в структуре)
    $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (email, phone, password, email_verified, email_token, token_expires, pending_niche, pending_product) VALUES (?, ?, ?, 0, ?, ?, ?, ?)");
    $stmt->bind_param('sssssss', $email, $phoneClean, $tempHash, $token, $expires, $niche, $product);
    $stmt->execute();
}

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
