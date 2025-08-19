---
applyTo: '**'
---

# Instructions IA – Rigueur et exigences PR Home Cloud

## Règles de sécurité et validation à respecter systématiquement

- **Validation stricte des noms de fichiers**
  - Autoriser uniquement : lettres, chiffres, espace, tiret, underscore, parenthèses, virgule
  - Extension obligatoire, un seul point, pas de points consécutifs, pas de point initial
  - Interdiction stricte des caractères spéciaux non listés (ex : point-virgule, accents, etc.)
  - Regex à jour et testée

- **Validation d’extension**
  - Utiliser `pathinfo` pour extraire l’extension réelle
  - Vérifier l’extension via `in_array` performant (case-insensitive)
  - Refuser toute extension non listée dans `ALLOWED_EXTENSIONS`

- **Noms réservés**
  - Vérification uniquement si extension présente
  - Refuser tous les noms Windows réservés (nul, con, prn, lpt*, com*, etc.)

- **Sécurité du chemin (path)**
  - Interdire toute séquence `..`, `/`, `\` et variantes encodées
  - Vérification sur le path décodé et brut

- **Validation du type MIME**
  - Autoriser uniquement les types explicitement listés

- **Validation de la taille**
  - Limite stricte à 100 Mo (104857600 octets)

- **Tests unitaires**
  - Couvrir tous les cas critiques (noms réservés, extensions, path, regex, taille, MIME)
  - Utiliser `assertNotEmpty` pour les erreurs attendues
  - Les tests doivent refléter la politique de sécurité métier

- **Lisibilité et clean code**
  - Utiliser les noms courts pour les classes Symfony
  - Pas de code mort, pas de credentials, pas de faille évidente
  - Un commit atomique par modification significative

- **Documentation et traçabilité**
  - Synchroniser la doc métier et technique à chaque évolution
  - Rappeler à chaque étape de faire un commit et une PR

---

> Ce fichier doit être pris en compte par l’IA à chaque proposition de code ou de test sur l’entité File ou la gestion des fichiers dans Home Cloud. Toute déviation doit être explicitement justifiée.
