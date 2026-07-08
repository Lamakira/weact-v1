# Runbook — Cache headers images `/storage` + rétrofit des variantes

> Contexte : spec « Optimisation intérimaire du rendu des images (sans R2) ».
> La conf nginx n'est **pas** dans le repo — ce runbook est la référence à appliquer
> manuellement sur le(s) serveur(s). Réutilisable tel quel après migration R2
> (seul le disque/origin changera).

## 1. Pourquoi c'est sûr de mettre `immutable`

Tous les fichiers servis sous `/storage/avatars/**` portent un **basename UUID**
(`3f2a….jpg`, `3f2a….webp`). Un fichier n'est **jamais modifié en place** : tout
nouvel upload génère un nouvel UUID, et la suppression retire le fichier. Une URL
donnée pointe donc vers un contenu immuable → `max-age` d'un an + `immutable`
sans risque de contenu périmé.

## 2. Bloc nginx à ajouter

Dans le `server {}` qui sert le site (avant le `location /` générique) :

```nginx
# Médias utilisateur (originaux + variantes thumbnails/medium/grid/large).
# Fichiers à basename UUID, immuables par construction.
location /storage/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    access_log off;
    try_files $uri =404;
}
```

Notes :

- `add_header` remplace les éventuels headers `Cache-Control` posés ailleurs pour
  ce `location` — vérifier qu'aucun autre `add_header Cache-Control` ne s'applique
  en amont (les `add_header` nginx ne s'héritent pas si le bloc en définit un).
- Ne PAS appliquer ce bloc aux réponses API (`/api/...`) ni au `index.html` du
  frontend : uniquement `/storage/`.
- Après modification : `nginx -t && systemctl reload nginx`.

## 3. Consignes deploy (ordre impératif)

1. **Migrer la base** : `php artisan migrate` (colonnes `profile_photo_grid`,
   `profile_photo_large`, `grid`, `large`).
2. **Vérifier que le worker de queue tourne** (queue `database`, workers prod
   existants) : les uploads ne génèrent plus AUCUNE variante dans la requête HTTP,
   tout part dans le job `GenerateImageVariants`. Sans worker, les nouvelles photos
   restent servies via le fallback (original) et les colonnes variantes restent null.
3. **Lancer le rétrofit UNE FOIS post-deploy** :

   ```bash
   # Estimation d'abord (aucune écriture) :
   php artisan images:generate-variants --dry-run

   # Puis le run réel (~3000 Faces / ~20 GB — prévoir plusieurs dizaines de minutes,
   # lancer dans tmux/screen ; idempotent et reprenable : relancer reprend là où
   # c'était rendu, sans jamais régénérer l'existant) :
   php artisan images:generate-variants
   ```

4. **Appliquer le bloc nginx** (section 2) puis `nginx -t && systemctl reload nginx`.

## 4. Vérification post-deploy

- DevTools réseau sur une grille publique (`/faces`) : les cartes chargent des
  `.webp` sous `.../grid/`, réponse avec `Cache-Control: public, max-age=31536000, immutable`.
- Profil public : hero en `.../large/` (ou fallback), lightbox en `large/`,
  l'original ne se charge qu'au clic « Voir l'original ».
- Re-lancer `php artisan images:generate-variants` : doit afficher 0 générée,
  tout en « skipped » (idempotence, log `images:generate-variants terminé` à l'appui).
