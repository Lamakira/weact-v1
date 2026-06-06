# Évaluation sécurité de l'authentification — décision & plan

- **Date** : 2026-06-06
- **Statut** : Proposition de décision — **à challenger par un second agent avant exécution**
- **Périmètre** : système d'authentification (backend Laravel 12 + Sanctum, frontend Vue 3)
- **But du document** : tracer **toutes les preuves et le raisonnement** qui mènent à la décision finale, plus le **petit plan** proposé. Tout est référencé en `fichier:ligne` pour être vérifié, pas cru sur parole.

> ⚠️ **Note méthodo pour le relecteur** : ce document est issu d'une vérification d'un audit antérieur. L'audit était **factuellement exact** mais sa **calibration de gravité était surévaluée**. La vérification a aussi **corrigé une recommandation initiale erronée** (« débrancher le middleware bearer ») — voir §4. Vérifie les claims toi-même via l'index de preuves (§9), ne te fie pas au ton.

---

## 1. Décision (TL;DR)

**Ne PAS entreprendre le gros refactor « passer le web à l'auth par cookie httpOnly » maintenant.** La **probabilité** d'un vol de token via XSS est faible (§5), **mais l'impact d'une fuite serait élevé** (paiements + données perso). Le bon arbitrage n'est donc **pas** « ce n'est pas grave » : c'est que **le gros refactor n'est pas rentable maintenant** (coût réel, multi-fichiers, entrelacé avec un bug historique — §4), **et que les petits durcissements (§7) sont OBLIGATOIRES, pas optionnels**.

À la place : **4 actions de durcissement bon marché** (§7) qui couvrent l'essentiel du risque pour une fraction du coût, et **garder l'infra token** pour la future app mobile.

**Revisiter la décision** si un jour le grand public peut publier du **contenu mis en forme (HTML riche)** — voir §8.

---

## 2. Comment fonctionne l'auth aujourd'hui (constat factuel)

L'app monte **deux mécanismes en parallèle** :

- **SPA stateful Sanctum (cookie de session + CSRF)** : activé globalement via `$middleware->statefulApi()` — `backend/bootstrap/app.php:52`. Guard session côté Sanctum : `'guard' => ['web']` — `backend/config/sanctum.php:37`.
- **Token Bearer (Personal Access Token)** : émis à chaque login — `backend/app/Services/Auth/LoginService.php:35` (`$user->createToken('auth-token')->plainTextToken`), renvoyé dans le corps JSON et utilisé par le frontend.

**C'est le token Bearer qui authentifie réellement.** Les routes protégées portent `['auth:sanctum', 'api.token']`, et `api.token` (= `EnsureApiBearerToken`, `backend/bootstrap/app.php:61`) **force** la résolution de l'utilisateur depuis le Bearer et **rejette en 401 toute requête sans token** — `backend/app/Http/Middleware/EnsureApiBearerToken.php:23-49`. Le middleware est appliqué sur **tous** les groupes protégés :
- `backend/routes/api.php:75`
- `backend/routes/api/admin.php:37`
- `backend/routes/api/face.php:42`
- `backend/routes/api/producer.php:27`
- `backend/routes/api/bookings.php:12`

Côté frontend, le token et l'utilisateur sont stockés dans **`localStorage`** (pas en cookie httpOnly) : `frontend/src/services/apiClient.ts:48` (clé `auth_token`), `:69` (lecture), `:71` (header `Authorization: Bearer`). Le client envoie aussi `withCredentials: true` (`:60`) et un header CSRF manuel (`:74-81`), avec un `getCsrfCookie()` (`:148`) appelé avant chaque mutation.

**Point important** : le login **ne crée aucune session web** (`LoginService.php:22` commentaire + absence de `Auth::login`). Voir §4.

---

## 3. Vérification des alertes de l'audit (claim → preuve → verdict)

| # | Alerte | Preuve vérifiée | Verdict | Gravité réelle |
|---|--------|-----------------|---------|----------------|
| A | Token Bearer en `localStorage` (exposition XSS) | `apiClient.ts:48,69,71` | **Confirmé** | **Faible en pratique** (voir §5) |
| B | Tokens **non-expirants**, abilities `['*']` | `sanctum.php:50` (`expiration => null`) + `LoginService.php:35` (pas de `expiresAt` ni scope) | **Confirmé** | Moyen (amplifie A) |
| C | **Accumulation** de tokens | nouveau token à chaque login (`LoginService.php:35`) + logout ne révoque que le token courant (`LogoutController`) → sessions abandonnées = tokens vivants pour toujours | **Confirmé** (sous-estimé par l'audit) | Moyen (hygiène) |
| D | Rate-limit login par **email seul** (pas IP) | route `/login` **sans** middleware throttle `backend/routes/api.php:55-56` ; clé `'login:'.email` `LoginController.php:29` | **Confirmé** | Moyen |
| E | `/auth/reset-password` **sans throttle** | `backend/routes/api.php:62-63` (vs `forgot-password` throttlé `:58-60`) | **Confirmé** (atténué par token signé) | Faible-moyen |
| F | `SESSION_SECURE_COOKIE` non forcé | `backend/config/session.php:172` (`env(...)`, donc null par défaut) | **Confirmé** | Faible (l'auth ne repose pas sur le cookie) |
| G | CSRF = surcoût/« travail mort » pour du Bearer | `apiClient.ts:148` round-trip avant chaque mutation | **Partiellement** : le CSRF *est* validé sur les requêtes stateful ; il est **redondant** (le Bearer n'est pas auto-envoyé cross-site), pas littéralement mort | Cosmétique/perf |
| H | Double mécanisme « contradictoire » | §2 + §4 | **Nuance** : design **intentionnel** (mobile prévu) ; le souci est que le **web** utilise la méthode du **mobile** (§4) | — |

**Points positifs confirmés** : réponse de login générique (`LoginController.php:55` « Email ou mot de passe incorrect ») → ne révèle pas l'existence de l'email ; révocation propre et multi-device au logout ; throttle présent sur register/forgot/password-change ; normalisation globale des erreurs `bootstrap/app.php:69-228`.

---

## 4. L'insight central : dual-mode intentionnel, mais le web est sur la mauvaise voie

**Le design à deux mécanismes est correct et voulu** : une app mobile était prévue dès le départ, et Sanctum est fait pour exactement ça — **cookie httpOnly pour le SPA web first-party, token Bearer pour le mobile/tiers**. Avoir les deux n'est pas le bug.

**Le vrai problème** : le **client web** utilise le mécanisme **du mobile** (token + `localStorage`), rangé au pire endroit pour un navigateur. Le risque XSS-token est donc un risque **web uniquement** (le mobile range son token dans le stockage sécurisé natif, ce qui est correct).

**Pourquoi le web est coincé sur le token** — origine vérifiée :
- Commit `f6801f1` : *« fix(auth): force API routes to use bearer tokens over stale Sanctum sessions »* + *« Add a regression test for re-registration after account deletion »*.
- Pas d'**Octane** installé (`composer.lock` : 0 occurrence `laravel/octane`) → aucun vrai « user mis en cache entre requêtes » au niveau framework. Le commentaire du code sur ce point est trompeur.
- **Aucun code applicatif n'authentifie la session web** : le seul `->login(` est `loginService->login()` (méthode de service, pas `Auth::login`). Pas de `Auth::login` / `loginUsingId` / `guard('web')->setUser` dans `backend/app/`. Donc en prod, la session n'est jamais peuplée d'un user authentifié.
- Le test de régression `backend/tests/Feature/Auth/AccountDeletionReRegistrationTest.php:15,71-79` **simule** la session périmée avec `actingAs($deletedUser)` (artefact de test) + un Bearer du nouveau compte, et assert que **le Bearer gagne**.

**Conséquences (et correction d'une reco initiale erronée)** :
1. En prod, comme la session n'est jamais authentifiée, un `auth:sanctum` simple retomberait **déjà** sur le Bearer. `api.token` est donc **fonctionnellement redondant en prod aujourd'hui**.
2. **MAIS** `api.token` n'est **pas** un déchet supprimable isolément :
   - le retirer **casserait** le test de régression (`actingAs` gagnerait) ;
   - surtout, **passer le web aux cookies réintroduit la classe de bug** que ce commit combattait : une suppression de compte + cookie de session qui traîne = « mauvais user ». Aujourd'hui c'est évité *par accident* (la session n'est jamais authentifiée). En cookie, il faudra **gérer rigoureusement le cycle de vie de la session** (invalidation au logout **et** à la suppression de compte + ré-inscription même-email).
3. Donc `api.token` est le **premier domino** de la migration web→cookie, pas un nettoyage autonome. Le bug d'origine est **réel** pour la cible cookie — c'est une sous-tâche dure connue, pas un détail.

---

## 5. Évaluation décisive : la surface XSS (vérifiée)

Le risque A (vol du token) n'est exploitable **que si un script malveillant peut s'exécuter sur le site**. Vérification de cette surface côté frontend :

- **Un seul `v-html` dans tout le site** : `frontend/src/views/ArticleDetailView.vue:122` (`v-html="sanitizedContent"`).
- Ce contenu est **nettoyé** : `sanitizedContent = computed(() => sanitizeHtml(article.value.content))` `ArticleDetailView.vue:9-11`, via `frontend/src/utils/sanitize.ts:8-9` (`DOMPurify.sanitize`). DOMPurify `^3.3.1` est en dépendance (`frontend/package.json`).
- Le nettoyage est **testé**, y compris le retrait de scripts : `frontend/src/utils/__tests__/sanitize.spec.ts`.
- Les **articles sont écrits par les admins/l'équipe**, pas par le grand public (CRUD admin : routes `v1/admin/articles/*`).
- **Tout le reste** du contenu utilisateur (profils, descriptions de missions, noms…) est rendu en **texte brut** via l'interpolation Vue `{{ }}`, **auto-échappée** → pas de vecteur.
- `innerHTML` n'apparaît que dans des **fichiers de test** (`*.spec.ts`), pas en code de prod.
- **Seul trou réel : aucune CSP** (Content-Security-Policy) — `grep` sur frontend + backend = aucune occurrence. C'est un « second mur » manquant, pas la défense principale.

**Conclusion** : la surface d'injection est **petite, à autorat de confiance (admin), et déjà nettoyée**. La probabilité d'un vol de token via XSS est **faible**.

---

## 6. Les entrées de la décision

| Entrée | Valeur (vérifiée / fournie par le PO) | Effet sur la décision |
|--------|----------------------------------------|------------------------|
| Surface XSS | **Petite + nettoyée** (§5) | **Probabilité** de vol faible (mais **impact élevé**) → migration peu rentable, durcissement **obligatoire** |
| Topologie prod | **Site et serveur à la même adresse** (PO) | La méthode cookie *serait* facile, mais ne suffit pas à justifier vu le risque faible |
| App mobile | **« Un jour », quand le web sera au point** (PO) | Pas d'urgence ; le token **reste** pour le mobile |
| Sensibilité | Paiements (FedaPay) + données perso (Code du Numérique) | Un compte volé est sérieux → relève la valeur des **petits** durcissements |
| Coût migration | Réel, entrelacé avec le bug §4 | Élevé vs bénéfice |

→ **Probabilité faible × impact élevé × coût réel = durcir maintenant (§7, obligatoire), migration plus tard si le profil de risque change (§8).**

---

## 7. Le petit plan proposé (durcissement bon marché)

> Préalable commun : **cartographier le blast-radius + baseline verte des tests auth** avant toute modif (l'auth touche chaque test authentifié). Chaque action suit le pattern « prouvée par test ».

> **État au 2026-06-06 :** Actions **1, 2, 4 ✅ implémentées et prouvées** (suite complète verte, 2644 tests) ; Action **3 ⏸️ reportée** (couche déploiement). Détail, fichiers et tests en **§12**.

### Action 1 — Throttle login robuste *(priorité haute, ~1-2 h)*
- **Quoi** : clé de throttle composite `email|ip` (défaut Laravel) **+** un `throttle` IP sur la route `/login`.
- **Où** : `LoginController.php:29` (clé) ; `routes/api.php:55` (ajouter le middleware).
- **Pourquoi** : (a) casse le **lockout de victime** (aujourd'hui un attaquant verrouille n'importe quel email connu) ; (b) borne le **password spraying** par IP.
- **Tests** : augmenter le test throttle existant + ajouter « victime non verrouillée depuis sa propre IP » + « spray IP borné ».
- **Honnêteté** : le rate-limit est de l'**hygiène, pas un rempart** contre une attaque distribuée (IPs tournantes). Complément cheap : **alerter** sur le log `auth.login.throttled` (déjà émis, `LoginController.php:32`).

### Action 2 — Expiration + purge des tokens *(priorité haute, ~S)*
- **Quoi** : donner une **expiration** aux tokens (`sanctum.php:50` ou `expiresAt` par token) + une **commande planifiée** de purge des tokens expirés/inactifs.
- **Où** : `config/sanctum.php:50` ; nouvelle commande + wiring (pattern existant `ScheduleWiringTest`).
- **Pourquoi** : si un token fuit, il ne vaut plus rien après le délai ; et ça stoppe l'**accumulation** non bornée (§3-C).
- **Décision requise du PO** : **durée d'expiration** (compromis UX/sécu). Sans flux de *refresh*, l'utilisateur sera déconnecté périodiquement — acceptable ? Sinon prévoir une expiration glissante (petit surcoût frontend).
- **Tests** : vérifier le blast-radius (les tests en `actingAs` ne sont pas affectés ; ceux qui créent un token et tablent sur sa longévité, oui).

### Action 3 — Ajouter une CSP (Content-Security-Policy) *(priorité haute, ~S, le seul vrai trou)*
- **Quoi** : poser des en-têtes CSP (middleware backend ou serveur web) pour bloquer les scripts non autorisés — un « second mur » même si un script passait le nettoyage. **Déployer d'abord en `Content-Security-Policy-Report-Only`** (collecte les violations sans rien casser), puis basculer en mode bloquant une fois la politique calibrée.
- **Pourquoi** : comble le **seul** point faible XSS identifié (§5), pour bien moins cher que la migration cookie.
- **Risque** : une CSP trop stricte casse l'app **silencieusement** → le **report-only d'abord** (raffinement de la revue indépendante, §11) évite ça ; calibrer et **tester** contre les scripts/styles inline, Vite, polices, et domaines externes type FedaPay.

### Action 4 — Hygiène de config *(priorité moyenne, ~XS)*
- **4a** : `SESSION_SECURE_COOKIE=true` en prod → `.env.example` + note de déploiement (pas de code ; `session.php:172` lit déjà l'env).
- **4b** : throttle sur `/auth/reset-password` (calque `forgot-password`) + test 429 → `routes/api.php:62`.

**Ce qui ne change PAS** : le token reste en `localStorage` côté web (mitigé par 2+3), et l'infra Bearer reste **intacte pour le mobile**.

---

## 8. Quand revisiter le « gros changement »

Le refactor web→cookie redevient pertinent si l'une de ces conditions apparaît :
- le **grand public** peut publier du **contenu mis en forme (HTML riche)** rendu via `v-html` (forum, commentaires riches, descriptions riches…) → la surface XSS s'agrandit ;
- un incident/une exigence de conformité impose l'élimination de la classe XSS-token, pas seulement sa réduction.

Le cas échéant, le chantier (rappel §4) : ajouter l'auth de session au login web, retirer `api.token`, **re-résoudre proprement le bug de session périmée** (invalider la session aussi à la suppression de compte), réécrire le test de régression en sémantique cookie, migrer le frontend (sortir le token du `localStorage`, s'hydrater via `/api/v1/user`). Le mobile garde le token.

---

## 9. Index de preuves (à spot-checker)

**Backend**
- `backend/bootstrap/app.php:52` — `statefulApi()`
- `backend/bootstrap/app.php:61` — alias `api.token => EnsureApiBearerToken`
- `backend/app/Http/Middleware/EnsureApiBearerToken.php:23-49` — force Bearer, 401 si pas de token
- `backend/config/sanctum.php:37` — `guard => ['web']` ; `:50` — `expiration => null`
- `backend/app/Services/Auth/LoginService.php:22,35` — login token-only, pas de session
- `backend/app/Http/Controllers/Api/V1/Auth/LoginController.php:29,31,51,55` — clé throttle email-only
- `backend/routes/api.php:55-56` (login no throttle), `:62-63` (reset-password no throttle), `:75` (groupe protégé)
- `backend/routes/api/{admin:37,face:42,producer:27,bookings:12}.php` — `api.token` partout
- `backend/config/session.php:172` (secure=env), `:185` (http_only), `:194` (same_site lax)
- `backend/config/auth.php:38-48` — guards `web`/`admin` (session)
- `backend/app/Http/Controllers/Api/V1/Auth/LogoutController.php:27,29` — logout web + invalidate
- `backend/tests/Feature/Auth/AccountDeletionReRegistrationTest.php:15,71-79` — stale session simulée via `actingAs`
- commit `f6801f1` — introduit `EnsureApiBearerToken`
- `composer.lock` — **pas** d'`laravel/octane`

**Frontend**
- `frontend/src/services/apiClient.ts:48,60,69,71,74-81,112-135,148` — token localStorage, withCredentials, XSRF manuel, intercepteur 401, getCsrfCookie
- `frontend/src/views/ArticleDetailView.vue:9-11,122` — unique `v-html`, contenu nettoyé
- `frontend/src/utils/sanitize.ts:8-9` — DOMPurify
- `frontend/src/utils/__tests__/sanitize.spec.ts` — tests de nettoyage
- `frontend/package.json` — `dompurify ^3.3.1`
- **Aucune** CSP (grep frontend + backend)

**Commandes de re-vérification suggérées** (lecture seule) :
```
grep -rn "v-html" frontend/src
grep -rniE "content-security-policy" frontend backend/app backend/bootstrap backend/config backend/public
grep -rn "api.token\|EnsureApiBearerToken" backend/routes backend/bootstrap
grep -rn "Auth::login\|->login(\|loginUsingId" backend/app
git show -s f6801f1
```

---

## 10. Réserves & limites (ce qui n'a PAS été vérifié)

À traiter avant exécution, surtout si le relecteur penche pour la migration :
1. ~~**Fortify**~~ — **RÉSERVE LEVÉE (revue indépendante 2026-06-06, §11)** : aucun Fortify détecté dans `backend/` ni `backend/composer.*`. La session n'est donc pas authentifiée par cette voie ; le constat « la session n'est jamais authentifiée » tient.
2. **Magnitude exacte du coût** : non chiffrée. Dépend de 3 comptages non faits — nombre de call-sites `getCsrfCookie`, nombre de tests qui assert `data.token`, taux de `actingAs` vs login réel.
3. **Re-audit complet non fait** : ce document **vérifie les claims de l'audit** + la surface XSS ; il ne re-audite pas toute la surface auth de zéro (origines CORS détaillées, client admin séparé `adminApiClient.ts`, store `auth.ts` pris de l'audit sans relecture directe, tous les fichiers de routes).
4. **CSP** : recommandée mais non spécifiée en détail (politique exacte à définir + tester contre FedaPay et les assets Vite).

---

## 11. Revue indépendante (2026-06-06)

Un **second agent** a relu et **spot-checké** ce document. Conclusion : **accord sur la décision** — ne pas lancer le chantier cookie-only maintenant, **à condition de faire vite les durcissements**.

**Vérifications confirmées de son côté** : pas de Fortify (dans `backend/` ni `backend/composer.*`), pas d'Octane, pas de `Auth::login` applicatif, `api.token` sur les groupes protégés (`api.php:75`, `admin.php:37`, `face.php:42`), unique `v-html` prod nettoyé (`ArticleDetailView.vue:122` ← `sanitize.ts:8`), aucune CSP, commit `f6801f1` conforme.

**Corrections adoptées dans ce document suite à la revue :**
- **Cadrage du risque** (§1, §6) : « risque faible » reformulé en **« probabilité faible mais impact élevé »** → les durcissements sont **obligatoires**, pas « pas grave ». *(C'était une imprécision réelle du premier passage : risque = probabilité × impact.)*
- **CSP en report-only d'abord** (§7, Action 3) pour éviter une casse silencieuse.
- **Réserve Fortify levée** (§10).

**Ordre de priorité confirmé par la revue** : (1) throttle login `email|ip` + throttle IP route ; (2) expiration + purge des tokens ; (3) CSP (report-only puis bloquant) ; (4) throttle `/auth/reset-password` + `SESSION_SECURE_COOKIE=true` en prod.

**Accord explicite des deux passages** : ne **pas** retirer `api.token` isolément — c'est un domino de la migration cookie-only, pas un simple nettoyage.

---

## 12. Implémentation (2026-06-06)

Actions 1, 2 et 4 implémentées et **prouvées par tests** (suite complète **2644 passed, 0 régression** ; Pint pass ; PHPStan No errors). Action 3 reportée (couche déploiement, décision PO). Rien retiré de l'infra token (le mobile la réutilisera).

| Action | État | Fichiers | Tests |
|---|---|---|---|
| **1 — Throttle login** | ✅ | `app/Http/Controllers/Api/V1/Auth/LoginController.php` (clé `email\|ip`) ; `routes/api.php` (`throttle:30,1` sur `/login`) | `LoginTest` : `throttle_does_not_lock_victim_from_a_different_ip`, `ip_throttle_bounds_spraying_across_many_emails`, `throttle_distinguishes_different_emails_on_same_ip` (renommé) |
| **2 — Expiration + purge** | ✅ | `config/sanctum.php` (`expiration` = 30 j) ; `routes/console.php` (`sanctum:prune-expired` quotidien, UTC, `withoutOverlapping`+`onOneServer`) | `LoginTest::test_token_expires_after_30_days` ; `ScheduleWiringTest::test_prune_expired_sanctum_tokens_is_scheduled_daily` |
| **3 — CSP** | ⏸️ reporté | — (header au serveur web, hors repo) | — |
| **4 — Config** | ✅ | `routes/api.php` (`throttle:5,1` sur `/reset-password`) ; `.env.example` (note `SESSION_SECURE_COOKIE` prod) | `ResetPasswordTest::test_reset_password_rate_limiting` |

**Détours vérifiés → aucun fix (fausses alertes)** : le login admin a déjà `throttle:5,1` par IP (et pas le défaut de lockout du login user, car clé IP et non email) ; les comptes admin n'ont pas de champ `is_active` (pas de désactivation → rien à vérifier au login, l'« off » d'un admin = suppression, déjà gérée par le rejet du token orphelin).

**Piège rencontré et documenté** : l'expiration globale Sanctum a d'abord semblé inopérante en test → c'était un **artefact de test** (un appel authentifié avant `travel()` cache l'utilisateur sur le guard `web` du conteneur persistant, masquant l'expiry). Vérifié contre la source Sanctum (`vendor/laravel/sanctum/src/Guard.php:148-150`) + absence de cache de config + valeur effective (`config('sanctum.expiration') === 43200`). **Code correct** ; commentaire ajouté dans le test pour ne pas réintroduire le piège.

**Reste à faire** :
- **Action 3 (CSP)** — quand l'accès au serveur de prod sera disponible : doc rollout (politique report-only + snippets nginx/Apache) + collecteur `/csp-report` in-repo.
- **`SESSION_SECURE_COOKIE=true`** à poser dans l'env de **production** (le code lit déjà la variable, `config/session.php:172`).
- **Migration cookie httpOnly** — décidée « pas maintenant » (§1) ; à revisiter si du contenu riche utilisateur est ouvert au public (§8).

---

*Fin du document. Décision validée par deux relectures indépendantes ; durcissements 1/2/4 implémentés et prouvés (2026-06-06). Reste : CSP (déploiement) + `SESSION_SECURE_COOKIE` prod.*
