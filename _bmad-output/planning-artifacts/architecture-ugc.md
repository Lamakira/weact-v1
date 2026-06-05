---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-06-05'
workflowType: 'architecture'
project_name: 'WEACT - Module UGC (User Generated Content)'
user_name: 'Lamakira'
date: '2026-06-05'
inputDocuments:
  - path: "docs/new-features-docs/ugc.docx"
    type: "spec"
    loaded: true
    notes: "Spec produit UGC — tient lieu de PRD (décision PO : pas de PRD séparé)"
  - path: "docs/design/Weact Ugc Design/design_handoff_ugc_module/README.md"
    type: "ux-design"
    loaded: true
    notes: "Design handoff hifi — 11 écrans × 2 variations A/B, primitives, tokens, behavior, state"
  - path: "_bmad-output/planning-artifacts/architecture-booking.md"
    type: "architecture"
    loaded: true
    notes: "Référence brownfield — format + patterns Booking/paiement existants"
  - path: "_bmad-output/project-context.md"
    type: "project-context"
    loaded: true
  - path: "_bmad-output/planning-artifacts/epics-face-premium-subscription-v2.md"
    type: "epics"
    loaded: true
    notes: "Abonnement Face : paywall UGC + suspension + FaceEntitlementService (réutilisé)"
  - path: "_bmad-output/planning-artifacts/epics-face-monetization-upsell.md"
    type: "epics"
    loaded: true
    notes: "Commission tier-aware (15/10/5) + commission producteur 10%"
decisionsLocked:
  - "Modèle de données EN EXTENSION de Booking/Mission (discriminant + colonnes UGC nullables + modèles partagés Deliverable & Shipment) — PAS d'entités dédiées"
  - "Périmètre : Booking UGC + Mission UGC ensemble"
  - "Réutiliser FedapayService/webhooks/PaymentOverlay + FaceSubscription/FaceEntitlementService (paywall + suspension)"
  - "Commission = max(2500, round(valeurProduit * 0.10)) FCFA, assise sur la valeur produit"
  - "Design : défaut variation A (safe), tranché par écran au moment de la story"
---

# WEACT — Module UGC : Document d'Architecture (Solution Design)

_Ce document se construit collaborativement, étape par étape. Les sections sont ajoutées au fur et à mesure de la validation de chaque décision d'architecture._

> **Substitution PRD** : par décision PO, ce module n'a pas de PRD séparé. Les exigences viennent de la spec `docs/new-features-docs/ugc.docx` et du design handoff `docs/design/Weact Ugc Design/design_handoff_ugc_module/`. Le brief produit et l'UX sont donc considérés comme acquis en entrée.

## Project Context Analysis

### Requirements Overview

**Functional Requirements (depuis la spec UGC + design handoff) :**
- FR-1 Booking UGC direct : nouveau `type_contenu = 'UGC'` sur Booking + toggle compensation (product | hybrid).
- FR-2 Champs dotation : product_name, product_value (base commission), video_count (product→2 figé ; hybrid→éditable + pay_amount).
- FR-3 Commission = max(2500, round(product_value*0.10)) FCFA ; paiement = condition d'envoi du booking.
- FR-4 Mission UGC : `type_mission = 'ugc'` ; paiement commission obligatoire pour publier (divergence vs mission standard).
- FR-5 Gating : accès opportunités UGC réservé aux Faces abonnées (Starter+) ; gratuit → paywall.
- FR-6 Tunnel 6 étapes avec « Produit reçu » comme déclencheur des chronos (7j Unboxing, 14j Avis).
- FR-7 Validation Producteur par livrable (valider/rejeter/retouche), SLA 48h, enchaînement des chronos.
- FR-8 Suspension auto + blocage abonnement sur dépassement de deadline ; notif Producteur + remplacement.

**Non-Functional Requirements :**
- Sécurité/anti-fraude : tunnel de validation strict, escrow (capture après acceptation Face, remboursement si refus), adresse KYC vérifiée.
- Paiement : FedaPay (MTN MoMo / Moov Money / carte), push USSD, webhooks idempotents, polling + reprise.
- Temps autoritatif serveur : deadlines et `progress` calculés côté serveur ; jobs planifiés pour le dépassement ; front = rendu pur.
- Médias : upload + lecture vidéo (remplacer placeholders), checklist conformité.
- Couplage abonnement : réutiliser FaceEntitlementService / FaceSubscription pour gating + suspension sans régresser les paliers.
- Notifications : escalade de deadline côté Face, événements côté Producteur.
- A11y / responsive / i18n : focus rings, prefers-reduced-motion ; Face mobile-first 360px, Producteur desktop ≥1024px ; FR accentué.

**Scale & Complexity :**
- Primary domain : full-stack (Laravel 12 API + Vue 3 SPA) avec jobs/scheduler asynchrones et intégration paiement.
- Complexity level : élevée (argent + bien physique + temps + multi-rôle + médias).
- Estimated architectural components : ~12 (extensions Booking/Mission, modèles Deliverable & Shipment, machine à états tunnel, moteur chronos/deadlines, job suspension, service commission UGC, intégration paiement, gating, notifications, contrôleurs/policies, primitives UI partagées, écrans Face/Producteur).

### Technical Constraints & Dependencies
- Brownfield : extension de Booking & Mission (décision PO), pas d'entités UGC dédiées — doit cohabiter avec les flux existants (listing, policies, paiement, commission tier-aware).
- Dépendances : FedapayService + webhook idempotent existant ; FaceSubscription / FaceEntitlementService ; queue + scheduler Laravel ; stockage médias ; lucide-vue-next + Tailwind tokens (design-system.md).
- Divergence de flux paiement Mission : UGC paie pour publier vs standard paie à la sélection — à isoler sans casser le standard.
- Nouvelle base de commission (valeur produit) distincte de la commission cash producteur 10 % existante.

### Cross-Cutting Concerns Identified
- Moteur de chronos/deadlines (jobs planifiés + état `progress` + escalade couleur) partagé Booking/Mission.
- Machine à états du tunnel 6 étapes (+ overdue/suspended) cohérente avec BookingStatus/MissionStatus.
- Moteur de suspension couplé à l'abonnement (effet de bord sur l'accès UGC et le gating).
- Cycle commission/escrow (capture/remboursement) branché sur FedaPay.
- Gating entitlements (paywall) et tri par palier.
- Idempotence : webhooks paiement ET transitions de livrables/chronos.
- Notifications (escalade deadline Face + événements Producteur).
- Médias : upload/stockage/lecture + checklist conformité.

## Starter Template Evaluation

### Primary Technology Domain
Full-stack brownfield : API Laravel 12 (PHP 8.2) + SPA Vue 3.5. **Aucun starter template** — le module UGC est une extension du codebase WeAct existant. La "fondation" est donc le socle technique et les conventions déjà en production.

### Décision : pas de starter (extension brownfield)
**Rationale :** introduire un starter casserait les conventions établies (features/*, Services, Form Requests, Policies, Enums, Resources) et la décision PO d'étendre Booking/Mission. Le module se greffe sur l'existant.

### Socle technique de référence (versions réelles vérifiées)
**Backend (`backend/composer.json`) :**
- PHP `^8.2`, Laravel `^12.0`, Sanctum `^4.2` (auth API).
- `fedapay/fedapay-php ^0.4.7` — paiement (réutilisé pour la commission UGC).
- `laravel/reverb ^1.8` — WebSockets (notifications temps réel deadline/validation).
- `php-ffmpeg ^1.3` + `intervention/image ^3.11` — médias (livrables vidéo + miniatures).
- Tests : PHPUnit (`php artisan test`).

**Frontend (`frontend/package.json`) :**
- Vue `^3.5.26`, Vite `^7`, TypeScript `~5.9`, Tailwind `^4.1` (CSS-first).
- Pinia `^3` (state), vue-router `^4`, `vee-validate ^4` + `zod ^4` (forms), `@vueuse/core ^14`.
- `lucide-vue-next ^0.562` (icônes du handoff), `reka-ui ^2.7` (primitives headless / shadcn-vue), `vue-toastification`.
- Temps réel : `laravel-echo ^2.3` + `pusher-js ^8.4`.
- Tests : Vitest `^4` + `@vue/test-utils` + `happy-dom`.

### Conventions établies à respecter (héritées, non négociables)
- Backend : un Form Request (validation) + un Service (logique métier) par opération ; Policies/Gates pour l'autorisation ; Enums PHP pour les statuts ; API Resources pour la sérialisation ; `HasRouteUuid` pour les URLs publiques ; recalcul serveur autoritatif des montants.
- Frontend : organisation `features/<domaine>/{components,composables,services,schemas,types}` ; composables pour l'état/flux ; services pour l'API ; schémas Zod pour la validation ; tokens Tailwind du design-system.md.

**Note :** aucune story d'initialisation de projet n'est nécessaire (brownfield) — la première story du module portera directement sur les migrations/extensions de schéma.

## Core Architectural Decisions

### Decision Priority Analysis

**Décisions héritées (non rejouées) :** MySQL · Sanctum · REST `/api/v1` · FedaPay SDK · queue + scheduler Laravel · storage privé + ffmpeg · Reverb/Echo (temps réel) · conventions Form Request + Service + Policy + Enum + Resource + recalcul serveur autoritatif.

**Critical (bloquent l'implémentation) :** D1 modèle de données, D2 machine à états du tunnel, D4 commission & capture, D5 suspension.
**Important (façonnent l'architecture) :** D3 moteur de chronos, D6 divergence paiement mission, D9 surface API, D10 gating.
**Différables / hors-MVP :** affinage des seuils d'escalade notif, remplacement automatique de Face (peut être semi-manuel au départ), checklist de conformité auto.

### Data Architecture

**D1 — Extension + polymorphisme (décision PO verrouillée).**
- `Booking` : colonnes UGC nullables — `compensation_type` (enum `product`|`hybrid`), `product_name`, `product_value` (base commission), `video_count`, `pay_amount` (hybride), `ugc_commission`. Discriminant `type_contenu='UGC'`.
- `Mission` : mêmes colonnes dotation + `type_mission='ugc'`.
- Modèles **partagés polymorphes** (`morphTo` owner = Booking **ou** Candidature) :
  - `Shipment` : `carrier`, `tracking_number`, `shipped_at`, `received_at` (déclencheur chrono), snapshot adresse KYC, `tunnel_status`.
  - `Deliverable` : `kind` (`unboxing`|`avis`|`extra`), `sequence`, `chrono_started_at`, `deadline_at`, média vidéo, `validation_status` (`pending_upload`|`in_review`|`validated`|`rejected`|`retouche_requested`), `review_note`, `validated_at`.
- Owner booking UGC = ligne `Booking` ; owner mission UGC = chaque `Candidature` retenue (table existante, statuts `pending/accepted/confirmed/in_progress/completed/rejected/cancelled`). Logique tunnel partagée par polymorphisme, sans entité top-level dédiée.
- *Rejeté :* deliverables en colonne JSON (besoin de chrono/validation/requêtes par ligne) ; entités dédiées UgcBooking/UgcMission (rejeté par PO).
- Migration rétroactive : N/A (colonnes nullables, aucune ligne UGC existante).

### Authentication & Security

**D5 — Suspension UGC : état dédié, PAS `is_active` (CRITIQUE).**
- `User.is_active=false` révoque les tokens + bloque le login (403 `ACCOUNT_DEACTIVATED`) → **contredit l'écran 10** (la Face suspendue doit se connecter, terminer la mission en retard, faire appel). On ne le réutilise donc PAS.
- Suspension douce : table `ugc_suspensions` (`face_id`, `deliverable_id` cause, `reason`, `suspended_at`, `appeal_status`, `reactivated_at`) + flag dérivé sur Face. Login OK ; `FaceEntitlementService` refuse l'accès UGC + gèle le premium tant que suspendu.
- Réactivation (écran 10) : terminer la mission en retard jusqu'à J+30, **ou** appel (revue manuelle ~24h) ; admin peut réactiver. Producteur notifié + remplacement proposé.
- *Rejeté :* `is_active=false` (trop radical, empêche réparation/appel).

**Autorisation :** Policies — le Producteur ne gère que ses bookings/missions ; la Face n'agit que sur ses candidatures/engagements. Montants toujours recalculés serveur.

### API & Communication Patterns

**D6 — Divergence paiement Mission.** UGC : `draft → pending_payment (commission) → published`. Mission standard inchangée (publication gratuite, paiement à la sélection). Branche sur `type_mission` dans le flux de publication.

**D8 — Notifications & temps réel.** Événements domaine : `ShipmentConfirmed`, `ProductReceived`, `DeliverableUploaded`, `Deliverable{Validated,Rejected,RetoucheRequested}`, `DeadlineApproaching(seuil)`, `FaceUgcSuspended` → notifs in-app + Reverb (réutilise l'infra realtime-notifications). Escalade idempotente par seuil.

**D9 — Surface API (REST `/api/v1`, contrôleurs UGC dédiés réutilisant modèles/services).**
- Producteur : booking UGC (extension create), mission UGC (create + publish-pay), Shipment confirm, Deliverable validate/reject/retouche.
- Face : découverte missions UGC (gated), accept, « produit reçu », upload livrable.

**Idempotence :** webhooks paiement (clé événement FedaPay) ET transitions livrables/chronos (gardes d'état).

### Frontend Architecture

- Organisation `features/{booking,mission,face}/{components,composables,services,schemas,types}`.
- Primitives partagées (design `shared.jsx`) : `ChronoRing`, `ChronoBadge`, `StatusPill`, `CompensationToggle`, `CommissionBreakdown`, `BookingTimeline` (V/H), `ReassuranceCard`, `PayTile`. Réutiliser `reka-ui` + tokens Tailwind.
- State : Pinia/composables ; le `progress` chrono et la couleur sont **dérivés** de timestamps serveur (le front ne décide ni du temps ni des montants).
- Validation : schémas Zod (`compensationType`, `productValue`, `payAmount`, `videoCount`).
- A11y/responsive : Face mobile-first 360px, Producteur desktop ≥1024px, `prefers-reduced-motion`, focus rings teal.
- Variations design : défaut **A (safe)**, direction tranchée par écran au moment de la story.

### Infrastructure & Deployment

**D3 — Moteur de chronos / deadlines (serveur autoritatif).**
- `deadline_at` absolu par Deliverable au démarrage (received_at + 7j Unboxing ; validation Unboxing + 14j Avis). Durées dans `config/ugc.php`.
- `progress = clamp((now − started_at)/(deadline_at − started_at), 0, 1)`.
- Job planifié `ugc:process-deadlines` (~15 min), idempotent : notifications d'escalade par seuil (`last_notified_threshold`) + suspension au franchissement sans upload validé.
- *Rejeté :* job retardé à l'instant exact de chaque deadline (dur à reprogrammer, fragile aux redémarrages).

**D4 — Commission & capture (confirmé PO).**
- Commission = `max(2500, round(product_value×0.10))`, recalculée serveur, stockée sur l'owner.
- **Débit immédiat + remboursement si non-acceptation** : mobile money (MTN/Moov) ne supporte pas l'autorisation/capture différée → pas de hold technique. « Encaissé après acceptation · remboursé si refus » = remboursement auto si la Face n'accepte pas dans la fenêtre (ou refuse). **À vérifier en story (spike) :** support refund du SDK FedaPay ; fallback remboursement ops + runbook.
- Réutilise `FedapayService` + webhook idempotent + `PaymentOverlay` + polling/reprise.

**D7 — Médias livrables.** Upload vidéo en storage privé, ffmpeg (miniature + durée), URLs signées pour lecture Producteur ; média polymorphe sur Deliverable (distinct de `FaceVideo` portfolio).

**D10 — Gating.** `FaceEntitlementService` : découverte/accept UGC requiert abonnement actif (Starter+) ; gratuit → paywall `/pricing` ; suspendu → refusé. Capability `canAccessUgc`.

### Decision Impact Analysis

**Implementation Sequence :** schéma/migrations (colonnes Booking+Mission, `shipments`, `deliverables`, `ugc_suspensions`) → enums → services (commission, transitions tunnel, deadline engine) → intégration paiement → contrôleurs/policies → jobs/scheduler → notifications → primitives UI → écrans.

**Cross-Component Dependencies :** D2 (machine à états) sous-tend D3/D5/D8 ; D4 (paiement) garde l'entrée de D2 ; D5 (suspension) dépend de D3 (franchissement deadline) + D10 (entitlements) ; D1 (modèle) est le socle de tout.

> **Correction de D1 (cohérence nommage) :** les colonnes dotation UGC sont en **français** (cohérence Booking/Mission dont les colonnes métier sont en FR), pas en anglais : `type_compensation`, `nom_produit`, `valeur_produit`, `nombre_videos`, `montant_remuneration`, `commission_ugc`, `transporteur`, `numero_suivi`, `recu_le`. Voir la section Patterns ci-dessous.

## Implementation Patterns & Consistency Rules

### Conflict Points Identifiés
~10 zones où des agents pourraient diverger : nommage colonnes, valeurs d'enum, format argent/temps, idempotence, événements, organisation front, gating, transitions d'état.

### Naming Patterns

**Base de données :**
- Colonnes **métier en français** (cohérence Booking/Mission) : `type_compensation`, `nom_produit`, `valeur_produit`, `nombre_videos`, `montant_remuneration`, `commission_ugc`, `transporteur`, `numero_suivi`, `recu_le`. Colonnes **techniques en anglais** : `status`/`*_status`, `*_at`, `*_id`, `fedapay_transaction_id`.
- snake_case partout ; FK `<entite>_id` ; tables au pluriel (`shipments`, `deliverables`, `ugc_suspensions`).
- Argent : entiers **FCFA** (jamais de décimales).

**Enums (PHP backed) :** valeurs en anglais + méthode `label()` française (modèle `FaceVideoType`). Nouveaux : `CompensationType` (product|hybrid), `UgcTunnelStatus`, `DeliverableKind` (unboxing|avis|extra), `DeliverableValidationStatus`.

**API :** REST `/api/v1`, binding `{uuid}` via `HasRouteUuid` pour les ressources publiques ; nommage d'endpoints aligné sur l'existant (ex. `/face/missions/{id}/apply`, `/producer/...`).

**Code :** Backend PSR-12, `declare(strict_types=1)`, Services suffixés `Service`, Form Requests suffixés `Request`. Frontend composants `PascalCase.vue`, composables `useXxx.ts`, services `xxxApi.ts`.

### Structure Patterns
- Backend : Form Request (validation) + Service (logique) + Policy (autz) + Resource (sérialisation) + Enum (statuts) par opération ; tests Feature dans `backend/tests/Feature/<Domaine>/`.
- Frontend : `features/<domaine>/{components,composables,services,schemas,types}` ; tests co-localisés dans `__tests__/`.

### Format Patterns
- Réponses via API Resources Laravel ; erreurs de validation = 422 + map `errors` (mappées au front via `setFieldError`). Erreurs métier = code + message FR.
- Dates : timestamps serveur (UTC en base), chaînes ISO en API ; le front dérive `progress`/couleur (jamais l'inverse).
- Booléens true/false ; montants entiers FCFA, formatés `toLocaleString('fr-FR')` au rendu.

### Communication Patterns
- Événements : classes d'événement PascalCase (`ProductReceived`, `DeliverableValidated`…), listeners via `#[AsEventListener]`. ⚠️ Après ajout d'un listener auto-discovered : `php artisan event:clear` (cache compilé).
- Notifications temps réel via Reverb/Echo ; payloads sérialisés via Resources.
- State front : Pinia/composables ; updates immutables ; le temps et l'argent viennent du serveur.

### Process Patterns
- **Idempotence (obligatoire)** : webhooks paiement gardés par clé événement FedaPay ; transitions tunnel/livrables gardées par état courant (re-jouer une transition ne double rien) ; notifications d'escalade gardées par `last_notified_threshold`.
- Recalcul serveur autoritatif de la commission (jamais la valeur client).
- Loading/erreur : overlays/spinners cohérents avec `PaymentOverlay` ; reprise de paiement via clé d'initiation.

### Enforcement Guidelines
**Tout agent dev DOIT :**
- Mettre les colonnes métier UGC en français, valeurs d'enum en anglais + `label()` FR.
- Recalculer la commission serveur ; ne jamais faire décider le temps/argent au front.
- Garder toute transition d'état et tout webhook idempotents.
- Réutiliser FedapayService / FaceEntitlementService / infra notifications plutôt que réimplémenter.
- Lancer `php artisan event:clear` après ajout d'un listener.

### Anti-Patterns à éviter
- Réutiliser `User.is_active` pour la suspension UGC (casse l'écran de réactivation).
- Étendre `BookingStatus` avec les états du tunnel (pollue le booking standard).
- Stocker les livrables en JSON (perte de chrono/validation par ligne).
- Colonnes dotation en anglais (incohérent avec Booking/Mission).

## Project Structure & Boundaries

### Arborescence (＋ nouveau · ~ modifié) — extension brownfield

```
backend/
├── app/
│   ├── Models/
│   │   ├── ~ Booking.php              # + colonnes UGC, relations shipment()/deliverables()
│   │   ├── ~ Mission.php              # + colonnes UGC dotation
│   │   ├── ＋ Shipment.php            # morphTo owner (Booking|Candidature)
│   │   ├── ＋ Deliverable.php         # morphTo owner ; chrono + validation
│   │   └── ＋ UgcSuspension.php       # suspension douce (face_id, appeal_status…)
│   ├── Enums/
│   │   ├── ＋ CompensationType.php    # product | hybrid (+ label() FR)
│   │   ├── ＋ UgcTunnelStatus.php     # awaiting_payment…completed/overdue/suspended
│   │   ├── ＋ DeliverableKind.php     # unboxing | avis | extra
│   │   ├── ＋ DeliverableValidationStatus.php
│   │   └── ＋ UgcSuspensionAppealStatus.php
│   ├── Services/
│   │   └── Ugc/
│   │       ├── ＋ UgcCommissionService.php   # max(2500, round(valeur*0.10))
│   │       ├── ＋ UgcTunnelService.php       # transitions gardées idempotentes
│   │       ├── ＋ UgcShipmentService.php     # confirm envoi + received → start chrono
│   │       ├── ＋ UgcDeliverableService.php  # upload / validate / reject / retouche
│   │       ├── ＋ UgcDeadlineService.php     # calcul deadline_at + progress + seuils
│   │       └── ＋ UgcSuspensionService.php   # suspend / appeal / reactivate
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Producer/
│   │   │   │   ├── ＋ UgcShipmentController.php       # confirm expédition + tracking
│   │   │   │   └── ＋ UgcDeliverableController.php    # valider/rejeter/retouche
│   │   │   └── Face/
│   │   │       ├── ＋ UgcMissionDiscoveryController.php  # liste gated
│   │   │       ├── ＋ UgcEngagementController.php        # accept + « produit reçu »
│   │   │       └── ＋ UgcDeliverableUploadController.php # upload vidéo
│   │   ├── Requests/
│   │   │   ├── ~ Booking/CreateBookingRequest.php    # + validation champs UGC
│   │   │   ├── ＋ Mission/PublishUgcMissionRequest.php
│   │   │   ├── ＋ Ugc/ConfirmShipmentRequest.php
│   │   │   ├── ＋ Ugc/ValidateDeliverableRequest.php
│   │   │   └── ＋ Ugc/UploadDeliverableRequest.php
│   │   └── Resources/
│   │       ├── ＋ ShipmentResource.php
│   │       ├── ＋ DeliverableResource.php
│   │       └── ＋ UgcEngagementResource.php
│   ├── Policies/
│   │   ├── ＋ ShipmentPolicy.php
│   │   └── ＋ DeliverablePolicy.php
│   ├── Events/        ＋ ProductReceived, ShipmentConfirmed, DeliverableUploaded,
│   │                    DeliverableValidated, DeliverableRejected, DeliverableRetoucheRequested,
│   │                    UgcDeadlineApproaching, FaceUgcSuspended
│   ├── Listeners/Booking|Mission/  ＋ notifications + remplacement Face
│   ├── Notifications/ ＋ DeadlineApproachingNotification, FaceSuspendedNotification, …
│   ├── Console/Commands/ ＋ ProcessUgcDeadlinesCommand.php   # `ugc:process-deadlines`
│   └── Services/  ~ FaceEntitlementService.php   # + canAccessUgc + blocage si suspendu
├── config/  ＋ ugc.php   # durées 7j/14j, taux 10%, plancher 2500, seuils escalade, fenêtre appel J+30
├── database/migrations/ ＋ add_ugc_columns_to_bookings, add_ugc_columns_to_missions,
│                          create_shipments_table, create_deliverables_table, create_ugc_suspensions_table
├── routes/ ~ api.php (routes UGC), ~ console.php (schedule ugc:process-deadlines)
└── tests/Feature/Ugc/  ＋ suite de tests (tunnel, commission, deadlines, suspension, gating)

frontend/src/
├── components/ugc/   ＋ ChronoRing, ChronoBadge, StatusPill, CompensationToggle,
│                       CommissionBreakdown, BookingTimeline(V/H), ReassuranceCard, PayTile
│                       (emplacement partagé — aligner sur la convention src/components/ existante)
├── features/
│   ├── booking/
│   │   ├── components/ ~ BookingFormSheet.vue (+ option UGC) · ＋ UgcBookingFields.vue,
│   │   │                  UgcShipmentForm.vue, UgcDeliverableReview.vue
│   │   ├── composables/ ＋ useUgcShipment.ts, useUgcDeliverable.ts
│   │   ├── schemas/ ~ booking.ts (+ champs UGC)  · services/ ~ (endpoints UGC)
│   ├── mission/
│   │   ├── components/ ~ MissionForm.vue (+ bloc dotation) · ＋ UgcDotationFields.vue
│   │   ├── composables/ ＋ useUgcMission.ts (publish-pay)
│   ├── face/
│   │   ├── components/ ＋ UgcTrackingCard.vue, UgcSuspensionScreen.vue, UgcDeadlineList.vue
│   │   └── composables/ ＋ useUgcEngagement.ts, useChrono.ts (progress→couleur), useUgcDiscovery.ts
│   └── notification/components/ ~ items d'escalade deadline
├── pages/
│   ├── producer/booking|mission/ ~ pages d'expédition + validation livrables
│   └── face/mission/   ＋ découverte UGC (paywall) + détail/acceptation
│        face/booking/  ＋ suivi (produit reçu, chronos, upload), suspension
└── (workflow diagramme écran 11 → doc interne / admin, non bloquant MVP)
```

### Architectural Boundaries
- **API** : `/api/v1/producer/*` (booking/mission UGC, shipment, validation) et `/api/v1/face/*` (découverte gated, accept, produit reçu, upload). Webhook `/api/v1/webhooks/fedapay` **réutilisé** (idempotent).
- **Données** : `shipments` & `deliverables` polymorphes (owner Booking|Candidature) ; `ugc_suspensions` → faces. Colonnes UGC nullables sur `bookings`/`missions`.
- **Événements** : événements domaine → Listeners → Notifications (in-app) + Reverb (temps réel).
- **Gating** : `FaceEntitlementService` (capability `canAccessUgc`, blocage si suspendu) en garde des contrôleurs Face.
- **Front** : le `progress`/couleur des chronos est dérivé de timestamps serveur (composable `useChrono`) ; primitives partagées sans logique métier.

### Requirements → Structure Mapping
- FR-1/FR-2/FR-3 (booking UGC + commission) → `BookingFormSheet` + `UgcBookingFields` + `UgcCommissionService` + migration bookings.
- FR-4 (mission UGC + publish-pay) → `MissionForm`/`UgcDotationFields` + `PublishUgcMissionRequest` + `useUgcMission` + migration missions.
- FR-5/FR-10 (gating) → `FaceEntitlementService` + `UgcMissionDiscoveryController` + pages face/mission.
- FR-6 (tunnel + chronos) → `Shipment`/`Deliverable` + `UgcTunnelService`/`UgcDeadlineService` + `ProcessUgcDeadlinesCommand` + `useChrono`.
- FR-7 (validation) → `UgcDeliverableController`/`UgcDeliverableService` + `UgcDeliverableReview.vue`.
- FR-8 (suspension) → `UgcSuspension` + `UgcSuspensionService` + `UgcSuspensionScreen.vue`.

### Integration Points
- **Interne** : Services UGC orchestrés par les contrôleurs ; events → notifications/Reverb ; scheduler → `ugc:process-deadlines`.
- **Externe** : FedaPay (commission + remboursement) ; storage privé + ffmpeg (vidéos).
- **Data flow** : paiement commission → webhook → tunnel `paid` → accept Face → shipment `shipped` → « produit reçu » `received` (start chrono) → upload+validation livrables (chrono enchaîné) → `completed` | franchissement deadline → suspension.

## Architecture Validation Results

### Coherence Validation ✅
- **Décisions** : D1→D10 compatibles, sans contradiction. Le polymorphisme (D1) sert D2/D3/D5 ; le sous-état tunnel (D2) évite de polluer `BookingStatus`/`CandidatureStatus` ; D4 garde l'entrée de D2 ; D5 dépend de D3+D10.
- **Patterns** : colonnes métier FR + enums anglais + idempotence + recalcul serveur cohérents avec les décisions.
- **Structure** : l'arborescence supporte chaque décision ; frontières API/données/événements/gating bien posées.

### Requirements Coverage Validation ✅
- **FR** : FR-1→FR-8 tous mappés (voir Requirements → Structure Mapping).
- **NFR** : sécurité/anti-fraude, paiement idempotent, temps serveur autoritatif, médias, gating, a11y, i18n — adressés.
- **Note validée** : commission assise sur la **valeur produit uniquement** (pas le cash hybride), conforme spec/design ; suspension douce (D5) cohérente avec l'écran 10 (login préservé).

### Implementation Readiness Validation ✅
- Décisions documentées avec rationale + alternatives rejetées ; patterns + anti-patterns explicites ; structure concrète (fichiers ＋/~).

### Gap Analysis Results

**🔴 Open items à lever avant / au début du dev (tracés, décision PO différée) :**
- **OI-1 — Commission mission multi-Faces → ✅ RÉSOLU le 2026-06-05 : Option A (une seule commission au publish, sur la valeur produit).** Argument décisif : la commission se paie **pour publier** (appel à projets), donc avant toute candidature/sélection → `N` est inconnu au paiement, l'option « × N Faces » est impossible sans déplacer le paiement à la confirmation (ce qui contredirait la spec). Plancher 2 500 FCFA appliqué **une fois par publication**. Réserve future (hors-MVP) : un frais par Face à la confirmation pourra être ajouté si abus constaté. Implémenté dans `UgcCommissionService` (story 1.3).
- **OI-2 — Refund FedaPay** : capacité de remboursement du SDK `fedapay/fedapay-php ^0.4.7` non vérifiée → **spike obligatoire** en 1re story paiement ; fallback remboursement ops + runbook si non supporté.

**🟠 À spécifier (non bloquant pour démarrer) :**
- OI-3 — « Remplacement proposé » (FR-8) : **semi-manuel au MVP** (notif Producteur + ré-ouverture slot / re-book) ; automatisation ultérieure.
- OI-4 — Checklist de conformité (écran 5) : **manuelle au MVP**.
- OI-5 — Seuils d'escalade (24h/4h…) + canaux notif (in-app / Reverb / push) à confirmer PO.

**🟡 Mineur :**
- OI-6 — Emplacement primitives `src/components/ugc/` à aligner sur la convention `src/components/` existante.

### Architecture Completeness Checklist
**Requirements Analysis** — [x] contexte · [x] échelle/complexité · [x] contraintes · [x] préoccupations transverses
**Architectural Decisions** — [x] décisions critiques documentées · [x] stack héritée spécifiée · [x] intégration paiement/temps · [x] sécurité/suspension
**Implementation Patterns** — [x] nommage · [x] structure · [x] communication/événements · [x] process/idempotence
**Project Structure** — [x] arborescence ＋/~ · [x] frontières · [x] points d'intégration · [x] mapping FR→structure

### Architecture Readiness Assessment
- **Overall Status : READY FOR IMPLEMENTATION** (sous réserve des open items 🔴 OI-1/OI-2 tracés à lever en story).
- **Confidence Level : moyenne-haute** — réutilisation forte de l'existant (FedaPay, abonnement, missions/bookings) ; risques résiduels concentrés sur le refund FedaPay et l'arbitrage commission multi-Faces.
- **Key Strengths** : extension non intrusive (colonnes nullables + polymorphisme), temps/argent autoritatifs serveur, suspension non destructive, réutilisation paiement/gating/notifications.
- **Future Enhancement** : remplacement Face automatique, conformité auto, finesse d'escalade des notifications.

### Implementation Handoff
- **AI Agent Guidelines** : suivre les décisions à la lettre ; colonnes métier FR + enums anglais ; idempotence systématique ; recalcul serveur ; réutiliser FedapayService / FaceEntitlementService / infra notifications ; `php artisan event:clear` après ajout de listener.
- **First Implementation Priority** : migrations/schéma (colonnes UGC Booking+Mission, `shipments`, `deliverables`, `ugc_suspensions`) + enums, puis services socle (`UgcCommissionService`, `UgcDeadlineService`, `UgcTunnelService`).
