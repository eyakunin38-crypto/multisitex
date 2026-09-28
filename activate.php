<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
session_start();

require '/var/www/multisitex.com/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================
// КОНФИГ
// ============================================
$dbCfgFile   = '/var/www/multisitex.com/config/db.php';
$mailCfgFile = '/var/www/multisitex.com/config/mail.php';

if (!file_exists($dbCfgFile) || !file_exists($mailCfgFile)) {
    http_response_code(500);
    exit('Config not found');
}

$dbCfg     = require $dbCfgFile;
$mailConfig = require $mailCfgFile;

$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
if ($db->connect_error) {
    http_response_code(500);
    exit('Ошибка БД');
}
$db->set_charset('utf8mb4');

$error   = '';
$user    = null;
$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');

// ============================================
// 1. ПРОВЕРКА ТОКЕНА
// ============================================
if ($token === '') {
    $error = 'Ссылка недействительна.';
} else {
    $stmt = $db->prepare("
        SELECT id, email, phone, email_verified, token_expires, pending_niche, pending_product
        FROM users
        WHERE email_token = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $error = 'Ссылка недействительна или уже использована.';
    } elseif ((int)$user['email_verified'] === 1) {
        $error = 'Этот email уже активирован. Войдите в админку.';
    } elseif (strtotime((string)$user['token_expires']) < time()) {
        $error = 'Ссылка истекла. Пройдите регистрацию заново.';
    }
}

// ============================================
// 2. ОБРАБОТКА ПАРОЛЯ
// ============================================
if ($user && $error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Пароль должен быть не менее 8 символов.';
    } elseif ($password !== $confirm) {
        $error = 'Пароли не совпадают.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Обновляем юзера
        $stmt = $db->prepare("
            UPDATE users
            SET password = ?, email_verified = 1, email_token = NULL, token_expires = NULL, last_login = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('si', $hash, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Вызов deploy.php
        require_once '/var/www/multisitex.com/api/deploy.php';

        $niche = $user['pending_niche'] ?: 'moduldom';

        $result = createSite([
            'niche'   => $niche,
            'phone'   => $user['phone']            ?: '',
            'product' => $user['pending_product'] ?: '',
            'user_id' => (int)$user['id'],
        ]);

        if (!$result['success']) {
            $error = 'Ошибка создания сайта: ' . ($result['error'] ?? 'неизвестно');
        } else {
            // Очищаем pending
            $stmt = $db->prepare("UPDATE users SET pending_niche = NULL, pending_product = NULL WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            // ===== УСТАНАВЛИВАЕМ СЕССИЮ =====
            $_SESSION['user_id'] = (int)$user['id'];
            session_regenerate_id(true);

            // Приветственное письмо
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
                $mail->addAddress($user['email']);
                $mail->Subject = 'Ваш сайт в Multisitex создан';
                $mail->Body = "Здравствуйте!\n\n"
                    . "Ваш сайт успешно создан.\n\n"
                    . "Админка: " . $result['admin_url'] . "\n"
                    . "Сайт: " . $result['url'] . "\n\n"
                    . "Multisitex";
                $mail->send();
            } catch (Exception $e) {
                error_log('Welcome mail error: ' . $mail->ErrorInfo);
            }

            // Редирект в админку
            header('Location: ' . $result['admin_url']);
            exit;
        }
    }
}

// ============================================
// РЕНДЕР
// ============================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Активация сайта — Multisitex</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f7fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.02);
            max-width: 480px;
            width: 100%;
            padding: 40px 32px;
        }
        h2 {
            font-size: 28px;
            font-weight: 300;
            color: #111;
            text-align: center;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-bottom: 28px;
        }
        .form-label {
            font-weight: 400;
            color: #555;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }
        .form-control {
            border-radius: 0;
            padding: 12px 0;
            border: none;
            border-bottom: 1px solid #eee;
            background: transparent;
            transition: border-color 0.2s;
            font-size: 15px;
            width: 100%;
            outline: none;
            font-family: inherit;
        }
        .form-control:focus { border-color: #111; }
        .form-group { margin-bottom: 24px; }
        .btn {
            background: #111;
            border: none;
            border-radius: 0;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 400;
            color: #fff;
            width: 100%;
            transition: background 0.3s;
            margin-top: 16px;
            cursor: pointer;
            font-family: inherit;
        }
        .btn:hover { background: #333; }
        .error {
            background: #fef2f2;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
        }
        .icon {
            font-size: 40px;
            text-align: center;
            display: block;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="card">
    <?php if ($error !== ''): ?>
        <span class="icon">⚠️</span>
        <h2>Не удалось активировать</h2>
        <p class="subtitle"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <span class="icon">🔐</span>
        <h2>Создайте пароль</h2>
        <p class="subtitle">Для входа в админку сайта</p>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label class="form-label">Пароль</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">Повторите пароль</label>
                <input type="password" name="password_confirm" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn">Активировать сайт</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
