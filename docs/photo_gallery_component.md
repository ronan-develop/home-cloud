# 🖼️ Composant UX : Galerie photo avec lazy loading

Le composant Symfony UX `photo_gallery` permet d’afficher la galerie de photos utilisateur avec lazy loading, sécurité et responsive design.

- **Emplacement PHP** : `src/Twig/Component/PhotoGalleryComponent.php`
- **Template Twig** : `templates/components/photo_gallery.html.twig`
- **Utilisation** :

  ```twig
  {{ component('photo_gallery', { photos: photos }) }}
  ```

- **Lazy loading** : via l’attribut `loading="lazy"` sur les balises `<img>`
- **Sécurité** : les URLs d’images pointent vers le contrôleur sécurisé (`photo_view`)
- **Mobile first** : grid responsive Tailwind, conforme `.github/copilot-instructions.md`

> Pour toute évolution, respecter le pattern mobile first et la logique d’accès sécurisé.

---

[⬅️ Retour à la documentation principale](../README.md)
