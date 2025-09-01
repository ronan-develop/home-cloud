# Limites connues & évolutions prévues

## Backlog des prochaines fonctionnalités

| Fonctionnalité                                 | Description courte                                      | Priorité   | Statut   |
|-----------------------------------------------|--------------------------------------------------------|------------|----------|
| Gestion fine des droits                       | ACL, rôles personnalisés, droits par fichier/dossier   | Haute      | À faire  |
| Mode collaboratif / multi-auteurs             | Plusieurs propriétaires, édition simultanée            | Haute      | À faire  |
| Historique et versionning                     | Audit trail, suivi des accès et modifications          | Moyenne    | À faire  |
| Notifications temps réel                      | Webhooks, emails, intégrations externes                | Moyenne    | À faire  |
| Upload avancé                                 | Drag & drop, multi-fichiers, gestion erreurs           | Moyenne    | À faire  |
| Support GraphQL                               | API GraphQL en plus du REST                            | Basse      | À faire  |
| Groupes et équipes                            | Partage multi-utilisateurs, gestion de groupes         | Moyenne    | À faire  |
| Quotas et alertes de stockage                 | Limites personnalisées, alertes utilisateurs           | Basse      | À faire  |
| Sécurité renforcée                            | 2FA, audit, logs avancés                               | Haute      | À faire  |

---

Ce tableau est mis à jour à chaque évolution majeure. Les priorités sont susceptibles d’évoluer selon les besoins utilisateurs et la roadmap.

## Limites connues

- Un seul propriétaire par espace privé (pas de multi-auteurs/collaboratif natif)
- Droits d’accès fins limités (pas de gestion granulaire par fichier/dossier, pas de rôles personnalisés)
- Pas d’historique détaillé des modifications ou des accès par fichier
- Pas de gestion avancée des quotas ou du stockage par utilisateur
- Notifications limitées (pas de webhooks externes, pas de notifications temps réel)
- API uniquement REST (pas encore de support GraphQL)
- Pas de gestion de groupes d’utilisateurs ou d’équipes

## Évolutions prévues

- Ajout d’un mode collaboratif (multi-auteurs, gestion des co-propriétaires)
- Gestion avancée des droits (rôles personnalisés, ACL par fichier/dossier)
- Historique complet des modifications et accès (audit trail, versionning)
- Notifications temps réel (webhooks, emails, intégrations externes)
- Support GraphQL en complément du REST
- Gestion des groupes, équipes et partages multi-utilisateurs
- Quotas et alertes de stockage personnalisables
- Amélioration de la sécurité (2FA, audit, logs renforcés)

---

> Ce document est mis à jour à chaque évolution majeure. Pour toute suggestion, ouvrez une issue sur GitHub.
