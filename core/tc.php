<?php
// ============================================
// tc.php — шаблонизатор для мультисайта
// ============================================

class tc {
    public $content;       // массив контента (тег => значение)
    public $templateName;  // имя файла шаблона (без пути)
    public $domain;        // папка сайта
    public $errorMessage;

    function Fill() {
        // 1. Ищем шаблон в папке сайта
        $templateFile = __DIR__ . '/../sites/' . $this->domain . '/templates/' . $this->templateName . '.html';

        // 2. Если нет — ищем index.html в папке сайта
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../sites/' . $this->domain . '/templates/index.html';
        }

        // 3. Если нет — ищем в глобальной папке ниши
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../templates/' . $this->templateName . '.html';
        }

        // 4. Если нет — берём main.html из default
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../templates/default/main.html';
        }

        // 5. Если и main.html нет — ошибка
        if (!file_exists($templateFile)) {
            return "❌ Шаблон не найден: " . $templateFile;
        }

        $html = file_get_contents($templateFile);

        if ($html === false) {
            return "❌ Не удалось прочитать шаблон: " . $templateFile;
        }

        // Подставляем контент (только современные теги {Tag})
        foreach ($this->content as $tag => $value) {
            $html = str_replace('{' . $tag . '}', $value, $html);
        }

        // Возвращаем HTML строкой (а не echo)
        return $html;
    }
}
?>
