<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();

$dbCfg = require '/var/www/multisitex.com/config/db.php';
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
$db->set_charset('utf8mb4');

$error = '';

// ===== Сообщение по reason =====
$reason = $_GET['reason'] ?? '';
$notice = '';

if ($reason === 'exists') {
    $notice = 'Этот email уже зарегистрирован. Войдите.';
} elseif ($reason === 'verified') {
    $notice = 'Этот email уже активирован. Войдите.';
}

// ===== Предзаполнение email из ?email= =====
$email_prefill = trim($_GET['email'] ?? '');

$redirect = trim($_POST['redirect'] ?? $_GET['redirect'] ?? '/');

if ($redirect === '' || $redirect[0] !== '/') {
    $redirect = '/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email.';
    } else {
        $stmt = $db->prepare("SELECT id, password, email_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Пользователь не найден.';
        } elseif ((int)$user['email_verified'] !== 1) {
            $error = 'Email не подтверждён. Проверьте почту.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Неверный пароль.';
        } else {
            $_SESSION['user_id'] = (int)$user['id'];
            session_regenerate_id(true);

            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            if ($redirect === '/' || $redirect === '/index.php') {
                $userId = (int)$user['id'];
                $stmt2 = $db->prepare("SELECT domain FROM sites WHERE user_id = ? ORDER BY id ASC LIMIT 1");
                $stmt2->bind_param('i', $userId);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $firstSite = $res2 ? $res2->fetch_assoc() : null;
                $stmt2->close();

                if ($firstSite && !empty($firstSite['domain'])) {
                    $redirect = '/sites/' . $firstSite['domain'] . '/admin.php';
                } else {
                    $redirect = '/';
                }
            }

            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Multisitex</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f7fa;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.02);
            max-width: 420px;
            width: 100%;
            padding: 40px 32px;
        }
        h2 {
            font-size: 26px;
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
        .form-group { margin-bottom: 24px; }
        .form-label {
            font-weight: 400;
            color: #555;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }
        .form-control {
            padding: 12px 0;
            border: none;
            border-bottom: 1px solid #eee;
            background: transparent;
            font-size: 15px;
            width: 100%;
            outline: none;
            font-family: inherit;
        }
        .form-control:focus { border-color: #111; }
        .btn {
            background: #111;
            border: none;
            padding: 14px 20px;
            font-size: 15px;
            color: #fff;
            width: 100%;
            cursor: pointer;
            margin-top: 16px;
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
        .notice {
            background: #eff6ff;
            color: #1e40af;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Вход</h2>
    <p class="subtitle">Войдите в админку сайта</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!empty($notice)): ?>
        <div class="notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required
                   value="<?= htmlspecialchars($_POST['email'] ?? $email_prefill, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn">Войти</button>
    </form>
</div>
</body>
</html>
