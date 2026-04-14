---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-14'
totalEpics: 1
totalStories: 6
project_name: 'WEACT - Correctifs Post-Lancement Sprint 11'
user_name: 'Amakira'
date: '2026-04-14'
---

# WEACT - Correctifs Post-Lancement Sprint 11 - Epic Breakdown

## Overview

Investigation d'un incident Face (toast générique « Une erreur est survenue » sur `Confirmer ma participation`) révèle que `CandidatureStatus::Accepted` est sémantiquement surchargé : il signifie simultanément « sélectionnée par le producer » **et** « paiement bloqué, confirmable par la Face », alors que ces deux signaux n'arrivent pas en même temps. `MissionPaymentService::prepareSelectionForPayment` écrit `Accepted` et envoie la notification `candidature_accepted` **avant** la confirmation du paiement FedaPay. Entre cette écriture et le webhook `Paid`, la Face est notifiée d'un état qu'elle ne peut pas utiliser : `Face/CandidatureController::confirm` exige en plus `MissionPaymentStatus::Paid` et renvoie un 422 masqué par le fallback générique du composable.

**Décision produit (2026-04-14) :** unifier le contrat autour d'un seul sens — `Accepted` = « sélection du producer confirmée par paiement, la Face peut confirmer sa participation dès maintenant ». La Face n'apprend qu'elle a été retenue qu'après paiement confirmé ; plus d'état ambigu, plus de notification prématurée, plus de divergence entre état affiché et action possible. Pas de statut intermédiaire — on déplace la transition `Accepted` vers le webhook `Paid`.

Cette unification entraîne en cascade : simplification du endpoint `confirm` (check `Paid` devenu redondant), simplification de la compensation FIX-19 (plus de rollback de `candidature.status` à faire puisque la prep ne mute plus rien), suppression du chemin legacy d'acceptation manuelle Producer (vestige pré-FedaPay qui contredit frontalement le nouveau contrat), et filet de sécurité frontend pour rendre lisibles les erreurs résiduelles de confirmation.

### Audit DB exécuté en prod (2026-04-14)

Résultats :

- **Requête 1 — candidatures `Accepted` avec `MissionPayment` non-`Paid`** : 4 lignes, sur 4 payments distincts (IDs 1, 2, 3, 7), 4 missions distinctes (8, 11, 14, 17). **`fedapay_transaction_id = NULL` sur les 4** — aucun checkout FedaPay n'a été créé avec succès. Candidatures concernées : 79, 349, 398, 489.
- **Requête 2 — candidatures `Rejected` orphelines** : 177 lignes, réparties sur les **mêmes 4 payments** (≈113 sur mission 8, ≈27 sur mission 11, ≈19 sur mission 14, ≈17 sur mission 17).
- **Total impact : ~181 candidatures à réconcilier, 4 Producers à notifier.**

**Anomalie payment 7 — résolue :** initialement, le payment 7 (mission 17, candidature 489, créé le 2026-04-14 07:37) paraissait postérieur à FIX-19.1 et donc suspect. Vérification a posteriori de la date de merge de la PR #23 (`fix(mission-payment): épopée FIX-19 — fiabiliser l'initiation de paiement mission`) : mergée sur `main` le 2026-04-14 ~10:23, soit ~2h45 **après** la création du payment 7. Conclusion : payment 7 est une row historique créée dans la dernière fenêtre vulnérable juste avant la mise en prod, pas le signal d'un chemin de code non couvert. Pas de FIX-19.1 résiduel à corriger. Une requête de sanity check post-merge doit confirmer qu'aucune nouvelle orpheline n'apparaît dans la fenêtre post-déploiement (sinon, réouvrir l'investigation).

Hypothèse sur l'incident initial : la candidature 489 (payment 7, 2026-04-14 07:37) correspond très probablement à l'incident Face « toast générique » qui a déclenché cette investigation. La Face voit `accepted`, clique « Confirmer ma participation », obtient un 422 `PAYMENT_NOT_CONFIRMED` masqué par le fallback générique.

### Requêtes d'audit (pour re-exécution)

```sql
-- Candidatures Accepted dont le MissionPayment n'est pas Paid
-- Sous le nouveau contrat, elles n'auraient jamais dû passer à Accepted
-- et doivent être rebasculées vers Pending par la migration.
SELECT c.id, c.uuid, c.status, c.mission_id,
       mp.id AS payment_id, mp.status AS payment_status,
       mp.fedapay_transaction_id, mp.created_at, mp.updated_at
FROM candidatures c
JOIN mission_payment_candidatures mpc ON mpc.candidature_id = c.id
JOIN mission_payments mp ON mp.id = mpc.mission_payment_id
WHERE c.status = 'accepted'
  AND mp.status <> 'paid';

-- Candidatures Rejected par le reject en masse de prepareSelectionForPayment
-- alors que le paiement de leur mission n'a jamais confirmé. Même logique :
-- sous le nouveau contrat, elles seraient restées Pending.
SELECT c.id, c.mission_id, c.status,
       mp.id AS payment_id, mp.status AS payment_status
FROM candidatures c
JOIN missions m ON m.id = c.mission_id
JOIN mission_payments mp ON mp.mission_id = m.id
WHERE c.status = 'rejected'
  AND mp.status <> 'paid'
  AND NOT EXISTS (
    SELECT 1 FROM mission_payment_candidatures mpc
    WHERE mpc.candidature_id = c.id AND mpc.mission_payment_id = mp.id
  );
```

Le résultat (audit 2026-04-14 : ~181 lignes) est embarqué dans FIX-20.6, dédiée à la réconciliation des données et aux notifications utilisateur.

## Requirements Inventory

### Functional Requirements

FIX20-FR1 : La transition `candidature.status → Accepted` ne doit se produire **qu'au moment** où le `MissionPayment` correspondant passe à `Paid` (via handler de webhook FedaPay).
FIX20-FR2 : Aucune notification `candidature_accepted` ne doit partir avant que la candidature ne soit effectivement `Accepted` sous le nouveau contrat — le signal utilisateur suit l'état réel.
FIX20-FR3 : `MissionPaymentService::prepareSelectionForPayment` ne doit plus muter aucun `candidature.status` (ni vers `Accepted`, ni vers `Rejected`). Elle se limite à créer le `MissionPayment` + ses `MissionPaymentCandidature` entries.
FIX20-FR4 : Le endpoint `Face/CandidatureController::confirm` doit pouvoir reposer uniquement sur `candidature.status === Accepted`. Le check `MissionPaymentStatus::Paid` devient défensif (log d'alerte en cas de rupture d'invariant) ou retiré, au choix documenté.
FIX20-FR5 : Aucun endpoint backend ne doit permettre de passer une candidature en `Accepted` hors de la transition webhook — suppression du chemin legacy `POST /producer/candidatures/{id}/accept` et de sa chaîne frontend.
FIX20-FR6 : La logique de compensation FIX-19 doit être simplifiée pour ne plus tenter de rollback de mutations qu'elle n'a plus fait subir aux candidatures (seule la suppression des `MissionPayment` + entries reste pertinente).
FIX20-FR7 : Côté frontend Face, toute erreur 422/429/500/network de `confirm` doit être affichée avec un message actionnable — en lisant le `error.message` backend quand il est présent — pour que toute rupture résiduelle soit diagnosticable en prod.
FIX20-FR8 : Les ~181 candidatures actuellement dans un état incompatible avec le nouveau contrat (4 `Accepted` + 177 `Rejected` sur 4 `MissionPayment` non-`Paid`) doivent être réconciliées avant le déploiement du refactor, avec notifications explicatives envoyées aux Faces et Producers concernés.

## Epic & Story Breakdown

---

### Epic FIX-20 : Unification du contrat `Accepted` = sélection payée et confirmable

**Goal :** Éliminer la surcharge sémantique de `CandidatureStatus::Accepted` en déplaçant la transition et les notifications associées vers le webhook `Paid`. Après refactor, `Accepted` signifie une seule chose — « la Face peut confirmer sa participation dès maintenant » — et toute la chaîne downstream (confirm, chat, notifications, compensation, UI) s'aligne sur ce contrat.

**Priority :** Haute — aucun incident bloquant en prod (audit DB initial : zéro candidature orpheline hors-flow), mais le bug du toast générique est reproductible dès qu'un checkout FedaPay est lent ou abandonné, et le chemin legacy reste un risque latent. Le refactor simplifie aussi durablement le code critique du flux mission.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-20.1 | Déplacer la transition `Accepted` + rejet en masse vers le webhook `Paid` | FIX20-FR1, FIX20-FR2, FIX20-FR3 | Critique |
| FIX-20.2 | `Accepted` intrinsèquement confirmable — simplifier `Face/CandidatureController::confirm` | FIX20-FR4 | Haute |
| FIX-20.3 | Supprimer le chemin legacy d'acceptation manuelle candidature Producer | FIX20-FR5 | Haute |
| FIX-20.4 | Simplifier la compensation FIX-19 rendue partiellement obsolète | FIX20-FR6 | Moyenne |
| FIX-20.5 | Filet de sécurité — mapping des erreurs de confirmation Face | FIX20-FR7 | Haute |
| FIX-20.6 | Réconcilier les données de production incompatibles avec le contrat cible | FIX20-FR8 | Critique |

**Ordre de livraison recommandé :**

1. **FIX-20.5** — quick win indépendant, améliore immédiatement le diagnostic des incidents en prod.
2. **FIX-20.3** — suppression du chemin legacy, indépendante, faible risque, cohérente avec le nouveau contrat.
3. **FIX-20.6** — réconciliation des 181 candidatures + reset `mission.status` + notifications. À exécuter **avant** le refactor pour que FIX-20.1 s'applique sur une base propre. L'investigation sur le payment 7 étant résolue (row historique pré-déploiement), la story est purement un nettoyage de données.
4. **FIX-20.1** — cœur du sprint, refactor du contrat métier. Point bascule.
5. **FIX-20.2** — simplification `confirm`, dépend de FIX-20.1.
6. **FIX-20.4** — simplification compensation, dépend de FIX-20.1.

---

#### FIX-20.1 : Déplacer la transition `Accepted` et le rejet en masse vers le webhook `Paid`

**Description :** Refactor du flux d'acceptation mission pour retirer **toute** mutation de `candidature.status` de `MissionPaymentService::prepareSelectionForPayment`. Les transitions (sélectionnées → `Accepted`, non-sélectionnées → `Rejected`) et leurs notifications (`candidature_accepted`, `candidature_rejected`) sont déplacées dans une méthode dédiée `applySelectionOutcomesOnPaid` appelée par `HandleFedapayWebhook` au moment où `MissionPayment.status` passe à `Paid`. Inclut la migration rétroactive des données existantes incompatibles avec le nouveau contrat si l'audit pré-sprint en révèle.

**Acceptance Criteria :**
- `MissionPaymentService::prepareSelectionForPayment` ne mute plus aucun `candidature.status`. Elle se limite à : créer le `MissionPayment`, créer les `MissionPaymentCandidature` entries, lancer l'appel FedaPay via `requestHostedCheckout`, finaliser le `fedapay_transaction_id`.
- Nouvelle méthode `MissionPaymentService::applySelectionOutcomesOnPaid(MissionPayment $payment)` (ou équivalent) qui, dans une transaction, fait : sélectionnées → `Accepted` + notifications `candidature_accepted`, autres candidatures `Pending` de la même mission → `Rejected` + notifications `candidature_rejected`, et **création obligatoire d'une `Conversation` via `Conversation::firstOrCreate(['candidature_id' => $candidature->id])` pour chaque Face Accepted**. Ce point est *critique* : FIX-20.3 a révélé que l'ancien endpoint manuel était le **seul** endroit du code qui créait des conversations, et la revue a confirmé qu'aucun autre chemin (controller Message, observer, listener, création lazy) n'en fabrique. Sans cet ajout à `applySelectionOutcomesOnPaid`, le chat Face–Producer reste silencieusement cassé pour toutes les candidatures issues du flow payé.
- `HandleFedapayWebhook` appelle `applySelectionOutcomesOnPaid` lorsqu'une `MissionPayment` transitionne vers `Paid` (et **pas** quand elle est déjà `Paid` à réception — idempotence).
- Aucune notification `candidature_accepted` ne part avant que le webhook `Paid` soit traité — vérifié par test.
- Le champ `chat_unlock` (si géré par statut) et `allowsChatAccess()` se basent toujours sur `Accepted` ; conséquence attendue : le chat entre Face et Producer ne s'ouvre qu'après paiement confirmé, à documenter explicitement dans les notes techniques (écart de comportement acceptable sous le nouveau contrat).
- **Migration rétroactive (conditionnelle à l'audit pré-sprint) :** commande Laravel idempotente `php artisan candidature:reconcile-accepted-contract --dry-run` qui :
  - Identifie les candidatures `Accepted` avec `MissionPayment` non-`Paid` → bascule vers `Pending`, logue l'opération, supprime les notifications `candidature_accepted` pending associées.
  - Identifie les candidatures `Rejected` issues du reject en masse sur une mission dont le payment n'a jamais confirmé → bascule vers `Pending`.
  - Mode `--dry-run` : affiche sans modifier. Mode normal : applique en transaction.
- Tests backend : unit test vérifiant que `prepareSelectionForPayment` n'altère plus `candidature.status` ; feature test du webhook vérifiant les transitions + notifications au moment du `Paid` ; test de la commande de migration (dry-run et apply).
- Les tests existants `MissionPaymentInitiationTest` sont adaptés : les assertions qui attendaient `Accepted` au retour du prepare sont remplacées par `Pending`, les assertions d'état final restent au webhook.
- Non-régression FIX-19 : les scénarios de reprise checkout (FIX-19.2), d'échec initiation (FIX-19.1), de guard UI (FIX-19.3) continuent de passer ou sont ajustés en cohérence avec le nouveau contrat (voir FIX-20.4).

**Technical Notes :**
- Root cause : `backend/app/Services/MissionPaymentService.php:165` (`$candidature->update(['status' => CandidatureStatus::Accepted])`) dans `prepareSelectionForPayment`, et `:179-181` pour le reject en masse. Ces mutations doivent être retirées et reportées dans le webhook handler.
- Webhook actuel : `backend/app/Jobs/HandleFedapayWebhook.php` — auditer pour le point d'insertion de `applySelectionOutcomesOnPaid`. La transition `MissionPayment → Paid` se fait probablement ligne ~545 de `MissionPaymentService.php` (à vérifier lors de l'implémentation).
- Audit consumers de `CandidatureStatus::Accepted` à faire pendant le refactor :
  - `backend/app/Enums/CandidatureStatus.php` — `allowsChatAccess()`, `allowsRatings()`, labels
  - `backend/app/Services/Admin/AdminDashboardService.php:151`
  - `backend/app/Http/Controllers/Api/V1/Producer/ProducerDashboardController.php:80,99`
  - `backend/app/Http/Controllers/Api/V1/Admin/FaceController.php:127`, `Admin/ProducerController.php:141`
  - `backend/app/Http/Requests/Mission/CompleteMissionRequest.php:122`
  - Resources API, notifications templates, etc.
- Chat unlock : documenter que sous le nouveau contrat, le chat entre Face et Producer ne s'ouvre qu'après webhook `Paid` (quelques secondes à quelques minutes après la validation du producer sur FedaPay). Avant le refactor, le chat s'ouvrait dès la prep. Écart acceptable vu que l'utilité du chat pré-paiement est discutable.
- **Gap préexistant révélé par FIX-20.3 — création de `Conversation`** : avant ce sprint, l'unique code de prod qui appelait `Conversation::firstOrCreate` était `Producer/CandidatureController::accept` (endpoint legacy supprimé par FIX-20.3). Aucun contrôleur de messages, aucun observer, aucun listener ne fabrique de conversation en prod. L'audit DB du 2026-04-14 ayant confirmé que le endpoint legacy n'a jamais été utilisé, **aucune conversation n'existe en prod aujourd'hui pour une candidature acceptée via le flow payé** — le chat Face–Producer est silencieusement non fonctionnel pour toutes les missions réellement passées par le paiement. FIX-20.1 doit impérativement rétablir la création de `Conversation` dans `applySelectionOutcomesOnPaid` pour que le chat soit à nouveau fonctionnel.
- Idempotence du webhook : la méthode doit tolérer un replay FedaPay (même transaction reçue deux fois). Si `candidature.status === Accepted` déjà, ne pas renvoyer de notification.
- Pattern Prove It :
  1. Test backend qui simule `prepareSelectionForPayment` puis vérifie que `candidature.status` reste `Pending` et qu'aucune notification `candidature_accepted` n'a été créée. Échoue avant le refactor, passe après.
  2. Test backend qui simule `prepareSelectionForPayment` + webhook `Paid` et vérifie les transitions + notifications dans le bon ordre. Passe avant et après, mais avec un séquencement différent.
- Convention projet : pas de commentaires explicatifs inutiles, migrations rétroactives pour tout changement de format DB (memory rule `feedback_db_retroactive_migration`).

---

#### FIX-20.2 : `Accepted` intrinsèquement confirmable — simplifier `Face/CandidatureController::confirm`

**Description :** Sous le nouveau contrat FIX-20.1, `candidature.status === Accepted` garantit que le `MissionPayment` associé est `Paid`. Le check `PAYMENT_NOT_CONFIRMED` dans `Face/CandidatureController::confirm` (lignes 185-192) devient redondant : soit on le retire, soit on le conserve en défense-en-profondeur avec un log d'alerte signalant une rupture d'invariant. Le check `NOT_IN_FINAL_SELECTION` (lignes 194-201) reste pertinent et inchangé — il protège contre un autre invariant (entry manquante dans `mission_payment_candidatures`).

**Acceptance Criteria :**
- Décision documentée dans les technical notes du fichier : retrait pur OU conservation défensive avec log.
- Si conservation : `Log::warning('INVARIANT_VIOLATION: candidature accepted without paid payment', ['candidature_id' => ..., 'mission_id' => ..., 'payment_status' => ...])` + alerting éventuel.
- Les tests `FaceConfirmCandidatureTest` sont ajustés : les fixtures qui construisaient manuellement un `MissionPayment` en `Paid` avant d'asserter `confirm` peuvent s'appuyer directement sur un helper `createConfirmedSelection` cohérent avec le nouveau contrat.
- Les chemins de confirmation existants continuent de passer.
- Le bouton frontend « Confirmer ma participation » reste visible uniquement sur `candidature.status === 'accepted'` — audit rapide de `FaceCandidaturesPage.vue` + `CandidatureCard.vue` pour confirmer qu'aucune condition composite n'est restée.

**Technical Notes :**
- Fichier principal : `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php:155-246`.
- Recommandation : conservation défensive avec log. Coût de maintenance nul, protection réelle si un bug futur réintroduit une mutation hors-webhook. Le log permet aussi de valider empiriquement que l'invariant tient en prod.
- Dépendance : FIX-20.1 doit être livré avant.
- Audit frontend : `FaceCandidaturesPage.vue`, `CandidatureCard.vue` — chercher toute référence à `mission_payment` ou `payment_status` en combinaison avec `candidature.status` pour les retirer si redondantes.

---

#### FIX-20.3 : Supprimer le chemin legacy d'acceptation manuelle candidature Producer

**Description :** `POST /producer/candidatures/{id}/accept` passe une candidature en `Accepted` sans créer de `MissionPayment`, contredisant frontalement le nouveau contrat FIX-20. L'UI Producer l'expose encore via un bouton « Accepter » hors mode sélection, alors que le backend exige pourtant `mission.status === Published` jamais vrai quand le bouton est visible — contradiction frontend/backend qui confirme qu'il s'agit d'un vestige legacy pré-FedaPay. Audit DB prod 2026-04-14 : zéro candidature orpheline, suppression sans migration rétroactive.

**Acceptance Criteria :**
- Route `POST /producer/candidatures/{id}/accept` supprimée de `backend/routes/api/producer.php` ; appel retourne 404.
- Méthode `accept` supprimée de `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php`.
- Test `backend/tests/Feature/Candidature/ProducerAcceptCandidatureTest.php` supprimé.
- Composable `frontend/src/features/candidature/composables/useAcceptCandidature.ts` supprimé.
- Méthode `candidatureApi.acceptCandidature` supprimée de `frontend/src/features/candidature/services/candidatureApi.ts`.
- Export de `useAcceptCandidature` retiré de `frontend/src/features/candidature/composables/index.ts`.
- Bouton « Accepter » retiré de `ProducerCandidatureCard.vue` (emit `accept`, `handleAccept`, `resetAccepting`, branche `canTakeAction && !selectionMode`).
- Imports et handlers `acceptCandidature` retirés de `ProducerCandidaturesSection.vue` et `ProducerMissionCandidaturesPage.vue` si référencés.
- Les spec files frontend (`ProducerCandidaturesSection.spec.ts`, etc.) ne référencent plus `acceptCandidature`.
- Le workflow de sélection payée reste pleinement fonctionnel — `php artisan test --filter=MissionPayment` + suites frontend mission passent.

**Technical Notes :**
- Audit DB 2026-04-14 : `SELECT c.id FROM candidatures c LEFT JOIN mission_payment_candidatures mpc ON mpc.candidature_id = c.id WHERE c.status = 'accepted' AND mpc.id IS NULL;` → `Empty set`. Zéro donnée à migrer.
- Fichiers backend : `Producer/CandidatureController.php:63-133`, `routes/api/producer.php:89`, `tests/Feature/Candidature/ProducerAcceptCandidatureTest.php`.
- Fichiers frontend : composables, services, composants et specs listés dans les AC.
- Pattern Prove It :
  1. Test backend feature qui `postJson('/api/v1/producer/candidatures/{id}/accept')` et asserte 404 → échoue avant, passe après.
  2. Test frontend de `ProducerCandidatureCard` vérifiant que le bouton « Accepter » n'est plus rendu → échoue avant, passe après.
- Indépendant de FIX-20.1 — peut être livré en parallèle ou avant.

---

#### FIX-20.4 : Simplifier la compensation FIX-19 rendue partiellement obsolète

**Description :** FIX-19.1 (`MissionPaymentService::handleInitiationFailure`) rollbacke les mutations `candidature.status` en cas d'échec FedaPay : `Accepted → Pending` et `Rejected → Pending`. Sous le nouveau contrat FIX-20.1, `prepareSelectionForPayment` ne mute plus aucun statut de candidature, donc cette partie de la compensation devient sans objet. Seule la suppression du `MissionPayment` + `MissionPaymentCandidature` + gestion `fedapay_transaction_id` reste pertinente. Nettoyage ciblé pour retirer le code devenu mort, mettre à jour les tests FIX-19 en conséquence, et réécrire la section correspondante du runbook.

**Acceptance Criteria :**
- `handleInitiationFailure` ne tente plus de rollback de `candidature.status` (puisque rien n'a été muté lors de la prep).
- La suppression des `MissionPaymentCandidature` + `MissionPayment` en cas d'échec reste en place et fonctionne.
- Les tests `backend/tests/Feature/Mission/MissionPaymentInitiationTest.php` qui assertaient `Accepted → Pending` / `Rejected → Pending` dans les scénarios d'échec sont retirés ou reformulés pour vérifier que `candidature.status` reste `Pending` tout au long.
- Le runbook `docs/runbook-mission-payment-recovery.md` est mis à jour : les scénarios de candidatures `Accepted` orphelines n'existent plus sous le nouveau contrat, la procédure de récupération est simplifiée en conséquence.
- Non-régression : les scénarios FIX-19.2 (resume checkout), FIX-19.3 (guard UI), FIX-19.4 (observabilité logs) continuent de passer.
- Les appels à `dispatchSelectionNotifications` qui partaient en fin de `confirmSelection` sont audités : si les notifications partaient prématurément, elles sont retirées (elles arriveront au webhook `Paid` via FIX-20.1).

**Technical Notes :**
- Fichier principal : `backend/app/Services/MissionPaymentService.php` — méthodes `handleInitiationFailure` (~327), `handleResumeInitiationFailure` (~272), toute la chaîne de compensation.
- Runbook : `docs/runbook-mission-payment-recovery.md` — section à réécrire pour refléter que les candidatures restent en `Pending` pendant toute la durée du checkout et jusqu'au webhook `Paid`.
- Dépendance : FIX-20.1 doit être livré avant.
- Attention à ne pas sur-supprimer — garder les compensations qui protègent des invariants encore pertinents (suppression `MissionPayment`, logs d'échec avec contexte, idempotence webhook, etc.).
- Pattern Prove It : test qui simule un échec FedaPay puis vérifie que `candidature.status` est resté `Pending` tout le long, que `MissionPayment` a bien été supprimé, et que les entries `MissionPaymentCandidature` ont disparu.

---

#### FIX-20.5 : Filet de sécurité — mapping des erreurs de confirmation Face

**Description :** Étendre `useConfirmCandidature.ts` pour mapper les statuts 422, 429, 500 et les erreurs réseau, en lisant le `error.message` backend quand il est présent. Utile même après le refactor FIX-20.1 : le 422 `PAYMENT_NOT_CONFIRMED` ne devrait plus jamais se produire en pratique, mais d'autres erreurs légitimes existent (`NOT_IN_FINAL_SELECTION`, 429 throttle, 500, erreurs réseau) et doivent être lisibles pour diagnostic. Story indépendante, livrable avant le refactor backend comme quick win de diagnostic.

**Acceptance Criteria :**
- Un 422 affiche le `error.message` backend s'il existe, sinon un fallback spécifique « Cette candidature ne peut pas être confirmée dans son état actuel ».
- Un 429 affiche un message dédié de throttling invitant à réessayer dans une minute.
- Un 500 et une erreur réseau (pas de `response`) sont distingués par deux messages explicites.
- Les statuts 400/403/404 existants restent mappés comme avant (non-régression).
- Tests unitaires du composable couvrant chaque branche de statut via mocks axios (400, 403, 404, 422×2 codes, 429, 500, erreur sans `response`).
- Test d'intégration sur `FaceCandidaturesPage` vérifiant qu'un 422 mocké affiche bien le message backend dans le toast custom.
- Tous les messages UI respectent les accents français corrects (memory `feedback_accents_francais`).

**Technical Notes :**
- Root cause : `frontend/src/features/candidature/composables/useConfirmCandidature.ts:32-43` ne teste que `status === 400 | 403 | 404`.
- Le backend expose `error.message` via le format `{ error: { code, message } }` pour les 422 dans `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php:172-201`.
- Indépendant de FIX-20.1 — peut être livré en premier comme quick win. Améliore immédiatement le diagnostic en prod, même avant que le refactor backend soit déployé.
- Pattern Prove It : écrire d'abord un test qui mock un 422 `PAYMENT_NOT_CONFIRMED` et assert le message backend — doit échouer avant le fix, passer après.
- Localisation : la correction est isolée au composable, `FaceCandidaturesPage.vue` n'a pas besoin d'être touché — `displayToast(confirmError.value || ...)` lira simplement le message mappé.

---

#### FIX-20.6 : Réconcilier les données de production incompatibles avec le contrat cible

**Description :** L'audit DB du 2026-04-14 révèle 181 candidatures dans un état qui sera illégal sous le nouveau contrat : 4 `Accepted` + 177 `Rejected` réparties sur 4 `MissionPayment` en `Pending` sans aucun `fedapay_transaction_id`, sur 4 missions bloquées en `mission.status = pending_payment`. Ces paiements n'ont jamais produit de checkout FedaPay exploitable — ils sont le reliquat du bug que FIX-19.1 corrige, mais créés avant le déploiement de ce fix en prod (merge 2026-04-14 ~10:23).

Scope figé après audit approfondi :

| Mission | `mission.status` | Payment | Accepted à migrer | Rejected à migrer | `cancelled` (hors scope) |
|---------|-----------------|---------|-------------------|-------------------|--------------------------|
| 8 (« Vidéo d'introduction pour une formation ») | `pending_payment` | 1 (2026-04-03) | 1 | 114 | 4 |
| 11 (« Mettre en avant un produit ») | `pending_payment` | 2 (2026-04-08) | 1 | 27 | 2 |
| 14 (« Créateur de contenu TikTok ») | `pending_payment` | 3 (2026-04-10) | 1 | 19 | 0 |
| 17 (« Publicité d'un soin sauna ») | `pending_payment` | 7 (2026-04-14) | 1 | 17 | 0 |
| **Total** | | **4 payments** | **4** | **177** | **6 préservés** |

Commande Laravel idempotente qui rollback les 181 candidatures ciblées vers `Pending`, marque les 4 `MissionPayment` comme `Failed`, rebascule les 4 `mission.status` de `pending_payment` vers `published` pour débloquer les producers, et notifie proprement Faces et Producers concernés. Les candidatures `cancelled` (désengagement volontaire de la Face) sont **explicitement préservées**.

**Acceptance Criteria :**

*Pré-requis — sanity check post-merge FIX-19.1 :*
- Requête de vérification exécutée après le merge de PR #23 : aucun nouveau `MissionPayment` en `pending` + `fedapay_transaction_id = NULL` créé plus de ~10 minutes après le 2026-04-14 10:23. Si une ligne existe, réouvrir l'investigation FIX-19.1 avant de démarrer la migration.

*Réconciliation :*
- Commande Laravel idempotente `php artisan candidature:reconcile-stale-selections` avec flags `--dry-run` (affiche sans modifier) et `--apply` (exécute en transaction).
- La commande identifie dynamiquement les rows impactées via les mêmes requêtes que l'audit (pas de liste hardcodée) — doit rester fonctionnelle même si le nombre de rows évolue entre deux exécutions.
- **Scope strict : seules les candidatures en `status IN ('accepted', 'rejected')` sont touchées.** Les candidatures `cancelled` (6 au total, désengagement volontaire de la Face) sont explicitement préservées — la commande doit avoir un test unitaire qui assert que les `cancelled` ne sont jamais modifiées, même en mode `--apply`.
- Pour chaque `MissionPayment` identifié : toutes les candidatures `Accepted` et `Rejected` liées au payment sont rebasculées vers `Pending` en transaction unique par payment, avec log structuré (`candidature_id`, ancien statut, nouveau statut, raison, `payment_id`, `mission_id`).
- Les `MissionPaymentCandidature` associées aux payments impactés sont supprimées.
- Les `MissionPayment` impactés sont marqués `Failed` (pas supprimés — on conserve la trace d'audit financier).
- **La `mission.status` est rebasculée de `pending_payment` vers `published` pour les 4 missions impactées**, dans la même transaction que les autres mutations du payment correspondant. Sans ce reset, les producers restent incapables de relancer leur sélection et la migration n'a aucun effet utilisateur.
- **Notifications Faces (181) :**
  - Les 4 Faces passées de `Accepted → Pending` reçoivent un message expliquant que le paiement du producteur n'a pas été finalisé et que leur candidature est remise en attente (ton : factuel, pas de blâme).
  - Les 177 Faces passées de `Rejected → Pending` reçoivent un message expliquant que la sélection précédente n'a pas été finalisée et que leur candidature est de nouveau en attente (ton : positif, la Face regagne une chance).
  - Envoi en batch pour éviter de saturer le service de notifications (max N par seconde, paramétrable via option CLI ou constante documentée).
- **Notifications Producers (4) :**
  - Chaque Producer impacté reçoit un message indiquant que le paiement de sa sélection n'a pas abouti, que la mission est de nouveau ouverte aux candidatures, et qu'il peut relancer la sélection depuis la page de la mission.
- Test feature Laravel reproduisant les 4 scénarios (missions 8, 11, 14, 17) avec fixtures fidèles (y compris les `cancelled` sur missions 8 et 11) et assertions post-migration :
  - Les 181 cibles sont bien passées à `Pending`.
  - Les 6 `cancelled` sont inchangées.
  - Les 4 `MissionPayment` sont marqués `Failed`.
  - Les 4 `mission.status` sont passés à `published`.
  - 181 notifications Faces + 4 notifications Producers ont été dispatchées.
  - La commande est idempotente : une seconde exécution ne génère aucune mutation supplémentaire et aucune notification doublon.
- Test unitaire de la commande en mode `--dry-run` garantissant zéro mutation.
- Revue manuelle du résultat `--dry-run` validée par Amakira **avant** exécution en `--apply` sur la prod.
- Runbook de la migration documenté : étapes, commandes, points de bascule, plan de rollback d'urgence (snapshot DB avant exécution, restauration manuelle si échec partiel détecté).
- Textes exacts des notifications respectent les accents français corrects (memory `feedback_accents_francais`).

**Technical Notes :**
- Fichier à créer : `backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php` (ou nom équivalent).
- Les requêtes SQL d'identification sont celles de l'audit (Requête 1 et Requête 2 dans l'overview de l'épic).
- La migration ne touche **pas** aux candidatures dont la mission a un `MissionPayment` en `Paid` — seulement celles liées aux paiements orphelins (status <> 'paid' ET fedapay_transaction_id IS NULL en pratique, à vérifier au moment du code).
- Mission 8 est bloquée depuis 11 jours, impact réputationnel probable sur le producer — la notification hors-app (email/WhatsApp) lui est probablement nécessaire. À trancher avec le métier.
- Préférer `Failed` à la suppression pour les `MissionPayment` : l'audit financier doit rester traçable, les suppressions destructives complexifient les reconciliations comptables futures.
- Notifications : réutiliser l'infrastructure `Notification` existante (création de rows `notifications` + broadcasting Echo/Reverb si actif). Ne pas envoyer d'email direct **dans la commande** — si communication hors-app nécessaire pour les producers, la traiter manuellement en dehors de la commande automatisée.
- **Dépendance ordering :** FIX-20.6 doit tourner **avant** FIX-20.1 pour que le refactor opère sur une base propre. Si FIX-20.1 est déployé avant FIX-20.6, les rows orphelines deviennent des données mortes qui ne causeront plus d'incident (grâce au nouveau contrat) mais resteront incohérentes et invisibles — mauvais pour l'audit financier.
- **Décisions produit à trancher avant démarrage :**
  1. Textes exacts des 3 types de notifications (Face rollback Accepted, Face rollback Rejected, Producer sélection annulée).
  2. Décision sur la communication hors-app (email/WhatsApp) pour les 4 Producers concernés, notamment le producer de mission 8 bloqué depuis ~11 jours.
- Pattern Prove It : test qui construit une base avec exactement les 4 payments orphelins + toutes leurs candidatures (y compris les `cancelled` sur missions 8 et 11), exécute la commande, et asserte que chaque row a transitionné comme prévu, que les `cancelled` sont intactes, que les 4 missions sont repassées en `published`, et que chaque notification attendue a été dispatchée.
- **Memory rule `feedback_db_retroactive_migration`** : « When changing DB data format, always migrate existing rows too — don't just fix new records. » FIX-19.1 n'a pas embarqué de backfill rétroactif, ce qui a laissé les payments 1/2/3 pourrir en prod (payment 7 n'entre pas dans cette catégorie — créé pendant la fenêtre pré-déploiement). Cette story rattrape le manquement historique **et** verrouille le pattern pour FIX-20.1 qui doit impérativement intégrer son propre backfill si nécessaire.
