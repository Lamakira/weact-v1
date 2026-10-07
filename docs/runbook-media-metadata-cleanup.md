# Runbook : nettoyage rétroactif des métadonnées médias

Commande : `php artisan media:strip-metadata --isolated`

Contexte : depuis le durcissement des uploads, les NOUVELLES images sont
nettoyées sans perte (EXIF/GPS/XMP/IPTC retirés, profil ICC conservé,
Orientation conservée dans un EXIF minimal) et les NOUVELLES vidéos sont
remuxées sans métadonnées. Cette commande applique le même traitement aux
fichiers DÉJÀ stockés (mesure en production avant déploiement : 808 JPEG avec
GPS sur 12 772, 22 vidéos publiques sur 515 avec localisation).

## Ce qui est fait aux fichiers

- Images (JPEG, PNG), au niveau des octets, SANS décodage ni ré-encodage :
  - JPEG : segments APP1 (Exif et XMP), APP13 (IPTC/Photoshop) et COM retirés ;
    APP0 (JFIF), APP2 (ICC), APP14 (Adobe) et tout le flux d'image conservés
    tels quels. Si l'EXIF d'origine avait une Orientation différente de 1, un
    APP1 minimal ne contenant QUE l'Orientation est réinséré (la photo reste à
    l'endroit dans les navigateurs ; ni GPS, ni appareil, ni date ne survivent).
  - PNG : chunks `eXIf`, `tEXt`, `iTXt`, `zTXt` retirés ; le reste est copié.
  - Le format est reconnu par les octets magiques, pas par l'extension.
- Vidéos : remux ffmpeg en copie de flux (`-c copy`, sans ré-encodage) ne gardant
  que les flux vidéo et audio (`-map 0:v -map 0:a?`) : les pistes de données,
  sous-titres et télémétrie (qui peuvent contenir le GPS) sont écartées, ainsi
  que les tags globaux et les chapitres. La rotation (display matrix) est
  conservée.
- Chemin et nom de fichier inchangés (les lignes en base les référencent).
- Idempotent : un fichier propre est ignoré, on peut relancer à volonté.

## Ce qui est traité

- Disque `public` : `avatars/faces`, `avatars/producers`, `avatars/faces/albums`,
  `logos/agencies`, `articles/featured`, `products` (images) ;
  `videos/faces/acting`, `videos/faces/ugc`, `videos/faces/presentation`.
- Disque UGC privé (`config('ugc.storage_disk')`) : `products` (photos produit
  et photos de réception), `ugc/deliverables` (vidéos livrables).
- Originaux uniquement : les variantes et miniatures (sous-dossiers
  `thumbnails`, `medium`, `grid`, `large`) sont déjà des ré-encodages sans EXIF.
- Les fichiers sont sélectionnés par octets magiques : une extension atypique
  (`.jfif`, `.jpe`, `.m4v`, aucune) est traitée. Les fichiers non reconnus sont
  comptés et loggés (colonne « ignorés »), jamais modifiés.
- Les temporaires d'un run interrompu (`*.stripping.tmp`, `*.stripped.*`) ne
  sont jamais traités comme médias ; avec `--apply`, ceux de plus d'une heure
  sont supprimés.

## Options

| Option | Effet |
|---|---|
| (aucune) | Dry run : compte par dossier (scannés / avec métadonnées / à nettoyer / échecs / ignorés), ne modifie rien |
| `--apply` | Nettoie réellement |
| `--limit=N` | S'arrête après N fichiers à nettoyer (ou signalés en dry run) : traitement par lots |
| `--path=PREFIXE` | Restreint aux dossiers sous ce préfixe, ex. `--path=avatars/faces` ou `--path=ugc/deliverables` |
| `--isolated` | **À mettre à chaque fois.** Sans lui, la protection contre deux exécutions simultanées (`Isolatable`) n'est PAS active. |

Code de sortie : 0 si aucun échec, 1 sinon (détail dans les logs, niveau
`warning`, avec disque et chemin).

## Utilisateur d'exécution

Les propriétaires ne sont pas préservés : un fichier réécrit appartient à
l'utilisateur qui lance la commande. Lancer la commande avec l'utilisateur du
serveur web, sinon PHP-FPM ne pourra plus écraser/supprimer ces fichiers :

```bash
sudo -u www-data php artisan media:strip-metadata --isolated
```

(adapter `www-data` à l'utilisateur réel du pool PHP-FPM). Les permissions
(mode) sont reprises de l'original.

## Cache : les fichiers sont modifiés EN PLACE

Les URL `/storage/...` ne changent pas alors que le contenu change. Le runbook
`docs/runbook-images-cache-headers.md` suppose des fichiers immuables
(`Cache-Control: public, max-age=31536000, immutable`). Après `--apply`, il faut
**purger le cache** de `/storage/*` s'il existe un cache partagé ou un CDN
devant le serveur (purge ciblée des chemins concernés, ou purge complète de
`/storage/`). Les navigateurs qui ont déjà mis un fichier en cache garderont
l'ancienne version (avec ses métadonnées) jusqu'à expiration ; ce n'est pas une
fuite côté serveur mais il faut en avoir conscience.

## Procédure

1. **Sauvegarder `storage/app`** avant `--apply`.
   Exemple : `tar czf /root/backup-storage-app-$(date +%F).tar.gz storage/app`
   (ou snapshot du volume).
2. Dry run, pour lire les volumes :
   `sudo -u www-data php artisan media:strip-metadata --isolated`
3. Appliquer par lots, en commençant par les images publiques :
   - `sudo -u www-data php artisan media:strip-metadata --isolated --apply --path=avatars --limit=200`
   - puis `--path=logos`, `--path=products`, `--path=articles`
   - puis les vidéos : `--path=videos/faces`, `--path=ugc/deliverables`
   Relancer la même commande jusqu'à « 0 avec métadonnées ».
4. Dry run final : tout doit afficher 0 « avec métadonnées » et 0 échec.
5. Purger le cache `/storage/` (section précédente).

## Durées attendues (estimations, à confirmer sur le premier lot)

- Images : le nettoyage est une copie d'octets sans décodage, en mémoire
  constante : de l'ordre de quelques dizaines de millisecondes par fichier
  (lecture + écriture du fichier). Le scan des 12 772 JPEG (lecture des seuls
  en-têtes) et le nettoyage des ~808 concernés se comptent en secondes à
  quelques minutes, dominés par les accès disque.
- Vidéos : un `ffprobe` par fichier (< 1 s), puis remux en copie de flux pour
  les ~22 concernées : quelques secondes chacune (temps dominé par la copie
  disque, fichiers jusqu'à 200 Mo, timeout du remux : 120 s).
- Prévoir un espace disque libre d'au moins la taille du plus gros fichier
  (écriture dans un fichier temporaire à côté de l'original).

## Comportement en cas d'échec

Chaque fichier est écrit dans un fichier temporaire du même dossier, puis
renommé atomiquement sur l'original, UNIQUEMENT après un nettoyage / remux
réussi. En cas d'échec (image corrompue, ffmpeg en erreur, disque plein) :
l'original n'est pas touché, le temporaire est supprimé, un warning est loggé,
l'échec est compté et la commande continue. Les échecs persistants se
repèrent dans le récapitulatif et les logs ; un fichier corrompu doit être
examiné à la main.

Un remux en échec lors d'un NOUVEL upload ne fait pas échouer l'upload : le
fichier est conservé tel quel, un warning est loggé (avec le chemin), et cette
commande le rattrapera au passage suivant. Ce remux est exécuté après la
transaction de base de données, jamais sous un verrou.

## Retour arrière

Aucun retour arrière automatique n'est nécessaire dans le sens où un original
n'est remplacé qu'après un nettoyage réussi. Le nettoyage des images est sans
perte (aucun ré-encodage) ; celui des vidéos est une copie de flux. En
revanche **le remplacement est définitif** : une fois nettoyé, le fichier
d'origine (avec son EXIF/GPS, et pour les vidéos ses pistes de données et
sous-titres) n'existe plus. La seule voie de retour est la sauvegarde de
`storage/app` faite à l'étape 1 : restaurer les fichiers concernés depuis
l'archive.
