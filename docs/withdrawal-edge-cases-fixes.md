# Correction des edge cases du workflow de retrait

**Date** : 2026-03-25
**Branche** : `feature/notifications/add-missing-notifications`

---

## Contexte

Audit du workflow de retrait (mode manuel + FedaPay) ayant identifié 8 edge cases. 6 ont été corrigés, 2 étaient déjà gérés.

---

## Edge cases corrigés

### Fix 1 — Multiples demandes `pending` simultanées (mode manuel)

**Problème** : En mode manuel, le solde n'est pas réservé à la soumission. Une Face pouvait soumettre plusieurs demandes de retrait alors qu'une précédente était encore `pending`. À l'approbation admin, les suivantes échouaient avec une erreur 422 (solde insuffisant).

**Fix** : Ajout d'une validation cross-field dans `WithdrawWalletRequest::withValidator()` qui bloque la soumission si une demande `pending` existe déjà pour l'utilisateur.

**Fichier modifié** : `backend/app/Http/Requests/Booking/WithdrawWalletRequest.php`

---

### Fix 2 — Double soumission (double-submit) en mode FedaPay

**Problème** : Un double-click rapide pouvait déclencher deux appels simultanés. Le flux FedaPay ne crée aucun `WithdrawalRequest`, donc le check `pending` du Fix 1 ne le protège pas. Chaque appel concurrent générait une nouvelle `idempotency_key` UUID unique, ce qui aurait permis deux payouts FedaPay distincts contre un même solde.

**Fix** : `Cache::lock("withdrawal_fedapay_{$user->id}", 30)` ajouté dans `WithdrawalService::initiateFedapayWithdrawal()`. Le verrou est acquis avant toute opération et libéré dans un bloc `finally`. Si le verrou est déjà pris (requête concurrente), une `RuntimeException` est levée immédiatement avec le message *"Un retrait est déjà en cours pour ce compte. Veuillez patienter."*

**Pourquoi pas la DB transaction seule** : `lockForUpdate()` sur le wallet protège contre le dépassement de solde, mais deux transactions peuvent démarrer l'une après l'autre si la première se termine très vite — le verrou de cache garantit la sérialisation dès l'entrée, avant même l'ouverture de la transaction.

**Fichier modifié** : `backend/app/Services/WithdrawalService.php`

---

### Fix 3 — Pas de montant minimum métier

**Problème** : La validation acceptait `min:1`, permettant un retrait de 1 XOF alors que les frais de virement Mobile Money dépassent largement ce montant.

**Fix** : Minimum porté à **500 XOF** via la constante `MIN_AMOUNT = 500` dans `WithdrawWalletRequest`. Le message d'erreur est mis à jour en conséquence.

**Fichier modifié** : `backend/app/Http/Requests/Booking/WithdrawWalletRequest.php`

---

### Fix 4 — Numéro de téléphone incohérent avec l'opérateur

**Problème** : La validation acceptait n'importe quel numéro de 6–15 chiffres sans vérifier que le préfixe correspondait à l'opérateur sélectionné. Un numéro MTN pouvait être soumis avec l'opérateur Moov.

**Fix** : Ajout d'une validation des préfixes EZAB officiels (source : ARCEP Bénin) pour les numéros béninois (`phone_country = 'bj'`). Le format attendu est 10 chiffres (EZAB). Les 4 premiers chiffres sont comparés aux listes officielles par opérateur.

**Préfixes configurés** :
- **MTN** : 0142, 0146, 0150–0154, 0156–0157, 0159, 0161–0162, 0166–0167, 0169, 0190–0191, 0196–0197
- **Moov** : 0145, 0155, 0158, 0160, 0163–0165, 0168, 0194–0195, 0198–0199
- **Celtiis** : 0120–0124, 0128–0129, 0140–0141, 0143–0144, 0147–0149, 0192–0193

Pour les autres pays (`tg`, `ci`, `sn`, `bf`), la validation de base (format numérique) s'applique sans contrôle de préfixe.

**Fichier modifié** : `backend/app/Http/Requests/Booking/WithdrawWalletRequest.php`

---

### Fix 5 — `payout.failed` webhook FedaPay non géré

**Statut** : **Déjà géré** — aucune modification nécessaire.

`HandleFedapayWebhook` gère déjà `payout.failed` : le wallet est recrédité du montant du retrait via `WalletService::creditDirect()` et la `WalletTransaction` est passée en `failed`.

**Fichier concerné** : `backend/app/Jobs/HandleFedapayWebhook.php` (non modifié)

---

### Fix 6 — Accents manquants dans les messages de retour API

**Problème** : Les messages retournés par `WithdrawalService::initiate()` manquaient d'accents français.
- `"Votre demande de retrait a ete soumise. Elle sera traitee sous 48h."`
- `"Retrait initie avec succes."`

**Fix** : Messages corrigés avec accents corrects.

**Fichier modifié** : `backend/app/Services/WithdrawalService.php`

---

### Fix 7 — `admin_email` vide silencieux

**Problème** : Si `config('app.admin_email')` n'était pas configuré, l'email admin était ignoré sans aucune trace. La demande restait `pending` indéfiniment sans que l'admin en soit informé.

**Fix** : Ajout d'un `Log::warning()` explicite lorsque `admin_email` est vide, avec l'ID de la demande et l'ID de l'utilisateur.

**Fichier modifié** : `backend/app/Services/WithdrawalService.php`

---

### Fix 8 — Pas de throttle spécifique sur la route de retrait

**Statut** : **Déjà géré** — aucune modification nécessaire.

La route `POST /wallet/withdraw` utilise déjà `->middleware(['face', 'throttle:withdrawals'])`. Le rate limiter `withdrawals` est configuré dans `AppServiceProvider` : **5 tentatives par 10 minutes** par utilisateur, avec message d'erreur localisé.

**Fichiers concernés** :
- `backend/routes/api/bookings.php` (non modifié)
- `backend/app/Providers/AppServiceProvider.php` (non modifié)

---

---

### Fix 9 — Validation inline absente dans le formulaire de retrait

**Problème** : Le formulaire `WalletWithdrawForm.vue` n'affichait aucun retour visuel immédiat à l'utilisateur. Le seul message existant était pour le cas `montant > solde`. Les autres erreurs (montant trop faible, format de numéro invalide, préfixe opérateur incorrect) n'étaient pas signalées — le bouton de soumission était simplement désactivé sans explication.

**Fix** : Ajout de deux computed `amountError` et `phoneError` qui produisent des messages d'erreur contextuels affichés sous chaque champ dès que l'utilisateur saisit une valeur invalide. Les champs passent en bordure rouge en cas d'erreur.

**Messages ajoutés** :
- Montant < 500 XOF → *"Montant minimum : 500 XOF"*
- Montant > solde → *"Solde insuffisant (disponible : X XOF)"*
- Numéro invalide (format) → *"Numéro invalide (8 à 15 chiffres requis)"*
- Numéro béninois ≠ 10 chiffres → *"Les numéros béninois doivent comporter 10 chiffres (ex : 0197XXXXXX)"*
- Préfixe incohérent avec l'opérateur → *"Ce numéro ne correspond pas à un préfixe MTN/MOOV/CELTIIS valide au Bénin"*

**Autres améliorations** :
- Placeholder du champ numéro dynamique : `0197XXXXXX` pour Bénin, `64000001` pour les autres pays
- `canSubmit` mis à jour pour prendre en compte `amountError` et `phoneError` (plus seulement `min:1`)
- La constante `BENIN_PREFIXES` est dupliquée volontairement côté frontend pour l'UX immédiate — le backend reste la source de vérité autoritaire

**Fichier modifié** : `frontend/src/features/wallet/components/WalletWithdrawForm.vue`

---

### Fix 10 — Accents manquants dans les templates d'emails de retrait

**Problème** : Les trois templates Blade des emails de retrait contenaient des mots sans accents français, ce qui produisait un rendu incorrect en boîte mail (ex : *"Retrait traite"*, *"ete envoye"*, *"Operateur"*, *"Numero"*).

**Fix** : Correction de tous les mots concernés dans les trois templates.

**Corrections par fichier** :

`withdrawal-approved.blade.php` :
- `"Retrait traite"` → `"Retrait traité"`
- `"a ete traite"` → `"a été traité"`
- `"a ete envoye"` → `"a été envoyé"`
- `"Operateur"` → `"Opérateur"`
- `"Numero"` → `"Numéro"`
- `"Traite le"` → `"Traité le"`

`withdrawal-rejected.blade.php` :
- `"rejetee"` → `"rejetée"`
- `"etre traitee"` (×2) → `"être traitée"`
- `"communiquee"` → `"communiquée"`
- `"apres"` → `"après"`
- `"Equipe WEACT"` → `"Équipe WEACT"`

`withdrawal-submitted.blade.php` :
- `"Operateur"` → `"Opérateur"`
- `"Numero"` → `"Numéro"`

**Fichiers modifiés** :
- `backend/resources/views/emails/withdrawal-approved.blade.php`
- `backend/resources/views/emails/withdrawal-rejected.blade.php`
- `backend/resources/views/emails/withdrawal-submitted.blade.php`

---

## Récapitulatif des fichiers modifiés

| Fichier | Type de modification |
|---|---|
| `backend/app/Http/Requests/Booking/WithdrawWalletRequest.php` | **Modifié** — min 500 XOF, check pending (manuel), validation préfixes EZAB |
| `backend/app/Services/WithdrawalService.php` | **Modifié** — Cache::lock FedaPay double-submit, accents messages API, log warning admin_email vide |
| `frontend/src/features/wallet/components/WalletWithdrawForm.vue` | **Modifié** — validation inline amountError + phoneError, préfixes EZAB frontend |
| `backend/resources/views/emails/withdrawal-approved.blade.php` | **Modifié** — accents français corrigés |
| `backend/resources/views/emails/withdrawal-rejected.blade.php` | **Modifié** — accents français corrigés |
| `backend/resources/views/emails/withdrawal-submitted.blade.php` | **Modifié** — accents français corrigés |

## Fichiers non modifiés (déjà corrects)

| Fichier | Raison |
|---|---|
| `backend/app/Jobs/HandleFedapayWebhook.php` | `payout.failed` déjà géré avec recrédit wallet |
| `backend/routes/api/bookings.php` | throttle `withdrawals` déjà appliqué |
| `backend/app/Providers/AppServiceProvider.php` | Rate limiter 5/10min déjà configuré |
