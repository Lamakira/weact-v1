# Runbook : nettoyage rétroactif des métadonnées médias

Commande : `php artisan media:strip-metadata --isolated`

Contexte : depuis le durcissement des uploads, les NOUVELLES images sont
nettoyées sans perte (EXIF/GPS/XMP/IPTC retirés, profil ICC conservé,
Orientation conservée dans un EXIF minimal) et les NOUVELLES vidéos sont
remuxées sans métadonnées. Cette commande applique le même traitement aux
fichiers DÉJÀ stockés (mesure en production avant déploiement : 808 JPEG avec
GPS sur 12 772, 22 vidéos publiques sur 515 avec localisation).

## Ce qui est fait aux fichiers

- Images (JPEG, PNG), au niveau des octets, SANS décodage ni ré-encodage des pixels :
  - JPEG, par ALLOWLIST de segments. Conservés : APP0 `JFIF` (réécrit sous sa forme
    canonique de 16 octets : version, unités et densité conservées, vignette 0x0),
    APP2 seulement s'il porte un profil ICC (`ICC_PROFILE`, multi-chunks dans
    l'ordre), APP14 `Adobe` (limité à ses 12 octets) et les segments de décodage
    (DQT, DHT, SOF, DRI, SOS…). Retirés : `JFXX` (il peut embarquer un JPEG avec son
    propre Exif), APP1 (Exif et XMP), APP2 non-ICC (dont MPF), APP3, APP11
    (JUMBF/C2PA), APP12, APP13, APP15… et les commentaires COM.
  - JPEG, octets APRÈS l'image principale : tout ce qui suit l'EOI de l'image
    principale est supprimé (images MPF secondaires avec leur propre Exif/GPS,
    trailers Samsung SEFT, vidéo des Motion Photos Google/Samsung), de même qu'un
    segment final tronqué. Pour les détecter, le fichier est parcouru en entier
    (données entropiques comprises). La détection (dry run) et l'application voient
    exactement les mêmes choses.
  - JPEG, orientation : si l'EXIF d'origine avait une Orientation différente de 1,
    un APP1 minimal ne contenant QUE l'Orientation est réinséré (la photo reste à
    l'endroit dans les navigateurs ; les pixels ne sont PAS pivotés ; ni GPS, ni
    appareil, ni date ne survivent).
  - PNG, par ALLOWLIST de chunks (IHDR, PLTE, IDAT, IEND, tRNS, cHRM, gAMA, iCCP,
    sBIT, sRGB, cICP, mDCv, cLLi, bKGD, hIST, pHYs, sPLT, acTL, fcTL, fdAT). Tout le
    reste est retiré : `tIME`, `tEXt`/`iTXt`/`zTXt`, `caBX` (C2PA), chunks privés. Un
    `eXIf` avec une Orientation ≠ 1 est remplacé par un `eXIf` minimal (Orientation
    seule), sinon supprimé. Les octets après `IEND` sont supprimés.
  - Le format est reconnu par les octets magiques, pas par l'extension. Les octets
    parasites entre segments d'en-tête d'un JPEG sont tolérés (comme libjpeg) ;
    sont REFUSÉS : un segment de longueur < 2, un second en-tête de frame (SOF)
    avant la fin de l'image principale, et un fichier sans SOS valide.
- Plafonds de taille d'image (à l'upload ET dans les jobs de queue) : 12000 px par
  côté et 52 millions de pixels au total (garde les 48/50 MP des téléphones,
  8160x6144 = 50,1 MP ; refuse les modes 108/200 MP). La mesure porte sur ce que le
  décodeur lira réellement (premier SOF / IHDR du fichier NETTOYÉ), pas seulement sur
  `getimagesize()` de l'original, et échoue FERMÉE : une dimension illisible est un
  refus. Les jobs qui décodent (`GenerateImageVariants`, vignette de logo d'agence)
  relisent l'en-tête avant de décoder : un fichier stocké hors plafonds ou illisible
  n'est jamais décodé ; le job loggue un warning (`image non décodée`) et abandonne
  sans exception ni retry (la commande `images:generate-variants` compte la ligne en
  échec). Contexte :
  GD (libgd système) alloue ses tampons en dehors de `memory_limit`.
- Vidéos : remux ffmpeg en copie de flux (`-c copy`, sans ré-encodage) ne gardant
  que les flux vidéo et audio (`-map 0:v -map 0:a?`) : les pistes de données,
  sous-titres et télémétrie (qui peuvent contenir le GPS) sont écartées, ainsi
  que les tags globaux, de flux et les chapitres. La rotation (display matrix) est
  conservée. Si le `-c copy` échoue (codec audio inconnu, par exemple l'audio
  spatial `apac` des iPhone récents avec ffmpeg 6.1, que ffmpeg ne sait pas
  copier), un repli sonde les flux avec ffprobe puis retente UNE fois, toujours en
  copie de flux, en ne mappant que la vidéo et les flux audio dont le codec est dans
  une allowlist (aac, mp3, opus, vorbis, flac, alac, ac3, eac3, pcm_s16le,
  pcm_s24le, pcm_f32le) ; les autres flux audio sont écartés. Aucun ré-encodage.
  S'il n'existe AUCUN flux audio allowlisté, l'audio n'est jamais supprimé en
  silence : l'original est conservé, un warning est loggé et le fichier est compté
  comme ÉCHEC (listé en fin de commande) pour traitement manuel. Il en va de même si
  le repli échoue à son tour : l'original reste en place, avec ses métadonnées.
- Chemin, nom de fichier ET mode (permissions) inchangés : les lignes en base les
  référencent et les fichiers restent lisibles par le serveur web.
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
- Les temporaires d'un run interrompu (`*.stripping.<aléa>.tmp`, `*.stripped.<aléa>.<ext>`) ne
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

Code de sortie : 0 si aucun échec, 1 sinon. Chaque échec est listé en fin de
sortie (disque, chemin, cause ; 50 premiers) et loggé en `warning`. Un échec ne
consomme PAS de slot de `--limit` : un fichier en échec déterministe est ignoré
et le lot continue sur les fichiers suivants (il réapparaîtra à chaque passage
tant qu'il n'est pas traité à la main).

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

## Durées et volumes attendus (estimations, à confirmer sur le premier lot)

- Images : le nettoyage est une copie d'octets sans décodage, en mémoire
  constante. En revanche, la détection (dry run comme `--apply`) lit chaque fichier
  EN ENTIER pour retrouver l'EOI et d'éventuelles données après l'image : prévoir
  une lecture complète des ~12 800 JPEG (plusieurs dizaines de Go), donc des
  minutes à quelques dizaines de minutes selon le disque. Le nettoyage des
  fichiers concernés ajoute une réécriture (quelques dizaines de ms chacun).
- Le nombre de fichiers « avec métadonnées » sera supérieur aux ~808 JPEG avec GPS
  mesurés : sont aussi comptés les APP1 sans GPS (appareil, dates), XMP, IPTC,
  commentaires, MPF et données après l'EOI. Le dry run donne le chiffre réel.
- Vidéos : 522 vidéos et plus (515 publiques mesurées + livrables privés). La
  plupart seront remuxées, pas seulement les ~22 avec localisation : `creation_time`,
  `encoder`, les tags de flux et les pistes de données/sous-titres comptent comme
  métadonnées. Compter un `ffprobe` (< 1 s) puis un remux en copie de flux par
  fichier : quelques secondes chacun (dominé par la copie disque, fichiers jusqu'à
  200 Mo, timeout de 120 s par tentative, deux tentatives au maximum avec le
  repli audio). Prévoir donc de l'ordre de la demi-heure à l'heure pour
  l'ensemble, à lancer par lots.
- Espace disque : le remux écrit une copie temporaire à côté de l'original. Prévoir
  un espace libre d'au moins la taille de la plus grosse vidéo (200 Mo au plus),
  en pratique quelques centaines de Mo de marge suffisent car les fichiers sont
  traités un par un.

## Comportement en cas d'échec

Chaque fichier est écrit dans un fichier temporaire du même dossier (nom unique),
puis renommé atomiquement sur l'original, UNIQUEMENT après un nettoyage / remux
réussi ET si l'original est toujours le même fichier (empreinte inode / taille /
mtime relue juste avant le rename, pour les images comme pour les vidéos) : une
photo supprimée ou remplacée pendant le nettoyage n'est jamais ressuscitée, le
temporaire est jeté. En cas d'échec (image corrompue, ffmpeg en erreur, disque plein) :
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
perte (aucun ré-encodage des pixels) ; celui des vidéos est une copie de flux (aucun
ré-encodage, y compris dans le repli, qui écarte seulement des flux audio non copiables). En
revanche **le remplacement est définitif** : une fois nettoyé, le fichier
d'origine (avec son EXIF/GPS, et pour les vidéos ses pistes de données et
sous-titres) n'existe plus. La seule voie de retour est la sauvegarde de
`storage/app` faite à l'étape 1 : restaurer les fichiers concernés depuis
l'archive.
