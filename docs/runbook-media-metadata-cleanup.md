# Runbook : nettoyage rétroactif des métadonnées médias

Commande : `php artisan media:strip-metadata`

Contexte : depuis le durcissement des uploads, les NOUVELLES images sont
ré-encodées (EXIF/GPS supprimé, orientation appliquée aux pixels) et les
NOUVELLES vidéos sont remuxées sans métadonnées. Cette commande applique le
même traitement aux fichiers DÉJÀ stockés (mesure en production avant
déploiement : 808 JPEG avec GPS sur 12 772, 22 vidéos publiques sur 515 avec
localisation).

## Ce qui est traité

- Disque `public` : `avatars/faces`, `avatars/producers`, `avatars/faces/albums`,
  `logos/agencies`, `articles/featured`, `products` (images jpg/jpeg/png) ;
  `videos/faces/acting`, `videos/faces/ugc`, `videos/faces/presentation`
  (mp4/mov/avi).
- Disque UGC privé (`config('ugc.storage_disk')`) : `products` (photos produit
  et photos de réception), `ugc/deliverables` (vidéos livrables).
- Originaux uniquement : les variantes et miniatures (sous-dossiers
  `thumbnails`, `medium`, `grid`, `large`) sont déjà des ré-encodages sans EXIF.
- Chemin et nom de fichier inchangés (les lignes en base les référencent).
- Idempotent : un fichier propre est ignoré, on peut relancer à volonté.

## Options

| Option | Effet |
|---|---|
| (aucune) | Dry run : compte par dossier (scannés / avec métadonnées / à nettoyer / échecs), ne modifie rien |
| `--apply` | Nettoie réellement |
| `--limit=N` | S'arrête après N fichiers à nettoyer (ou signalés en dry run) : traitement par lots |
| `--path=PREFIXE` | Restreint aux dossiers sous ce préfixe, ex. `--path=avatars/faces` ou `--path=ugc/deliverables` |

Code de sortie : 0 si aucun échec, 1 sinon (détail dans les logs, niveau
`warning`, avec disque et chemin).

## Procédure

1. **Sauvegarder `storage/app`** avant `--apply` (voir « Retour arrière »).
   Exemple : `tar czf /root/backup-storage-app-$(date +%F).tar.gz storage/app`
   (ou snapshot du volume).
2. Dry run, pour lire les volumes : `php artisan media:strip-metadata`
3. Appliquer par lots, en commençant par les images publiques :
   - `php artisan media:strip-metadata --apply --path=avatars --limit=200`
   - puis `--path=logos`, `--path=products`, `--path=articles`
   - puis les vidéos : `--path=videos/faces`, `--path=ugc/deliverables`
   Relancer la même commande jusqu'à « 0 avec métadonnées ».
4. Dry run final : tout doit afficher 0 « avec métadonnées » et 0 échec.

La commande est `Isolatable` : deux exécutions simultanées sont refusées.

## Durées attendues (estimations, à confirmer sur le premier lot)

- Images : une inspection d'en-tête (lecture de quelques Ko) puis, pour les
  seuls fichiers concernés, un décodage et ré-encodage GD : de l'ordre de
  0,1 à 0,5 s par fichier ; scan des 12 772 JPEG en quelques minutes, nettoyage
  des ~808 concernés en quelques minutes.
- Vidéos : un `ffprobe` par fichier (< 1 s), puis remux en copie de flux
  (sans ré-encodage) pour les ~22 concernées : quelques secondes chacune, le
  temps étant dominé par la copie disque (fichiers jusqu'à 200 Mo).
- Prévoir un espace disque libre d'au moins la taille du plus gros fichier
  (écriture dans un fichier temporaire à côté de l'original).

## Comportement en cas d'échec

Chaque fichier est écrit dans un fichier temporaire du même dossier, puis
renommé atomiquement sur l'original, UNIQUEMENT après un ré-encodage / remux
réussi. En cas d'échec (image illisible, ffmpeg en erreur, disque plein) :
l'original n'est pas touché, le temporaire est supprimé, un warning est loggé,
l'échec est compté et la commande continue. Les échecs persistants se
repèrent dans le récapitulatif et les logs ; un fichier corrompu doit être
examiné à la main.

Un remux en échec lors d'un NOUVEL upload ne fait pas échouer l'upload : le
fichier est conservé tel quel, un warning est loggé (avec le chemin), et cette
commande le rattrapera au passage suivant.

## Retour arrière

Aucun retour arrière automatique n'est nécessaire dans le sens où un original
n'est remplacé qu'après un ré-encodage réussi. En revanche, **le remplacement
est définitif** : une fois nettoyé, le fichier d'origine (avec son EXIF/GPS) n'existe
plus, et les JPEG sont ré-encodés avec perte (qualité 85). Si l'on veut pouvoir
revenir en arrière (par exemple pour constater une dégradation visuelle), la
seule voie est la sauvegarde de `storage/app` faite à l'étape 1 : restaurer les
fichiers concernés depuis l'archive.
