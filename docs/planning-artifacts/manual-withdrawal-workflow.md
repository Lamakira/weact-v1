# Plan — Workflow de Retrait Manuel (sans FedaPay)

## Contexte

FedaPay Payouts nécessite une activation spécifique du compte. En attendant, on implémente un workflow manuel :

- La Face soumet une demande de retrait (montant + numéro Mobile Money)
- L'admin traite manuellement le dépôt hors plateforme
- L'admin marque la demande comme traitée dans son panel
- Le solde est débité et la Face est notifiée par mail

Le mode actif est contrôlé par `WITHDRAWAL_MODE=manual|fedapay` dans `.env`. **Un seul mode actif à la fois.**

## Note d'exploitation — annulations payées de booking

- Une annulation de booking déjà payé ne tente plus de remboursement FedaPay.
- Le Producer est recrédité directement dans son wallet à hauteur de 90 % de `montant_total_producteur`.
- Les 10 % restants sont conservés par la plateforme comme retenue métier.
- Le Producer retire ensuite ce solde via le workflow de retrait manuel déjà décrit dans ce document.
- Pour rattraper des annulations déjà passées avant ce correctif, utiliser la commande :

```bash
cd backend
php artisan bookings:backfill-cancelled-wallet-refunds --dry-run
php artisan bookings:backfill-cancelled-wallet-refunds
```

- La commande est idempotente. Pour cibler un booking précis :

```bash
cd backend
php artisan bookings:backfill-cancelled-wallet-refunds --booking-id=123
```

---

## Décisions validées

- Débit du solde au moment de l'**approbation admin** (pas à la soumission)
  → Avantage : si l'admin rejette, pas de remboursement à gérer
  → Inconvénient : le solde n'est pas "réservé" pendant que la demande est pending (acceptable pour l'instant)
- Garantie 48h affichée à la Face dans l'UI
- Mail à l'admin à chaque nouvelle demande
- Mail à la Face à l'approbation
- Les deux workflows (manual + fedapay) coexistent dans le code, seul l'env var détermine lequel est actif

---

## Phase 1 — Backend

### 1.1 Variable d'environnement

**Fichier :** `backend/.env` et `backend/.env.example`

```
WITHDRAWAL_MODE=manual
```

Valeurs possibles : `manual` | `fedapay`

---

### 1.2 Migration : table `withdrawal_requests`

**Fichier à créer :** `backend/database/migrations/xxxx_create_withdrawal_requests_table.php`

```
id
user_id (FK users, cascade)
amount (unsigned int)
payment_mode (string) — mtn, moov, momo_test
phone_number (string)
phone_country (string, 5)
status (enum: pending | approved | rejected) default pending
notes (text, nullable) — notes admin (ex: raison de rejet)
wallet_transaction_id (FK wallet_transactions, nullable, nullOnDelete) — rempli à l'approbation
processed_by (FK users, nullable) — admin qui a traité
processed_at (timestamp, nullable)
timestamps
```

---

### 1.3 Model `WithdrawalRequest`

**Fichier :** `backend/app/Models/WithdrawalRequest.php`

- `$fillable` : tous les champs ci-dessus
- `$casts` : `amount` → int, `processed_at` → datetime
- Relations : `user()`, `walletTransaction()`, `processedBy()` (→ User)
- Helper : `isPending(): bool`

---

### 1.4 Service `WithdrawalService`

**Fichier :** `backend/app/Services/WithdrawalService.php`

Ce service est le **point d'entrée unique** pour les retraits. Il route vers le bon workflow selon `config('app.withdrawal_mode')`.

```php
public function initiate(User $user, array $validated): array
```

Logique interne :
```
if WITHDRAWAL_MODE === 'manual'
  → createManualRequest($user, $validated)
  → retourner ['mode' => 'manual', 'message' => '...48h...']
else
  → appeler FedapayService->initiateWithdrawal(...)
  → retourner ['mode' => 'fedapay', 'checkout_url' => ...]
```

**`createManualRequest(User, array): WithdrawalRequest`**
- Crée `WithdrawalRequest` (status: pending)
- Dispatch `WithdrawalRequestSubmittedNotification` à l'admin (mail)
- Retourne la requête créée

---

### 1.5 Update `WalletController::withdraw()`

**Fichier :** `backend/app/Http/Controllers/Api/V1/WalletController.php`

Remplacer le code FedaPay direct par un appel à `WithdrawalService->initiate()`.

En mode `manual` : **ne pas débiter** le solde à la soumission.

Réponse JSON enrichie :
```json
{
  "message": "Votre demande de retrait a été soumise. Elle sera traitée sous 48h.",
  "status": "ok",
  "withdrawal_mode": "manual"
}
```

---

### 1.6 Admin Controller `WithdrawalRequestController`

**Fichier :** `backend/app/Http/Controllers/Api/V1/Admin/WithdrawalRequestController.php`

```php
// GET /v1/admin/finance/withdrawal-requests
// Query params : ?status=pending|approved|rejected (default: all)
// Retourne : liste paginée (20/page) avec user email, montant, mode, téléphone, date
public function index(Request $request): JsonResponse

// POST /v1/admin/finance/withdrawal-requests/{withdrawalRequest}/approve
// Body : notes? (optionnel)
// Actions :
//   1. Vérifier status === pending
//   2. DB::transaction :
//      a. WalletService->debit($user->id, $amount, description)
//      b. WithdrawalRequest->update(status: approved, wallet_transaction_id, processed_by, processed_at)
//   3. Dispatch WithdrawalApprovedMail → Face
//   4. Retourner 200
public function approve(WithdrawalRequest $withdrawalRequest, Request $request): JsonResponse

// POST /v1/admin/finance/withdrawal-requests/{withdrawalRequest}/reject
// Body : notes (obligatoire)
// Actions :
//   1. Vérifier status === pending
//   2. Update : status = rejected, notes, processed_by, processed_at
//   3. Dispatch WithdrawalRejectedMail → Face
//   4. Retourner 200
public function reject(WithdrawalRequest $withdrawalRequest, Request $request): JsonResponse
```

---

### 1.7 Mails

**`backend/app/Mail/WithdrawalRequestSubmittedMail.php`**
- Destinataire : admin (depuis `config('app.admin_email')` ou `env('ADMIN_EMAIL')`)
- Sujet : "Nouvelle demande de retrait — [prénom] [montant] XOF"
- Contenu : prénom de la Face, montant, opérateur, numéro, date de soumission, lien vers le panel admin

**`backend/app/Mail/WithdrawalApprovedMail.php`**
- Destinataire : Face (user email)
- Sujet : "Votre retrait de [montant] XOF a été traité"
- Contenu : confirmation que le montant a été envoyé sur son numéro, rappel de l'opérateur et numéro

**`backend/app/Mail/WithdrawalRejectedMail.php`**
- Destinataire : Face (user email)
- Sujet : "Votre demande de retrait n'a pas pu être traitée"
- Contenu : montant, raison du rejet (notes), invitation à soumettre une nouvelle demande

Templates blade dans `resources/views/emails/` suivant le pattern existant (table HTML, header teal #198496).

---

### 1.8 Config

**Fichier :** `backend/config/app.php`

Ajouter :
```php
'withdrawal_mode' => env('WITHDRAWAL_MODE', 'manual'),
'admin_email'     => env('ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
```

---

### 1.9 Routes admin

**Fichier :** `backend/routes/api/admin.php` — dans le groupe `admin.role:superadmin,admin`

```php
Route::get('/finance/withdrawal-requests', [WithdrawalRequestController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('admin.finance.withdrawal-requests.index');

Route::post('/finance/withdrawal-requests/{withdrawalRequest}/approve', [WithdrawalRequestController::class, 'approve'])
    ->middleware('throttle:30,1')
    ->name('admin.finance.withdrawal-requests.approve');

Route::post('/finance/withdrawal-requests/{withdrawalRequest}/reject', [WithdrawalRequestController::class, 'reject'])
    ->middleware('throttle:30,1')
    ->name('admin.finance.withdrawal-requests.reject');
```

---

### 1.10 Update AdminFinanceController

**Fichier :** `backend/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php`

Mettre à jour `overview()` pour inclure les demandes en attente :
```php
'withdrawal_requests' => [
    'pending_count'  => WithdrawalRequest::where('status', 'pending')->count(),
    'pending_amount' => (int) WithdrawalRequest::where('status', 'pending')->sum('amount'),
],
```

---

### 1.11 Route throttle retrait

**Fichier :** `backend/routes/api/bookings.php`

Passer `throttle:5,1` → `throttle:withdrawals` avec un rate limiter nommé dans `AppServiceProvider` :
```php
RateLimiter::for('withdrawals', function (Request $request) {
    return Limit::perMinutes(10, 5)
        ->by($request->user()?->id ?: $request->ip())
        ->response(fn() => response()->json([
            'message' => 'Trop de tentatives de retrait. Veuillez réessayer dans quelques minutes.',
        ], 429));
});
```

---

## Phase 2 — Frontend

### 2.1 Exposer `withdrawal_mode` dans la réponse wallet

**Fichier :** `backend/app/Http/Resources/WalletResource.php`

Ajouter `'withdrawal_mode' => config('app.withdrawal_mode')` dans `toArray()`.

**Fichier :** `frontend/src/features/wallet/types/wallet.ts`

Ajouter `withdrawal_mode: 'manual' | 'fedapay'` à l'interface `WalletData`.

---

### 2.2 Update `WalletWithdrawForm.vue`

**Fichier :** `frontend/src/features/wallet/components/WalletWithdrawForm.vue`

Recevoir prop `withdrawalMode: 'manual' | 'fedapay'`.

En mode `manual` :
- Afficher un bandeau info sous le formulaire :
  > "Votre demande sera traitée manuellement sous 48h. Vous recevrez un email de confirmation dès que le virement est effectué."
- Bouton : "Soumettre la demande" (au lieu de "Retirer")
- Supprimer/masquer les boutons d'opérateur si l'opérateur est optionnel (à discuter)

En mode `fedapay` : comportement actuel inchangé.

---

### 2.3 Mise à jour des types API

**Fichier :** `frontend/src/features/admin/services/adminFinanceApi.ts`

Ajouter :

```ts
export interface WithdrawalRequestEntry {
  id: number
  amount: number
  payment_mode: string
  phone_number: string
  phone_country: string
  status: 'pending' | 'approved' | 'rejected'
  notes: string | null
  processed_at: string | null
  created_at: string
  user_email: string
  user_prenom: string
}

// Méthodes
getWithdrawalRequests(status?: string): Promise<{data: WithdrawalRequestEntry[]; message: string}>
approveWithdrawalRequest(id: number, notes?: string): Promise<void>
rejectWithdrawalRequest(id: number, notes: string): Promise<void>
```

---

### 2.4 Nouveau composant `AdminWithdrawalRequestsTable.vue`

**Fichier :** `frontend/src/features/admin/components/AdminWithdrawalRequestsTable.vue`

Tableau listant les demandes avec :
- Colonnes : Face (email + prénom), Montant, Opérateur, Numéro, Date soumission, Statut badge, Actions
- Badge statut : `pending` = jaune, `approved` = vert, `rejected` = rouge
- Actions sur les `pending` : bouton "Approuver" (vert) + bouton "Rejeter" (rouge, ouvre modal avec champ notes)
- Pagination ou infinite scroll

---

### 2.5 Update `AdminFinancePage.vue`

**Fichier :** `frontend/src/pages/admin/AdminFinancePage.vue`

Ajouter une section "Demandes de retrait en attente" en haut de la page (si `pending_count > 0`, badge rouge sur le titre de section).

Onglets ou sections :
1. Vue d'ensemble financière (existant)
2. Demandes de retrait (nouveau)

---

## Fichiers à créer

| Fichier | Type |
|---|---|
| `backend/database/migrations/xxxx_create_withdrawal_requests_table.php` | Migration |
| `backend/app/Models/WithdrawalRequest.php` | Model |
| `backend/app/Services/WithdrawalService.php` | Service |
| `backend/app/Mail/WithdrawalRequestSubmittedMail.php` | Mail |
| `backend/app/Mail/WithdrawalApprovedMail.php` | Mail |
| `backend/app/Mail/WithdrawalRejectedMail.php` | Mail |
| `backend/resources/views/emails/withdrawal-submitted.blade.php` | Template |
| `backend/resources/views/emails/withdrawal-approved.blade.php` | Template |
| `backend/resources/views/emails/withdrawal-rejected.blade.php` | Template |
| `backend/app/Http/Controllers/Api/V1/Admin/WithdrawalRequestController.php` | Controller |
| `frontend/src/features/admin/components/AdminWithdrawalRequestsTable.vue` | Composant |

## Fichiers à modifier

| Fichier | Modification |
|---|---|
| `backend/.env` + `.env.example` | Ajouter `WITHDRAWAL_MODE` + `ADMIN_EMAIL` |
| `backend/config/app.php` | Ajouter `withdrawal_mode` + `admin_email` |
| `backend/app/Http/Controllers/Api/V1/WalletController.php` | Déléguer à `WithdrawalService` |
| `backend/app/Http/Resources/WalletResource.php` | Exposer `withdrawal_mode` |
| `backend/app/Providers/AppServiceProvider.php` | Ajouter rate limiter `withdrawals` |
| `backend/routes/api/bookings.php` | Utiliser throttle nommé |
| `backend/routes/api/admin.php` | Nouvelles routes withdrawal-requests |
| `backend/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php` | Ajouter `pending_withdrawal_requests` dans overview |
| `frontend/src/features/wallet/types/wallet.ts` | Ajouter `withdrawal_mode` |
| `frontend/src/features/wallet/components/WalletWithdrawForm.vue` | Mode manual UI |
| `frontend/src/features/admin/services/adminFinanceApi.ts` | Nouvelles méthodes |
| `frontend/src/pages/admin/AdminFinancePage.vue` | Section demandes retrait |

---

## Points d'attention pour l'implémentation

1. **Race condition** : L'approbation doit utiliser `DB::transaction` avec `lockForUpdate` sur le `WithdrawalRequest` pour éviter double-approbation en cas de double-clic admin.
2. **Solde insuffisant à l'approbation** : Si la Face a dépensé ses fonds entre la soumission et l'approbation, `WalletService->debit()` lèvera une `RuntimeException` → retourner 422 à l'admin avec message explicite.
3. **Idempotence** : Vérifier `status === 'pending'` avant tout traitement dans approve/reject.
4. **Migration FedaPay** : Quand FedaPay Payouts est activé, il suffira de passer `WITHDRAWAL_MODE=fedapay` → aucun code à changer.
