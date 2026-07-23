<?php

declare(strict_types=1);

// Miroir de .deploy-targets (racine du repo) — à maintenir manuellement en
// synchro à chaque nouvelle instance. Ne PAS lire .deploy-targets depuis le
// PHP applicatif : fichier shell, format non garanti, usage réservé à
// bin/deploy-all.sh.
return [
    'ronan'    => 'https://ronan.lenouvel.me',
    'yannick'  => 'https://yannick.lenouvel.me',
    'coralie'  => 'https://coralie.lenouvel.me',
    'elea'     => 'https://elea.lenouvel.me',
    'corentin' => 'https://corentin.lenouvel.me',
    'damien'   => 'https://damien.lenouvel.me',
    'baptiste' => 'https://baptiste.lenouvel.me',
];
