# Brief — Migration médias WeAct vers Cloudflare R2 + optimisation images

> **Instruction pour Claude Code :** Ce brief résume les décisions d'architecture prises en amont. Analyse d'abord l'état réel du code (structure du stockage actuel, modèles concernés, format des URLs en base, config filesystems, présence ou non d'une queue) et adapte les détails d'implémentation en conséquence. Les objectifs et contraintes ci-dessous sont fermes ; les modalités techniques sont à ajuster selon le code existant.

## Contexte

- **Plateforme :** WeAct (Vue 3 + TS + Pinia / Laravel 12 + MySQL + Sanctum), hébergée sur VPS Hostinger à **Paris**. Utilisateurs à **Cotonou, Bénin**.
- **État actuel :** ~3 000 Faces inscrites, ≥1 photo chacune. ~20 GB de médias stockés en local sur le VPS (capacité 100 GB).
- **Problème :** Chargement lent des images pour les utilisateurs au Bénin. Cause principale : latence Paris ↔ Cotonou (~120-180 ms/requête), aggravée probablement par des images servies en taille originale, sans variantes ni cache.

## Décisions prises (fermes)

1. **Rester sur le VPS Paris** pour l'app (fin d'abonnement annuel Hostinger) — seule la couche média migre.
2. **Stockage : Cloudflare R2** (S3-compatible, egress gratuit, ~0,30 $/mois à cette échelle).
3. **Distribution : domaine custom `media.weact.bj`** attaché au bucket R2, derrière le **proxy Cloudflare (orange cloud)** → cache servi depuis le PoP de Lagos pour les utilisateurs béninois. Plan Cloudflare **Free** suffisant.
4. **Prérequis externe (hors code) :** les nameservers de `weact.bj` doivent être chez Cloudflare. À vérifier avant déploiement.
5. **Compression/variantes : côté Laravel sur le VPS** (pas de Cloudflare Image Resizing, payant). R2 ne transforme rien — il stocke tel quel.

## Stratégie images : original + variantes

Pour chaque photo :

| Version | Spécification | Usage |
|---|---|---|
| **Original** | Fichier intact, jamais modifié | Source de vérité, régénération future, téléchargement HQ |
| **Large** | ~1600px bord long, WebP qualité 82-85 | Vue profil plein écran |
| **Thumbnail** | ~400px, WebP | Grilles de Faces, cards, listes |

- Le frontend ne charge **jamais** l'original — toujours la variante adaptée au contexte.
- Génération avec **Intervention Image** (vérifier si déjà installé, sinon l'ajouter).
- Traitement en **Job de queue** à l'upload (jamais en synchrone dans la requête HTTP). Vérifier qu'un worker de queue tourne (Supervisor) ; sinon, le signaler comme prérequis d'infra. Prévoir `memory_limit` PHP ≥ 256 MB pour les photos de smartphone.
- Appliquer la même logique aux vidéos si pertinent (au minimum : thumbnails de vidéos, pas de transcodage pour l'instant).

## Flux d'upload cible

1. Upload utilisateur → serveur Laravel (comme actuellement)
2. Job en queue : génération des variantes
3. Upload original + variantes vers R2 via le driver S3 de Laravel (`league/flysystem-aws-s3-v3`)
4. Suppression du fichier temporaire local
5. En base : stocker des chemins/clés relatifs (pas d'URLs absolues en dur si possible) et construire les URLs publiques via `https://media.weact.bj/...`

## Configuration Laravel attendue

- Disque `r2` dans `config/filesystems.php` (driver `s3`, endpoint `https://<account-id>.r2.cloudflarestorage.com`, région `auto`, `url` => `https://media.weact.bj`)
- Credentials en `.env` (Access Key ID / Secret depuis le dashboard R2)
- Analyser comment les URLs médias sont actuellement stockées/générées dans le code (accessors, `Storage::url()`, colonnes en base, chemins en dur ?) et adapter.

## Script de migration des ~20 GB existants

À exécuter sur le VPS (fichiers déjà locaux, pas de téléchargement nécessaire) :

1. Pour chaque fichier média existant : générer les variantes → uploader original + variantes vers R2
2. Mettre à jour les références en base (URLs/chemins) — de préférence via une commande Artisan idempotente et reprenable (batch, log de progression, skip des fichiers déjà migrés)
3. **Ne pas supprimer les fichiers locaux immédiatement** — garder comme backup jusqu'à validation complète en prod, puis nettoyage dans un second temps
4. Alternative pour la copie brute des originaux : `rclone sync`, mais la génération des variantes nécessite de toute façon un passage par script PHP/Laravel

## Points d'attention

- **Cache headers :** configurer `Cache-Control` longs (ex. `public, max-age=31536000, immutable`) sur les objets R2 — les fichiers étant nommés par UUID, ils sont immuables, cache agressif sans risque
- **Noms de fichiers :** conserver le principe UUID non prédictibles (exigence sécurité NFR-S6/données perso du PRD)
- **Rollback :** tant que les fichiers locaux existent, un rollback = revert des URLs en base
- **Vérification post-migration :** script de contrôle comparant le nombre de fichiers locaux vs objets R2, et échantillonnage d'URLs pour valider le HTTP 200 via `media.weact.bj`
- **Uploads pendant la migration :** définir la bascule (feature flag ou déploiement coordonné) pour que les nouveaux uploads partent sur R2 dès que le code est prêt, afin d'éviter une double source

## Résultat attendu

- Images servies depuis le cache Cloudflare de Lagos (~20-40 ms) au lieu de Paris (~150 ms+)
- Poids des images divisé par ~10 sur les grilles (thumbnails WebP vs originaux)
- ~20 GB libérés sur le VPS (après période de backup)
- Coût : ~0,30 $/mois (R2), plan Cloudflare Free
