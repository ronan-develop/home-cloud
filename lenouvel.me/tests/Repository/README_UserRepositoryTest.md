# Couverture de tests sur UserRepository

## Pourquoi le coverage n'est pas à 100% ?

La classe `UserRepository` ne contient qu'une seule méthode métier pertinente : `upgradePassword`. Le constructeur et les méthodes commentées/générées ne comportent aucune logique métier à tester.

- Le coverage tool compte le constructeur comme une méthode, mais il n'a pas d'intérêt métier à être testé.
- Les tests couvrent tous les cas d'usage métier réels : succès, échec, cas limites.

## Bonnes pratiques appliquées

- Seules les méthodes métier sont testées (pas de test du constructeur ou de méthodes générées).
- Les tests couvrent :
  - La mise à jour effective du mot de passe
  - Les cas d'erreur métier (ex : utilisateur sans username)
- Toute nouvelle méthode métier devra être testée de façon similaire.

## Justification documentaire

Le coverage affiché à 50% sur la classe est donc **pertinent et assumé**. Il n'est pas nécessaire d'ajouter des tests artificiels pour le constructeur ou des méthodes sans logique métier.

> Ce choix est documenté pour garantir la pertinence, la maintenabilité et la robustesse des tests.
