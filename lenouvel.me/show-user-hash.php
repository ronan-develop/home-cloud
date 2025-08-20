<?php
require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$env = parse_ini_file('.env.test');
$url = $env['DATABASE_URL'];
if (str_starts_with($url, 'env:')) {
    $url = getenv(substr($url, 4));
}
$conn = DriverManager::getConnection(['url' => $url]);
$user = $conn->fetchAssociative('SELECT email, password FROM user WHERE email = ?', ['demo@homecloud.local']);
print_r($user);
