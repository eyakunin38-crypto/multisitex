<?php
header('Content-Type: application/json');

$name = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['domain'] ?? ''));
$niche = $_POST['niche'] ?? '';
$lang = $_POST['lang'] ?? 'ru';

if (!$name || !$niche) {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
    exit;
}

$multisiteDomain = "$name.multisitex.com";
$clientPath = "/var/www/multisites/$multisiteDomain";
$templatePath = "/var/www/multisitex.com/templates/$niche";

if (!is_dir($templatePath)) {
    $templatePath = "/var/www/multisitex.com/templates/default";
}

if (file_exists($clientPath)) {
    echo json_encode(['success' => false, 'error' => 'Мультисайт с таким именем уже существует']);
    exit;
}

// Создаём структуру мультисайта
mkdir($clientPath, 0755, true);
exec("cp -r $templatePath/* $clientPath/");

// nginx-конфиг для этого мультисайта
$nginxConf = "/etc/nginx/sites-available/$multisiteDomain.conf";
file_put_contents($nginxConf, "
server {
    listen 80;
    server_name $multisiteDomain;
    root $clientPath;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
");
symlink($nginxConf, "/etc/nginx/sites-enabled/");
exec("nginx -s reload");

echo json_encode(['success' => true, 'url' => "http://$multisiteDomain"]);
