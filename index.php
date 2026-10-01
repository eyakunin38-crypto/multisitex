<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();

// ===== Определение «новый/постоянный» + email =====
$isReturning = false;
$hasEmail    = false;

if (!empty($_COOKIE['visitor_id'])) {
    $dbCfgFile = '/var/www/multisitex.com/config/db.php';
    if (file_exists($dbCfgFile)) {
        $dbCfg = require $dbCfgFile;
        $dbCheck = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
        if (!$dbCheck->connect_error) {
            $dbCheck->set_charset('utf8mb4');
            $stmt = $dbCheck->prepare("SELECT id, email FROM users WHERE visitor_id = ?");
            $stmt->bind_param('s', $_COOKIE['visitor_id']);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $dbCheck->close();

            if ($u) {
                $isReturning = true;
                $hasEmail    = !empty($u['email']);
            }
        }
    }
}

$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'ru';
setcookie('lang', $lang, time() + 86400 * 365, '/');

$text = [
    'ru' => [
        'title'             => 'Создать мультисайт',
        'h2'                => 'Создать сайт за минуту',
        'sub'               => 'Выберите сферу и получите готовый лендинг',
        'niche_label'       => 'Сфера бизнеса',
        'select_default'    => '-- выберите --',
        'custom_option'     => 'Другая сфера (создадим под вас)',
        'product_label'     => 'Что продаете?',
        'product_placeholder' => 'Например: Шкафы-купе на заказ',
        'phone_label'       => 'Ваш телефон',
        'phone_placeholder' => '+7 (___) ___-__-__',
        'btn'               => 'Создать сайт для рекламы',
        'creating'          => 'Создаём...',
        'error'             => 'Ошибка',
        'email_title'       => 'Куда прислать доступ?',
        'email_sub'         => 'Мы отправим ссылку для активации сайта',
        'email_label'       => 'Ваш email',
        'email_placeholder' => 'you@example.com',
        'email_btn'         => 'Активировать сайт',
        'email_sending'     => 'Активируем...',
        'email_invalid'     => 'Введите корректный email',
        'email_error'       => 'Не удалось активировать. Попробуйте позже.',
        'check_title'       => 'Проверьте почту',
        'check_sub'         => 'Мы отправили ссылку активации на',
        'check_hint'        => 'Перейдите по ссылке в письме, чтобы завершить создание сайта. Ссылка действует 24 часа.',
        'already_account'   => 'Уже есть аккаунт? Войти',
        'cancel'            => 'Отмена',
        'pricing_link'      => 'Тарифы',
        'site_ready'        => 'Сайт создан!',
        'site_sub'          => 'Ссылка на сайт:',
        'site_admin'        => 'Админка сайта:',
        'copy_btn'          => 'Копировать',
        'copied'            => 'Скопировано!',
        'open_btn'          => 'Открыть',
        'adaptive_btn'      => 'Проверить адаптивность',
        'save_email_title'  => 'Хотите получать заявки?',
        'save_email_sub'    => 'Укажите email — будем присылать заявки с сайта',
        'save_email_btn'    => 'Сохранить',
        'skip_email'        => 'Пропустить',
        'go_admin_btn'      => 'Перейти в админку',
        'create_more_btn'   => 'Создать ещё сайт',
    ],
    'en' => [
        'title'             => 'Create multisite',
        'h2'                => 'Create website in 1 minute',
        'sub'               => 'Choose your niche and get a ready-made landing page',
        'niche_label'       => 'Business area',
        'select_default'    => '-- select --',
        'custom_option'     => 'Another area (create for you)',
        'product_label'     => 'What do you offer?',
        'product_placeholder' => 'e.g. Custom wardrobes',
        'phone_label'       => 'Your phone',
        'phone_placeholder' => '+7 (___) ___-__-__',
        'btn'               => 'Create site for ads',
        'creating'          => 'Creating...',
        'error'             => 'Error',
        'email_title'       => 'Where to send access?',
        'email_sub'         => 'We will send a link to activate your site',
        'email_label'       => 'Your email',
        'email_placeholder' => 'you@example.com',
        'email_btn'         => 'Activate site',
        'email_sending'     => 'Activating...',
        'email_invalid'     => 'Enter a valid email',
        'email_error'       => 'Could not activate. Try again later.',
        'check_title'       => 'Check your email',
        'check_sub'         => 'We sent an activation link to',
        'check_hint'        => 'Follow the link in the email to finish creating your site. The link is valid for 24 hours.',
        'already_account'   => 'Already have an account? Log in',
        'cancel'            => 'Cancel',
        'pricing_link'      => 'Pricing',
        'site_ready'        => 'Site created!',
        'site_sub'          => 'Site link:',
        'site_admin'        => 'Site admin:',
        'copy_btn'          => 'Copy',
        'copied'            => 'Copied!',
        'open_btn'          => 'Open',
        'adaptive_btn'      => 'Check adaptability',
        'save_email_title'  => 'Want to receive leads?',
        'save_email_sub'    => 'Enter email — we will send leads from your site',
        'save_email_btn'    => 'Save',
        'skip_email'        => 'Skip',
        'go_admin_btn'      => 'Go to admin',
        'create_more_btn'   => 'Create another site',
    ]
];

$dbCfg = require '/var/www/multisitex.com/config/db.php';
$db = new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
$niches = [];
if (!$db->connect_error) {
    $db->set_charset('utf8mb4');
    $result = $db->query("SELECT name, title FROM niches ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $niches[] = $row;
    }
}
$db->close();

$lang_data = $text[$lang] ?? $text['ru'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lang_data['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .hidden { display: none !important; }
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
            border: none;
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
        .form-select, .form-control {
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
        .form-select:focus, .form-control:focus {
            border-color: #111;
            box-shadow: none;
        }
        .btn-create {
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
        .btn-create:hover { background: #333; }
        .btn-create:disabled { background: #999; cursor: not-allowed; }
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
        .lang-switch a:hover:not(.active) { background: #f5f5f5; }
        .login-link {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
        }
        .login-link a {
            color: #888;
            text-decoration: none;
            border-bottom: 1px solid #ddd;
        }
        .login-link a:hover { color: #111; border-bottom-color: #111; }
        .check-block { text-align: center; padding: 10px 0; }
        .check-block .icon-big {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
        }
        .check-block .email {
            font-weight: 600;
            color: #111;
            margin: 8px 0 16px;
            word-break: break-all;
            font-size: 15px;
        }
        .check-block .hint {
            font-size: 13px;
            color: #888;
            line-height: 1.5;
        }
        .link-row {
            display: flex;
            align-items: center;
            background: transparent;
            border-bottom: 1px solid #eee;
            padding: 8px 4px 8px 0;
            margin-bottom: 12px;
        }
        .link-row .url {
            font-size: 14px;
            color: #111;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            flex: 1;
        }
        .link-row .btn-copy {
            background: transparent;
            border: none;
            padding: 6px;
            cursor: pointer;
            color: #555;
            font-size: 12px;
        }
        .link-row .btn-open {
            background: transparent;
            color: #111;
            padding: 6px 0;
            font-size: 13px;
            text-decoration: none;
            border-bottom: 1px solid #111;
        }
        .btn-adaptive {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #eee;
            font-size: 14px;
            color: #111;
            text-decoration: none;
            background: transparent;
            cursor: pointer;
            margin-top: 12px;
            font-family: inherit;
        }
        .btn-secondary-custom {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #eee;
            font-size: 14px;
            color: #111;
            text-decoration: none;
            background: transparent;
            cursor: pointer;
            margin-top: 12px;
            font-family: inherit;
            text-align: center;
        }
        .btn-secondary-custom:hover { background: #f9f9f9; }
        .modal-custom {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1050;
        }
        .modal-card {
            background: #fff;
            border-radius: 20px;
            max-width: 420px;
            width: 100%;
            padding: 36px 28px;
        }
        .modal-card h3 {
            font-size: 22px;
            font-weight: 300;
            color: #111;
            text-align: center;
            margin-bottom: 4px;
        }
        .modal-card .modal-sub {
            font-size: 13px;
            color: #888;
            text-align: center;
            margin-bottom: 24px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .modal-actions .btn-cancel {
            background: transparent;
            border: 1px solid #eee;
            padding: 12px 20px;
            font-size: 14px;
            color: #555;
            cursor: pointer;
            font-family: inherit;
            flex: 1;
        }
        .modal-actions .btn-submit {
            background: #111;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            flex: 1;
        }
        .modal-actions .btn-submit:hover { background: #333; }
        .modal-actions .btn-submit:disabled { background: #999; cursor: not-allowed; }
        .modal-error {
            color: #b91c1c;
            font-size: 13px;
            text-align: center;
            margin-top: 12px;
            min-height: 18px;
        }
        @media (max-width: 480px) {
            .card { padding: 24px 20px; }
            h2 { font-size: 22px; }
        }
    </style>
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=112304158', 'ym');

        ym(112304158, 'init', {
            ssr: true,
            webvisor: true,
            clickmap: true,
            referrer: document.referrer,
            url: location.href,
            accurateTrackBounce: true,
            trackLinks: true
        });
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/112304158" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <script src="//code.jivo.ru/widget/jmf4AkbuEc" async></script>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="lang-switch">
            <a href="?lang=ru" class="<?= $lang === 'ru' ? 'active' : '' ?>">Русский</a>
            <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">English</a>
        </div>

        <div id="formBlock">
            <h2><?= htmlspecialchars($lang_data['h2'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="subtitle"><?= htmlspecialchars($lang_data['sub'], ENT_QUOTES, 'UTF-8') ?></p>

            <form id="deployForm">
                <div class="mb-4">
                    <label class="form-label"><?= htmlspecialchars($lang_data['niche_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="niche" class="form-select" id="nicheSelect" required>
                        <option value=""><?= htmlspecialchars($lang_data['select_default'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($niches as $niche): ?>
                            <option value="<?= htmlspecialchars($niche['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($niche['title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom"><?= htmlspecialchars($lang_data['custom_option'], ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <div class="mb-4 hidden" id="productBlock">
                    <label class="form-label"><?= htmlspecialchars($lang_data['product_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="product" class="form-control" placeholder="<?= htmlspecialchars($lang_data['product_placeholder'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="mb-4">
                    <label class="form-label"><?= htmlspecialchars($lang_data['phone_label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="tel" name="phone" class="form-control" placeholder="<?= htmlspecialchars($lang_data['phone_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-create" id="submitBtn"><?= htmlspecialchars($lang_data['btn'], ENT_QUOTES, 'UTF-8') ?></button>
                <div id="status" class="mt-3 text-center"></div>
            </form>

            <div class="login-link">
                <a href="/pricing.php"><?= htmlspecialchars($lang_data['pricing_link'], ENT_QUOTES, 'UTF-8') ?></a>
                ·
                <a href="/login.php"><?= htmlspecialchars($lang_data['already_account'], ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>

        <div id="checkBlock" class="check-block hidden">
            <span class="icon-big">✉️</span>
            <h2><?= htmlspecialchars($lang_data['check_title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="subtitle"><?= htmlspecialchars($lang_data['check_sub'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="email" id="emailDisplay"></div>
            <p class="hint"><?= htmlspecialchars($lang_data['check_hint'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div id="siteReadyBlock" class="check-block hidden">
            <span class="icon-big">✅</span>
            <h2><?= htmlspecialchars($lang_data['site_ready'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="subtitle"><?= htmlspecialchars($lang_data['site_sub'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="link-row">
                <span class="url" id="siteUrlDisplay"></span>
                <button class="btn-copy" id="copyBtn"><?= htmlspecialchars($lang_data['copy_btn'], ENT_QUOTES, 'UTF-8') ?></button>
                <a href="#" id="openSiteBtn" target="_blank" class="btn-open"><?= htmlspecialchars($lang_data['open_btn'], ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="link-row">
                <span class="url" id="adminUrlDisplay"></span>
                <a href="#" id="openAdminBtn" target="_blank" class="btn-open"><?= htmlspecialchars($lang_data['site_admin'], ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <button class="btn-adaptive" id="adaptiveBtn"><?= htmlspecialchars($lang_data['adaptive_btn'], ENT_QUOTES, 'UTF-8') ?></button>

            <!-- Для известного: кнопка "Перейти в админку" -->
            <a href="#" id="goAdminBtn" class="btn-secondary-custom hidden"><?= htmlspecialchars($lang_data['go_admin_btn'], ENT_QUOTES, 'UTF-8') ?></a>

            <!-- Для нового: блок с email -->
            <div id="emailSaveBlock" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #eee;">
                <h3 style="font-size: 18px; font-weight: 300; color: #111; margin-bottom: 4px;"><?= htmlspecialchars($lang_data['save_email_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="subtitle" style="margin-bottom: 16px;"><?= htmlspecialchars($lang_data['save_email_sub'], ENT_QUOTES, 'UTF-8') ?></p>
                <input type="email" id="quickEmail" class="form-control" placeholder="<?= htmlspecialchars($lang_data['email_placeholder'], ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom: 12px;">
                <div style="display: flex; gap: 12px;">
                    <button class="btn-cancel" id="skipEmailBtn" style="flex: 1;"><?= htmlspecialchars($lang_data['skip_email'], ENT_QUOTES, 'UTF-8') ?></button>
                    <button class="btn-submit" id="saveEmailBtn" style="flex: 1;"><?= htmlspecialchars($lang_data['save_email_btn'], ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div class="modal-error" id="quickError"></div>
            </div>

            <!-- Для известного: кнопка "Создать ещё сайт" -->
            <button id="createMoreBtn" class="btn-secondary-custom hidden"><?= htmlspecialchars($lang_data['create_more_btn'], ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>

<script>
    const LANG = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
    const TEXT = <?= json_encode($lang_data, JSON_UNESCAPED_UNICODE) ?>;
    const IS_RETURNING = <?= json_encode($isReturning) ?>;
    const HAS_EMAIL    = <?= json_encode($hasEmail) ?>;

    const form         = document.getElementById('deployForm');
    const nicheSelect  = document.getElementById('nicheSelect');
    const productBlock = document.getElementById('productBlock');
    const submitBtn    = document.getElementById('submitBtn');
    const statusDiv    = document.getElementById('status');

    const formBlock      = document.getElementById('formBlock');
    const checkBlock     = document.getElementById('checkBlock');
    const siteReadyBlock = document.getElementById('siteReadyBlock');
    const emailDisplay   = document.getElementById('emailDisplay');

    const emailSaveBlock = document.getElementById('emailSaveBlock');
    const goAdminBtn     = document.getElementById('goAdminBtn');
    const createMoreBtn  = document.getElementById('createMoreBtn');

    let currentSite = null;

    nicheSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            productBlock.classList.remove('hidden');
        } else {
            productBlock.classList.add('hidden');
        }
    });

    // ===== Отправка формы =====
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        statusDiv.innerHTML = '⏳ ' + TEXT.creating;
        submitBtn.disabled = true;

        const data = new FormData(form);

        try {
            const response = await fetch('/api/deploy.php', { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                currentSite = result;

                // Ставим cookie visitor_id
                if (result.visitor_id) {
                    document.cookie = 'visitor_id=' + result.visitor_id + '; max-age=' + (365*24*60*60) + '; path=/';
                }

                // Метрика
                if (typeof ym === 'function') {
                    const niche   = (data.get('niche')   || '').toString().trim();
                    const product = (data.get('product') || '').toString().trim();

                    ym(112304158, 'params', {
                        niche:   niche || 'unknown',
                        product: product || '',
                        lang:    LANG,
                        site_url: result.url
                    });
                    ym(112304158, 'reachGoal', 'site_created');
                }

                // Показываем «Сайт создан!»
                formBlock.classList.add('hidden');
                document.getElementById('siteUrlDisplay').textContent = result.url.replace('https://', '');
                document.getElementById('adminUrlDisplay').textContent = result.admin_url.replace('https://', '');
                document.getElementById('openSiteBtn').href = result.url;
                document.getElementById('openAdminBtn').href = result.admin_url;
                goAdminBtn.href = result.admin_url;
                siteReadyBlock.classList.remove('hidden');

                // Для известного с email: без формы
                // Иначе (новый или email NULL): форма
                if (IS_RETURNING && HAS_EMAIL) {
                    emailSaveBlock.classList.add('hidden');
                    goAdminBtn.classList.remove('hidden');
                    createMoreBtn.classList.remove('hidden');
                } else {
                    emailSaveBlock.classList.remove('hidden');
                    goAdminBtn.classList.add('hidden');
                    createMoreBtn.classList.add('hidden');
                }
            } else {
                statusDiv.innerHTML = '❌ ' + (result.error || TEXT.error);
                submitBtn.disabled = false;
            }
        } catch (err) {
            statusDiv.innerHTML = '❌ ' + err.message;
            submitBtn.disabled = false;
        }
    });

    // ===== Кнопки на экране «Сайт готов» =====
    document.getElementById('copyBtn').addEventListener('click', function() {
        if (!currentSite) return;
        navigator.clipboard.writeText(currentSite.url).then(() => {
            this.textContent = TEXT.copied;
            setTimeout(() => this.textContent = TEXT.copy_btn, 1500);
        });
    });

    document.getElementById('adaptiveBtn').addEventListener('click', function() {
        if (!currentSite) return;
        const adaptiveUrl = 'https://iloveadaptive.com/ru/url/' + encodeURIComponent(currentSite.url);
        window.open(adaptiveUrl, '_blank');
    });

    document.getElementById('skipEmailBtn').addEventListener('click', function() {
        // Пользователь пропустил email — сайт работает 24 часа
        siteReadyBlock.classList.add('hidden');
        formBlock.classList.remove('hidden');
        form.reset();
        submitBtn.disabled = false;
        statusDiv.innerHTML = '';
    });

    document.getElementById('createMoreBtn').addEventListener('click', function() {
        siteReadyBlock.classList.add('hidden');
        formBlock.classList.remove('hidden');
        form.reset();
        submitBtn.disabled = false;
        statusDiv.innerHTML = '';
    });

    document.getElementById('saveEmailBtn').addEventListener('click', async function() {
        const email = document.getElementById('quickEmail').value.trim();
        const quickError = document.getElementById('quickError');

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            quickError.textContent = TEXT.email_invalid;
            return;
        }

        if (!currentSite) return;

        const data = new FormData(form);
        data.append('email', email);

        this.disabled = true;
        quickError.textContent = '';

        try {
            const response = await fetch('/api/register.php', { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                siteReadyBlock.classList.add('hidden');
                formBlock.classList.add('hidden');
                emailDisplay.textContent = email;
                checkBlock.classList.remove('hidden');
            } else {
                if (result.redirect) {
                    window.location.href = result.redirect;
                    return;
                }
                quickError.textContent = result.error || TEXT.email_error;
                this.disabled = false;
            }
        } catch (err) {
            quickError.textContent = TEXT.email_error;
            this.disabled = false;
        }
    });
</script>

</body>
</html>
