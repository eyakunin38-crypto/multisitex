<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата не прошла — Multisitex</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f7fa;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .icon { font-size: 48px; display: block; margin-bottom: 12px; }
        h1 { font-size: 26px; font-weight: 500; color: #1a1a1a; margin-bottom: 8px; }
        p { font-size: 15px; color: #555; margin-bottom: 24px; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: #1a1a1a;
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
        }
        .btn:hover { background: #333; }
    </style>
</head>
<body>
    <div class="card">
        <span class="icon">⚠️</span>
        <h1>Оплата не прошла</h1>
        <p>Попробуйте ещё раз или свяжитесь с нами.</p>
        <a href="https://multisitex.com/" class="btn">На главную</a>
    </div>
</body>
</html>
