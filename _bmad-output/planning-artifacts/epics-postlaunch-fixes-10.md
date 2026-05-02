---
stepsCompleted: [1, 2]
status: 'draft'
draftedAt: '2026-04-27'
totalEpics: 1
totalStories: 10
project_name: 'WEACT - Validation Présence Mission & Règlement par Face Sprint 13'
user_name: 'Amakira'
date: '2026-04-27'
---

# WEACT - Validation Présence Mission & Règlement par Face Sprint 13 - Epic Breakdown

## Overview

Sprint 13 ouvre un nouvel axe métier identifié après stabilisation du flow paiement (Sprint 12) : **la faille de présomption de présence sur le workflow mission multi-Faces**. Aujourd'hui, lorsqu'un Producer poste une mission et sélectionne N Faces, la fin de mission libère automatiquement l'escrow vers **toutes** les Faces sélectionnées — sans aucun mécanisme pour acter qu'une Face s'est effectivement présentée le jour du tournage. Une Face absente est donc payée comme une Face présente, et le Producer n'a aucun moyen de récupérer la part correspondante.

Le no-show existe déjà côté **bookings directs** (`BookingService::reportNoShow` + `EscrowService::refund` + `WalletService::creditDirect`), mais ce concept n'a jamais été porté côté missions multi-Faces. Cet épic comble ce trou et introduit un workflow de validation présence symétrique au booking, adapté à la nature N-Faces de la mission.

### Investigations 2026-04-27 — état du codebase

L'investigation préalable a confirmé que **80 % de l'infrastructure technique est déjà en place** :

- **Escrow individualisé** : la table `mission_payment_candidatures` porte déjà un montant par Face (`montant_face_recoit`) et un `escrow_status` enum (`Pending`, `Locked`, `Released`, **`Refunded` déjà disponible**) avec timestamps `locked_at` / `released_at`. Pas de refonte schéma escrow nécessaire.
- **Mécanique refund wallet Producer** : `WalletService::creditDirect` + `EscrowService::refund` + journalisation immuable via `financial_events` (avec idempotency key) + journal `wallet_transactions`. Réutilisable tel quel.
- **Commission plateforme non matérialisée au paiement** : la commission est seulement calculée et stockée dans `MissionPayment.commission_*` au `markAsPaid`, jamais journalisée dans `wallet_transactions` à ce stade. Elle se matérialise comme revenu plateforme uniquement au release vers la Face. **Conséquence** : pour une Face absente, on ne crédite simplement pas → aucune commission encaissée → aucune écriture inverse nécessaire.
- **Pas de blocage légal** : le délai actuel de 14 jours (`missions:auto-release-funds`) **n'est pas** un droit de rétractation (le Code du Numérique exclut les services intégralement fournis comme une mission de casting réalisée). Il peut être restructuré librement.

### Le vrai trou — ce qui manque

1. **Aucun champ de présence** sur les candidatures ou les entries de paiement. L'enum `CandidatureStatus` (`pending`, `accepted`, `confirmed`, `in_progress`, `completed`, `rejected`, `cancelled`) track le cycle de vie de la candidature, pas la présence physique.
2. **Aucun statut intermédiaire sur Mission** entre `Closed` et `Completed` pour modéliser « en attente de validation présence ».
3. **Aucune mécanique de contestation Face** côté missions.
4. **Le cron `missions:auto-release-funds` 14j court-circuite** la future validation : il complète la mission et libère tous les escrows sans demander au Producer.
5. **L'invariant atomique actuel** `Mission Completed ⟹ toutes entries Released` (cf. `MissionService::completeMission` qui appelle `releaseFunds()` avant de basculer la mission) **doit être assoupli** pour autoriser des entries `Refunded` ou `Locked+disputed` après une mission métier-terminée.

### Décisions produit (2026-04-27)

1. **Stockage attendance sur `mission_payment_candidatures`**, pas sur `candidatures`. Justification : la présence impacte directement une part d'escrow individuelle ; il est cohérent de la stocker au même niveau que l'entry financière.
2. **Démarrage des 72h Producer à `date_tournage 23:59:59`** (timezone serveur). `date_tournage` est typé `DATE` pur (pas de datetime) ; on normalise donc le point de départ à la fin de la journée du tournage. Pas de champ `heure_fin` ajouté (over-engineering MVP).
3. **Délai de contestation Face : 72h** après notification d'absence. Symétrie avec le délai Producer.
4. **Statut nouveau sur Mission : `PendingAttendanceValidation`** entre `Closed` et `Completed`. Pas de `partially_settled` — la finalisation financière est déjà tracée par-entry via `escrow_status`.
5. **Présence binaire** (`present` / `absent`) pour le MVP. Pas de présence partielle, retard, demi-paiement.
6. **Notifications email + in-app** uniquement (pas de SMS au départ).
7. **Refund 100 % au Producer** sur les Faces absentes (sans commission plateforme). Pas d'écriture inverse nécessaire (cf. découverte commission ci-dessus).
8. **Crons séparés** : un pour l'auto-validation (Producer inactif), un pour le règlement des contestations expirées. Tests indépendants.
9. **Date pivot pour les missions legacy** : les missions créées avant la mise en prod restent sur l'ancien comportement. Une commande admin one-shot règle les missions en transit.
10. **Nouveau contrat métier explicite** : `Mission Completed` ne signifie plus « tout l'argent est définitivement sorti de l'escrow », mais « la mission est terminée côté réalisation ». Le règlement financier final se fait au niveau de chaque `mission_payment_candidature`.

### Risque principal

La **Story FIX-26.2** (assouplissement de l'invariant `releaseFunds`) touche le cœur financier du produit. Elle doit être blindée par des tests exhaustifs (invariants, idempotence, cas adverses) **avant** que les autres stories ne s'y branchent. Cf. section "Nouveaux invariants financiers" plus bas pour la liste exhaustive des règles à respecter.

## Nouveaux invariants financiers

Les règles suivantes deviennent **invariants du système** dès la livraison de FIX-26.2. Tout code qui touche aux entries de `mission_payment_candidatures` doit les respecter, et chaque story qui les manipule doit les tester explicitement.

### Règles de transition d'`escrow_status` par entry

1. Une entry `Locked` avec `attendance_status = present` **peut** passer à `Released` (crédit wallet Face).
2. Une entry `Locked` avec `attendance_status = absent` et hors fenêtre de contestation **peut** passer à `Refunded` (crédit wallet Producer).
3. Une entry `Locked` avec `attendance_status = disputed` (Face conteste) **reste** `Locked` jusqu'à résolution admin.
4. Une entry `Locked` avec `attendance_status = pending` **ne peut pas** être finalisée — c'est une violation d'invariant qui doit lever une `RuntimeException`.

### Règles d'état de la Mission

5. Une mission `Completed` **peut** contenir simultanément des entries `Released`, `Refunded`, ou `Locked + attendance_status=disputed`.
6. Une mission `Completed` **ne peut pas** contenir d'entries `Locked + attendance_status=pending` (toutes les présences doivent avoir été tranchées avant le passage en Completed).
7. Une mission **ne peut pas** régresser de `Completed` vers `PendingAttendanceValidation` ou `Closed`.

### Règles d'idempotence et de sécurité

8. Aucune entry ne peut subir un double release ou un double refund — toute opération de finalisation doit lire l'`escrow_status` actuel sous lock optimiste ou pessimiste avant d'agir.
9. Toutes les opérations financières (release, refund, mark present/absent, contestation) doivent être idempotentes et journalisées dans `financial_events` avec une idempotency key dérivée de l'entry et de l'opération.
10. Les **montants ne viennent jamais du frontend**. Toute valeur financière utilisée dans une transition (montant à créditer, à refund) est lue depuis l'entry persistée (`montant_face_recoit`) ou recalculée serveur-side via les services existants (`BookingPricing` / `MissionPaymentService`).
11. Un Producer ne peut marquer présent/absent que les Faces de **ses propres missions**. Garde-fou autorization à enforcer dans le service ET dans la request validation.
12. Une Face ne peut contester que sa **propre** déclaration d'absence. Garde-fou identique côté contestation.

## Requirements Inventory

### Functional Requirements

**FIX26-FR1** : La table `mission_payment_candidatures` doit porter un nouveau champ `attendance_status` (enum : `pending`, `present`, `absent`, `disputed`) avec valeur par défaut `pending`. Une nouvelle valeur d'enum est ajoutée au statut Mission : `PendingAttendanceValidation`.

**FIX26-FR2** : La méthode `releaseFunds(Mission $mission)` doit traiter chaque entry selon son `attendance_status` : `present` → release vers wallet Face ; `absent` → refund vers wallet Producer ; `disputed` → laisser `Locked`. L'invariant `Mission Completed ⟹ toutes entries Released` est assoupli vers `Mission Completed ⟹ toutes entries Released, Refunded, ou Locked+disputed`.

**FIX26-FR3** : Un nouveau service `MissionAttendanceService` orchestre les transitions : marquer présent/absent en batch pour une mission, déclencher le refund par entry, gérer les transitions Mission `Closed` → `PendingAttendanceValidation` → `Completed`. Toutes les opérations sont idempotentes et journalisées dans `financial_events`.

**FIX26-FR4** : Deux endpoints API Producer sont exposés : `GET /api/v1/producer/missions/{uuid}/attendance-form` (retourne la liste des Faces sélectionnées avec leur état) et `POST /api/v1/producer/missions/{uuid}/validate-attendance` (accepte un payload des présences/absences, applique les transitions). Un Producer ne peut agir que sur ses propres missions.

**FIX26-FR5** : Quand une Face est marquée `absent`, elle reçoit immédiatement une notification email (`FaceMarkedAbsentMail`) + in-app indiquant qu'elle a 72h pour contester. Un endpoint `POST /api/v1/face/missions/{uuid}/dispute-attendance` permet à la Face de basculer son entry en `disputed`. Une Face ne peut contester que sa propre déclaration.

**FIX26-FR6** : Deux commandes console Laravel sont créées : (a) `missions:auto-validate-attendance` qui auto-marque toutes les Faces comme `present` pour les missions en `PendingAttendanceValidation` dont le délai 72h post-`date_tournage 23:59:59` est dépassé sans action Producer ; (b) `missions:settle-disputed-attendance` qui finalise les entries `absent` non contestées 72h après notification Face. Tests indépendants pour chaque flow.

**FIX26-FR7** : Une page Vue `AttendanceValidationView.vue` côté Producer permet de valider la présence des Faces : bouton « Toutes présentes » par défaut, sinon checkbox individuelles. Affichage clair des conséquences financières (montant remboursé / payé). Composants shadcn-vue préférés à la création custom (cf. CLAUDE.md).

**FIX26-FR8** : Une vue Back-office admin minimale liste les entries `disputed` en attente de résolution avec deux actions : « Trancher en faveur de la Face » (release normal) ou « Trancher en faveur du Producer » (refund). Audit trail dans `financial_events`.

**FIX26-FR9** : Une commande admin one-shot `missions:legacy-attendance-settlement` permet de traiter les missions actuellement en `Closed` avec `date_tournage` passée selon une stratégie pivot : missions créées avant date pivot → ancien comportement (auto-release toutes Faces) ; missions après pivot → nouveau workflow.

**FIX26-FR10** : La commande `missions:auto-release-funds` (14j actuelle) est désactivée pour les missions post-pivot et conservée uniquement pour le résiduel legacy. Une non-régression vérifie qu'aucune mission post-pivot ne passe par cette commande.

## Epic & Story Breakdown

---

### Epic FIX-26 : Validation présence Mission & règlement financier par Face

**Goal :** Combler la faille du workflow mission multi-Faces où la fin automatique libère l'escrow sans valider la présence physique. Introduire un cycle Producer-valide-présences → Face-peut-contester-72h → règlement par-entry, avec un nouveau contrat métier où `Mission Completed` ne signifie plus règlement financier finalisé. Le statut financier devient source de vérité au niveau `mission_payment_candidatures`.

**Priority :** Haute — protection du Producer contre les no-show, protection de la Face contre la fraude Producer, élimination d'un contournement financier connu. Dépendance bloquante de FIX-26.2 (refactor `releaseFunds`) sur toutes les autres stories applicatives.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-26.1 | Schéma DB : `attendance_status` + statut Mission `PendingAttendanceValidation` | FIX26-FR1 | Haute |
| FIX-26.2 | Refactor `releaseFunds` : assouplir l'invariant et router par `attendance_status` | FIX26-FR2 | Haute (cœur financier — risqué) |
| FIX-26.3 | Service `MissionAttendanceService` + transitions Mission | FIX26-FR3 | Haute |
| FIX-26.4 | Endpoints Producer : afficher et soumettre la validation présence | FIX26-FR4 | Haute |
| FIX-26.5 | Notification absence Face + endpoint contestation | FIX26-FR5 | Haute |
| FIX-26.6 | Crons split : auto-validation Producer inactif & règlement contestation expirée | FIX26-FR6 | Haute |
| FIX-26.7 | UI Producer : écran de validation présence | FIX26-FR7 | Moyenne |
| FIX-26.8 | Back-office admin : résolution litiges minimaliste | FIX26-FR8 | Moyenne |
| FIX-26.9 | Migration legacy : commande one-shot avec date pivot | FIX26-FR9 | Moyenne |
| FIX-26.10 | Désactivation/repurpose `missions:auto-release-funds` 14j | FIX26-FR10 | Moyenne |

**Ordre de livraison recommandé :**

1. **FIX-26.1** — schéma DB en premier, base de tout le reste.
2. **FIX-26.2** — refactor `releaseFunds`, **avec tests d'invariants exhaustifs** (cf. section "Nouveaux invariants financiers"). Doit être livré avant FIX-26.3 et avant tout cron.
3. **FIX-26.3** — service métier qui orchestre. Dépend de FIX-26.1 et FIX-26.2.
4. **FIX-26.4** + **FIX-26.5** — peuvent être livrés en parallèle après FIX-26.3.
5. **FIX-26.6** — crons. Dépend de FIX-26.3.
6. **FIX-26.7** — UI Producer. Dépend de FIX-26.4.
7. **FIX-26.8** — back-office admin. Dépend de FIX-26.3 et FIX-26.5.
8. **FIX-26.9** + **FIX-26.10** — migration legacy + désactivation cron. Livrable en fin de sprint, après que tout le nouveau flow tourne en staging.

---

#### FIX-26.1 : Schéma DB — `attendance_status` + statut Mission `PendingAttendanceValidation`

**Description :** Ajouter le champ `attendance_status` (enum) sur `mission_payment_candidatures` avec valeur par défaut `pending`. Étendre l'enum `MissionStatus` PHP avec la nouvelle valeur `PendingAttendanceValidation` insérée logiquement entre `Closed` et `Completed`. Mettre à jour le cast Eloquent côté `Mission.php` et `MissionPaymentCandidature.php`. Aucune logique métier n'est implémentée dans cette story — juste la plomberie schéma + enums + casts.

**Acceptance Criteria (draft) :**
- Migration crée la colonne `attendance_status` sur `mission_payment_candidatures` (enum string, default `pending`).
- Index utile à valider sur `(mission_id, attendance_status)` selon les patterns de query attendus dans FIX-26.6.
- Enum PHP `App\Enums\AttendanceStatus` créé (valeurs : `pending`, `present`, `absent`, `disputed`).
- Enum PHP `App\Enums\MissionStatus` étendu avec `PendingAttendanceValidation`.
- Cast Eloquent ajouté sur `MissionPaymentCandidature::$casts`.
- Test schéma : un fixture `mission_payment_candidature` créé sans valeur explicite a bien `pending`.
- Migration legacy intégrée (rétro-compatible) : tous les enregistrements existants reçoivent `pending` par défaut. Vérif explicite dans le test.

**Technical Notes :**
- Vérifier que la migration ne casse pas les fixtures de tests existants (`CompleteMissionTest`, `AutoReleaseMissionFundsCommandTest`, etc.).
- Pas de changement de logique applicative dans cette story — juste schéma + casts. Les services lisent encore `pending` partout, l'invariant n'est pas encore assoupli.

---

#### FIX-26.2 : Refactor `releaseFunds` — assouplir l'invariant et router par `attendance_status`

**Description :** Modifier `MissionPaymentService::releaseFunds(Mission $mission)` pour qu'au lieu de boucler sur toutes les entries `Locked` et toutes les passer en `Released`, il route chaque entry selon son `attendance_status` : `present` → crédit wallet Face + `Released` ; `absent` (hors fenêtre de contestation) → crédit wallet Producer + `Refunded` ; `disputed` → ne rien faire (rester `Locked`) ; `pending` → lever `RuntimeException` (violation d'invariant). Adapter `MissionService::completeMission` pour ne plus exiger que toutes les entries soient `Released` après l'appel.

**Acceptance Criteria (draft) :**
- `releaseFunds` itère sur les entries `Locked` et applique la transition correspondant à `attendance_status`.
- Une entry `present` est créditée au wallet Face exactement comme aujourd'hui (montant `montant_face_recoit`, `escrow_status = Released`, `released_at = now()`).
- Une entry `absent` provoque un crédit wallet **Producer** du montant `montant_face_recoit` + `escrow_status = Refunded`. Le crédit transite par `EscrowService::refund` ou équivalent existant pour réutiliser la logique du booking no-show.
- Une entry `disputed` n'est pas touchée.
- Une entry `pending` lève une `RuntimeException` claire (« presence not yet validated for entry X »).
- `MissionService::completeMission` accepte de marquer la mission `Completed` même si certaines entries restent `Locked + disputed` après l'appel à `releaseFunds`.
- Test exhaustif des invariants 1-12 listés dans la section "Nouveaux invariants financiers" : un test feature dédié par invariant, avec des fixtures multi-Faces concrets (cf. CLAUDE.md sur la spécification des fixtures multi-entités).
- Test idempotence : appeler `releaseFunds` deux fois de suite sur la même mission ne provoque ni double crédit ni double refund.
- Test de non-régression : le comportement d'une mission « 100 % présentes » (cas nominal historique) reste identique côté soldes wallet.

**Technical Notes :**
- **Story la plus risquée du sprint** — cœur financier. Pattern Prove It impératif : subagent écrit tests d'invariants avant le fix, ils échouent, puis fix, puis ils passent.
- Réutiliser au maximum `EscrowService::refund` existant (côté booking) plutôt que de dupliquer la logique de crédit wallet Producer.
- Idempotency key sur `financial_events` : pattern `mission_attendance_release:{entry_id}` ou `mission_attendance_refund:{entry_id}` selon l'opération.
- Audit trail dans `financial_events` pour chaque transition d'entry — doit inclure le motif (`present` / `absent` / `disputed_resolved_face` / `disputed_resolved_producer`).
- Vérifier impact sur `CompleteMissionTest` et `AutoReleaseMissionFundsCommandTest` — les tests existants assument l'invariant strict, ils devront être adaptés ou doublonnés.

---

#### FIX-26.3 : Service `MissionAttendanceService` + transitions Mission

**Description :** Créer `App\Services\MissionAttendanceService` qui expose les opérations métier de la validation présence : `markAttendance(Mission, array $presences)` pour appliquer un batch de présences/absences, `disputeAttendance(MissionPaymentCandidature)` pour passer une entry en `disputed`, `resolveDispute(MissionPaymentCandidature, ResolveOutcome)` pour trancher un litige admin. Le service gère les transitions `Mission Closed → PendingAttendanceValidation → Completed`. Toutes les opérations sont idempotentes, autorisées (Producer = sa mission, Face = sa propre entry), et journalisées dans `financial_events`.

**Acceptance Criteria (draft) :**
- Service injectable `App\Services\MissionAttendanceService` créé.
- Méthode `markAttendance(Mission $mission, array $entryIdToStatus)` :
  - Vérifie que la mission appartient au Producer authentifié.
  - Vérifie que la mission est en `PendingAttendanceValidation` (ou Closed avec date_tournage passée → bascule auto en `PendingAttendanceValidation`).
  - Applique l'`attendance_status` voulu par entry.
  - Déclenche `releaseFunds` à la fin si toutes les entries sont tranchées (pas de `pending` restant). Sinon l'erreur est une violation d'invariant.
  - Bascule la mission en `Completed`.
- Méthode `disputeAttendance(entry)` : passe `attendance_status = disputed`, journalise l'événement, déclenche notification interne (admin notif).
- Méthode `resolveDispute(entry, outcome)` : selon outcome, déclenche un release wallet Face ou un refund wallet Producer ; passe l'entry en `Released` ou `Refunded` ; journalise.
- Toutes les méthodes sont idempotentes (rejouer le même appel ne provoque pas d'effet secondaire dupliqué).
- Tests feature pour chaque méthode + chaque garde-fou autorization.

**Technical Notes :**
- S'appuyer sur le pattern `BookingService` existant (cf. `BookingService::reportNoShow`) pour la cohérence d'architecture.
- Verrous DB pessimistes (`SELECT ... FOR UPDATE`) sur les entries pendant les transitions financières pour éviter les races.
- Étudier si la transition `Closed → PendingAttendanceValidation` doit être faite par ce service ou par un cron qui détecte le passage de `date_tournage`. Choix à acter au moment du passage en ready-for-dev.

---

#### FIX-26.4 : Endpoints Producer — afficher et soumettre la validation présence

**Description :** Exposer deux endpoints API Producer : `GET /api/v1/producer/missions/{uuid}/attendance-form` qui retourne la liste des Faces sélectionnées avec leur `attendance_status` actuel et les montants associés ; `POST /api/v1/producer/missions/{uuid}/validate-attendance` qui accepte un payload `{entries: [{entry_id, status: 'present'|'absent'}]}` et délègue à `MissionAttendanceService::markAttendance`.

**Acceptance Criteria (draft) :**
- Routes définies dans `routes/api/producer.php` (à confirmer selon convention projet) avec middleware `auth:sanctum` + policy Producer.
- `GET` retourne 200 avec un payload JSON listant les entries (id, face name, montant_face_recoit, attendance_status).
- `POST` valide le payload via une `FormRequest` dédiée, vérifie que toutes les entries `pending` sont couvertes, délègue au service.
- Erreurs 4xx au format standard `{ error: { message, code } }` (cf. FIX-22.2 livré au Sprint 12).
- Tests feature : Producer peut voir ses propres missions, ne peut pas voir une mission d'un autre Producer (403), payload invalide → 422 avec message FR.

**Technical Notes :**
- Les **montants ne viennent jamais du frontend** (cf. invariant 10). Le payload accepté contient uniquement `entry_id` + `status`. Tous les calculs financiers se font côté serveur.
- Format de la réponse cohérent avec les autres endpoints Producer existants (regarder `ProducerMissionController` pour le pattern).

---

#### FIX-26.5 : Notification absence Face + endpoint contestation

**Description :** Quand une entry passe en `absent` via `MissionAttendanceService`, déclencher l'envoi d'un mail `FaceMarkedAbsentMail` (template Blade dédié, cf. décision Sprint 12 de ne pas avoir de template générique) + une notification in-app via `Notification::create()`. Le mail/notif explique que la Face a 72h pour contester avec un lien direct vers la page de contestation. Endpoint `POST /api/v1/face/missions/{uuid}/dispute-attendance` permet à la Face de basculer son entry en `disputed`.

**Acceptance Criteria (draft) :**
- Mailable `FaceMarkedAbsentMail` créé avec template Blade dédié `emails/face-marked-absent.blade.php` (utilisant le layout commun `emails/layouts/base.blade.php` créé au Sprint 12).
- Notification in-app de type `face.attendance.absent` créée au même moment.
- Le mail et la notif contiennent : nom de la mission, nom du Producer, date de tournage, montant en jeu, deadline de contestation, lien direct.
- Endpoint `POST .../dispute-attendance` :
  - Vérifie que l'authent est bien la Face propriétaire de l'entry (autorization).
  - Vérifie que l'entry est en `attendance_status = absent` et dans la fenêtre de 72h.
  - Délègue à `MissionAttendanceService::disputeAttendance`.
  - Retourne 200 avec confirmation, ou 4xx (avec code sémantique) si hors fenêtre / déjà contestée.
- Tests feature : Face conteste dans les temps → entry passe `disputed` ; Face conteste hors fenêtre → 4xx avec code `DISPUTE_WINDOW_EXPIRED` ; autre Face conteste → 403.
- Tests `Mail::fake()` + `Notification::fake()` pour vérifier l'envoi sur `markAttendance` avec un absent.

**Technical Notes :**
- Pattern email aligné sur les 5 mailables livrés au Sprint 12 (FIX-24).
- Listener vs sync : décider si le mail part dans un listener event-driven (`AttendanceMarkedAbsent`) ou en inline dans le service. Préférence pour event-driven (cohérent avec mémoire `event:clear` après ajout listener).

---

#### FIX-26.6 : Crons split — auto-validation Producer inactif & règlement contestation expirée

**Description :** Créer deux commandes console Laravel distinctes (mais dans le même fichier groupé si plus simple opérationnellement) avec des responsabilités clairement séparées et testées indépendamment :
- `missions:auto-validate-attendance` : pour chaque mission en `PendingAttendanceValidation` dont `date_tournage 23:59:59 + 72h` est dépassée sans action Producer, marque automatiquement toutes les entries comme `present` puis bascule la mission en `Completed`.
- `missions:settle-disputed-attendance` : pour chaque entry en `attendance_status = absent` (donc en fenêtre de contestation), si `notified_at + 72h` est dépassé sans contestation, finalise l'entry en `Refunded` (refund Producer).

**Acceptance Criteria (draft) :**
- Deux commandes Artisan distinctes ou une commande avec deux sous-routines clairement séparées.
- Chaque commande est planifiée dans le `Schedule` (fréquence : toutes les heures à valider).
- Tests dédiés pour chaque flow :
  - `missions:auto-validate-attendance` : mission avec `date_tournage` < now()-72h, status `PendingAttendanceValidation`, Producer n'a rien fait → toutes entries passent `present` puis `Released`, mission `Completed`.
  - `missions:settle-disputed-attendance` : entry `absent` notifiée il y a > 72h sans contestation → `Refunded`. Entry `absent` contestée dans les temps → ignorée.
- Tests bornés : entries `pending` ne sont pas touchées par le second cron, entries `disputed` ne sont pas touchées par le premier cron.
- Idempotence : rejouer le cron deux fois consécutives ne provoque pas de double action.

**Technical Notes :**
- Stocker `notified_at` sur l'entry au moment où la Face est marquée `absent` pour servir de point de départ au délai 72h (à confirmer en story DB ou ici).
- Le cron `missions:auto-validate-attendance` ne doit pas marquer comme `present` une entry qui aurait déjà un statut autre que `pending` (idempotence).

---

#### FIX-26.7 : UI Producer — écran de validation présence

**Description :** Page Vue 3 `AttendanceValidationView.vue` dans `frontend/src/features/mission/views/` permettant au Producer de valider la présence des Faces sélectionnées sur sa mission. Bouton primaire « Toutes présentes » + checkboxes individuelles pour les cas mixtes. Affichage clair des conséquences financières (somme remboursée au wallet Producer / total payé aux Faces présentes).

**Acceptance Criteria (draft) :**
- Page accessible depuis le dashboard Producer pour une mission en `PendingAttendanceValidation`.
- Affiche la liste des Faces sélectionnées avec leur photo, nom, et montant individuel.
- Action « Toutes présentes » coche toutes les checkboxes en un clic.
- Récap financier en bas : « X Faces présentes — Y FCFA versés. Z Faces absentes — W FCFA remboursés sur votre wallet ».
- Bouton « Valider » désactivé tant que toutes les Faces n'ont pas une décision.
- Modal de confirmation avant soumission (« Cette action est définitive. Les Faces marquées absentes auront 72h pour contester. »).
- Toast de succès / erreur post-soumission.
- Tests E2E ou intégration sur le flow complet (validation présence → soumission → redirection dashboard).

**Technical Notes :**
- Préférer composants **shadcn-vue** existants (Card, Checkbox, Button, Dialog) plutôt que custom (cf. CLAUDE.md). Vérifier la registry avant de designer.
- Si layout custom nécessaire (non-trivial), Gemini MCP — mais en respectant les rate limits du tier free (5 RPM, 20 RPD).
- Accents français corrects partout (memory `feedback_accents_francais`).

---

#### FIX-26.8 : Back-office admin — résolution litiges minimaliste

**Description :** Une vue admin minimale qui liste toutes les entries `attendance_status = disputed` en attente de résolution. Chaque ligne affiche le contexte (mission, Producer, Face, montant, date d'absence déclarée, date de contestation) et deux boutons d'action : « Trancher en faveur de la Face » (release wallet Face) ou « Trancher en faveur du Producer » (refund wallet Producer). Toutes les décisions sont auditées dans `financial_events`.

**Acceptance Criteria (draft) :**
- Endpoint admin `GET /api/v1/admin/attendance-disputes` liste les entries `disputed`.
- Endpoint admin `POST /api/v1/admin/attendance-disputes/{entry_id}/resolve` accepte `{outcome: 'face' | 'producer', notes: string}` et délègue à `MissionAttendanceService::resolveDispute`.
- Page admin Vue avec tableau des litiges + actions inline.
- Audit trail : chaque résolution ajoute un `financial_event` avec l'admin user_id, le motif et le outcome.
- Test feature : admin peut résoudre, non-admin → 403, entry non-disputed → 422.

**Technical Notes :**
- Minimaliste pour le MVP — pas de chat, pas de pièces jointes, pas de SLA. Juste la décision binaire admin avec un champ note.
- Volume attendu faible au début (probablement < 5 par semaine au lancement). Pas besoin de pagination avancée pour l'instant.

---

#### FIX-26.9 : Migration legacy — commande one-shot avec date pivot

**Description :** Commande Artisan `missions:legacy-attendance-settlement` exécutée une seule fois au moment du déploiement du nouveau workflow. Pour les missions actuellement en `Closed` avec `date_tournage` passée et entries encore `Locked` : missions créées avant la date pivot (date du déploiement) → traitement legacy (auto-release toutes Faces, comme avant) ; missions créées après la date pivot → entrent dans le nouveau workflow et passent en `PendingAttendanceValidation`.

**Acceptance Criteria (draft) :**
- Commande accepte un argument `--pivot-date=YYYY-MM-DD` obligatoire.
- Commande mode dry-run par défaut, exécution réelle avec `--apply`.
- Pour les missions pré-pivot avec entries `Locked` : appel direct à l'ancien `releaseFunds` legacy (à conserver provisoirement) ou release inline équivalent.
- Pour les missions post-pivot avec entries `Locked` : bascule status mission en `PendingAttendanceValidation`, entries restent `pending` sur `attendance_status`.
- Output console clair : nombre de missions traitées par catégorie.
- Test de la commande avec fixture mixte (3 missions pré-pivot, 3 post-pivot) → 3 finalisées, 3 basculées.

**Technical Notes :**
- Doit tourner après FIX-26.1, 25.2, 25.3 et 25.6 mais **avant** la désactivation du cron 14j (FIX-26.10).
- Validation manuelle staging : exécuter en dry-run, comparer counts attendus vs observés, puis `--apply`.

---

#### FIX-26.10 : Désactivation/repurpose `missions:auto-release-funds` 14j

**Description :** Le cron historique `missions:auto-release-funds` qui complète les missions 14j après `date_tournage` doit être conservé uniquement pour le résiduel legacy (aucune mission post-pivot ne doit y passer) ou complètement désactivé du `Schedule` selon le résultat de FIX-26.9. Une non-régression vérifie qu'aucune mission post-pivot ne passe par cette commande.

**Acceptance Criteria (draft) :**
- Selon décision pendant l'implémentation : (a) commande retirée du `Schedule` Laravel et du `routes/console.php` ; OU (b) commande modifiée pour filtrer `created_at < pivot_date` et ignorer les missions post-pivot.
- Test : une mission post-pivot avec `date_tournage` > 14j en `Closed` n'est PAS complétée par cette commande.
- README ou commit message explicite sur la décision prise.

**Technical Notes :**
- Cette story est principalement défensive — elle s'assure qu'on n'a pas deux mécaniques concurrentes qui se marchent dessus après le déploiement.
- À livrer en toute fin de sprint, après que FIX-26.6 (nouveaux crons) tourne en staging et que FIX-26.9 (migration) ait été exécutée.

---

## Dépendances et risques

### Dépendances externes au sprint
- Le format d'erreur API standard `{ error: { message, code } }` (livré au Sprint 12 / FIX-22) est utilisé par toutes les nouvelles routes — pas de retour en arrière.
- Le layout Blade commun `emails/layouts/base.blade.php` (livré au Sprint 12 / FIX-24) est réutilisé par `FaceMarkedAbsentMail`.

### Risques principaux
1. **Refactor `releaseFunds` (FIX-26.2)** : tout autre code applicatif qui dépend de l'invariant strict `Mission Completed ⟹ toutes entries Released` peut casser. Audit grep exhaustif requis avant la story (chercher les usages de `escrow_status === Released` corrélés à `mission status === Completed`).
2. **Race condition sur la marquage présence** : si un Producer soumet en parallèle de l'auto-validation cron, on doit garantir une seule transition. Verrous DB pessimistes obligatoires.
3. **Volume des notifications** : pour une mission avec 10 absents, on envoie 10 mails simultanément. Vérifier que le mailer (cf. infra existant Sprint 12) supporte le burst sans throttle externe.

### Discipline `ready-for-dev` (CLAUDE.md)
Les acceptance criteria de cet epic sont **en draft** et doivent être affinés story par story avant promotion en `ready-for-dev`. Pour chaque story, avant promotion :
- Vérifier 100 % des références citées (lignes, signatures, méthodes) dans le code actuel.
- Spécifier les fixtures multi-entités concrets (mission + N candidatures + payment).
- Lever les ambiguïtés actives (notamment : transition `Closed → PendingAttendanceValidation` automatique ou manuelle ?).
- Désigner par nom les tests à augmenter (`CompleteMissionTest::test_X` à modifier vs nouveau test).
- Auto-cohérence interne (skeleton code ↔ AC ↔ règles de vérification).

## Status

**Cet epic est en `draft`.** La validation produit du cadrage (présence binaire, fenêtres 72h/72h, refund 100 %, statut intermédiaire `PendingAttendanceValidation`, contrat métier assoupli sur `Mission Completed`) est actée au 2026-04-27. La promotion vers `ready-for-dev` se fait story par story, en respectant la discipline du CLAUDE.md.
