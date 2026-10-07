# Runbook : en-têtes de sécurité nginx (API / app / `/storage/`)

Statut : **préparé, NON appliqué**. Toute modification du nginx de production
exige l'accord du propriétaire. À ce jour, le nginx de production ne pose
**aucun** en-tête de sécurité.

Contexte : les médias publics (photos de profil, albums, logos, vidéos) sont
servis depuis `/storage/` sur le domaine de l'API. Le code applicatif dérive
désormais l'extension stockée du contenu réel (allowlist), mais les en-têtes
ci-dessous restent la défense en profondeur : un fichier servi avec le mauvais
type ne doit jamais pouvoir s'exécuter dans le contexte du domaine.

## 1. Blocs `server` (API et app)

À placer dans chaque bloc `server { ... }` concerné (HTTPS). `always` garantit
la présence de l'en-tête aussi sur les réponses 4xx/5xx.

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;

# HSTS : commencer court (5 minutes), augmenter progressivement.
add_header Strict-Transport-Security "max-age=300" always;
```

Notes :

- `X-Frame-Options: DENY` convient si aucune page n'est embarquée dans une
  iframe. Si la SPA s'embarque elle-même (ou est affichée dans une iframe du
  même domaine), utiliser `SAMEORIGIN`.
- `Permissions-Policy` minimal : adapter si l'app utilise la caméra, le micro
  ou la géolocalisation côté navigateur (retirer alors la directive concernée
  ou la passer à `(self)`).
- HSTS : démarrer à `max-age=300`, puis `86400`, `604800`, enfin `31536000`
  une fois tous les sous-domaines confirmés en HTTPS. Ne pas ajouter
  `includeSubDomains` ni `preload` sans validation explicite : ils sont
  difficiles à annuler.

## 2. `location /storage/`

Attention : en nginx, un `add_header` posé dans une `location` **remplace**
tous ceux hérités du `server`. Il faut donc répéter les en-têtes du bloc 1
dans cette `location`.

```nginx
location /storage/ {
    # (conserver ici le root/alias/try_files existant)

    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Security-Policy "sandbox; default-src 'none'; img-src 'self'; media-src 'self'" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=300" always;
}
```

Effet :

- `nosniff` : le navigateur respecte le `Content-Type` déclaré, pas de
  détection de contenu (un `.jpg` contenant du HTML n'est pas interprété).
- `Content-Security-Policy: sandbox; default-src 'none'; ...` : si un fichier
  est ouvert directement, il est exécuté dans une origine opaque, sans script
  ni ressource externe ; `img-src` et `media-src` autorisent uniquement le
  rendu d'images/vidéos de la même origine.

Considérations `Content-Disposition` :

- Ne pas forcer `Content-Disposition: attachment` sur `/storage/` : les
  `<img>` et `<video>` du site chargent ces URL directement, et `attachment`
  n'empêche pas l'affichage embarqué mais complique l'ouverture dans un
  onglet. Les téléchargements explicites (livrables UGC) passent par
  l'application Laravel, qui pose déjà `attachment`.
- Option plus stricte si les médias n'ont jamais besoin d'être ouverts
  directement dans un onglet : ajouter `add_header Content-Disposition
  "attachment" always;` pour les seules extensions non-média
  (`location ~* \.(?!jpe?g$|png$|webp$|mp4$|mov$|avi$)`), à tester avant.
- Les fichiers historiques déjà stockés avec une extension douteuse (`.html`,
  `.svg`…) sont neutralisés par `nosniff` + CSP `sandbox`. Un audit du
  répertoire `storage/app/public` pour repérer ces fichiers reste recommandé :
  `find storage/app/public -type f ! -iregex '.*\.\(jpe?g\|png\|webp\|mp4\|mov\|avi\)$'`.

## 3. Déploiement

1. Sauvegarder la configuration : `sudo cp -a /etc/nginx /etc/nginx.bak-$(date +%F)`.
2. Appliquer les directives (fichier de site, ou un snippet
   `/etc/nginx/snippets/security-headers.conf` inclus via `include`).
3. Valider la syntaxe : `sudo nginx -t`.
4. Recharger sans coupure : `sudo systemctl reload nginx`.
5. Vérifier (section 4), puis parcourir l'application (connexion, upload photo,
   lecture vidéo, paiement FedaPay) pour détecter une régression.

## 4. Vérification

```bash
# Page / API
curl -sI https://<domaine-api>/api/v1/health | grep -iE 'x-content-type|x-frame|referrer|strict-transport|permissions'

# Média public (remplacer par un chemin réel)
curl -sI https://<domaine-api>/storage/avatars/faces/<fichier>.jpg \
  | grep -iE 'x-content-type|content-security|content-type'
```

Attendu : `X-Content-Type-Options: nosniff` partout, la CSP `sandbox` sur
`/storage/`, et un `Content-Type` cohérent avec l'extension (`image/jpeg`).

## 5. Retour arrière

1. Retirer les directives ajoutées (ou le `include` du snippet), ou restaurer
   la sauvegarde : `sudo cp -a /etc/nginx.bak-<date>/. /etc/nginx/`.
2. `sudo nginx -t && sudo systemctl reload nginx`.
3. Re-vérifier avec `curl -I` que les en-têtes ont disparu.

Cas HSTS : un en-tête HSTS déjà reçu reste mémorisé par les navigateurs
jusqu'à expiration de `max-age` ; c'est la raison de démarrer avec une valeur
courte. Pour l'annuler, servir `max-age=0` en HTTPS.
