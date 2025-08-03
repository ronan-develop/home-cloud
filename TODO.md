# TODO – Prochaine étape Home Cloud (août 2025)

- Lancer Caddy avec le Caddyfile adapté pour reverse proxy sur le port 8080.
- Tester l’accès à l’application Symfony via http://[adresse_serveur]:8080 (ou tunnel SSH si besoin).
- Vérifier le bon fonctionnement de la chaîne Caddy → FrankenPHP → Symfony.
- Documenter tout retour d’expérience ou adaptation supplémentaire dans `.github/projet-context.md`.
- Poursuivre la configuration multi-tenant et la sécurisation.
- Mettre à jour la documentation à chaque avancée.
- Après validation du fonctionnement Caddy + FrankenPHP, réaliser un schéma d’architecture API + PWA :
  - Backend : API Symfony avec ApiPlatform (multi-tenant)
  - Frontend : PWA (Vue, Angular ou React à choisir)
  - Décrire les flux, la sécurité, et la séparation des responsabilités.

---

*Ce fichier sert de pense-bête pour la reprise rapide du projet Home Cloud.*
