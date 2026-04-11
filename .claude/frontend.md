# Design Frontend

## Style : Material Design + Liquid Glass

Glassmorphism avec distorsion SVG (`feTurbulence` / `feDisplacementMap`).

## Palette Tailwind v4

| Rôle         | Valeur                                              |
|--------------|-----------------------------------------------------|
| Fond page    | `from-slate-900 via-blue-950 to-indigo-900`         |
| Surface      | `bg-white/10 backdrop-blur-2xl` + SVG filter        |
| Accent       | `bg-blue-500` / `text-blue-400`                     |
| Arrondi      | `rounded-2xl` ou `rounded-3xl` (toujours)           |
| Transitions  | `transition-all` sur tous les éléments interactifs  |

## Règles

- Jamais de coins droits (`rounded-none`) sur les cards ou boutons
- Toujours `transition-all` sur les éléments cliquables/survolables
- Le SVG filter de distorsion s'applique sur les surfaces flottantes (modals, cards)
