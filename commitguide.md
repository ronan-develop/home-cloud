# Guide de commit

## Structure à respecter

```txt
[type] : nom de la feature ou du fix
Courte description sur une ou deux lignes
```

### Types de commit et emojis associés

- ✨ **feat** : Ajout d’une nouvelle fonctionnalité  
  _Exemple_ :  

  ```txt
  ✨ feat : gestion des utilisateurs
  Ajoute la création et la suppression d’utilisateurs dans l’interface d’administration.
  ```

- 🐛 **fixes** : Correction de bug  
  _Exemple_ :  

  ```txt
  🐛 fixes : connexion base de données
  Corrige un problème de connexion lors de l’utilisation de PostgreSQL.
  ```

- 📝 **docs** : Documentation  
  _Exemple_ :  

  ```txt
  📝 docs : mise à jour du README
  Ajoute la section sur le déploiement de FrankenPHP.
  ```

- 🙈 **ignore** : Modification du `.gitignore`  
    _Exemple_ :  

```txt
🙈 ignore : ajout de fichiers temporaires
Ajoute les fichiers `.env.local` et `/tmp` au `.gitignore`.
```
