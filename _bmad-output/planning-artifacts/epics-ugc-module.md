---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
completedAt: '2026-06-05'
totalEpics: 6
totalStories: 23
project_name: 'WEACT - Module UGC (User Generated Content)'
user_name: 'Lamakira'
date: '2026-06-05'
inputDocuments:
  - path: "_bmad-output/planning-artifacts/architecture-ugc.md"
    type: "architecture"
    loaded: true
  - path: "docs/new-features-docs/ugc.docx"
    type: "spec"
    loaded: true
    notes: "Tient lieu de PRD"
  - path: "docs/design/Weact Ugc Design/design_handoff_ugc_module/README.md"
    type: "ux-design"
    loaded: true
    notes: "11 écrans × 2 variations A/B + primitives + tokens"
inputDocumentsExcluded:
  - "prd.md / ux-design-specification.md / architecture-booking.md (projet booking, hors périmètre UGC)"
---

# WEACT - Module UGC (User Generated Content) - Epic Breakdown

## Overview

Ce document décompose les exigences du module UGC (spec `ugc.docx` + design handoff + architecture `architecture-ugc.md`) en epics et stories implémentables. Périmètre : **Booking UGC + Mission UGC** ensemble. Modèle de données en **extension** de Booking/Mission + modèles partagés `Shipment`/`Deliverable`/`UgcSuspension`. Défaut design **variation A** (A/B tranché par story).

## Requirements Inventory

### Functional Requirements

- **FR1** — Booking UGC direct : le Producteur crée un booking de type contenu « UGC » avec un toggle de compensation (`produit seul` par défaut | `produit + argent`/hybride).
- **FR2** — Champs dotation dynamiques : nom du produit, valeur marchande (base commission), nombre de vidéos (produit seul → **figé à 2** « 1 Unboxing + 1 Avis » ; hybride → **éditable** + champ montant de la rémunération Face).
- **FR3** — Commission WeAct affichée et recalculée live = `max(2500, round(valeur_produit × 0.10))` FCFA ; **son paiement valide l'envoi** du booking.
- **FR4** — Mission UGC (appel à projets) : même bloc dotation ; **le paiement de la commission est obligatoire pour publier** la mission (divergence vs mission standard payée à la confirmation de sélection).
- **FR5** — Gating : l'accès aux opportunités UGC est réservé aux Faces **abonnées (Starter+)** ; les Faces gratuites sont poussées vers `/pricing` (paywall).
- **FR6** — Tunnel anti-arnaque 6 étapes : Paiement → Acceptation Face → Expédition + tracking → « Produit reçu » (**déclencheur des chronos**) → Livrable Unboxing (chrono 7j, validation Producteur) → Livrable Avis (chrono 14j, validation → clôture).
- **FR7** — Validation Producteur par livrable : Valider / Rejeter / Demander retouche (SLA 48h) ; valider l'Unboxing démarre le chrono Avis (14j) ; valider l'Avis clôture le booking (`completed`).
- **FR8** — Sanction : si la Face a cliqué « Produit reçu » mais n'uploade pas les vidéos validées dans les délais → **suspension automatique du compte + blocage de l'abonnement** + notification Producteur + remplacement proposé.

### NonFunctional Requirements

- **NFR1 (Sécurité/anti-fraude)** — tunnel de validation strict ; escrow : commission encaissée seulement après acceptation Face, **remboursée si refus** ; adresse KYC vérifiée pour l'expédition.
- **NFR2 (Paiement)** — FedaPay (MTN MoMo / Moov Money / carte), push USSD, webhooks **idempotents**, polling + reprise de paiement.
- **NFR3 (Temps autoritatif serveur)** — deadlines absolues et `progress` calculés côté serveur ; le front ne fait que rendre la couleur d'escalade.
- **NFR4 (Médias)** — upload + lecture vidéo (storage privé, ffmpeg miniature/durée, URLs signées).
- **NFR5 (Couplage abonnement)** — gating + suspension via `FaceEntitlementService`/`FaceSubscription` sans régresser les paliers existants.
- **NFR6 (Notifications)** — escalade de deadline côté Face + événements côté Producteur (in-app + Reverb temps réel), idempotentes par seuil.
- **NFR7 (A11y/Responsive/i18n)** — focus rings teal, `prefers-reduced-motion` ; Face mobile-first 360px, Producteur desktop ≥1024px ; UI en français accentué.
- **NFR8 (Idempotence)** — webhooks paiement ET transitions livrables/chronos rejouables sans effet de bord.

### Additional Requirements

(Exigences techniques issues de `architecture-ugc.md` — décisions D1–D10)

- **AR1 (D1 — Données)** — étendre `bookings` & `missions` (colonnes UGC nullables, **noms FR** : `type_compensation`, `nom_produit`, `valeur_produit`, `nombre_videos`, `montant_remuneration`, `commission_ugc`) + modèles partagés polymorphes `Shipment` & `Deliverable` (owner = Booking | Candidature) + table `ugc_suspensions`.
- **AR2 (D2 — Machine à états)** — enum `UgcTunnelStatus` porté par le Shipment (ne PAS polluer `BookingStatus`/`CandidatureStatus`) ; transitions gardées idempotentes.
- **AR3 (D3 — Chronos)** — `deadline_at` absolu par Deliverable ; commande planifiée `ugc:process-deadlines` (~15 min) idempotente (escalade par seuil + déclenchement suspension).
- **AR4 (D4 — Commission/paiement)** — `UgcCommissionService` (recalcul serveur) + débit immédiat FedaPay + **remboursement si non-acceptation** (réutilise `FedapayService` + webhook idempotent + `PaymentOverlay`). **[OI-2 : spike refund FedaPay]**.
- **AR5 (D5 — Suspension)** — suspension **douce** dédiée (table `ugc_suspensions`, **PAS** `User.is_active`) ; login préservé ; réactivation : terminer mission en retard jusqu'à J+30 OU appel (revue ~24h) OU admin.
- **AR6 (D6 — Publish-gate mission)** — mission UGC : `draft → pending_payment (commission) → published` ; mission standard inchangée.
- **AR7 (D7 — Médias)** — média polymorphe sur Deliverable, distinct de `FaceVideo` (portfolio).
- **AR8 (D8 — Événements/notifs)** — événements domaine (`ProductReceived`, `DeliverableValidated`…) → listeners → notifications + Reverb ; `php artisan event:clear` après ajout de listener.
- **AR9 (D9 — API)** — contrôleurs UGC dédiés dans les namespaces Producer/Face ; Policies (`ShipmentPolicy`, `DeliverablePolicy`) ; recalcul serveur des montants.
- **AR10 (D10 — Gating)** — capability `canAccessUgc` dans `FaceEntitlementService` (abonné Starter+ ; refusé si suspendu).
- **AR11 (Config)** — `config/ugc.php` : durées 7j/14j, taux 10 %, plancher 2500, seuils d'escalade, fenêtre d'appel J+30.
- **AR12 (Brownfield)** — pas de story d'init projet ; 1re story = migrations/schéma + enums.
- **AR-OI1 [✅ RÉSOLU 2026-06-05 — Option A]** — commission mission = **une seule au publish** sur la valeur produit (`max(2500, round(valeur*0.10))`). Forcé par la règle « paiement pour publier » : N Faces inconnu au paiement. Réserve future hors-MVP : frais par Face à la confirmation si abus.

### UX Design Requirements

(Du design handoff — chaque primitive et chaque écran est un item actionnable ; défaut variation A)

**Primitives partagées (à factoriser en premier) :**
- **UX-DR1** — `ChronoRing` : anneau de compte à rebours SVG, couleur interpolée teal→ambre→orange→rouge selon `progress`, transitions douces (pivot du parcours Face).
- **UX-DR2** — `ChronoBadge` : pill linéaire avec icône timer, tailles sm/lg, même logique de couleur.
- **UX-DR3** — `StatusPill` : pastille de statut booking (pending/paid/accepted/shipped/received/delivered/completed/overdue/suspended).
- **UX-DR4** — `CompensationToggle` : toggle segmenté produit seul ⇄ produit + argent (pilote l'affichage dynamique du formulaire).
- **UX-DR5** — `CommissionBreakdown` : récap valeur produit (+ rémunération si hybride) + commission `max(2500, round(valeur*0.10))` + total.
- **UX-DR6** — `BookingTimeline` : 6 étapes, variantes verticale (`BookingTimelineV`) et horizontale (`BookingTimelineH`), props `current`/`overdue`.
- **UX-DR7** — `ReassuranceCard` : « Comment WeAct protège votre envoi » (4 garanties anti-arnaque).
- **UX-DR8** — `PayTile` : tuile méthode de paiement (MTN / Moov / Carte FedaPay).
- **UX-DR9** — Chrome & primitives de base : réutiliser DashChrome (sidebar w-56), PhoneFrame/MobileTopBar/MobileTabBar, boutons (BtnPrimary/Outline/Ghost), `Field`/`TextInput` (focus ring teal), `StripePh` (placeholders → vrais médias).

**Écrans :**
- **UX-DR10** — Écran 1 : Formulaire Booking UGC dynamique (Producteur). Variations 1A single-colonne / 1B split + rail aperçu live.
- **UX-DR11** — Écran 2 : Création mission UGC appel à projets (Producteur). 2A empilé / 2B stepper 4 étapes + rail récap.
- **UX-DR12** — Écran 3 : Tunnel paiement commission (Producteur, étape 1/6). 3A modal + PayTile + succès REF / 3B checkout split + PIN USSD.
- **UX-DR13** — Écran 4 : Confirmation expédition + tracking (Producteur, étape 3/6). 4A panneau inline sous BookingTimelineH / 4B split + carte adresse KYC.
- **UX-DR14** — Écran 5 : Validation des livrables (Producteur, SLA 48h). 5A 2 panes liste/preview+checklist / 5B viewer immersif sombre.
- **UX-DR15** — Écran 6 : Découverte missions UGC + paywall (Face mobile). 6A liste + cartes verrouillées / 6B hero teaser + carte paywall + aperçu flouté.
- **UX-DR16** — Écran 7 : Détail mission UGC + acceptation (Face mobile). 7A scroll + CTA sticky / 7B full-bleed + bottom sheet + 2 mini ChronoRing à 0.
- **UX-DR17** — Écran 8 : Espace de suivi Face — produit reçu → chronos → upload (mobile). 8A BookingTimelineV + ChronoRing + dropzone / 8B hero countdown 140px.
- **UX-DR18** — Écran 9 : États de notification deadlines (Face mobile, escalade teal→orange→rouge). 9A inbox listée / 9B hero d'alerte critique.
- **UX-DR19** — Écran 10 : Page suspension / abonnement bloqué (Face mobile). 10A bandeau + blocs pourquoi/réactiver / 10B takeover sombre.
- **UX-DR20** — Écran 11 : Workflow système 6 étapes (vue interne/doc). 11A flux horizontal / 11B swim-lanes verticales. **Non bloquant MVP** (doc).
- **UX-DR21** — Tokens & fondations : couleurs sémantiques chrono (teal/ambre `#F59E0B`/orange `#EA580C`/rouge `#DC2626` + shipped/received/completed/suspension), typo Inter + mono, rayons/ombres, espacements ; a11y (`prefers-reduced-motion`, focus rings) ; responsive (Face 360px / Producteur ≥1024px). Repris **strictement** de `design-system.md`.

### FR Coverage Map

- **FR1** → Epic 1 — toggle compensation + type contenu UGC sur le booking.
- **FR2** → Epic 1 — champs dotation dynamiques (produit seul figé 2 / hybride éditable + rémunération).
- **FR3** → Epic 1 — commission calculée + paiement valide l'envoi du booking.
- **FR4** → Epic 1 — mission UGC + paiement obligatoire pour publier.
- **FR5** → Epic 2 — gating abonnement + paywall découverte missions.
- **FR6** → Epic 2 (acceptation) / Epic 3 (expédition + « produit reçu » → chronos) / Epic 4 (livrables Unboxing+Avis).
- **FR7** → Epic 4 — validation Producteur (valider/rejeter/retouche) + enchaînement chronos.
- **FR8** → Epic 5 — suspension auto + blocage abonnement + notif Producteur + remplacement.

## Epic List

### Epic 1: Configuration & paiement d'une dotation UGC (Producteur)
Le Producteur peut configurer une dotation UGC (produit seul ou hybride) en **booking direct** ou en **mission**, voir la commission calculée en live, et la payer — ce qui **envoie le booking** ou **publie la mission**. Inclut les fondations de schéma (colonnes UGC sur `bookings`/`missions`), `UgcCommissionService` (recalcul serveur), l'intégration paiement FedaPay et la divergence publish-gate mission. Autonome : un Producteur peut configurer et payer une dotation même avant que le tunnel aval n'existe.
**FRs covered:** FR1, FR2, FR3, FR4
**AR:** AR1 (colonnes booking+mission), AR4, AR6, AR9 (contrôleurs create), AR11, AR-OI1 (tâche), OI-2 (spike refund — tâche)
**UX-DR:** UX-DR4, UX-DR5, UX-DR7, UX-DR8, UX-DR9, UX-DR10, UX-DR11, UX-DR12, UX-DR21

### Epic 2: Découverte & acceptation UGC (Face) + gating
Une Face **abonnée (Starter+)** découvre les missions UGC (paywall + redirection `/pricing` pour les gratuites), consulte le détail (stats, brief, livrables à venir, producteur vérifié) et **accepte** un deal en s'engageant sur les délais. Autonome : ouvre la boucle bilatérale (le Producteur a publié en E1, la Face accepte).
**FRs covered:** FR5, FR6 (acceptation)
**AR:** AR10 (capability `canAccessUgc`), AR1 (engagement sur Candidature), AR9 (contrôleurs Face découverte/accept)
**UX-DR:** UX-DR3, UX-DR1/UX-DR2 (mini ChronoRing preview à 0), UX-DR15, UX-DR16

### Epic 3: Expédition, tracking & déclenchement des chronos
Après acceptation, le Producteur **confirme l'expédition** (transporteur + numéro de suivi visible par la Face) ; la Face clique « **Produit reçu** », ce qui **démarre les chronos**. Met en place le modèle `Shipment`, l'enum `UgcTunnelStatus`, la `BookingTimeline` et les fondations du moteur de deadlines.
**FRs covered:** FR6 (expédition + réception)
**AR:** AR1 (Shipment), AR2 (UgcTunnelStatus), AR3 (fondation deadlines), AR8 (events ShipmentConfirmed/ProductReceived)
**UX-DR:** UX-DR6, UX-DR13, UX-DR17 (entrée), UX-DR1

### Epic 4: Livrables vidéo — upload sous chrono & validation Producteur
La Face **uploade** la vidéo Unboxing puis la vidéo Avis sous chrono ; le Producteur **valide / rejette / demande retouche** (SLA 48h) ; valider l'Unboxing démarre le chrono Avis (14j), valider l'Avis **clôture** le booking. Met en place le modèle `Deliverable`, les médias (ffmpeg + URLs signées) et les notifications d'escalade de deadline.
**FRs covered:** FR6 (livrables), FR7
**AR:** AR1 (Deliverable), AR3 (escalade + `ugc:process-deadlines`), AR7 (médias), AR8 (events validation)
**UX-DR:** UX-DR14, UX-DR17 (upload), UX-DR18

### Epic 5: Anti-arnaque — suspension automatique & réactivation
Une Face qui dépasse un chrono sans upload validé est **suspendue automatiquement** (suspension douce, login préservé) et son **premium est gelé** ; elle voit la page de suspension et peut **terminer en retard (jusqu'à J+30)** ou **faire appel** ; le Producteur est notifié et un **remplacement** est proposé. Met en place la table `ugc_suspensions`, le déclencheur de suspension dans le job de deadlines et le gating de la suspension.
**FRs covered:** FR8
**AR:** AR5 (suspension douce), AR3 (déclencheur suspension), AR8 (event FaceUgcSuspended), AR10 (blocage si suspendu)
**UX-DR:** UX-DR19, UX-DR18 (état critique)

### Epic 6: Documentation workflow & vue système *(différable — non-MVP)*
Documenter le tunnel anti-arnaque en 6 étapes (vue interne / support / admin) reflétant les déclencheurs et la branche d'exception. Aucune FR ; livrable de documentation.
**FRs covered:** —
**UX-DR:** UX-DR20

---

## Epic 1: Configuration & paiement d'une dotation UGC (Producteur)

Le Producteur peut configurer une dotation UGC (produit seul ou hybride) en booking direct ou en mission, voir la commission calculée en live, et la payer — ce qui envoie le booking ou publie la mission.

### Story 1.1: Backend — Créer un booking UGC & calculer la commission

As a Producteur,
I want pouvoir créer un booking de type contenu « UGC » avec une compensation produit seul ou hybride et une valeur produit,
So that la commission WeAct soit calculée et le booking prêt à être payé.

**Acceptance Criteria:**

**Given** un Producteur authentifié et une Face cible
**When** il crée un booking avec `type_contenu='UGC'`, `type_compensation='product'`, un `nom_produit` et une `valeur_produit`
**Then** le serveur enregistre les colonnes UGC (migration sur `bookings`) et fixe `nombre_videos=2` (verrouillé)
**And** `commission_ugc = max(2500, round(valeur_produit*0.10))` est recalculée serveur (jamais lue du client) via `UgcCommissionService`

**Given** une compensation `hybrid`
**When** le Producteur fournit `nombre_videos` (éditable) et `montant_remuneration`
**Then** la validation accepte ces champs et la commission reste assise sur la `valeur_produit` uniquement
**And** une `valeur_produit` manquante ou ≤ 0 renvoie une erreur 422 mappée au champ

**Given** un enum `CompensationType` (product|hybrid, `label()` FR) et `config/ugc.php` (taux 10 %, plancher 2500, durées 7j/14j)
**When** la story est livrée
**Then** ces fondations existent et sont couvertes par des tests Feature.

### Story 1.2: Frontend — Formulaire Booking UGC dynamique

As a Producteur,
I want voir apparaître les champs dotation quand je choisis le type « UGC » dans le formulaire de booking,
So that je configure ma dotation et visualise la commission avant de payer.

**Acceptance Criteria:**

**Given** le `BookingFormSheet`
**When** je sélectionne le type de contenu « UGC »
**Then** les champs dotation apparaissent via `UgcBookingFields` + `CompensationToggle` (produit seul ⇄ produit + argent)
**And** « produit seul » verrouille le nombre de vidéos à 2 (mention « 1 Unboxing + 1 Avis »), « hybride » le rend éditable + affiche le champ rémunération

**Given** une valeur produit saisie
**When** elle change
**Then** `CommissionBreakdown` recalcule en live `max(2500, round(valeur*0.10))` (formaté `toLocaleString('fr-FR')`) et le CTA affiche « Payer la commission · X FCFA »
**And** le schéma Zod valide les champs ; design en variation **1A (safe)** par défaut.

### Story 1.3: Backend — Mission UGC & paiement obligatoire pour publier

As a Producteur,
I want créer une mission UGC (appel à projets) dont la publication exige le paiement de la commission,
So that seules les missions payées soient visibles des Faces.

**Acceptance Criteria:**

**Given** un Producteur et une mission `type_mission='ugc'`
**When** il soumet le bloc dotation (mêmes champs que le booking UGC)
**Then** la mission est créée en `draft` puis passe en `pending_payment` (et non `published`) tant que la commission n'est pas réglée
**And** la commission est recalculée serveur via `UgcCommissionService`

**Given** la divergence avec la mission standard
**When** une mission standard est publiée
**Then** son flux (publication gratuite, paiement à la sélection) reste inchangé

**Décision AR-OI1 (✅ tranchée 2026-06-05 — Option A) :** la commission mission est **unique au publish**, sur la valeur produit (`max(2500, round(valeur_produit*0.10))`) — N Faces inconnu au paiement. `UgcCommissionService` calcule donc une commission identique pour booking et mission (pas de × N). Réserve future hors-MVP : frais par Face à la confirmation si abus constaté.

### Story 1.4: Frontend — Création d'une mission UGC

As a Producteur,
I want un formulaire de création de mission UGC avec le bloc dotation et le récap de commission,
So that je publie une offre UGC ouverte après paiement.

**Acceptance Criteria:**

**Given** le formulaire de mission
**When** je choisis une offre UGC
**Then** le bloc dotation (`UgcDotationFields`) + `CommissionBreakdown` s'affichent et le CTA mène au paiement obligatoire
**And** les 2 livrables (Unboxing 7j / Avis 14j) sont décrits ; design en variation **2A (safe)** par défaut

**Given** une soumission invalide
**When** un champ dotation manque
**Then** l'erreur 422 est mappée sous le champ correspondant.

### Story 1.5: Paiement de la commission (FedaPay) & envoi/publication

As a Producteur,
I want payer la commission via MTN MoMo / Moov Money / carte,
So that mon booking soit envoyé ou ma mission publiée.

**Acceptance Criteria:**

**Given** un booking/mission UGC en attente de paiement
**When** je paie via le tunnel (réutilisant `FedapayService` + `PaymentOverlay` + push USSD + polling/reprise)
**Then** à réception du webhook `transaction.approved` (idempotent), le booking passe en envoyé / la mission en `published`
**And** un échec/annulation laisse le booking/mission non envoyé et permet de relancer le paiement

**Given** le design écran 3
**When** le tunnel s'affiche
**Then** il suit la variation **3A (safe)** par défaut (modal → PayTile → état succès avec référence)

**Tâche OI-2 (spike, à réaliser dans cette story) :** vérifier le support du remboursement par le SDK `fedapay/fedapay-php ^0.4.7` ; documenter la capacité (refund auto possible ou fallback ops + runbook). Conditionne la Story 2.5.

---

## Epic 2: Découverte & acceptation UGC (Face) + gating

Une Face abonnée découvre les missions UGC (paywall pour les gratuites), consulte le détail et accepte un deal en s'engageant sur les délais.

### Story 2.1: Backend — Gating UGC (capability `canAccessUgc`)

As a système,
I want exposer une capability `canAccessUgc` et filtrer l'accès aux opportunités UGC,
So that seules les Faces abonnées (Starter+) et non suspendues y accèdent.

**Acceptance Criteria:**

**Given** `FaceEntitlementService`
**When** on évalue une Face Découverte (gratuite)
**Then** `canAccessUgc=false` ; une Face Starter/Pro/Élite active → `true` ; une Face suspendue → `false`

**Given** l'endpoint de liste des missions UGC
**When** une Face non éligible appelle l'API
**Then** la réponse signale le paywall (sans fuiter les détails complets des missions)
**And** des tests couvrent chaque palier + l'état suspendu.

### Story 2.2: Frontend — Découverte des missions UGC + paywall

As a Face,
I want parcourir les missions UGC,
So that je trouve des opportunités, avec une incitation à m'abonner si je suis gratuite.

**Acceptance Criteria:**

**Given** une Face abonnée
**When** elle ouvre la découverte UGC (mobile)
**Then** elle voit la liste des missions avec `StatusPill` et filtres (design **6A** par défaut)

**Given** une Face gratuite
**When** elle ouvre la découverte ou tape une mission verrouillée
**Then** un bandeau/paywall l'invite à s'abonner et tout tap mène vers `/pricing`
**And** `StatusPill` (primitive) est factorisée dans cette story.

### Story 2.3: Détail d'une mission UGC

As a Face,
I want voir le détail d'une mission UGC,
So that je comprenne le produit, la rémunération, les livrables et l'engagement avant d'accepter.

**Acceptance Criteria:**

**Given** une mission UGC (accès autorisé)
**When** la Face ouvre le détail
**Then** elle voit stats produit/cash/vidéos, brief, liste des livrables (Unboxing 7j, Avis 14j), bloc producteur vérifié (design **7A** par défaut)
**And** 2 mini `ChronoRing` à 0 illustrent les chronos à venir (primitive `ChronoRing` factorisée ici)

**Given** une Face non éligible
**When** elle tente d'ouvrir le détail
**Then** elle est redirigée vers le paywall.

### Story 2.4: Acceptation d'un deal UGC

As a Face,
I want accepter un booking ou une mission UGC,
So that je m'engage sur les délais et déclenche l'envoi du produit par le Producteur.

**Acceptance Criteria:**

**Given** un booking UGC payé ou une candidature à une mission UGC publiée
**When** la Face accepte (CTA sticky « Accepter »)
**Then** le tunnel passe à `accepted` (la candidature mission passe `accepted/confirmed`) et le Producteur est notifié
**And** une Face non éligible/suspendue ne peut pas accepter

**Given** l'acceptation
**When** elle est confirmée
**Then** l'engagement sur les délais est explicitement mentionné à la Face (règles + chronos à venir).

### Story 2.5: Remboursement du Producteur si non-acceptation / refus

As a Producteur,
I want être remboursé si la Face n'accepte pas dans la fenêtre prévue ou refuse,
So that je ne paie pas pour une dotation qui n'a pas lieu.

**Acceptance Criteria:**

**Given** un booking/mission payé sans acceptation dans la fenêtre (config) ou refusé
**When** la fenêtre expire / le refus est enregistré
**Then** le remboursement est déclenché (refund auto FedaPay si supporté — cf. spike OI-2 ; sinon flux ops + runbook) et le tunnel passe `refunded`/`cancelled` (idempotent)
**And** le Producteur est notifié du remboursement.

---

## Epic 3: Expédition, tracking & déclenchement des chronos

Le Producteur confirme l'expédition + le suivi ; la Face clique « Produit reçu », ce qui démarre les chronos.

### Story 3.1: Backend — Modèle Shipment & confirmation d'expédition

As a Producteur,
I want confirmer l'expédition du produit avec transporteur et numéro de suivi,
So that la Face sache que le produit est en route.

**Acceptance Criteria:**

**Given** un deal `accepted`
**When** le Producteur confirme l'expédition (transporteur, `numero_suivi`)
**Then** un `Shipment` polymorphe (owner Booking|Candidature, migration `shipments`) est créé et le `UgcTunnelStatus` passe `shipped` ; l'event `ShipmentConfirmed` est émis
**And** un Producteur ne peut confirmer que ses propres deals (ShipmentPolicy)

**Given** l'enum `UgcTunnelStatus`
**When** la story est livrée
**Then** il porte le micro-tunnel (sans polluer `BookingStatus`/`CandidatureStatus`) et les transitions sont idempotentes.

### Story 3.2: Frontend — Confirmation d'expédition + tracking (Producteur)

As a Producteur,
I want un écran pour saisir le transporteur et le suivi,
So that je confirme l'envoi et vois où en est le tunnel.

**Acceptance Criteria:**

**Given** un deal accepté
**When** le Producteur ouvre l'écran d'expédition
**Then** il voit la `BookingTimelineH` (étape 3) et un panneau de saisie transporteur/suivi (design **4A** par défaut)
**And** un rappel indique « le chrono démarrera quand la Face cliquera Produit reçu »
**And** la primitive `BookingTimeline` (V/H) est factorisée ici.

### Story 3.3: Backend — « Produit reçu » & démarrage des chronos

As a Face,
I want signaler la réception du produit,
So that les chronos de livrables démarrent.

**Acceptance Criteria:**

**Given** un deal `shipped`
**When** la Face clique « Produit reçu »
**Then** `recu_le` est enregistré, `UgcTunnelStatus` passe `received`, et `deadline_at` du livrable Unboxing = `recu_le + 7j` (via `UgcDeadlineService`) ; l'event `ProductReceived` est émis
**And** l'action est idempotente (un second clic ne réinitialise pas le chrono).

### Story 3.4: Frontend — ChronoRing & carte de suivi Face

As a Face,
I want voir le compte à rebours et l'état du tunnel après réception,
So that je sache combien de temps il me reste pour uploader.

**Acceptance Criteria:**

**Given** un deal et ses timestamps serveur
**When** la Face ouvre son espace de suivi
**Then** le composable `useChrono` dérive `progress` et la couleur (teal→ambre→orange→rouge) ; `ChronoRing` + `BookingTimelineV` s'affichent (design **8A** par défaut)
**And** le bouton « Produit reçu » est présent avant réception ; le front ne calcule jamais le temps lui-même (timestamps serveur).

---

## Epic 4: Livrables vidéo — upload sous chrono & validation Producteur

La Face uploade Unboxing puis Avis sous chrono ; le Producteur valide / rejette / demande retouche ; enchaînement 7j→14j → clôture.

### Story 4.1: Backend — Modèle Deliverable & upload vidéo

As a Face,
I want uploader une vidéo de livrable,
So that le Producteur puisse la valider.

**Acceptance Criteria:**

**Given** un deal `received` (chrono Unboxing actif)
**When** la Face uploade la vidéo Unboxing
**Then** un `Deliverable` polymorphe (migration `deliverables`, `kind='unboxing'`) stocke la vidéo en storage privé (ffmpeg miniature + durée), `validation_status='in_review'`, et le tunnel passe `unboxing_in_review`
**And** l'upload hors délai/quota est rejeté avec un message FR ; le média est distinct de `FaceVideo` (portfolio).

### Story 4.2: Frontend — Upload du livrable sous chrono (Face)

As a Face,
I want une zone d'upload avec le chrono visible,
So that je dépose ma vidéo avant l'échéance.

**Acceptance Criteria:**

**Given** un chrono actif
**When** la Face ouvre l'écran de suivi
**Then** elle voit `ChronoRing` (jours/heures restants) + dropzone d'upload + rappel orange « dépassement = suspension automatique » (design **8A** par défaut)
**And** après upload, l'état passe « en attente de validation ».

### Story 4.3: Backend — Validation Producteur (valider/rejeter/retouche)

As a Producteur,
I want valider, rejeter ou demander une retouche sur chaque livrable,
So that je contrôle la conformité avant clôture.

**Acceptance Criteria:**

**Given** un livrable `in_review`
**When** le Producteur valide l'Unboxing
**Then** `validation_status='validated'`, le chrono Avis démarre (`deadline_at = now + 14j`), le livrable Avis devient attendu ; events émis
**When** le Producteur valide l'Avis
**Then** le tunnel passe `completed` (clôture)
**When** le Producteur rejette ou demande une retouche
**Then** le statut reflète l'action (re-upload attendu) et la transition est idempotente ; SLA 48h tracé.

### Story 4.4: Frontend — Validation des livrables (Producteur)

As a Producteur,
I want visionner et statuer sur les livrables,
So that je valide ou demande une correction.

**Acceptance Criteria:**

**Given** des livrables en attente
**When** le Producteur ouvre l'écran de validation
**Then** il voit la liste (avec `ChronoBadge`) + preview vidéo + checklist de conformité + zone note/retouche, et la barre d'action Valider/Rejeter/Retouche (design **5A** par défaut)
**And** valider l'Unboxing affiche la transition vers le livrable Avis.

### Story 4.5: Notifications d'escalade de deadline

As a Face,
I want être alertée quand une échéance approche,
So that je n'oublie pas d'uploader.

**Acceptance Criteria:**

**Given** la commande planifiée `ugc:process-deadlines` (~15 min)
**When** un chrono franchit un seuil d'escalade
**Then** une notification (in-app + Reverb) est envoyée, idempotente par `last_notified_threshold` (pas de spam)
**And** l'inbox de notifications affiche l'escalade visuelle (teal→orange→rouge) (design **9A** par défaut).

---

## Epic 5: Anti-arnaque — suspension automatique & réactivation

Dépassement de chrono → suspension douce + premium gelé ; page de suspension, terminer en retard / appel ; Producteur notifié + remplacement.

### Story 5.1: Backend — Suspension automatique sur dépassement

As a système,
I want suspendre automatiquement une Face qui dépasse un chrono sans upload validé,
So that les Producteurs soient protégés contre les arnaques.

**Acceptance Criteria:**

**Given** un livrable dont `deadline_at` est franchi sans upload validé
**When** `ugc:process-deadlines` s'exécute
**Then** une ligne `ugc_suspensions` est créée (cause, `suspended_at`), `canAccessUgc` devient `false` et le premium est gelé — **sans** toucher `User.is_active` (login préservé)
**And** l'event `FaceUgcSuspended` est émis, le Producteur est notifié et un remplacement est proposé (semi-manuel : ré-ouverture de slot / re-book) ; opération idempotente.

### Story 5.2: Frontend — Page de suspension / réactivation

As a Face suspendue,
I want comprendre pourquoi mon compte est suspendu et comment le réactiver,
So that je puisse régulariser ma situation.

**Acceptance Criteria:**

**Given** une Face suspendue qui se connecte
**When** elle accède à son espace
**Then** elle voit la page de suspension (design **10A** par défaut) : « pourquoi » (vidéo manquante + abonnement bloqué) et « comment réactiver » (terminer jusqu'à J+30, faire appel ~24h) + CTAs support/terminer
**And** le login reste fonctionnel (cohérent avec la suspension douce).

### Story 5.3: Backend — Réactivation (terminer en retard / appel)

As a Face suspendue,
I want réactiver mon compte en terminant la mission en retard ou en faisant appel,
So that je retrouve l'accès UGC et mon premium.

**Acceptance Criteria:**

**Given** une suspension active
**When** la Face termine la mission en retard avant J+30
**Then** la suspension est levée (`reactivated_at`), `canAccessUgc` redevient `true`, le premium est dégelé
**When** la Face fait appel
**Then** une revue manuelle/admin peut réactiver (statut d'appel tracé)
**And** au-delà de J+30 sans action, l'état reste suspendu (réactivation manuelle uniquement).

---

## Epic 6: Documentation workflow & vue système *(différable — non-MVP)*

### Story 6.1: Vue/diagramme du workflow 6 étapes

As a membre de l'équipe (support/admin),
I want une vue documentée du tunnel anti-arnaque en 6 étapes,
So that je comprenne les déclencheurs et la branche d'exception.

**Acceptance Criteria:**

**Given** le tunnel UGC
**When** on consulte la doc/vue interne
**Then** les 6 étapes (Paiement → Acceptation → Expédition → Réception → Unboxing → Avis) et la branche d'exception (dépassement → suspension + blocage abo + notif Producteur + remplacement) sont représentées (design écran 11)
**And** cette story est explicitement hors périmètre MVP (peut être traitée en doc hors-BMAD).
