<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\EncryptionServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Chiffrement au repos des fichiers stockés (encryption at rest).
 *
 * Algorithme : XChaCha20-Poly1305 via sodium_crypto_secretstream.
 * - Authentifié : détecte toute falsification (auth tag par chunk)
 * - Streaming : aucun fichier entier chargé en RAM, chunk par chunk (8 Ko)
 * - Built-in PHP 8 : zéro dépendance externe (libsodium natif)
 *
 * Format sur disque :
 *   HEADER (24 bytes) | chunk1_size (4 bytes LE) | chunk1 | chunk2_size | chunk2 | ...
 *   Le dernier chunk est marqué TAG_FINAL par sodium.
 *
 * Objectifs de sécurité :
 * - Breach filesystem (o2switch shared hosting) → fichiers illisibles sans la clé
 * - Breach DB (paths exposés) → fichiers illisibles sans la clé
 * - Fichiers "sensibles" (SVG, HTML, JS) → binaire opaque sur disque,
 *   non exécutable même en cas de path traversal résiduel
 *
 * La clé (32 bytes) est injectée via APP_ENCRYPTION_KEY (base64) → services.yaml.
 * Ne jamais stocker la clé en DB ni dans le code.
 */
final class EncryptionService implements EncryptionServiceInterface
{
    private const CHUNK_SIZE = 8192;

    public function __construct(
        /** Clé brute 32 bytes décodée via env(base64:APP_ENCRYPTION_KEY) */
        #[Autowire(param: 'app.encryption_key')]
        private readonly string $key,
    ) {}

    /**
     * Chiffre $sourcePath et écrit le résultat dans $destPath.
     * $sourcePath et $destPath peuvent être le même chemin (chiffrement en place via temp).
     *
     * @throws \RuntimeException si l'ouverture des fichiers échoue
     */
    public function encrypt(string $sourcePath, string $destPath): void
    {
        $inPlace = ($sourcePath === $destPath);
        $tempPath = $inPlace ? $destPath . '.enc_tmp' : $destPath;

        $in = fopen($sourcePath, 'rb')
            ?? throw new \RuntimeException("Cannot open source file: $sourcePath");
        $out = fopen($tempPath, 'wb')
            ?? throw new \RuntimeException("Cannot open dest file: $tempPath");

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);
            fwrite($out, $header);

            while (!feof($in)) {
                $plain = fread($in, self::CHUNK_SIZE);
                if ($plain === false || $plain === '') {
                    break;
                }
                $tag = feof($in)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, '', $tag);
                fwrite($out, pack('V', strlen($cipher)));
                fwrite($out, $cipher);
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($state);
        }

        if ($inPlace) {
            rename($tempPath, $destPath);
        }
    }

    /**
     * Déchiffre $sourcePath vers un fichier temporaire et retourne son chemin.
     * L'appelant est responsable de supprimer le fichier temp (dans un finally).
     *
     * @throws \RuntimeException si le déchiffrement échoue (fichier corrompu ou mauvaise clé)
     */
    public function decryptToTempFile(string $sourcePath): string
    {
        $tempPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8));

        $in = fopen($sourcePath, 'rb')
            ?? throw new \RuntimeException("Cannot open encrypted file: $sourcePath");
        $out = fopen($tempPath, 'wb')
            ?? throw new \RuntimeException("Cannot open temp file: $tempPath");

        try {
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($header === false || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new \RuntimeException('Invalid encrypted file: missing header');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);

            while (!feof($in)) {
                $lenBytes = fread($in, 4);
                if ($lenBytes === false || strlen($lenBytes) < 4) {
                    break;
                }
                $len = unpack('V', $lenBytes)[1];
                $cipher = fread($in, $len);
                if ($cipher === false || strlen($cipher) !== $len) {
                    throw new \RuntimeException('Truncated encrypted chunk');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new \RuntimeException('Decryption failed: authentication tag mismatch');
                }
                [$plain] = $result;
                fwrite($out, $plain);
            }
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($state);
        }

        return $tempPath;
    }

    /**
     * Déchiffre $sourcePath et écrit le contenu en clair directement dans $output (resource).
     * Utilisé pour les StreamedResponse (aucun fichier temp, streaming direct vers HTTP).
     *
     * @param resource $output
     *
     * @throws \RuntimeException si le déchiffrement échoue
     */
    public function decryptToStream(string $sourcePath, mixed $output): void
    {
        $in = fopen($sourcePath, 'rb')
            ?? throw new \RuntimeException("Cannot open encrypted file: $sourcePath");

        try {
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($header === false || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new \RuntimeException('Invalid encrypted file: missing header');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);

            while (!feof($in)) {
                $lenBytes = fread($in, 4);
                if ($lenBytes === false || strlen($lenBytes) < 4) {
                    break;
                }
                $len = unpack('V', $lenBytes)[1];
                $cipher = fread($in, $len);
                if ($cipher === false || strlen($cipher) !== $len) {
                    throw new \RuntimeException('Truncated encrypted chunk');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new \RuntimeException('Decryption failed: authentication tag mismatch');
                }
                [$plain] = $result;
                fwrite($output, $plain);
            }
        } finally {
            fclose($in);
            sodium_memzero($state);
        }
    }
}
