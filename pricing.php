<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();

// Загружаем тарифы
$tariffs = require '/var/www/multisitex.com/config/tariffs.php';

$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'ru';
setcookie('lang', $lang, time() + 86400 * 365, '/');

$text = [
    'ru' => [
        'title'       => 'Тарифы — MultisiteX',
        'h2'          => 'Тарифы',
        'sub'         => 'Начните бесплатно. Перейдите на «Старт», когда будете готовы.',
        'free_title'  => 'Бесплатно',
        'free_price'  => '0 ₽',
        'free_period' => 'навсегда',
        'free_features' => [
            'Создание сайтов за минуту',
            'До 5 сайтов',
            'Подмена заголовков под ключевик',
            'Передача региона лида',
            'MultisiteX CRM — лиды со всех сайтов в одном месте',
            'Сбор заявок',
            'Админка для каждого сайта',
        ],
        'free_btn'    => 'Создать сайт бесплатно',
        'start_title' => 'Старт',
        'start_period'=> 'в месяц',
        'start_features' => [
            'Всё из «Бесплатно»',
            'Убрать плашку MultisiteX',
            'Свои картинки',
            'Настройка дизайна шаблона',
            'Своя Яндекс.Метрика',
            'UTM-метки для рекламы',
            'Готовые ссылки для рекламных кампаний',
            'Расширенная подмена контента',
            'Гео-подмена контента под регион',
            'MultisiteX CRM — расширенная (экспорт, фильтры, статусы)',
            'Аналитика по сайтам',
            'Приоритетная поддержка',
        ],
        'start_btn'   => 'Активировать Старт',
        'business_title' => 'Бизнес',
        'business_period'=> 'в месяц',
        'business_features' => [
            'Всё из «Старт»',
            'Настройка под ключ (шаблон, контент, подмены)',
            'Привязка своего домена',
            'Настройка UTM-подмен под клиента',
            'Настройка телефона, WhatsApp, почты',
            'Первичная настройка аналитики (Метрика с целями)',
            'Приоритетная поддержка (ответ за час)',
            'Персональный менеджер',
        ],
        'business_btn' => 'Активировать Бизнес',
        'back'        => '← На главную',
    ],
    'en' => [
        'title'       => 'Pricing — MultisiteX',
        'h2'          => 'Pricing',
        'sub'         => 'Start free. Upgrade to Start when ready.',
        'free_title'  => 'Free',
        'free_price'  => '0 ₽',
        'free_period' => 'forever',
        'free_features' => [
            'Create sites in a minute',
            'Up to 5 sites',
            'Title substitution by keyword',
            'Lead region detection',
            'MultisiteX CRM — leads from all sites in one place',
            'Lead collection',
            'Admin panel for each site',
        ],
        'free_btn'    => 'Create site for free',
        'start_title' => 'Start',
        'start_period'=> 'per month',
        'start_features' => [
            'Everything in Free',
            'Remove MultisiteX badge',
            'Your own images',
            'Template design customization',
            'Your own Yandex.Metrika',
            'UTM tags for ads',
            'Ready-made links for ad campaigns',
            'Extended content substitution',
            'Geo content substitution by region',
            'MultisiteX CRM — extended (export, filters, statuses)',
            'Analytics per site',
            'Priority support',
        ],
        'start_btn'   => 'Activate Start',
        'business_title' => 'Business',
        'business_period'=> 'per month',
        'business_features' => [
            'Everything in Start',
            'Turnkey setup (template, content, substitutions)',
            'Your own domain binding',
            'UTM substitution setup for client',
            'Phone, WhatsApp, email setup',
            'Initial analytics setup (Metrika with goals)',
            'Priority support (response within an hour)',
            'Personal manager',
        ],
        'business_btn' => 'Activate Business',
        'back'        => '← Back to home',
    ]
];

$lang_data = $text[$lang] ?? $text['ru'];

// Цены из конфига
$startPrice    = number_format($tariffs['start']['price'], 0, '.', ' ') . ' ₽';
$businessPrice = number_format($tariffs['business']['price'], 0, '.', ' ') . ' ₽';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lang_data['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f7fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h2 {
            font-size: 36px;
            font-weight: 300;
            color: #111;
            text-align: center;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        .subtitle {
            font-size: 15px;
            color: #888;
            text-align: center;
            margin-bottom: 40px;
        }
        .plans {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .plan {
            background: #fff;
            border-radius: 20px;
            padding: 32px 28px;
            flex: 1;
            min-width: 280px;
            max-width: 360px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.04);
        }
        .plan.highlight {
            border: 2px solid #111;
        }
        .plan h3 {
            font-size: 22px;
            font-weight: 500;
            color: #111;
            margin-bottom: 4px;
        }
        .price {
            font-size: 32px;
            font-weight: 300;
            color: #111;
            margin: 12px 0 4px;
        }
        .period {
            font-size: 13px;
            color: #888;
            margin-bottom: 24px;
        }
        .features {
            list-style: none;
            margin-bottom: 28px;
        }
        .features li {
            font-size: 14px;
            color: #444;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .features li:last-child { border-bottom: none; }
        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 400;
            text-align: center;
            text-decoration: none;
            border-radius: 0;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
        }
        .btn-free {
            background: transparent;
            color: #111;
            border: 1px solid #111;
        }
        .btn-free:hover { background: #f5f5f5; }
        .btn-start {
            background: #111;
            color: #fff;
            border: none;
        }
        .btn-start:hover { background: #333; }
        .back {
            text-align: center;
            margin-top: 40px;
        }
        .back a {
            color: #888;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #ddd;
        }
        .back a:hover { color: #111; border-bottom-color: #111; }
        .lang-switch {
            text-align: right;
            margin-bottom: 20px;
        }
        .lang-switch a {
            color: #aaa;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .lang-switch a.active { color: #111; background: #f0f0f0; }
        @media (max-width: 640px) {
            h2 { font-size: 28px; }
            .plan { padding: 24px 20px; }
        }
    </style>
    <!-- Jivo -->
    <script src="//code.jivo.ru/widget/jmf4AkbuEc" async></script>
</head>
<body>
<div class="container">
    <div class="lang-switch">
        <a href="?lang=ru" class="<?= $lang === 'ru' ? 'active' : '' ?>">Русский</a>
        <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">English</a>
    </div>

    <h2><?= htmlspecialchars($lang_data['h2'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="subtitle"><?= htmlspecialchars($lang_data['sub'], ENT_QUOTES, 'UTF-8') ?></p>

    <div class="plans">
        <!-- Бесплатный -->
        <div class="plan">
            <h3><?= htmlspecialchars($lang_data['free_title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="price"><?= htmlspecialchars($lang_data['free_price'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="period"><?= htmlspecialchars($lang_data['free_period'], ENT_QUOTES, 'UTF-8') ?></div>
            <ul class="features">
                <?php foreach ($lang_data['free_features'] as $f): ?>
                    <li><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="/" class="btn btn-free"><?= htmlspecialchars($lang_data['free_btn'], ENT_QUOTES, 'UTF-8') ?></a>
        </div>

        <!-- Старт -->
        <div class="plan highlight">
            <h3><?= htmlspecialchars($lang_data['start_title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="price"><?= htmlspecialchars($startPrice, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="period"><?= htmlspecialchars($lang_data['start_period'], ENT_QUOTES, 'UTF-8') ?></div>
            <ul class="features">
                <?php foreach ($lang_data['start_features'] as $f): ?>
                    <li><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="/login.php" class="btn btn-start"><?= htmlspecialchars($lang_data['start_btn'], ENT_QUOTES, 'UTF-8') ?></a>
        </div>

        <!-- Бизнес -->
        <div class="plan">
            <h3><?= htmlspecialchars($lang_data['business_title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="price"><?= htmlspecialchars($businessPrice, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="period"><?= htmlspecialchars($lang_data['business_period'], ENT_QUOTES, 'UTF-8') ?></div>
            <ul class="features">
                <?php foreach ($lang_data['business_features'] as $f): ?>
                    <li><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="/login.php" class="btn btn-start"><?= htmlspecialchars($lang_data['business_btn'], ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>

    <div class="back">
        <a href="/"><?= htmlspecialchars($lang_data['back'], ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</div>
<script src="//code.jivo.ru/widget/jmf4AkbuEc" async></script>

</body>
</html>
