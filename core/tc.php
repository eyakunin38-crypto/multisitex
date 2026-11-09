<?php
// ============================================
// tc.php — шаблонизатор для мультисайта (v2, безопасный)
// Изменения: экранирование значений, одна замена за проход,
// путь к файлу не показывается посетителю, домен проверяется.
// ============================================

class tc {
    public $content;       // массив контента (тег => значение)
    public $templateName;  // имя файла шаблона (без пути)
    public $domain;        // папка сайта
    public $errorMessage;

    function Fill() {
        // Имена только из безопасных символов
        $domain = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)$this->domain);
        $name   = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$this->templateName);
        if ($domain === '' || $name === '') {
            error_log('tc: empty domain or template name');
            return 'Ошибка шаблона';
        }

        $base = __DIR__ . '/..';

        // 1. Шаблон в папке сайта
        $templateFile = "$base/sites/$domain/templates/$name.html";

        // 2. index.html в папке сайта
        if (!file_exists($templateFile)) {
            $templateFile = "$base/sites/$domain/templates/index.html";
        }

        // 3. Глобальный шаблон
        if (!file_exists($templateFile)) {
            $templateFile = "$base/templates/$name.html";
        }

        // 4. default/main.html
        if (!file_exists($templateFile)) {
            $templateFile = "$base/templates/default/main.html";
        }

        // 5. Ничего нет
        if (!file_exists($templateFile)) {
            error_log('tc: template not found for ' . $domain);
            return 'Шаблон не найден';
        }

        $html = file_get_contents($templateFile);
        if ($html === false) {
            error_log('tc: cannot read ' . $templateFile);
            return 'Ошибка шаблона';
        }

        // Все значения экранируются, замена за один проход:
        // значение с текстом {Phone} уже не подменится повторно.
        $map = [];
        foreach ((array)$this->content as $tag => $value) {
            $map['{' . $tag . '}'] = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return strtr($html, $map);
    }
}
