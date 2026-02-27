<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Contrat pour le chiffrement au repos des fichiers (encryption at rest).
 *
 * Dépendre de cette interface — jamais de EncryptionService directement —
 * permet de swapper l'implémentation (sodium, OpenSSL, mock test) sans
 * modifier les consommateurs (principe DIP).
 */
interface EncryptionServiceInterface
{
    /**
     * Chiffre $sourcePath et écrit le résultat dans $destPath.
     * sourcePath et destPath peuvent être identiques (chiffrement en place).
     */
    public function encrypt(string $sourcePath, string $destPath): void;

    /**
     * Déchiffre $sourcePath vers un fichier temporaire et retourne son chemin.
     * L'appelant est responsable de supprimer le fichier temporaire (unlink).
     */
    public function decryptToTempFile(string $sourcePath): string;

    /**
     * Déchiffre $sourcePath et écrit le contenu en clair dans $output (resource).
     * Utilisé pour les StreamedResponse (zéro copie disque intermédiaire).
     */
    public function decryptToStream(string $sourcePath, mixed $output): void;
}
