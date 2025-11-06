# Installation de FrankenPHP (mutualisé, CentOS 8/CloudLinux)

1. Télécharge le binaire FrankenPHP (Linux x86_64) dans ton dossier personnel :

   ```sh
   curl -Lo frankenphp "https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-amd64"
   chmod +x frankenphp
   ./frankenphp --version
   ```

2. Mets à jour le Caddyfile pour utiliser FrankenPHP comme backend PHP (voir exemple ci-dessous).

3. Lance Caddy avec ce Caddyfile pour servir ton application Symfony via FrankenPHP.

---

## Exemple de Caddyfile pour Symfony + FrankenPHP (port 8080)

```caddyfile
:8080 {
    root * /home9/ron2cuba/www/public
    php_fastcgi frankenphp:9000
    file_server
}
```

- Place le binaire `frankenphp` dans le même dossier que Caddy ou dans ton PATH utilisateur.
- Lance FrankenPHP en mode FastCGI sur le port 9000 :

   ```sh
   ./frankenphp php-server --listen :9000 /home9/ron2cuba/www/public
   ```

- Puis lance Caddy avec le Caddyfile adapté.

---

*Documente chaque étape dans `.github/projet-context.md` pour garder une trace.*
