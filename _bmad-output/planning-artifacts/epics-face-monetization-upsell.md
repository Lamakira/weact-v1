---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 1
totalStories: 6
project_name: 'WEACT - Face Monetization: Upsell Incentives & Tier-Aware Commission'
user_name: 'Lamakira'
date: '2026-05-30'
---

# WEACT - Incitations Upsell Face & Commission par Palier - Epic Breakdown

## Overview

Deux problèmes de monétisation coexistent aujourd'hui :

1. **La commission Face est hardcodée à 10 % et ne tient pas compte du palier.** `MissionPricing` (`backend/app/ValueObjects/MissionPricing.php:9`) et `BookingPricing` (`backend/app/ValueObjects/BookingPricing.php:9`) utilisent tous deux `private const COMMISSION_RATE = 0.10`. Pourtant le taux par palier existe déjà en config (`backend/config/face_subscription_tiers.php` : free `0.10`, starter `0.10`, pro `0.10`, elite `0.05`) et est exposé via `FaceEntitlementService::capabilities($face)->commissionRate` (`TierCapabilities.php:22`, alimenté en `FaceEntitlementService.php:177`). **Ce taux n'est jamais consommé par le calcul de paiement.** Résultat : une Face Élite et une Face Découverte subissent le même prélèvement de 10 %, ce qui annule l'argument commercial des paliers payants.

2. **Les Faces gratuites (et payantes non-Élite) ne sont pas incitées à monter en gamme.** Aucune surface produit ne rappelle le palier courant ni le bénéfice du palier supérieur (quota de médias, commission réduite). Le parcours post-inscription tombe directement sur le dashboard sans présenter l'offre.

Cet epic prolonge la série **Face Premium** (`FEATURE-FP-1`, `FEATURE-FP-2`) en alignant **le prélèvement réel sur la promesse commerciale des paliers** et en ajoutant les **incitations d'upsell** correspondantes.

## Requirements Inventory

### Functional Requirements

FP3-FR1: La commission prélevée sur les **gains d'une Face** dépend de **son** palier d'abonnement : Découverte **15 %**, Starter **10 %**, Pro **10 %**, Élite **5 %**.

FP3-FR2: La **commission producteur** (supplément payé par le producteur au-dessus du budget) **reste 10 %**, indépendante du palier des Faces.

FP3-FR3: Pour un **Booking direct**, `faceReceives = baseTarif − round(baseTarif × tauxCommissionDeLaFace)`, le taux étant résolu via `capabilities($face)->commissionRate` au moment de la facturation du booking.

FP3-FR4: Pour une **Mission multi-Faces**, la commission est calculée **par-Face** selon le palier de chaque Face sélectionnée ; `commission_faces_total` = somme des commissions par-Face ; le `montant_face_recoit` de chaque `MissionPaymentCandidature` est **individuel** (fin du split uniforme `montantParFace`).

FP3-FR5: Le dashboard Face affiche une carte **« Plan actuel »** (palier + statut) **sous le portefeuille**, avec CTA → `/face/billing` et lien secondaire **« Comparer les plans »** → `/pricing`.

FP3-FR6: Les libellés de quota du profil (album photos + 3 types de vidéos : présentation, acting, UGC) affichent un texte **dynamique** « Ajoutez jusqu'à {quota}… Passez au plan {palier supérieur} pour… » ; l'**Élite** (pas de palier supérieur) n'affiche que la phrase de quota, **sans incitation**.

FP3-FR7: Le tableau de comparaison du pricing affiche, pour **Découverte** : « Missions rémunérées » → **Oui**, « Commission plateforme » → **15 %**.

FP3-FR8: À la fin de l'inscription Face, l'utilisateur est dirigé vers une **page d'upsell dédiée** (au lieu d'une redirection directe vers le dashboard), de façon compatible avec l'étape de vérification d'email du flux `useAuth`.

### Non-Functional Requirements

FP3-NFR1: **Forward-only.** Aucun recalcul ni reversement sur les bookings/missions déjà payés ou déjà entrés en cycle de paiement (`status` ≥ Pending). Le nouveau taux ne s'applique qu'aux nouvelles facturations.

FP3-NFR2: **Source de vérité unique.** Le taux provient de `FaceEntitlementService::capabilities($face)->commissionRate` (lui-même alimenté par `face_subscription_tiers.php`). Interdiction d'introduire un second taux hardcodé.

FP3-NFR3: **Invariants monétaires entiers (XOF/FCFA).** Arrondis cohérents ; pour une mission : `Σ montant_face_recoit + commission_faces_total = sousTotal` ; pour un booking : `faceReceives + faceCommission = baseTarif`.

FP3-NFR4: **Tests « money » obligatoires** (discipline CLAUDE.md « Prove It ») : cas mono-palier ET missions à **paliers mixtes** (Faces de paliers différents dans la même sélection).

## Epic & Story Breakdown

---

### Epic FEATURE-FP-3: Face Monetization — Upsell Incentives & Tier-Aware Commission

**Goal:** Faire correspondre le prélèvement réel à la promesse des paliers (commission Face tier-aware) et installer les incitations d'upsell (dashboard, profil, pricing, post-inscription) qui rendent la montée en gamme désirable.

**Priority:** Haute — la commission uniforme casse l'argument de vente des paliers payants (Élite paie autant qu'un gratuit aujourd'hui), et les Faces gratuites ne sont jamais sollicitées pour upgrader.

**Contexte technique:**
- Calcul mission : `MissionPaymentService::?()` instancie `new MissionPricing($mission->budget, $selectedCandidatures->count())` (`MissionPaymentService.php:142`) puis, dans la boucle `foreach ($selectedCandidatures as $candidature)` (`:158`), persiste `'montant_face_recoit' => $pricing->montantParFace` (`:163`) — **uniforme**. Le payout lit ensuite `$entry->montant_face_recoit` (`creditDirect` en `:742` et `:844`).
- Calcul booking : `BookingService::?()` instancie `new BookingPricing($tarifBase)` (`BookingService.php:79`) et persiste `'montant_face_recoit' => $pricing->faceReceives` (`:93`).
- Taux par palier : `config/face_subscription_tiers.php` (free `0.10` → cible `0.15`, starter/pro `0.10`, elite déjà `0.05`) → `FaceEntitlementService.php:177` → `TierCapabilities::$commissionRate` (`:22`) → `capabilities($face)->commissionRate`.
- Frontend abonnement : `useSubscriptionStatus` (composable partagé, cache 60 s) expose palier + statut ; page facturation `/face/billing` (`FaceBillingPage.vue`) et pricing public `/pricing` (`frontend/src/views/PricingView.vue`) existent déjà.
- Profil : `Face::profile_completion_missing` produit les clés de quota ; quotas album/vidéos pilotés par les capabilities du palier.

#### Stories

| ID | Story | FRs | Priority | Dépendance |
|----|-------|-----|----------|------------|
| FP-3.1a | Commission par palier — config + BOOKING | FP3-FR1, FP3-FR2, FP3-FR3, FP3-NFR1, FP3-NFR2, FP3-NFR3, FP3-NFR4 | Haute | — |
| FP-3.1b | Commission par palier — MISSION (par-Face) | FP3-FR1, FP3-FR2, FP3-FR4, FP3-NFR1, FP3-NFR2, FP3-NFR3, FP3-NFR4 | Haute | FP-3.1a |
| FP-3.2 | Carte « Plan actuel » au dashboard Face | FP3-FR5 | Moyenne | — |
| FP-3.3 | Texte upsell dynamique sur les quotas du profil | FP3-FR6 | Moyenne | — |
| FP-3.4 | Corrections du tableau pricing (Découverte) | FP3-FR7 | Basse | — |
| FP-3.5 | Page d'upsell post-inscription Face | FP3-FR8 | Moyenne | — |

**Ordre d'exécution recommandé :** FP-3.1a → FP-3.1b → (FP-3.4 en parallèle, indépendant) → FP-3.2 → FP-3.3 → FP-3.5.

---

#### FP-3.1a: Commission par palier — config + BOOKING

**Description:** Aligner la config (`free` `0.10` → `0.15`) et faire consommer le taux du palier de la Face par la facturation **Booking**. La commission producteur reste 10 %. C'est la story « money » fondatrice : elle établit le pattern « résoudre le taux via `capabilities($face)->commissionRate` » que FP-3.1b réutilisera.

**Acceptance Criteria:**
- `config/face_subscription_tiers.php` : `free.capabilities.commission_rate` passe de `0.10` à **`0.15`** ; starter/pro inchangés à `0.10` ; elite inchangé à `0.05`.
- `BookingPricing` cesse d'utiliser la constante `COMMISSION_RATE` pour le **côté Face** : `faceCommission = round(baseTarif × tauxFace)` où `tauxFace` est injecté (et non plus `0.10`).
- La **commission producteur** (`producerCommission`) reste `round(baseTarif × 0.10)` — inchangée, indépendante du palier.
- `BookingService` (`:79`) résout le palier de la Face concernée via `FaceEntitlementService::capabilities($face)->commissionRate` et le passe à `BookingPricing` ; `'montant_face_recoit'` (`:93`) reflète le nouveau `faceReceives`.
- Invariant booking préservé : `faceReceives + faceCommission = baseTarif`.
- Forward-only : aucun booking existant (déjà persisté) n'est recalculé.
- **Tests money** : Découverte → 15 %, Starter/Pro → 10 %, Élite → 5 % ; vérifier `faceReceives`, `faceCommission`, `producerCommission` (toujours 10 %) et l'invariant somme.

**Décision de design (ambiguïté tranchée) :** `BookingPricing` reçoit le **taux Face** par paramètre constructeur (ex. `new BookingPricing($tarifBase, $faceCommissionRate)`), plutôt que de recevoir le model `Face` (garde le ValueObject pur, sans dépendance au service). Le `producerCommissionRate` reste la constante interne `0.10` (non paramétré tant que produit confirme qu'il reste fixe). Alternative non retenue : injecter `Face` dans le VO → couplage VO↔ORM, rejeté.

**Non-scope (blast-radius control) :**
- La MISSION est **hors scope ici** (traitée en FP-3.1b) — ne pas toucher `MissionPricing`/`MissionPaymentService` dans cette story.
- Pas de migration de données rétroactive (forward-only).
- Pas de changement de la commission producteur.

---

#### FP-3.1b: Commission par palier — MISSION (par-Face)

**Description:** Rendre la commission Face **par-Face** sur les missions multi-Faces. Aujourd'hui `MissionPricing` calcule un `montantParFace` **uniforme** (`MissionPricing.php:36-37`) et la boucle de sélection persiste ce montant identique pour toutes les Faces (`MissionPaymentService.php:163`). Dès que les Faces sélectionnées ont des paliers différents, ce split est faux. Il faut calculer la commission de chaque Face selon SON palier au moment de la sélection/facturation.

**Acceptance Criteria:**
- Dans la boucle `foreach ($selectedCandidatures as $candidature)` (`MissionPaymentService.php:158`), `montant_face_recoit` de chaque `MissionPaymentCandidature` (`:163`) = `budgetParFace − round(budgetParFace × tauxDeCetteFace)`, le taux étant résolu via `capabilities` sur la Face de `$candidature->face_id`.
- `commission_faces_total` (`MissionPayment` créé en `:153`) = **somme** des commissions par-Face (plus la valeur uniforme `pricing->commissionFacesTotal`).
- `montant_total_faces` (`:154`) = **somme** des `montant_face_recoit` par-Face.
- La commission producteur (`commission_producteur` `:151`, `montant_total_producteur` `:152`) reste basée sur 10 % — **inchangée**.
- Le payout (`creditDirect` `:742`/`:844`) crédite chaque Face de son `montant_face_recoit` individuel — il lit déjà `$entry->montant_face_recoit`, donc aucune modification du payout n'est requise une fois le stockage par-Face correct.
- Invariant mission préservé : `Σ montant_face_recoit + commission_faces_total = sousTotal`.
- Forward-only : aucun `MissionPayment` existant n'est recalculé.
- **Tests money — paliers mixtes obligatoires** : une mission avec p.ex. 1 Face Découverte (15 %) + 1 Face Pro (10 %) + 1 Face Élite (5 %) → vérifier les 3 crédits distincts, `commission_faces_total` = somme, et l'invariant.

**Décision de design (ambiguïté tranchée) :** la résolution du taux par-Face se fait **dans `MissionPaymentService` au sein de la boucle** (le service a déjà accès à `FaceEntitlementService`), et `MissionPricing` est conservé pour le **côté producteur + le sous-total** (qui restent globaux). `MissionPricing` n'a donc plus à porter `montantParFace`/`commissionFacesTotal` uniformes pour le côté Face — option : déprécier ces champs OU les laisser comme « estimation legacy » non utilisée par le stockage. **À trancher au ready-for-dev** après lecture des consommateurs de `montantParFace` (grep). Alternative non retenue : passer un tableau de taux à `MissionPricing` → alourdit le VO, rejeté au profit du calcul dans le service.

**Pré-requis à vérifier au ready-for-dev (NE PAS supposer) :**
- Eager-loading : confirmer que `$selectedCandidatures` (issu de `$candidatures->get(...)`, `MissionPaymentService.php:139-140`) donne accès à la Face + son abonnement actif sans N+1 ; sinon ajouter le `with([...])` adéquat.
- Lister tous les consommateurs de `MissionPricing::$montantParFace` / `$commissionFacesTotal` avant de les modifier/déprécier.

**Non-scope :**
- Le BOOKING (couvert en FP-3.1a).
- Pas de recalcul historique.
- Pas de changement producteur.

---

#### FP-3.2: Carte « Plan actuel » au dashboard Face

**Description:** Ajouter sous le portefeuille du dashboard Face une carte affichant le palier courant et son statut, avec un CTA vers la facturation et un lien vers la comparaison des plans.

**Acceptance Criteria:**
- Une carte « Plan actuel » s'affiche **sous la carte portefeuille** du dashboard Face.
- Elle lit le palier + statut via `useSubscriptionStatus` (réutilise le cache 60 s, pas de nouvel appel réseau dédié si déjà chargé).
- Affiche le libellé du palier (Découverte / Starter / Pro / Élite) et l'état (actif / annulé / en attente de paiement) de façon cohérente avec l'onglet Facturation.
- CTA principal → `/face/billing`.
- Lien secondaire « Comparer les plans » → `/pricing`.
- États gérés : chargement (skeleton/placeholder) et palier gratuit (Découverte) → la carte invite explicitement à découvrir les plans payants.
- Test composant : rendu pour un palier gratuit ET un palier payant, présence des deux liens.

**Non-scope :** pas de logique de paiement ici (déjà dans `/face/billing`) ; pas de modification de `useSubscriptionStatus`.

---

#### FP-3.3: Texte upsell dynamique sur les quotas du profil

**Description:** Rendre dynamiques les libellés de quota des médias du profil (album photos + vidéos présentation/acting/UGC) pour indiquer le quota du palier courant et inciter au palier supérieur. L'Élite n'a pas de palier supérieur → pas d'incitation.

**Acceptance Criteria:**
- Pour chaque section média (album, vidéo présentation, vidéo acting, vidéo UGC), le texte affiche « Ajoutez jusqu'à {quota} … » où {quota} vient des capabilities du palier courant.
- Pour un palier **non-Élite**, le texte ajoute « Passez au plan {palier supérieur} pour {bénéfice} » (quota supérieur).
- Pour l'**Élite**, seul le texte de quota s'affiche — **aucune** phrase d'incitation.
- Un helper centralisé résout « palier suivant + quota associé » (pas de duplication par section).
- Tests : un palier intermédiaire (incitation présente + bon palier suivant) et Élite (incitation absente).

**Pré-requis à vérifier au ready-for-dev :** la map des quotas par palier (album + 3 vidéos) et la structure exacte des capabilities exposées au frontend (via `useSubscriptionStatus` / API offers) — confirmer la forme avant de coder le helper.

**Non-scope :** pas de changement des quotas eux-mêmes (backend) ; uniquement le copy + l'incitation.

---

#### FP-3.4: Corrections du tableau pricing (Découverte)

**Description:** Corriger le tableau de comparaison du pricing pour Découverte et le mettre en cohérence avec la commission tier-aware introduite en FP-3.1a.

**Acceptance Criteria:**
- Dans `frontend/src/views/PricingView.vue`, la ligne « Missions rémunérées » pour **Découverte** affiche **Oui** (et non « Non »/absence).
- La ligne « Commission plateforme » pour **Découverte** affiche **15 %** (cohérent avec `free.commission_rate = 0.15`).
- Les autres paliers restent cohérents (Starter/Pro 10 %, Élite 5 %).
- `PricingView.spec` mis à jour pour asserter ces deux valeurs Découverte.

**Pré-requis à vérifier au ready-for-dev :** ouvrir `PricingView.vue` pour confirmer si la table est hardcodée ou alimentée par les offres dynamiques ; ajuster au bon endroit. Recompter les lignes du tableau avant d'éditer.

**Non-scope :** pas de refonte visuelle du pricing (déjà fait en FP-2.13) ; uniquement les valeurs Découverte + cohérence commission.

---

#### FP-3.5: Page d'upsell post-inscription Face

**Description:** Après l'inscription Face, présenter une page dédiée d'upsell au lieu de rediriger directement vers le dashboard, en respectant l'étape de vérification d'email.

**Acceptance Criteria:**
- À la fin de l'inscription Face, la navigation cible une **page d'upsell dédiée** (nouvelle route) plutôt que le dashboard.
- La page présente les paliers (réutilise les offres / la comparaison existante) avec un CTA « Choisir un plan » → flux de paiement, et une sortie claire « Continuer en Découverte » → dashboard.
- L'enchaînement avec la **vérification d'email** est respecté : la page d'upsell ne court-circuite pas l'étape de vérification gérée dans `useAuth` (la séquence email-verification → upsell → dashboard est cohérente).
- Pas de boucle de redirection ; un utilisateur déjà inscrit qui revient n'est pas repiégé sur l'upsell.
- Tests : redirection post-inscription pointe sur la page d'upsell ; le bouton « Continuer en Découverte » mène au dashboard.

**Pré-requis à vérifier au ready-for-dev (NE PAS supposer) :** lire `useAuth` pour la logique exacte de redirection post-inscription et l'ordre vis-à-vis de la vérification d'email avant de décider du point d'insertion.

**Non-scope :** pas de changement du parcours Producer ; pas de modification du backend d'inscription.

---

## Requirements Coverage Map

| FR | Stories |
|----|---------|
| FP3-FR1 | FP-3.1a, FP-3.1b |
| FP3-FR2 | FP-3.1a, FP-3.1b |
| FP3-FR3 | FP-3.1a |
| FP3-FR4 | FP-3.1b |
| FP3-FR5 | FP-3.2 |
| FP3-FR6 | FP-3.3 |
| FP3-FR7 | FP-3.4 |
| FP3-FR8 | FP-3.5 |
| FP3-NFR1 (forward-only) | FP-3.1a, FP-3.1b |
| FP3-NFR2 (source unique) | FP-3.1a, FP-3.1b |
| FP3-NFR3 (invariants) | FP-3.1a, FP-3.1b |
| FP3-NFR4 (tests money) | FP-3.1a, FP-3.1b |
