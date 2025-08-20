<?php
// script temporaire pour vérifier le hash du mot de passe en base
require_once __DIR__ . '/vendor/autoload.php';

$hash = '$2y$04$vEMxGP7YJYfvsJNUajzLNOXVaMIStRfzTL4ZuCi/7Zq2Zt1U/jmO2'; // hash extrait de la base
$plain = 'test';

if (password_verify($plain, $hash)) {
    echo "OK: Le mot de passe 'test' correspond bien au hash en base.\n";
} else {
    echo "FAIL: Le mot de passe 'test' NE correspond PAS au hash en base.\n";
}
// ...fin du script...
