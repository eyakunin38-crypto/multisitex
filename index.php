<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();

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
    ]
];

$db = new mysqli('localhost', 'multisitex_user', 'Mbx7gt8123!', 'multisitex_db');
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
                <button type="submit" class="btn-create"><?= htmlspecialchars($lang_data['btn'], ENT_QUOTES, 'UTF-8') ?></button>
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
    </div>
</div>

<div id="emailModal" class="modal-custom hidden">
    <div class="modal-card">
        <h3><?= htmlspecialchars($lang_data['email_title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="modal-sub"><?= htmlspecialchars($lang_data['email_sub'], ENT_QUOTES, 'UTF-8') ?></p>
        <div style="margin-bottom: 24px;">
            <label class="form-label"><?= htmlspecialchars($lang_data['email_label'], ENT_QUOTES, 'UTF-8') ?></label>
            <input type="email" id="emailInput" class="form-control" placeholder="<?= htmlspecialchars($lang_data['email_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="modal-error" id="modalError"></div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" id="cancelBtn"><?= htmlspecialchars($lang_data['cancel'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn-submit" id="submitEmailBtn"><?= htmlspecialchars($lang_data['email_btn'], ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>

<script>
    const LANG = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
    const TEXT = <?= json_encode($lang_data, JSON_UNESCAPED_UNICODE) ?>;

    const form         = document.getElementById('deployForm');
    const nicheSelect  = document.getElementById('nicheSelect');
    const productBlock = document.getElementById('productBlock');

    const emailModal     = document.getElementById('emailModal');
    const emailInput     = document.getElementById('emailInput');
    const modalError     = document.getElementById('modalError');
    const submitEmailBtn = document.getElementById('submitEmailBtn');
    const cancelBtn      = document.getElementById('cancelBtn');

    const formBlock  = document.getElementById('formBlock');
    const checkBlock = document.getElementById('checkBlock');
    const emailDisplay = document.getElementById('emailDisplay');

    nicheSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            productBlock.classList.remove('hidden');
        } else {
            productBlock.classList.add('hidden');
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        modalError.textContent = '';
        emailModal.classList.remove('hidden');
        emailInput.value = '';
        submitEmailBtn.disabled = false;
        submitEmailBtn.textContent = TEXT.email_btn;
        setTimeout(() => emailInput.focus(), 50);
    });

    cancelBtn.addEventListener('click', function() {
        emailModal.classList.add('hidden');
        modalError.textContent = '';
    });

    submitEmailBtn.addEventListener('click', async function() {
        const email = emailInput.value.trim();

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            modalError.textContent = TEXT.email_invalid;
            return;
        }

        const data = new FormData(form);
        data.append('email', email);

        submitEmailBtn.disabled = true;
        submitEmailBtn.textContent = TEXT.email_sending;
        modalError.textContent = '';

        try {
            const response = await fetch('/api/register.php', { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                if (typeof ym === 'function') {
                    const niche   = (data.get('niche')   || '').toString().trim();
                    const product = (data.get('product') || '').toString().trim();

                    ym(112304158, 'params', {
                        niche:   niche || 'unknown',
                        product: product || '',
                        lang:    LANG
                    });
                    ym(112304158, 'reachGoal', 'registration_started');
                    if (niche === 'custom') {
                        ym(112304158, 'reachGoal', 'custom_niche_started');
                    }
                }

                emailModal.classList.add('hidden');
                formBlock.classList.add('hidden');
                emailDisplay.textContent = email;
                checkBlock.classList.remove('hidden');
            } else {
                modalError.textContent = result.error || TEXT.email_error;
                submitEmailBtn.disabled = false;
                submitEmailBtn.textContent = TEXT.email_btn;
            }
        } catch (err) {
            modalError.textContent = TEXT.email_error;
            submitEmailBtn.disabled = false;
            submitEmailBtn.textContent = TEXT.email_btn;
        }
    });

    emailInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitEmailBtn.click();
        }
    });
</script>

</body>
</html>
