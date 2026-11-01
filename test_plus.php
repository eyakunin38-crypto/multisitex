<?php
$email = 'yesmarketing+test@yandex.ru';
if (strpos($email, '@') !== false) {
    [$local, $domain] = explode('@', $email, 2);
    if (($pos = strpos($local, '+')) !== false) {
        $local = substr($local, 0, $pos);
    }
    $email = $local . '@' . $domain;
}
echo "Normalized: " . $email . "\n";
