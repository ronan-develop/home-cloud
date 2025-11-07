# 🛠️ Mise en place de l’environnement de développement – Home Cloud

Ce guide détaille toutes les étapes pour installer et configurer l’environnement de développement du projet Home Cloud : Symfony, npm, Tailwind, Stimulus, etc. Il est destiné à garantir une installation reproductible et conforme aux contraintes O2Switch.

---

## 1. Prérequis

- PHP >= 8.2
- Composer
- Node.js >= 18 & npm
- MySQL (local ou distant)
- Accès SSH (optionnel, pour O2Switch)

## 2. Installation du projet Symfony

```bash
composer install
```

- Vérifiez que les extensions PHP requises sont installées.
- Configurez vos variables d’environnement dans `.env.local` (jamais en prod/test).

## 3. Initialisation de la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

- Pour les tests : configurez `.env.test` et utilisez une base suffixée `_test`.

## 4. Installation des dépendances front (npm)

```bash
npm install
```

- Les dépendances JS sont listées dans `package.json`.

## 5. Mise en place Tailwind CSS

- Tailwind est utilisé pour le style moderne et responsive.
- Configuration dans `tailwind.config.js` (à créer si absent).
- Exemple d’installation :

```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init
```

- Ajoutez Tailwind dans `assets/styles/app.css` :

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

## 6. Stimulus & Turbo

- Utilisés pour l’interactivité front (contrôleurs JS dans `assets/controllers/`).
- Déjà présents via `@hotwired/stimulus` et `@hotwired/turbo`.

## 7. Lancement du serveur Symfony

```bash
symfony serve
```

- Ou via Apache natif sur O2Switch (voir documentation spécifique).

## 8. Compilation des assets

```bash
npm run build
```

- Pour le mode dev :

```bash
npm run dev
```

## 9. Bonnes pratiques

- Ne jamais committer `.env.local` ou credentials.
- Utiliser des branches pour chaque fonctionnalité.
- Documenter toute évolution dans le README et Services.md.

---

> Pour toute question ou problème, consultez la documentation métier ou ouvrez une issue sur le dépôt.
