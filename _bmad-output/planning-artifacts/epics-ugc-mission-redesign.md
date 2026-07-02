---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
completedAt: '2026-06-24'
totalEpics: 1
totalStories: 5
project_name: 'WEACT — Refonte Mission UGC (flux candidature classique + hybride par-Face)'
user_name: 'Lamakira'
date: '2026-06-22'
requirementsSource: 'Décisions PO verrouillées 2026-06-22 (cf. session + memory/project_ugc_mission_redesign.md). Brownfield : pas de PRD formel pour cette refonte.'
inputDocuments:
  - path: "_bmad-output/planning-artifacts/architecture-ugc.md"
    type: "architecture"
    notes: "Contexte technique du module UGC à étendre"
  - path: "_bmad-output/planning-artifacts/epics-ugc-module.md"
    type: "epics-reference"
    notes: "6 épics UGC existants ; reprend l'épic différé ugc-epic-rh-mission"
  - path: "investigation-code-2026-06-22"
    type: "code-grounding"
    notes: "Cartographie au code (3 sous-agents) : escrow 100% Booking-typed, tunnel Shipment owner Booking|Candidature, flux candidature actuel (auto-acceptation), UgcDeliverableService:286 completion Booking-only"
---

# WEACT — Refonte Mission UGC : Epic Breakdown

## Overview

Refonte de la **mission UGC** pour qu'elle adopte le **cycle de candidature classique** (la Face postule, le Producteur accepte/refuse explicitement, la Face reconfirme) avec **certains champs de tournage masqués**, et pour qu'elle prenne en charge le **règlement hybride par-Face** (escrow + versement wallet à la complétion) — reprise de l'épic différé `ugc-epic-rh-mission`.

Décisions PO **verrouillées le 2026-06-22** (anti-double-paiement, money model par-Face miroir RH.2). Brownfield : on **étend** le module UGC existant (tunnel `Shipment`/`Deliverable` owner `Booking|Candidature`, `MissionService`, `UgcRefundService`, `UgcSuspensionService`, candidatures classiques) — on ne repart pas de zéro.

## Requirements Inventory

### Functional Requirements

**A — Champs mission UGC (masquage tournage)**

FR1: Le formulaire de création de mission UGC masque `date_tournage`, `durée` et `lieu` ; il conserve `date_limite_candidature` et tous les autres champs pertinents (titre, description, type, `nombre_faces_voulu`, genre, profil recherché, + champs de dotation déjà présents).
FR2: Côté backend, pour une mission UGC, `date_tournage`/`lieu`/`duree` deviennent optionnels (relâchés dans `StoreMissionRequest`, jamais dans le trait partagé `MissionValidationRules`), la règle croisée `date_tournage after:date_limite_candidature` est neutralisée, et `MissionService` persiste `null` pour ces 3 champs.

**B — Cycle candidature explicite (fin de l'auto-acceptation)**

FR3: Une Face éligible (paywall abonnement FR5-UGC existant) postule à une mission UGC publiée → candidature `pending` (flux `apply` standard).
FR4: Le Producteur voit les candidatures `pending` de sa mission UGC et peut **accepter** ou **refuser** chacune explicitement (action « accepter candidature UGC » = net-neuf ; aujourd'hui seul `reject` existe pour l'UGC).
FR5: L'auto-acceptation actuelle est **retirée** (`UgcEngagementController::accept` : candidature → `confirmed` direct + auto-close à capacité).
FR6: Sur **refus**, la candidature passe `rejected` et la Face est **notifiée** (in-app + email).
FR7: Sur **acceptation**, la candidature passe `accepted`, la Face est **notifiée** et doit **reconfirmer** sa participation (2ᵉ « oui ») pour passer `confirmed`.
FR8: Le nombre de Faces acceptées/confirmées est **borné par `nombre_faces_voulu`** ; la mission cesse d'accepter de nouvelles candidatures une fois la capacité de Faces engagées atteinte.
FR9: Une candidature `confirmed` enclenche le tunnel existant (le Producteur peut confirmer l'expédition) — aval inchangé.

**C — Money model produit-seul (inchangé, à préserver)**

FR10: Mission **produit-seul** : commission WeAct = `max(2500, valeur_produit × 10%)` payée **au publish** (gate `pending_payment → published` conservé) ; l'acceptation d'une Face est **gratuite** ; la Face est rémunérée par le **produit** (hors-plateforme) ; **aucun crédit wallet**.

**D — Money model hybride (par-Face, miroir RH.2)**

FR11: Mission **hybride** : **aucune commission sur la valeur produit** ; la mission est **publiée sans paiement au publish** (pas de gate `pending_payment`). La commission WeAct porte **uniquement sur le cash**.
FR12: À l'**acceptation** d'une Face sur une mission hybride, le Producteur paie `cash + 10% frais de service` (FedaPay) → **escrow `Locked` par Candidature** (réutilise `BookingPricing`). La Face reconfirme ensuite (FR7) → tunnel.
FR13: À la **complétion** du tunnel d'une Candidature (Avis validé), l'escrow est libéré → **wallet Face = `cash − commission palier (15/10/5)`** ; WeAct conserve `10% frais + palier`.
FR14: Si la Face **décline après paiement**, est **suspendue**, ou **rate une deadline** du tunnel, l'escrow est **remboursé au Producteur**.

**E — Frontend (2 côtés)**

FR15: Côté **Face** : UI pour postuler à une mission UGC, suivre le statut de sa candidature (pending / acceptée / refusée) et **reconfirmer** après acceptation.
FR16: Côté **Producteur** : UI de gestion des candidatures UGC (accepter / refuser ; pour l'hybride, déclencher le paiement FedaPay à l'acceptation).

### NonFunctional Requirements

NFR1: Étendre `EscrowService` (aujourd'hui **100% typé `Booking`** — toutes signatures `Booking $booking`) à un owner `Candidature`, **sans régresser** le chemin booking.
NFR2: Le hook de complétion `UgcDeliverableService:286` (aujourd'hui `$owner instanceof Booking` only) gère désormais aussi `Candidature` (release escrow + clôture de la candidature/tunnel).
NFR3: **Atomicité argent/tunnel** : toute opération escrow (lock/release/refund) dans une transaction DB ; idempotence des webhooks FedaPay ; settlements appelés par le webhook **no-throw** (log + no-op sur statut inattendu, poison-safe).
NFR4: **Non-régression stricte** : missions cash standard, bookings UGC (hybride RH.2), tunnel/deliverables existants, suspension/refund — inchangés. Le trait partagé `MissionValidationRules` **n'est pas modifié** (le jumeau `UpdateMissionRequest` bloque déjà l'UGC, ugc-3-5).
NFR5: Migration `missions` (`date_tournage`/`lieu`/`duree` nullable) **sans backfill destructif** (les missions standard conservent leurs valeurs) ; l'édition d'une mission UGC reste **bloquée** (ugc-3-5).
NFR6: Notifications/emails additifs **non-fataux** (convention module : `try/catch` sans re-throw quand le dispatch est in-transaction).

### Additional Requirements

- **Réutiliser le tunnel existant** `Shipment`/`Deliverable` (owner morphTo `Booking|Candidature`) — déjà supporté côté `Candidature` (expédition → réception → Unboxing → Avis → validation).
- **Réutiliser `BookingPricing`** (RH.2) pour le calcul du split par-Face (cash + 10% frais ; Face − palier 15/10/5).
- **Calquer la relaxation nullable** sur `CreateBookingRequest:67-70` (booking UGC) + la migration `..._make_shoot_fields_nullable_for_ugc_bookings` (pattern up/down avec backfill placeholder).
- **Pas de starter template** : brownfield, extension du module UGC existant (Epic 1 Story 1 N/A).
- **Réutiliser** les chemins de suspension/refund (`UgcSuspensionService`, `UgcRefundService`) en y branchant le remboursement escrow Producteur côté `Candidature` (aujourd'hui Booking-only).

### UX Design Requirements

UX-DR1: Écran/section **Face — postuler à une mission UGC** (CTA postuler, états de candidature pending/acceptée/refusée, CTA **reconfirmer**).
UX-DR2: Écran/section **Producteur — gestion des candidatures UGC** (liste des candidatures `pending`, actions accepter/refuser ; pour l'hybride : overlay de paiement FedaPay à l'acceptation, calque `PaymentOverlay` existant).
UX-DR3: Cohérence du formulaire mission UGC après masquage (la Section « Dates & Lieu » ne montre plus que `date_limite_candidature` — restructurer/renommer si nécessaire).

### FR Coverage Map

| FR | Epic / Story | Note |
|---|---|---|
| FR1, FR2 | E1 / S1 | Masquage champs tournage + migration nullable |
| FR3 | E1 / S2-S3 | Face postule (apply) |
| FR4, FR5 | E1 / S2 | Producteur accepte/refuse ; retrait auto-acceptation |
| FR6, FR7 | E1 / S2 (back) + S3 (front) | Notifs accept/refus + reconfirm Face |
| FR8 | E1 / S2 | Bornage capacité `nombre_faces_voulu` |
| FR9 | E1 / S2 | Candidature `confirmed` → tunnel |
| FR10 | E1 / S2 | Produit-seul : acceptation gratuite, pas de wallet |
| FR11, FR12 | E1 / S4 | Hybride publié sans paiement + paiement à l'acceptation → escrow |
| FR13, FR14 | E1 / S4 | Release wallet Face + refunds décline/suspension/deadline |
| FR15 | E1 / S3 | Frontend Face (postuler/statut/reconfirmer) |
| FR16 | E1 / S3 (gestion) + S5 (overlay paiement) | Frontend Producteur |
| NFR1, NFR2 | E1 / S4 | Escrow `Candidature` + hook complétion Candidature-aware |
| NFR3, NFR4, NFR6 | E1 / S2, S4 | Atomicité, non-régression, notifs non-fatales |
| NFR5 | E1 / S1 | Migration sans backfill destructif |
| UX-DR1, UX-DR2, UX-DR3 | E1 / S1, S3, S5 | Écrans Face/Producteur + cohérence form |

## Epic List

### Epic 1: Mission UGC — flux candidature classique + hybride par-Face

Permettre aux missions UGC de fonctionner comme les missions classiques — formulaire épuré (sans champs de tournage), candidatures **explicites** (la Face postule, le Producteur accepte/refuse, la Face reconfirme), tunnel en aval — et prendre en charge le **règlement hybride par-Face** (escrow à l'acceptation, versement wallet à la complétion, remboursement Producteur sur échec). Les missions **produit-seul** sont fonctionnelles de bout en bout dès la story 3 ; l'hybride est complété par les stories 4-5.

**FRs couverts :** FR1→FR16 · **NFRs :** NFR1→NFR6 · **UX-DR :** UX-DR1→UX-DR3.

**Stories planifiées (5) :**

1. **Formulaire UGC épuré** — masquage `date_tournage`/`durée`/`lieu` (FE form + `StoreMissionRequest` relax + migration nullable + `MissionService` null). *FR1-2, NFR5, UX-DR3.* 🟢 minuscule (miroir booking UGC).
2. **Cycle candidature — backend** — action Producteur accepter/refuser, retrait de l'auto-acceptation (`UgcEngagementController::accept`), reconfirmation Face (2ᵉ oui), bornage capacité, notifs accept/refus. Produit-seul gratuit. *FR3-10, NFR3-4, NFR6.* 🟡 moyen.
3. **Cycle candidature — frontend** — Face : postuler/statut/reconfirmer ; Producteur : gérer les candidatures (accepter/refuser). *FR15, FR16 (gestion), UX-DR1-2.* 🟡 moyen. → **produit-seul de bout en bout après cette story.**
4. **Hybride — backend** — étendre `EscrowService` à owner `Candidature` + hook complétion `UgcDeliverableService:286` Candidature-aware ; mission hybride publiée sans paiement ; paiement FedaPay à l'acceptation → escrow `Locked` ; release → wallet Face à la complétion ; refunds décline/suspension/deadline. *FR11-14, NFR1-3.* 🔴 gros (réutilise `BookingPricing`/RH.2).
5. **Hybride — frontend** — overlay paiement FedaPay à l'acceptation. *FR16 (overlay), UX-DR2.* 🟢 petit (réutilise `PaymentOverlay`).

## Epic 1: Mission UGC — flux candidature classique + hybride par-Face

Permettre aux missions UGC de fonctionner comme les missions classiques (formulaire épuré, candidatures explicites Producteur-validées, tunnel en aval) et prendre en charge le règlement hybride par-Face (escrow à l'acceptation → versement wallet à la complétion → remboursement Producteur sur échec). Produit-seul fonctionnel de bout en bout dès la story 1.3 ; hybride complété par 1.4-1.5.

### Story 1.1: Formulaire mission UGC épuré (masquage des champs de tournage)

As un Producteur créant une mission UGC,
I want que le formulaire ne me demande pas de date de tournage, de durée ni de lieu (inutiles pour une dotation),
So that je crée une mission UGC sans saisir des champs hors-sujet.

**Acceptance Criteria:**

**Given** le formulaire de création de mission avec `type_mission = 'ugc'`
**When** il s'affiche
**Then** les champs `date_tournage`, `durée` (`duree_preset`/jours custom) et `lieu` sont masqués
**And** `date_limite_candidature` et tous les autres champs (titre, description, type, `nombre_faces_voulu`, genre, profil, dotation) restent affichés

**Given** une soumission de mission UGC depuis le formulaire
**When** le frontend construit le payload
**Then** il n'envoie pas `date_tournage`/`lieu`/`duree` (ou les envoie à `null`)

**Given** une requête `StoreMissionRequest` avec `type_mission='ugc'`
**When** elle est validée
**Then** `date_tournage`/`lieu`/`duree` sont optionnels (`nullable`) et la règle croisée `date_tournage after:date_limite_candidature` ne s'applique pas
**And** le trait partagé `MissionValidationRules` n'est PAS modifié (le jumeau `UpdateMissionRequest` continue de bloquer l'UGC, ugc-3-5)

**Given** `MissionService::createUgcMission`
**When** une mission UGC est créée
**Then** `date_tournage`/`lieu`/`duree` sont persistés `null`

**Given** la table `missions` (colonnes aujourd'hui NOT NULL)
**When** la migration est appliquée
**Then** `date_tournage`/`lieu`/`duree` deviennent nullables
**And** les missions standard existantes conservent leurs valeurs (pas de backfill destructif)

**Given** une mission standard (non-UGC)
**When** elle est créée/validée
**Then** ces 3 champs restent requis (non-régression)

### Story 1.2: Cycle de candidature UGC explicite — backend

As un Producteur d'une mission UGC,
I want accepter ou refuser explicitement chaque candidature (au lieu d'une acceptation automatique),
So that je choisis qui réalise ma mission, comme sur une mission classique.

**Acceptance Criteria:**

**Given** une mission UGC publiée
**When** une Face éligible (paywall abonnement) postule via `apply`
**Then** une candidature est créée en `pending` (flux standard réutilisé)

**Given** une candidature UGC `pending` sur une mission produit-seul
**When** le Producteur l'accepte
**Then** elle passe `accepted` sans aucun paiement
**And** la Face est notifiée (in-app + email)

**Given** une candidature UGC `pending`
**When** le Producteur la refuse
**Then** elle passe `rejected`
**And** la Face est notifiée (in-app + email)

**Given** une candidature UGC `accepted`
**When** la Face reconfirme sa participation (2ᵉ « oui »)
**Then** elle passe `confirmed` (prête pour l'expédition/tunnel)

**Given** l'auto-acceptation actuelle (`UgcEngagementController::accept` → candidature `confirmed` directe + auto-close à capacité)
**When** la refonte est livrée
**Then** ce chemin est retiré (plus d'acceptation implicite par la Face)

**Given** une mission UGC dont `nombre_faces_voulu` Faces sont déjà engagées (`accepted`/`confirmed`)
**When** le Producteur tente d'accepter une candidature supplémentaire
**Then** l'acceptation est refusée (capacité atteinte)
**And** la mission cesse d'accepter de nouvelles candidatures

**Given** un échec d'envoi de notification/email lors d'une transition
**When** il survient
**Then** il est non-fatal (try/catch sans re-throw quand in-transaction) et ne rollback pas la transition de candidature

**Given** les missions cash standard
**When** leurs candidatures sont gérées (sélection + paiement escrow)
**Then** leur flux est inchangé (non-régression)

### Story 1.3: Cycle de candidature UGC explicite — frontend

As une Face et un Producteur,
I want des écrans pour postuler/suivre/reconfirmer (Face) et gérer les candidatures (Producteur),
So that le cycle de candidature explicite est utilisable de bout en bout (produit-seul complet).

**Acceptance Criteria:**

**Given** une mission UGC publiée affichée à une Face éligible
**When** la Face clique « Postuler »
**Then** sa candidature est créée et l'UI reflète le statut `pending`

**Given** une Face ayant une candidature
**When** son statut change (acceptée/refusée)
**Then** l'UI affiche l'état correspondant

**Given** une candidature `accepted`
**When** la Face ouvre la mission
**Then** un CTA « Reconfirmer ma participation » est disponible et la fait passer `confirmed`

**Given** un Producteur sur la gestion des candidatures d'une mission UGC
**When** il consulte la liste
**Then** il voit les candidatures `pending` avec actions « Accepter » / « Refuser »

**Given** un Producteur sur une mission produit-seul
**When** il accepte/refuse une candidature
**Then** l'UI reflète la transition, sans étape de paiement

**Given** les écrans de candidature des missions cash standard
**When** cette story est livrée
**Then** ils sont inchangés (non-régression)

### Story 1.4: Règlement hybride par-Face — backend (escrow sur Candidature)

As un Producteur d'une mission UGC hybride,
I want payer le cash d'une Face à son acceptation et que la Face soit créditée à la complétion,
So that la Face hybride est rémunérée on-plateforme de façon sécurisée (escrow).

**Acceptance Criteria:**

**Given** `EscrowService` (aujourd'hui 100% typé `Booking`)
**When** la story est livrée
**Then** il gère un owner `Candidature` (lock/release/refund)
**And** le chemin `Booking` n'est pas régressé

**Given** une mission UGC hybride
**When** elle est créée
**Then** elle est publiée sans paiement au publish (pas de gate `pending_payment`)
**And** aucune commission n'est prélevée sur la valeur produit

**Given** une candidature `pending` sur une mission hybride
**When** le Producteur l'accepte
**Then** un checkout FedaPay est initié pour `cash + 10% frais de service` (via `BookingPricing`)
**And** à confirmation du paiement, l'escrow de la Candidature passe `Locked` et la candidature `accepted`

**Given** une Candidature hybride dont le tunnel atteint la complétion (Avis validé)
**When** la complétion est traitée
**Then** l'escrow est libéré → wallet Face = `cash − commission palier (15/10/5)`
**And** WeAct conserve `10% frais + palier`
**And** le hook de complétion `UgcDeliverableService` (jusqu'ici `$owner instanceof Booking` only) traite désormais l'owner `Candidature`

**Given** une Face qui décline après paiement, OU une suspension, OU une deadline ratée
**When** l'événement survient
**Then** l'escrow `Locked` de la Candidature est remboursé au Producteur (branché dans le chemin de décline, `UgcSuspensionService`, `UgcRefundService`)

**Given** toute opération escrow (lock/release/refund)
**When** elle s'exécute
**Then** elle est atomique (transaction DB), idempotente côté webhook, et no-throw sur statut inattendu

**Given** les bookings UGC hybrides (RH.2) et les missions produit-seul
**When** la story est livrée
**Then** leur règlement est inchangé (non-régression)

### Story 1.5: Règlement hybride par-Face — frontend (overlay de paiement)

As un Producteur acceptant une candidature sur une mission UGC hybride,
I want un overlay de paiement FedaPay au moment de l'acceptation,
So that je règle le cash de la Face acceptée et déclenche son engagement.

**Acceptance Criteria:**

**Given** un Producteur acceptant une candidature sur une mission hybride
**When** il clique « Accepter »
**Then** un overlay de paiement FedaPay s'affiche pour `cash + 10% frais de service` (calque `PaymentOverlay` existant)

**Given** le paiement réussi (webhook/poll confirmé)
**When** il se termine
**Then** la candidature passe `accepted` (escrow `Locked`) et l'UI reflète l'état

**Given** le paiement échoué ou annulé
**When** il se termine
**Then** la candidature reste `pending` et le Producteur peut réessayer

**Given** une mission produit-seul
**When** le Producteur accepte une candidature
**Then** aucun overlay de paiement n'apparaît (acceptation directe)
