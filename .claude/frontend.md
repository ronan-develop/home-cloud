# Design Frontend

## Style : design system `theme.css` (variables `--hc-*`)

Le design system actif repose sur `assets/styles/theme.css` : variables CSS custom, palette light/dark, ombres nettes (pas de glassmorphism, pas de `backdrop-filter` décoratif).

## Variables principales (`--hc-*`)

| Rôle                 | Variable(s)                                                        |
|----------------------|---------------------------------------------------------------------|
| Accent               | `--hc-accent`, `--hc-accent-2`, `--hc-accent-soft`                 |
| Accents secondaires  | `--hc-indigo`, `--hc-indigo-soft`, `--hc-green`, `--hc-green-soft` |
| Fond                 | `--hc-bg`, `--hc-bg-2`                                             |
| Surface              | `--hc-surface`, `--hc-surface-2`, `--hc-surface-strong`, `--hc-input` |
| Bordure              | `--hc-border`, `--hc-border-row`, `--hc-border-strong`             |
| Texte                | `--hc-text`, `--hc-text-2`, `--hc-text-3`                          |
| Ombre                | `--hc-shadow`, `--hc-shadow-lg`, `--hc-shadow-crisp`               |
| Statuts              | `--hc-ok`, `--hc-warn`, `--hc-err`                                 |

Dark mode : `[data-theme="dark"]` (toggle JS) ou `@media (prefers-color-scheme: dark)` en fallback OS. Toute nouvelle valeur de couleur doit passer par ces variables plutôt que des couleurs Tailwind en dur, pour rester cohérente entre light et dark.

## Règles

- Jamais de coins droits (`rounded-none`) sur les cards ou boutons
- Toujours `transition-all` (ou `transition-colors`) sur les éléments cliquables/survolables
- Toute surface (modal, card, toolbar) doit utiliser `var(--hc-surface)` / `var(--hc-border)` / `var(--hc-shadow-lg)` — pas de `bg-white/x`, `backdrop-blur`, ou couleurs Tailwind en dur
- Pas de `backdrop-filter` décoratif : les seuls flous tolérés sont des overlays de lisibilité texte/icône sur média (galerie, albums), à valider au cas par cas
