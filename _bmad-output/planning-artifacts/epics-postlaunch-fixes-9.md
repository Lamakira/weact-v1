---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-19'
totalEpics: 5
totalStories: 12
project_name: 'WEACT - Correctifs Post-Lancement Sprint 12'
user_name: 'Amakira'
date: '2026-04-19'
---

# WEACT - Correctifs Post-Lancement Sprint 12 - Epic Breakdown

## Overview

Sprint 12 regroupe 4 axes de correctifs post-lancement remontés en parallèle après stabilisation du flow mission/paiement (Sprint 11, FIX-20). Les 4 issues touchent des couches orthogonales du produit : auth (throttle login), couche transverse d'erreurs (format backend + extraction frontend), fiabilité d'un feature flag (`REGISTRATION_ENABLED`), et infrastructure email du cycle mission/paiement. Les investigations (19 avril 2026) ont révélé les recoupements suivants :

- **Racine commune Issue 1 + Issue 2** : `config/app.php:87` a `APP_LOCALE=en` en défaut — tous les messages Laravel vendor (throttle, validation) partent en anglais. Le sprint traite les deux issues séparément (décision produit) mais le fix du locale dans FIX-22 aura un effet collatéral bénéfique sur le message throttle si FIX-21 n'était pas déjà livré.
- **Faux positif Issue 2** : il n'y a pas « deux systèmes de toast » en prod — `vue-toastification` est la seule librairie installée, exposée via `useToast()` dans `frontend/src/composables/`. Le vrai problème est **l'incohérence du pipeline d'extraction des messages** (4-5 composables font du direct-access au lieu d'utiliser `getApiErrorMessage()`) et **deux formats de réponse backend** coexistent (`{error:{message,code}}` vs `{message}`). L'epic est recentré sur ces deux racines.
- **Issue 3 — comportement non-déterministe expliqué** : `RegisterProducerPage.vue:18` et `RegisterFacePage.vue:18` ont un `catch { registrationEnabled.value = false }`. Toute erreur réseau/CDN transitoire masque silencieusement le formulaire pour l'utilisateur concerné, même quand `REGISTRATION_ENABLED=true`. Pas de `VITE_*` en fallback build-time, pas de cache HTTP backend → chaque montage déclenche un appel live dont l'échec cache le form.
- **Issue 4 — 5 moments clés sans email** : audit de `backend/app/Mail/` et `backend/app/Listeners/` confirme que seul `BookingCancelledMail` est effectivement envoyé via listener. Les 5 events visés (sélection post-paiement, confirmation Face, clôture mission, booking reçu, crédit wallet Producer) n'ont que des notifications in-app via `Notification::create()`. Le plus volumineux des 4 epics.

### Décisions produit (2026-04-19)

1. **Issues 1 et 2 scindées** en epics séparés malgré la racine partagée sur la locale — tracking plus clair, livrables indépendants, risque réduit de régressions croisées.
2. **Chaque email du cycle mission a son propre template Blade** — pas de template générique. Temps d'implémentation plus long mais UX email soignée (objectifs commerciaux : faire ressentir le sérieux de la plateforme).
3. **Pattern Prove It** (CLAUDE.md) appliqué à chaque story de bug (FIX-21, FIX-22, FIX-23) : un subagent écrit un test qui reproduit le bug avant le fix, confirmation que le test échoue, puis fix, puis test passe. Pour FIX-24 (feature add, pas bug fix), tests directs sur les listeners + mailables via `Mail::fake()`.

## Requirements Inventory

### Functional Requirements

**FIX21-FR1** : La route `POST /api/v1/auth/login` doit utiliser un rate limiter dédié `auth` (et non le `throttle:5,1` middleware par défaut) qui renvoie un 429 avec un body JSON au format `{ error: { message, code } }` et un message en français actionnable.

**FIX21-FR2** : Le fallback frontend du 429 sur login continue de fonctionner (`authApi.ts:207-208`) mais le chemin nominal doit afficher le message backend français, pas le fallback.

**FIX22-FR1** : La locale Laravel doit être forcée à `fr` de bout en bout : défaut dans `config/app.php`, middleware `SetLocale` appliqué à toutes les routes API, fichiers `lang/fr/*.php` créés pour `validation`, `auth`, `passwords`, `pagination`, `throttle` si nécessaire.

**FIX22-FR2** : Tous les endpoints API renvoyant une erreur (4xx/5xx) doivent utiliser le format unique `{ error: { message, code } }`. Les endpoints existants qui renvoient `{ message: ... }` seul (notamment `WalletController`) sont alignés.

**FIX22-FR3** : Le frontend doit extraire les messages d'erreur via une fonction centralisée `formatApiError()` définie dans `frontend/src/services/errorFormatter.ts`. Tous les composables qui font actuellement du direct-access (`err.response?.data?.error?.message`, `err.response?.data?.message`, `err.response?.data?.errors?.amount[0]`, etc.) appellent cette fonction.

**FIX23-FR1** : La décision d'affichage du formulaire d'inscription doit être déterministe pour tous les utilisateurs quand `REGISTRATION_ENABLED=true`. Une erreur réseau sur l'endpoint `/auth/registration-status` ne doit plus résulter en « formulaire masqué » mais en un fallback build-time via `VITE_REGISTRATION_ENABLED`.

**FIX23-FR2** : L'endpoint `GET /auth/registration-status` doit exposer un header `Cache-Control: public, max-age=300` pour réduire la fréquence des appels et permettre aux CDN/proxys de servir une réponse cohérente.

**FIX24-FR1** : Sélection d'une Face pour une mission après paiement Producer réussi déclenche un email à chaque Face sélectionnée (`FaceSelectedMail`) précisant : nom de la mission, nom du Producer, date de tournage, rémunération, et lien pour confirmer la participation.

**FIX24-FR2** : Confirmation de participation d'une Face à une mission après paiement déclenche un email au Producer (`FaceConfirmedMail`) : nom de la Face, mission concernée, rappel de la date de tournage.

**FIX24-FR3** : Clôture de mission par le Producer déclenche un email à chaque Face en mission terminée (`MissionCompletedMail`) précisant : montant crédité sur le portefeuille Face, lien vers son dashboard/wallet, nom de la mission.

**FIX24-FR4** : Réception d'une demande de booking par une Face déclenche un email (`BookingReceivedMail`) : nom du Producer, date et lieu de tournage, lien pour accepter/refuser.

**FIX24-FR5** : Crédit du portefeuille Producer (après webhook FedaPay de retour d'un paiement remboursé, signalement no-show, etc.) déclenche un email au Producer (`WalletCreditedMail`) : montant crédité, motif, nouveau solde.

**FIX24-FR6** : Un layout Blade commun `emails/layouts/base.blade.php` est créé, réutilisé par les 5 mailables. Chaque mail a en plus son propre template dédié avec son contenu spécifique.

## Epic & Story Breakdown

---

### Epic FIX-21 : Throttle login — message français via rate limiter dédié

**Goal :** Éliminer l'affichage du message anglais « Too Many Attempts » lors d'un throttle login en remplaçant le `throttle:5,1` middleware Laravel par défaut par un rate limiter custom `auth` dont la réponse 429 est en français et respecte le format d'erreur standard de l'API.

**Priority :** Moyenne — impact UX direct (utilisateurs bloqués voient un message EN incompréhensible). Fix techniquement isolé, faible risque, livrable en standalone.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-21.1 | Rate limiter `auth` dédié avec réponse 429 française | FIX21-FR1, FIX21-FR2 | Moyenne |

**Ordre de livraison recommandé :** livraison unique — la story est atomique.

---

#### FIX-21.1 : Rate limiter `auth` dédié avec réponse 429 française

**Description :** Créer un rate limiter nommé `auth` dans `AppServiceProvider::boot()` qui retourne un 429 avec un body JSON `{ error: { message, code: 'THROTTLED' } }` en français (« Trop de tentatives de connexion. Veuillez réessayer dans une minute. »). Remplacer `throttle:5,1` par `throttle:auth` sur la route `POST /api/v1/auth/login` dans `backend/routes/api.php`. Le fix précédent (commit `6107110`, Sprint wallet) avait appliqué ce pattern pour `withdrawals` mais n'avait jamais été étendu au login.

**Acceptance Criteria :**
- Nouveau rate limiter `auth` défini dans `backend/app/Providers/AppServiceProvider.php::boot()` :
  - 5 tentatives par 1 minute
  - Clé : `$request->ip()` (l'utilisateur n'est pas authentifié au moment du login, donc pas de user ID disponible)
  - Réponse 429 au format : `{ "error": { "message": "Trop de tentatives de connexion. Veuillez réessayer dans une minute.", "code": "THROTTLED" } }`
- Route `POST /api/v1/auth/login` dans `backend/routes/api.php:55-57` passe de `throttle:5,1` à `throttle:auth`.
- Test backend feature : 6ᵉ tentative consécutive sur `/auth/login` retourne 429 avec le body JSON français exact.
- Non-régression : les 5 premières tentatives légitimes fonctionnent toujours (login réussi OU échec credentials → 401/422).
- Le composable frontend `authApi.ts:183-223` (`getApiErrorMessage`) lit correctement `error.message` du body et l'affiche — test unitaire qui mocke une réponse 429 au nouveau format vérifie l'affichage du message français dans le toast.
- Accents français corrects respectés (memory `feedback_accents_francais`).

**Technical Notes :**
- Fichiers impactés :
  - `backend/app/Providers/AppServiceProvider.php::boot()` (ajouter le `RateLimiter::for('auth', ...)` après les limiters existants, notamment `withdrawals` ligne ~60-80 à vérifier)
  - `backend/routes/api.php:55-57` (changer le middleware throttle)
  - `backend/tests/Feature/Auth/LoginThrottleTest.php` (nouveau fichier — test 6 tentatives + vérif body FR)
  - `frontend/src/features/auth/services/authApi.ts` (vérifier lecture de `error.message`, déjà prévu à la ligne 189 — pas de modification si la forme match)
- Le fix du locale (FIX-22.1) rendrait le message vendor Laravel FR aussi, mais on veut ici un message **actionnable et projet-spécifique** (mention claire « connexion », durée explicite), pas une simple traduction littérale de « Too Many Attempts ».
- Pattern Prove It :
  1. Subagent écrit `LoginThrottleTest::test_sixth_attempt_returns_french_429()` qui pousse 6 `postJson('/api/v1/auth/login', bad credentials)` et asserte `status === 429` + body FR. **Doit échouer avant le fix** (car le middleware par défaut renvoie `{ message: "Too Many Attempts" }`).
  2. Appliquer le fix dans `AppServiceProvider` + routes.
  3. Le test passe.
- Indépendant des autres epics du Sprint 12.

---

### Epic FIX-22 : Cohérence des messages d'erreur — locale + format + extraction

**Goal :** Uniformiser la communication d'erreurs entre backend et frontend sur 3 axes complémentaires : (1) forcer la locale Laravel à `fr` partout pour que les messages vendor (validation, auth, etc.) soient en français ; (2) standardiser le format de réponse d'erreur API sur le modèle unique `{ error: { message, code } }` ; (3) centraliser l'extraction du message côté frontend via `formatApiError()` et remplacer les direct-access hétérogènes dans les composables existants.

**Priority :** Haute — l'incohérence actuelle force les utilisateurs à voir un mélange de FR/EN selon l'écran, et complique le debug côté dev (chaque composable a son propre fallback). Le fix est transverse mais chaque story est livrable indépendamment.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-22.1 | Forcer la locale backend à `fr` + middleware `SetLocale` | FIX22-FR1 | Haute |
| FIX-22.2 | Standardiser le format de réponse d'erreur API | FIX22-FR2 | Haute |
| FIX-22.3 | Centraliser l'extraction d'erreur frontend (`formatApiError`) | FIX22-FR3 | Haute |

**Ordre de livraison recommandé :**

1. **FIX-22.1** en premier — impact large immédiat (tous les messages vendor passent en FR), pré-requis pour que les tests des stories suivantes puissent asserter sur des messages FR cohérents.
2. **FIX-22.2** ensuite — nécessite un inventaire exhaustif des endpoints qui renvoient `{ message }` seul et leur alignement sur `{ error: { message, code } }`.
3. **FIX-22.3** en dernier — s'appuie sur la stabilité du format backend pour remplacer les direct-access frontend par `formatApiError()`.

---

#### FIX-22.1 : Forcer la locale backend à `fr` + middleware `SetLocale`

**Description :** Le défaut actuel `'locale' => env('APP_LOCALE', 'en')` dans `backend/config/app.php:87` signifie qu'en prod ou en test si `APP_LOCALE` n'est pas explicitement positionné à `fr`, Laravel tombe sur `en` — et tous les messages vendor (validation, auth, passwords, pagination, throttle) partent en anglais. Ce ticket force `fr` comme défaut immuable, ajoute un middleware `SetLocale` appliqué à toutes les routes API pour rester robuste si un futur contexte modifie la locale transitoirement, et crée les fichiers `lang/fr/*.php` pour couvrir les messages traduisibles non couverts par le package vendor FR par défaut.

**Acceptance Criteria :**
- `backend/config/app.php:87` passe de `'locale' => env('APP_LOCALE', 'en')` à `'locale' => env('APP_LOCALE', 'fr')`.
- `backend/config/app.php` — `fallback_locale` reste `'en'` (sécurité si une clé n'est pas traduite).
- Nouveau middleware `backend/app/Http/Middleware/SetLocale.php` qui appelle `App::setLocale('fr')` (ou lit un header `Accept-Language` si futur besoin, mais pour l'instant hardcodé `fr`).
- Middleware enregistré dans `backend/bootstrap/app.php` (Laravel 11/12) appliqué au groupe `api`.
- Fichiers de traduction FR créés si absents :
  - `backend/lang/fr/validation.php` (messages standard Laravel `required`, `email`, `min`, `max`, etc.)
  - `backend/lang/fr/auth.php` (`failed`, `password`, `throttle`)
  - `backend/lang/fr/passwords.php`
  - `backend/lang/fr/pagination.php`
- Test backend feature : un `postJson('/api/v1/auth/login', [])` (corps vide) retourne un 422 avec des messages de validation en français (« Le champ email est obligatoire. »).
- Test backend : un `postJson('/api/v1/auth/login', ['email' => 'wrong@x.com', 'password' => 'bad'])` retourne un 422 avec le message backend FR (« Identifiants incorrects » — déjà FR dans `LoginController.php:32`, vérifier non-régression).
- Non-régression : aucun endpoint actuellement en FR ne régresse vers EN.
- `.env.example` mis à jour : `APP_LOCALE=fr` est le défaut documenté.

**Technical Notes :**
- Root cause : `backend/config/app.php:87` défaut `'en'` + `.env` parfois vide en test/CI.
- Laravel 11/12 — le middleware `SetLocale` s'enregistre dans `bootstrap/app.php` via `$middleware->append()` ou `$middleware->prependToGroup('api', ...)`.
- Vérifier avant création des fichiers `lang/fr/*` si le vendor `laravel-lang/lang` ou équivalent est déjà installé (`composer show | grep lang`). Si oui, les fichiers FR vendor sont auto-publishés — ne créer que les overrides nécessaires.
- Pattern Prove It :
  1. Subagent écrit `ValidationLocaleTest::test_login_empty_body_returns_french_validation_messages()` qui assert `{ errors: { email: ["Le champ email est obligatoire."] } }`. Doit échouer avant (Laravel répond EN).
  2. Appliquer les 3 changements (config, middleware, fichiers lang).
  3. Le test passe.
- Indépendant du reste du sprint mais doit être livré **avant** FIX-22.2 et FIX-22.3 pour que leurs assertions de tests soient stables.
- **Ne pas** retirer les messages FR déjà hardcodés dans les controllers (`LoginController:32`, `EmailChangeController`, `WalletController:61-69`) — ils restent valides. Ce ticket concerne uniquement les messages vendor Laravel.

---

#### FIX-22.2 : Standardiser le format de réponse d'erreur API

**Description :** Deux formats de réponse d'erreur coexistent actuellement côté backend : `{ error: { message, code } }` (utilisé par `auth`, `email-change`) et `{ message: ... }` seul (utilisé par `WalletController:61,65,69` et d'autres endpoints). Cette divergence force chaque composable frontend à connaître le format exact de l'API qu'il appelle, et est la racine du problème d'extraction côté client. Ce ticket aligne tous les endpoints sur le format unique `{ error: { message, code } }` via un handler d'exception global et la refonte des endpoints non conformes.

**Acceptance Criteria :**
- Audit exhaustif préalable : lister tous les endpoints qui renvoient actuellement `response()->json(['message' => ...], 4xx|5xx)` sans objet `error`. Dresser la liste dans un commentaire technique de la PR.
- Handler d'exception global (`backend/bootstrap/app.php` ou `backend/app/Exceptions/Handler.php` selon Laravel 11/12) normalise le format : toute `HttpException` ou exception non capturée retourne `{ error: { message: <msg>, code: <slug> } }`.
- `WalletController::withdraw` (et toute autre méthode retournant `{ message }` seul) est refactoré pour utiliser `response()->json(['error' => ['message' => ..., 'code' => ...]], 4xx)` avec des codes sémantiques (`INSUFFICIENT_BALANCE`, `WITHDRAWAL_FAILED`, etc.).
- `ValidationException` est sérialisée dans le format : `{ error: { message: <first error>, code: 'VALIDATION_FAILED', errors: { field: [...] } }` — on conserve la map `errors` pour les champs mais l'enveloppe globale est uniforme.
- Tous les endpoints impactés ont un test feature qui assert le nouveau format.
- Non-régression : les endpoints déjà au format `{ error: { message, code } }` (auth, email-change) continuent de passer leurs tests.
- **Le frontend n'est pas modifié dans ce ticket** — il peut continuer à lire les deux formats. L'alignement frontend est fait dans FIX-22.3 une fois le backend stabilisé.

**Technical Notes :**
- Inventaire initial (à valider par audit complet) :
  - `backend/app/Http/Controllers/Api/V1/Wallet/WalletController.php:61,65,69` (trois chemins `{ message }`)
  - `backend/app/Http/Controllers/Api/V1/Admin/*` (plusieurs controllers)
  - Grep à lancer : `grep -rn "response()->json(\[.*'message'" backend/app/Http/Controllers --include="*.php" | grep -v "error"`
- Codes d'erreur à définir : lister dans un enum/const `backend/app/Constants/ErrorCodes.php` pour éviter la duplication de strings.
- Handler d'exception Laravel 12 : la normalisation peut se faire via `->render()` dans `bootstrap/app.php` avec un closure qui intercepte les `Throwable` et renvoie le format uniforme si la request attend du JSON.
- Pattern Prove It :
  1. Subagent écrit `WalletWithdrawErrorFormatTest::test_insufficient_balance_returns_standardized_format()` qui assert `{ error: { message, code: 'INSUFFICIENT_BALANCE' } }`. Doit échouer avant (format actuel `{ message }`).
  2. Refactorer `WalletController` + handler global.
  3. Le test passe.
- Dépendance : FIX-22.1 livré avant (sinon les messages dans les tests peuvent encore partir en EN via vendor Laravel).
- Attention à ne pas casser les clients externes — cette API est consommée uniquement par le frontend WEACT, pas d'API publique exposée, donc pas de contrainte de versioning.

---

#### FIX-22.3 : Centraliser l'extraction d'erreur frontend (`formatApiError`)

**Description :** Les composables frontend font actuellement du direct-access hétérogène pour extraire les messages d'erreur — `authApi.ts` utilise `getApiErrorMessage()` (bon pattern), mais `useWallet.ts`, `useApplyToMission.ts`, `useAdminDashboardStats.ts` et probablement d'autres lisent directement `err.response?.data?.message` ou `err.response?.data?.error?.message` avec des fallbacks custom. Ce ticket extrait la logique de `getApiErrorMessage` (actuellement dans `frontend/src/features/auth/services/authApi.ts:183-223`) vers `frontend/src/services/errorFormatter.ts` et remplace les direct-access par des appels à `formatApiError()`.

**Acceptance Criteria :**
- Nouveau fichier `frontend/src/services/errorFormatter.ts` exportant :
  - `formatApiError(err: unknown, fallback?: string): string` — extrait le message depuis le format standard `{ error: { message, code } }`, puis `{ message }` legacy, puis `{ errors: { field: [...] } }` pour les 422, puis retourne `fallback` ou un message générique FR par défaut.
  - `getApiErrorCode(err: unknown): string | null` — extrait le `code` si présent.
  - `isNetworkError(err: unknown): boolean` — distingue une erreur HTTP d'une erreur réseau/offline.
- Les composables suivants sont refactorés pour appeler `formatApiError()` au lieu de direct-access :
  - `frontend/src/features/wallet/composables/useWallet.ts` (lignes 70-73)
  - `frontend/src/features/candidature/composables/useApplyToMission.ts` (lignes 49-51)
  - `frontend/src/features/admin/composables/useAdminDashboardStats.ts` (ligne 94)
  - Audit exhaustif : grep `err.response?.data` dans `frontend/src/` et refactorer tous les chemins.
- `authApi.ts` continue d'utiliser `getApiErrorMessage` renommé/redirigé vers `formatApiError` (ou conservé en wrapper temporaire pour non-régression des tests auth).
- Tests unitaires de `formatApiError()` couvrant :
  - Format standard `{ error: { message, code } }` → retourne message
  - Format legacy `{ message }` → retourne message
  - Format validation `{ errors: { email: ["..."] } }` → retourne premier message
  - Erreur réseau (pas de `response`) → retourne fallback ou message générique
  - `err` null/undefined → retourne fallback ou message générique
- Tests des composables modifiés sont adaptés : les mocks axios renvoient du format standard, les assertions de messages FR tiennent.
- Non-régression : les tests existants de `authApi.ts` passent toujours.

**Technical Notes :**
- Le wrapper actuel `frontend/src/composables/useToast.ts` (autour de `vue-toastification`) est conservé tel quel — ce n'est pas le problème. Seule l'extraction amont change.
- Pour garder des messages actionnables, `formatApiError()` prend un `fallback` optionnel. Exemple : `useWallet.ts` appellera `formatApiError(err, 'Retrait échoué. Veuillez réessayer.')`.
- Le code backend étant aligné par FIX-22.2, `formatApiError` peut privilégier le format standard et traiter les autres comme fallback de compatibilité pendant la période de transition — ça permet un déploiement progressif si besoin.
- Pattern Prove It :
  1. Subagent écrit `useWallet.spec.ts::test_withdraw_error_displays_french_message_from_error_code()` qui mocke un 422 au format standard et assert que le toast reçoit le message FR backend, pas un fallback local. Doit échouer avant (direct-access lit mal le format standard).
  2. Refactorer `useWallet` pour appeler `formatApiError()`.
  3. Le test passe.
- Dépendance : FIX-22.2 livré avant (sinon on formate autour d'un backend incohérent).
- Memory `feedback_accents_francais` : tous les fallbacks par défaut respectent les accents.

---

### Epic FIX-23 : Fiabilité du flag `REGISTRATION_ENABLED`

**Goal :** Rendre l'affichage du formulaire d'inscription déterministe pour tous les utilisateurs lorsque `REGISTRATION_ENABLED=true`, en corrigeant le fallback silencieux `catch { registrationEnabled.value = false }` qui masque le formulaire à la moindre erreur réseau, en ajoutant un cache HTTP sur l'endpoint backend, et en fournissant un fallback build-time `VITE_REGISTRATION_ENABLED` exploitable côté client quand l'API échoue.

**Priority :** Haute — impact commercial direct (utilisateurs ne peuvent pas s'inscrire → conversion perdue). Fix isolé, faible risque, livrable en une seule story.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-23.1 | Fallback `VITE_REGISTRATION_ENABLED` + cache HTTP sur `/auth/registration-status` | FIX23-FR1, FIX23-FR2 | Haute |

---

#### FIX-23.1 : Fallback `VITE_REGISTRATION_ENABLED` + cache HTTP

**Description :** Trois changements coordonnés :
1. **Frontend — fallback build-time** : dans `RegisterProducerPage.vue:14-21` et `RegisterFacePage.vue:14-21`, remplacer le `catch { registrationEnabled.value = false }` actuel par un fallback qui lit `import.meta.env.VITE_REGISTRATION_ENABLED` (défaut `'true'` → affichage) si l'appel API échoue. Une erreur réseau ou un 5xx ne doit plus masquer le formulaire quand le flag est à `true`.
2. **Backend — cache HTTP** : la route `GET /auth/registration-status` dans `backend/routes/api.php:43-44` ajoute un header `Cache-Control: public, max-age=300` pour que les CDN/proxys servent une réponse cohérente pendant 5 min.
3. **Frontend — env doc** : ajouter `VITE_REGISTRATION_ENABLED=true` dans `frontend/.env.example` et documenter dans le README que cette variable est le fallback build-time quand l'API est indisponible.

**Acceptance Criteria :**
- `frontend/src/pages/auth/RegisterProducerPage.vue:14-21` : le catch utilise `import.meta.env.VITE_REGISTRATION_ENABLED !== 'false'` comme fallback (défaut permissif `true` quand la variable est absente).
- `frontend/src/pages/auth/RegisterFacePage.vue:14-21` : même changement, cohérent.
- `backend/routes/api.php:43-45` : la route retourne la réponse avec `->header('Cache-Control', 'public, max-age=300')`.
- `frontend/.env.example` : nouvelle ligne `VITE_REGISTRATION_ENABLED=true`.
- Test frontend (vitest) : simuler un `authApi.getRegistrationStatus()` qui rejette (erreur réseau), vérifier que `registrationEnabled.value` reste `true` si `VITE_REGISTRATION_ENABLED` est `true` / non défini.
- Test backend feature : `get('/api/v1/auth/registration-status')` retourne 200 avec header `Cache-Control` contenant `max-age=300`.
- Non-régression : si `REGISTRATION_ENABLED=false` côté backend ET l'API répond correctement, le formulaire est bien masqué (le fallback ne prend le dessus que si l'API échoue).
- Documentation : commentaire dans les deux pages Vue expliquant la logique « fallback build-time si API KO ».

**Technical Notes :**
- Root cause : `catch { registrationEnabled.value = false }` + aucun fallback build-time + pas de cache HTTP = chaque rafraîchissement déclenche un appel live et le moindre échec masque le form.
- Avant : `registrationEnabled: ref<boolean | null>(null)` → `false` sur erreur (impossible de distinguer « erreur réseau » de « désactivé par flag »).
- Après : `false` uniquement si l'API confirme `false`. Erreur réseau → valeur de `VITE_REGISTRATION_ENABLED` (défaut `true`).
- Le cache HTTP `max-age=300` est un compromis : court assez pour propager un toggle rapide en prod, long assez pour réduire la charge et stabiliser la réponse au niveau CDN.
- Pattern Prove It :
  1. Subagent écrit `RegisterProducerPage.spec.ts::test_registration_form_visible_when_api_fails_and_vite_flag_true()` qui mocke `authApi.getRegistrationStatus()` rejetant, set `VITE_REGISTRATION_ENABLED=true`, vérifie que le form est rendu. Doit échouer avant (le catch met `false`, le form est masqué).
  2. Appliquer le changement dans les deux pages.
  3. Le test passe.
- Indépendant des autres epics du Sprint 12.
- Pas de dépendance sur FIX-22 (le format d'erreur n'est pas lu — on fallback sans regarder l'erreur).

---

### Epic FIX-24 : Notifications email du cycle mission

**Goal :** Implémenter les 5 notifications email manquantes sur les événements critiques du cycle mission/paiement/wallet, avec un layout commun + un template dédié par email pour soigner l'UX. L'infrastructure email existante (Mail config, queue, layouts) est augmentée d'un layout de base réutilisable et d'une config SMTP prod validée.

**Priority :** Haute — les 5 événements sont des moments clés de la relation Producer/Face et du cycle financier. L'absence d'email dégrade la confiance (le silence après un paiement réussi est anxiogène) et force l'utilisateur à re-consulter l'app pour savoir où il en est.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-24.1 | Infrastructure email — layout de base + config SMTP prod | FIX24-FR6 | Haute |
| FIX-24.2 | Email « Face sélectionnée après paiement Producer » | FIX24-FR1 | Haute |
| FIX-24.3 | Email « Participation Face confirmée » → Producer | FIX24-FR2 | Haute |
| FIX-24.4 | Email « Mission clôturée → crédit wallet Face » | FIX24-FR3 | Haute |
| FIX-24.5 | Email « Demande de booking reçue » → Face | FIX24-FR4 | Haute |
| FIX-24.6 | Email « Portefeuille Producer crédité » | FIX24-FR5 | Haute |

**Ordre de livraison recommandé :**

1. **FIX-24.1** en premier — infrastructure partagée, débloque les 5 stories suivantes qui réutilisent le layout et la config.
2. **FIX-24.2 à FIX-24.6** en parallèle — chaque email est isolé, aucune dépendance croisée. Possibilité de paralléliser sur plusieurs devs ou les traiter en rafale.

---

#### FIX-24.1 : Infrastructure email — layout de base + config SMTP prod

**Description :** Créer un layout Blade commun `emails/layouts/base.blade.php` qui servira de cadre visuel à tous les emails du cycle mission/booking (header WEACT avec logo, pied de page avec mentions légales/unsubscribe, typographie et palette cohérentes avec l'identité de marque). Valider la config SMTP pour la prod (driver actuellement `log` en dev) et documenter la procédure de déploiement. Ajouter un job helper `SendEmailJob` (ou vérifier que le pattern existant suffit) pour homogénéiser l'envoi asynchrone via la queue `database`.

**Acceptance Criteria :**
- Nouveau fichier `backend/resources/views/emails/layouts/base.blade.php` contenant :
  - Header : logo WEACT, couleur primaire de la marque
  - Slot `@yield('content')` pour le contenu spécifique du mail
  - Footer : mentions légales, lien « se désinscrire » (pointant vers une page de préférences email — à créer en placeholder si absente), coordonnées.
  - HTML compatible avec les principaux clients mail (Gmail, Outlook, Apple Mail) — tables pour la structure, inline CSS pour les styles critiques.
- Classe `Mail` de base `backend/app/Mail/BaseMail.php` (abstract) qui configure le `from`, le `replyTo`, et extend `Mailable` — les 5 futures mailables héritent de cette base.
- Config SMTP prod documentée dans `docs/runbook-email-notifications.md` (nouveau fichier) : variables `.env` à positionner (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`), provider recommandé (Mailgun/SES/SMTP dédié), procédure de test post-déploiement.
- Queue config vérifiée : `QUEUE_CONNECTION=database` en prod, worker tourne (`php artisan queue:work`) — doc dans le runbook.
- Test backend : un `Mail::fake()` + `Mail::to('test@example.com')->queue(new FakeBaseMail())` (mail de test local qui hérite de `BaseMail`) → vérifier que le layout est appliqué et que le from/replyTo sont positionnés.
- Design du layout validé visuellement (screenshot dans la PR) — rendu dans Mailtrap ou équivalent.

**Technical Notes :**
- Le projet utilise déjà `backend/config/mail.php` et a 5 mailables existants (`ContactFormMail`, `BookingCancelledMail`, `WithdrawalApproved/Rejected/Submitted`). Auditer leur code pour réutiliser des helpers si pertinents.
- **Décision produit** : chaque email du cycle mission a son propre template (pas de template générique unique). Le layout de base sert de cadre commun, pas de contenu.
- HTML email — contraintes techniques importantes :
  - Pas de CSS moderne (flexbox, grid) — utiliser des tables
  - Inline CSS via un transformeur (ex: laravel `premailer` ou intégration manuelle)
  - Images hébergées sur un CDN accessible publiquement (pas de S3 privé)
- Le runbook `docs/runbook-email-notifications.md` doit aussi couvrir : comment tester en local avec Mailtrap, comment vérifier qu'un mail a été envoyé en prod (logs queue + logs SMTP provider), procédure de rollback si spam / bounce rate élevé.
- Pas de pattern Prove It ici — c'est de l'infrastructure. Tests backend classiques : `Mail::fake()` + vérif assertion.
- Indépendant du reste du sprint, bloque FIX-24.2 à FIX-24.6.

---

#### FIX-24.2 : Email « Face sélectionnée après paiement Producer »

**Description :** Quand `MissionPaymentService::applySelectionOutcomesOnPaid()` (introduit par FIX-20.1) transite une candidature Face vers `Accepted` au moment du webhook `Paid`, envoyer un email à chaque Face sélectionnée via la nouvelle mailable `FaceSelectedMail`. L'email annonce : « Vous avez été sélectionnée pour la mission *X* » + détails (Producer, date de tournage, rémunération) + lien pour confirmer sa participation.

**Acceptance Criteria :**
- Nouvelle mailable `backend/app/Mail/FaceSelectedMail.php` héritant de `BaseMail`, prenant en paramètres : Face (pour le destinataire), Mission (pour le titre/date), Producer (pour le nom), amount (rémunération).
- Nouveau template `backend/resources/views/emails/face-selected.blade.php` étendant `emails/layouts/base` avec le contenu spécifique : salutation avec prénom Face, nom mission, nom Producer, date de tournage, rémunération en FCFA, CTA « Confirmer ma participation » pointant vers `https://{APP_URL}/face/candidatures`.
- Nouveau listener `backend/app/Listeners/Mission/SendFaceSelectedEmail.php` écoutant un event `FaceSelectedForMission` (à créer si inexistant) ou attaché directement dans `MissionPaymentService::applySelectionOutcomesOnPaid()` à l'endroit où `Notification::create()` est appelé pour le statut Accepted (référence : ligne ~545-599 de `MissionPaymentService.php`).
- Email asynchrone via `Mail::to($face->user->email)->queue(new FaceSelectedMail(...))`.
- Test backend feature : exécuter le flow webhook `Paid` avec une candidature sélectionnée, `Mail::fake()` + assert `Mail::assertQueued(FaceSelectedMail::class)` avec les bonnes données (destinataire, mission, montant).
- Test unit du rendu du template : snapshot du HTML rendu avec des fixtures, vérifier qu'il contient le prénom Face, le nom mission, le CTA.
- Non-régression : le flow webhook `Paid` continue de créer la `Notification` in-app + la `Conversation` (FIX-20.1), l'ajout de l'email ne casse rien.
- Accents français corrects dans le template (memory `feedback_accents_francais`).

**Technical Notes :**
- Point d'insertion : `backend/app/Services/MissionPaymentService.php::applySelectionOutcomesOnPaid()`, dans la boucle qui passe chaque candidature sélectionnée à `Accepted`. Juste après `Notification::create()` et `Conversation::firstOrCreate()`, ajouter `Mail::to($face->user->email)->queue(new FaceSelectedMail($face, $mission, $producer, $amount))`.
- Alternative plus propre : créer un event `FaceSelectedForMission` dispatché depuis `applySelectionOutcomesOnPaid`, avec deux listeners — `NotifyFaceInApp` (existant) et `SendFaceSelectedEmail` (nouveau). Préférer cette approche si le pattern event/listener est déjà utilisé ailleurs dans le domaine mission (voir `backend/app/Listeners/Booking/` pour la convention existante).
- Contenu email : s'assurer que le lien CTA pointe vers la bonne page frontend. Utiliser `config('app.frontend_url')` ou équivalent.
- Pas de Prove It (feature add, pas bug fix) — tests d'assertion directs.
- Dépendance : FIX-24.1 livré avant (layout de base).

---

#### FIX-24.3 : Email « Participation Face confirmée » → Producer

**Description :** Quand une Face appelle `POST /api/v1/face/candidatures/{id}/confirm` et que sa candidature transite effectivement à l'état confirmé (voir `backend/app/Http/Controllers/Api/V1/Face/CandidatureController::confirm` lignes 156-257), envoyer un email au Producer propriétaire de la mission via `FaceConfirmedMail`. L'email annonce : « *NomFace* a confirmé sa participation à votre mission *X* » + date de tournage + rappel de prochaine étape (contacter la Face, finaliser la logistique).

**Acceptance Criteria :**
- Nouvelle mailable `backend/app/Mail/FaceConfirmedMail.php` héritant de `BaseMail`, prenant en paramètres : Producer (destinataire), Face (nom), Mission (titre + date).
- Nouveau template `backend/resources/views/emails/face-confirmed.blade.php` étendant le layout de base.
- Déclenchement dans `Face/CandidatureController::confirm` au même point que la notification in-app (ligne ~218-237 selon l'investigation).
- Email asynchrone via `Mail::to($producer->email)->queue(new FaceConfirmedMail(...))`.
- Test feature : simuler un `postJson` confirm réussi, assert `Mail::assertQueued(FaceConfirmedMail::class)` avec bon destinataire.
- Test unit du template : HTML rendu contient nom Face, titre mission, date.
- Non-régression : le confirm endpoint continue de renvoyer 200 + update status + notification in-app.
- Accents français corrects.

**Technical Notes :**
- Point d'insertion exact : `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php:225` (juste après le `Notification::create` qui existe pour le Producer).
- Alternative : dispatcher un event `FaceConfirmedParticipation` + listener — recommandé si FIX-24.2 a suivi le pattern event/listener.
- Dépendance : FIX-24.1.
- Indépendant de FIX-24.2 mais bénéficie du même pattern si event-driven.

---

#### FIX-24.4 : Email « Mission clôturée → crédit wallet Face »

**Description :** Quand un Producer clôture une mission via `MissionService::completeMission()` (ou équivalent), les funds sont libérés vers les wallets des Faces qui ont participé. À ce moment, envoyer à chaque Face un email `MissionCompletedMail` annonçant : « La mission *X* est terminée, votre portefeuille a été crédité de *Y* FCFA » + lien vers son dashboard wallet.

**Acceptance Criteria :**
- Nouvelle mailable `backend/app/Mail/MissionCompletedMail.php` + template `emails/mission-completed.blade.php`.
- Déclenchement dans `MissionService::completeMission()` à l'endroit où le wallet de chaque Face est crédité (`walletService->creditDirect()` selon l'investigation).
- Email asynchrone, un par Face participante.
- L'email inclut : nom mission, montant crédité, nouveau solde du wallet Face (ou au moins le montant crédité), lien CTA vers le dashboard wallet `/face/wallet` ou équivalent.
- Test feature : simuler un `completeMission` avec 3 Faces participantes, assert `Mail::assertQueued(MissionCompletedMail::class)` exactement 3 fois avec les bons destinataires et montants.
- Test unit du template.
- Non-régression : la clôture mission continue de fonctionner (Notification in-app + crédit wallet) sans régression.

**Technical Notes :**
- Point d'insertion : `backend/app/Services/MissionService.php::completeMission()` ou `::releaseFunds()` selon l'architecture — à confirmer en lisant le code. L'investigation a pointé cette méthode comme responsable du crédit wallet Face.
- Attention au timing : envoyer l'email **après** le credit wallet confirmé en DB (pas avant), sinon risque d'annoncer un crédit qui n'a pas eu lieu si la transaction fail.
- Le `montant crédité` doit être le montant réel crédité (après éventuelle commission plateforme), pas le montant brut de la mission.
- Dépendance : FIX-24.1.

---

#### FIX-24.5 : Email « Demande de booking reçue » → Face

**Description :** Quand un Producer crée un booking ciblant une Face (via `BookingService::create()` ou équivalent, voir `backend/app/Services/BookingService.php:49-99`), la Face reçoit actuellement une notification in-app (via listener `NotifyFaceOnBookingReceived` sur l'event `BookingCreated`). Ajouter un email en parallèle via `BookingReceivedMail` : « *NomProducer* vous propose un booking pour le *date* au *lieu* » + montant proposé + CTA accepter/refuser.

**Acceptance Criteria :**
- Nouvelle mailable `backend/app/Mail/BookingReceivedMail.php` + template `emails/booking-received.blade.php`.
- Nouveau listener `backend/app/Listeners/Booking/SendBookingReceivedEmail.php` enregistré sur l'event `BookingCreated` — **en parallèle** du listener existant `NotifyFaceOnBookingReceived` (qui crée la notification in-app). Ne pas modifier le listener existant.
- Email asynchrone.
- Contient : nom Producer, date de tournage, lieu, durée estimée, montant proposé, CTA « Voir la demande » pointant vers `/face/bookings`.
- Test feature : dispatcher `BookingCreated`, assert `Mail::assertQueued(BookingReceivedMail::class)` + assert `Notification::create` toujours appelée (non-régression).
- Test unit du template.

**Technical Notes :**
- Event `BookingCreated` dispatché dans `backend/app/Services/BookingService.php:96` (selon investigation).
- EventServiceProvider (ou auto-discovery) doit mapper `BookingCreated` → `[NotifyFaceOnBookingReceived, SendBookingReceivedEmail]`.
- Dépendance : FIX-24.1.

---

#### FIX-24.6 : Email « Portefeuille Producer crédité »

**Description :** Quand le wallet d'un Producer est crédité (webhook FedaPay de remboursement, signalement no-show Face qui restitue des fonds, etc., voir `HandleFedapayWebhook:79-80` et `WalletService::creditDirect`), envoyer un email au Producer via `WalletCreditedMail` : « Votre portefeuille a été crédité de *X* FCFA » + motif du crédit + nouveau solde + lien dashboard wallet.

**Acceptance Criteria :**
- Nouvelle mailable `backend/app/Mail/WalletCreditedMail.php` + template `emails/wallet-credited.blade.php`.
- Déclenchement sur toutes les voies de crédit du wallet Producer :
  - `HandleFedapayWebhook` (webhook remboursement)
  - `NoShowReportService` ou équivalent (signalement absence Face)
  - Toute autre voie de crédit identifiée dans `WalletService::creditDirect` caller audit
- Email asynchrone.
- Contient : montant crédité, motif clair et actionnable (ex: « Remboursement mission annulée », « Crédit suite à absence signalée de la Face »), nouveau solde, lien CTA vers `/producer/wallet`.
- Test feature pour chaque voie de crédit : assert `Mail::assertQueued(WalletCreditedMail::class)` avec le bon motif.
- Test unit du template.

**Technical Notes :**
- Audit des callers de `WalletService::creditDirect()` (ou équivalent) à faire en début de story pour lister toutes les voies qui crédite un Producer.
- Alternative architecturale : créer un event `ProducerWalletCredited` dispatché par `WalletService::creditDirect()` quand le bénéficiaire est un Producer, avec un listener `SendWalletCreditedEmail`. Recommandé — évite d'ajouter l'appel email dans chaque caller et centralise la logique.
- Attention : distinguer les crédits **initiaux** (paiement d'un Producer pour une mission — ce n'est pas un « crédit » de son wallet, c'est un paiement sortant) des crédits **retour** (remboursement, no-show). L'email ne doit partir que pour les seconds.
- Dépendance : FIX-24.1.

---

### Epic FIX-25 : Hardening — bloquer les commandes DB destructives en local

**Goal :** Empêcher un `php artisan migrate:fresh`, `migrate:refresh` ou `db:wipe` de vider la base de dev `weact` par accident (incident historique rapporté par le user 2026-04-19). Autoriser uniquement l'env `testing` où `RefreshDatabase` en a légitimement besoin sur `weact_test`.

**Priority :** Critique — protection contre perte de données utilisateur. Fix atomique de 2 lignes, déjà appliqué en pré-commit direct après accord du user.

**Contexte de l'incident :** Le user a rapporté avoir constaté plusieurs vidages inexpliqués de sa base `weact` après certaines sessions de tests. Root cause identifiée : `AppServiceProvider.php:31` ne prohibait les commandes destructives qu'en production (`$this->app->isProduction()`), laissant l'env `local` totalement exposé. Scénarios plausibles : un `artisan migrate:fresh` tapé sans `--env=testing`, ou une session de tests lancée avec `APP_ENV` mal positionné dans le shell → `RefreshDatabase` exécute `migrate:fresh` sur `weact`.

#### Stories

| ID | Story | Priority |
|----|-------|----------|
| FIX-25.1 | `prohibitDestructiveCommands` actif partout sauf env testing | Critique |

---

#### FIX-25.1 : `prohibitDestructiveCommands` actif partout sauf env testing

**Description :** Modifier `backend/app/Providers/AppServiceProvider.php:31` pour inverser la logique : au lieu de bloquer **uniquement** en production, bloquer **partout sauf en testing**. L'env `testing` reste autorisé car `RefreshDatabase` exécute `migrate:fresh` sur `weact_test` (base dédiée, jamais `weact`).

**Acceptance Criteria :**
- `backend/app/Providers/AppServiceProvider.php:31` passe de `DB::prohibitDestructiveCommands($this->app->isProduction())` à `DB::prohibitDestructiveCommands(! $this->app->environment('testing'))`.
- `php artisan test` continue de passer (l'env testing autorise les commandes destructives nécessaires à `RefreshDatabase`).
- En env local, `php artisan migrate:fresh` → lève `ProhibitedDestructiveCommandException`.
- En env production, le comportement reste inchangé (commandes toujours prohibées).
- Commentaire dans le code explique pourquoi testing est exempté (référence à `RefreshDatabase` + DB dédiée `weact_test`).

**Technical Notes :**
- Fichier : `backend/app/Providers/AppServiceProvider.php:29-35`.
- Pas de test Prove It automatisé : tester un `migrate:fresh` depuis une suite PHPUnit tournant en env testing demande de spawn un sous-processus avec `APP_ENV=local`. Pertinent un jour mais trop lourd pour un hardening de 2 lignes.
- Vérification manuelle après application : `cd backend && php artisan test --filter=LoginTest::test_successful_face_login_returns_200` → doit passer (confirme que testing n'est pas bloqué).
- **Effet secondaire bénéfique pour le sprint** : prévient les incidents similaires à celui qui a probablement motivé l'ajout de l'`.env.testing` à un moment donné. Sécurise aussi les futurs agents (Claude, Codex) qui pourraient taper des commandes destructives par erreur.
- Référence Laravel : `Illuminate\Support\Facades\DB::prohibitDestructiveCommands(bool $prohibit)` — Laravel 11+ API, disponible en Laravel 12.
