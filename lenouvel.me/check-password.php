<?php
require 'vendor/autoload.php';

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use App\Entity\User;

$hash = '$2y$04$47EmuHySMkRHOzmCvC.MHOrSCNDLDumpLcs6zxN9smp0F1ZHbBE6e';

$user = new User();
$user->setEmail('demo@homecloud.local');
$user->setPassword($hash);

$factory = new PasswordHasherFactory([
    App\Entity\User::class => ['algorithm' => 'auto', 'cost' => 4]
]);
$hasher = new UserPasswordHasher($factory);

$plain = 'test';
$isValid = $hasher->isPasswordValid($user, $plain);

echo $isValid ? "OK : le mot de passe est valide\n" : "ERREUR : le mot de passe est invalide\n";
