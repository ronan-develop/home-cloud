# Layout CSS — référence rapide

## Structure HTML (base.html.twig)

```
body
└── .hc-app-grid
    ├── .hc-sidebar          (desktop: colonne gauche 248px, mobile: drawer fixed)
    │   ├── .hc-sidebar-header
    │   ├── .hc-sidebar-nav
    │   └── .hc-sidebar-footer
    └── .hc-layout-main      (flex-col, overflow:hidden)
        ├── .hc-topbar       (flex-shrink:0)
        └── .hc-content      (flex:1, overflow:auto — zone scrollable)
.tab-bar                     (mobile uniquement, fixed bottom-0, height:64px)
```

## Breakpoints

| Breakpoint | Règle CSS              | Comportement                          |
|------------|------------------------|---------------------------------------|
| Mobile     | `@media (max-width: 767px)` | sidebar → drawer, tab-bar visible, `height: calc(100vh - 64px)` |
| Desktop    | `@media (min-width: 1024px)` | ajustements sidebar                 |

## Règles de scroll

- **Desktop** : `.hc-content { overflow: auto }` — scroll naturel dans la zone de contenu
- **Mobile** : `.hc-content { overflow: auto; -webkit-overflow-scrolling: touch }` + `.hc-app-grid { height: calc(100vh - 64px) }`
- **Sidebar desktop** : `.hc-sidebar { overflow-y: auto }` — scroll indépendant
- **Ne jamais** mettre `overflow` sur `.hc-layout-main` (il est `overflow:hidden` pour confiner le scroll à `.hc-content`)

## Variables CSS clés (`assets/styles/layout.css`)

| Variable              | Rôle                        |
|-----------------------|-----------------------------|
| `--hc-accent`         | Bleu principal (#2b5fff)    |
| `--hc-bg`             | Fond page                   |
| `--hc-surface`        | Surface card/panel (rgba)   |
| `--hc-surface-strong` | Surface opaque (modal, tab-bar) |
| `--hc-border`         | Bordure subtile (rgba)      |
| `--hc-text`           | Texte principal             |
| `--hc-text-2`         | Texte secondaire            |
| `--hc-text-3`         | Texte désactivé/label       |
| `--hc-shadow`         | Ombre standard              |
| `--hc-err`            | Rouge (#ef4444)             |

Thème sombre : redéfinies sous `@media (prefers-color-scheme: dark)`.

## Tab-bar mobile

```css
.tab-bar { position: fixed; bottom: 0; height: 64px; z-index: 40; }
/* 4 items : grid-template-columns: repeat(4, 1fr) */
```

Conséquence : tout conteneur pleine-hauteur sur mobile doit soustraire 64px (`calc(100vh - 64px)`).
