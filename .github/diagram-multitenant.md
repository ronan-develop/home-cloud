# Schéma conceptuel multi-tenant (multi-bases)

```mermaid
graph TD;
    A[Requete HTTP sur sous-domaine autorisé (ex : ronan.lenouvel.me, elea.lenouvel.me, ...)]
    B[Détection du sous-domaine]
    C[Détermination du tenant (utilisateur)]
    D[Sélection de la base MySQL dédiée à ce tenant]
    E[Connexion Doctrine sur la base correspondante]
    F[Accès aux données strictement isolées]
    G[Réponse HTTP personnalisée]

    A --> B --> C --> D --> E --> F --> G
```

**Résumé** :

- Chaque utilisateur (User) dispose de sa propre base de données MySQL dédiée, totalement isolée des autres.
- L’application détecte le sous-domaine utilisé lors de la requête (ex : ronan.lenouvel.me, elea.lenouvel.me, etc.).
- Le domaine racine (lenouvel.me) n’est jamais accessible directement : toute requête doit passer par un sous-domaine déclaré.
- À chaque requête, Symfony détecte le sous-domaine, détermine le tenant (utilisateur), puis sélectionne dynamiquement la base de données MySQL dédiée à ce tenant.
- Doctrine utilise la connexion appropriée pour garantir l’isolation stricte des données.
- Le cœur applicatif (code, logique métier) reste unique, seules les données sont isolées par base.

*Ce diagramme et ce workflow sont la référence pour toute évolution multi-tenant/multi-bases du projet. À prendre en compte dans toute modélisation, entité, fixture ou endpoint API.*
