---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 3
totalStories: 6
project_name: 'WEACT - Correctifs Post-Lancement Sprint 6'
user_name: 'Lamakira'
date: '2026-04-06'
---

# WEACT - Correctifs Post-Lancement Sprint 6 - Epic Breakdown

## Overview

Correctifs et améliorations remontés le 06/04/2026 (issues client 06-03-2026). Couvre : champ WhatsApp modifiable + complétion profil + hint dashboard, workflow de remboursement en cas d'absence de la Face, et visibilité des emails utilisateurs côté admin.

## Requirements Inventory

### Functional Requirements

FIX5-FR1: Le numéro WhatsApp doit être modifiable dans le profil Face (section informations personnelles)
FIX5-FR2: Le numéro WhatsApp doit être compté dans le pourcentage de complétion du profil Face
FIX5-FR3: Si le numéro WhatsApp n'est pas renseigné, un bandeau persistant doit apparaître en haut du dashboard Face expliquant l'utilité du numéro (contact pour bookings/missions)
FIX5-FR4: Le Producer doit pouvoir signaler l'absence d'une Face sur un booking confirmé/complété
FIX5-FR5: Lorsqu'une absence est signalée et confirmée, le Producer doit être remboursé (escrow refund)
FIX5-FR6: L'admin doit voir les emails des utilisateurs (Faces et Producers) dans les pages de détail admin

## Epic & Story Breakdown

---

### Epic FIX-9: Champ WhatsApp — Édition, complétion profil et hint dashboard

**Goal:** Rendre le numéro WhatsApp modifiable après inscription, l'intégrer dans le calcul de complétion du profil, et afficher un bandeau incitatif si le champ est vide.

**Priority:** Haute — le numéro WhatsApp est le canal de contact principal pour les bookings et missions.

**Contexte technique:**
- `whatsapp_number` est déjà dans `Face.$fillable` et collecté à l'inscription (`FaceRegistrationForm.vue`)
- Le champ n'apparaît nulle part dans le profil éditable (`ProfileEditPage.vue`, `PersonalInfoForm.vue`)
- Le profil completion compte actuellement 8 champs (`Face.php:319-354`) — `whatsapp_number` n'en fait pas partie
- Le système de bandeaux existe dans `FaceLayout.vue` (EmailVerificationBanner, TarifsMissingBanner)
- L'endpoint `PersonalInfoController` (GET/PUT `/face/personal-info`) gère déjà sexe, date_naissance, nationalite, pays, show_age

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-9.1 | Rendre le WhatsApp modifiable dans le profil + complétion | FIX5-FR1, FIX5-FR2 | Haute |
| FIX-9.2 | Bandeau hint WhatsApp sur le dashboard Face | FIX5-FR3 | Haute |

---

#### FIX-9.1: Rendre le WhatsApp modifiable dans le profil + complétion

**Description:** Ajouter le champ WhatsApp dans le formulaire PersonalInfoForm.vue pour permettre la modification après inscription, et l'intégrer dans le calcul de complétion du profil Face.

**Acceptance Criteria:**
- Le champ "Numéro WhatsApp" (type tel, icône Phone) apparaît dans la section informations personnelles du profil Face (`PersonalInfoForm.vue`)
- Le champ est pré-rempli avec la valeur existante si renseignée à l'inscription
- La mise à jour du numéro WhatsApp fonctionne via PUT `/face/personal-info`
- Le numéro WhatsApp est compté dans le pourcentage de complétion du profil (total passe de 8 à 9 champs)
- Si le WhatsApp n'est pas renseigné, il apparaît dans `profile_completion_missing` avec le label "Ajoutez votre numéro WhatsApp"
- La validation accepte un string nullable de max 30 caractères (cohérent avec l'inscription)

---

#### FIX-9.2: Bandeau hint WhatsApp sur le dashboard Face

**Description:** Afficher un bandeau persistant en haut du dashboard Face (comme les bandeaux email et tarifs existants) lorsque le numéro WhatsApp n'est pas renseigné, expliquant que ce numéro est utilisé uniquement pour contacter la Face lors de bookings/missions.

**Acceptance Criteria:**
- Un bandeau d'information (style info/bleu, pas alerte) apparaît en haut de toutes les pages Face (via `FaceLayout.vue`) quand `whatsapp_number` est vide/null
- Le message explique : "Votre numéro WhatsApp est utilisé uniquement pour vous contacter lorsque vous êtes retenu(e) pour une mission ou booké(e). Renseignez-le pour ne manquer aucune opportunité."
- Un bouton CTA redirige vers la page de profil (`/face/profile`)
- Le bandeau disparaît dès que le numéro WhatsApp est renseigné
- Le bandeau est affiché après les bandeaux existants (email vérification > tarifs > WhatsApp)
- L'ordre de priorité des bandeaux est respecté (les 3 peuvent être affichés simultanément)

---

### Epic FIX-10: Wallet Producer, Retrait & Signalement absence Face

**Goal:** Donner un wallet aux Producers (même système que les Faces), permettre les retraits manuels via admin, puis implémenter le signalement d'absence qui crédite le wallet Producer.

**Priority:** Haute — impact financier direct, actuellement aucun recours pour le Producer en cas d'absence.

**Contexte technique:**
- Le système wallet existe déjà pour les Faces : `WalletService`, `wallet_transactions` table, `balance` column sur User, page Portefeuille, demande de retrait → admin traite manuellement
- Les Producers n'ont PAS de wallet actuellement — il faut réutiliser le même système
- Le `BookingService` gère les transitions d'état via state machine
- `BookingCancellationReason` enum a 3 valeurs (schedule_conflict, price_disagreement, other) — pas de "no_show"
- La Face reçoit déjà un `rating_penalty` de +1.0 quand elle annule un booking
- FedaPay n'a pas le payout activé — tous les remboursements/retraits passent par le traitement manuel admin

#### Stories

| ID | Story | FRs | Priority | Dépendance |
|----|-------|-----|----------|------------|
| FIX-10.1 | Wallet Producer : solde, historique, page dashboard | FIX5-FR5 | Haute | — |
| FIX-10.2 | Retrait Producer : demande retrait → admin traite manuellement | FIX5-FR5 | Haute | FIX-10.1 |
| FIX-10.3 | Signalement absence Face + crédit wallet Producer + pénalité | FIX5-FR4, FIX5-FR5 | Haute | FIX-10.1 |

---

#### FIX-10.1: Wallet Producer — solde, historique, page dashboard

**Description:** Activer le système wallet pour les Producers en réutilisant l'infrastructure existante des Faces (WalletService, wallet_transactions, balance). Ajouter une page Portefeuille dans le dashboard Producer avec le solde et l'historique des transactions.

**Acceptance Criteria:**
- Le Producer dispose d'un solde wallet (`balance` column sur User, déjà existante)
- Le Producer peut consulter son solde et l'historique de ses transactions wallet dans une page dédiée
- La page Portefeuille est accessible depuis le sidebar Producer
- L'historique affiche le type de transaction, le montant, la date et le statut
- Le solde est initialisé à 0 pour les Producers existants

---

#### FIX-10.2: Retrait Producer — demande retrait via formulaire + traitement admin

**Description:** Permettre au Producer de demander un retrait de son wallet vers Mobile Money, avec le même workflow que les Faces : formulaire de demande → visible dans l'admin → admin traite manuellement → notification par email.

**Acceptance Criteria:**
- Le Producer peut soumettre une demande de retrait depuis sa page Portefeuille
- Le formulaire demande le montant et le numéro Mobile Money
- La demande apparaît dans le dashboard admin (même vue que les retraits Face)
- L'admin peut marquer la demande comme traitée
- Le Producer reçoit une notification email quand le retrait est traité
- Le solde wallet est débité au moment de la demande (pas au moment du traitement)

---

#### FIX-10.3: Signalement absence Face + crédit wallet Producer + pénalité

**Description:** Permettre au Producer de signaler l'absence d'une Face sur un booking payé dont la date de tournage est passée. Le signalement crédite 100% du montant dans le wallet Producer et applique une pénalité à la Face.

**Acceptance Criteria:**
- Le Producer peut signaler l'absence de la Face sur un booking en statut `paid` dont la `date_debut` est passée
- Le signalement se fait via un bouton "Signaler une absence" sur la page détail booking
- Le booking passe en statut `no_show` (nouveau statut à ajouter à `BookingStatus`)
- 100% de `montant_total_producteur` est crédité dans le wallet Producer (la plateforme ne retient rien — la Face est en tort)
- Une pénalité de rating (+1.0) est appliquée à la Face (même mécanisme que `cancelByFace`)
- Le Producer et la Face reçoivent une notification
- L'action est irréversible
- Après 72h sans signalement ni confirmation, le flow auto-complete existant s'applique normalement

---

### Epic FIX-11: Visibilité emails utilisateurs côté admin

**Goal:** Afficher les emails des Faces et Producers dans les pages de détail et liste du panel admin.

**Priority:** Moyenne — l'admin en a besoin pour contacter les utilisateurs.

**Contexte technique:**
- L'API retourne déjà l'email via `FaceResource` et `ProducerResource` (via `whenLoaded('user')`)
- Les contrôleurs admin chargent déjà la relation `user` (eager load)
- Les types TypeScript frontend déclarent déjà `email?: string`
- **Seul le template frontend manque** — les données sont disponibles, il suffit de les afficher
- `AdminFaceDetailPage.vue` et `AdminProducerDetailPage.vue` n'affichent pas `{{ face.email }}` / `{{ producer.email }}`

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-11.1 | Afficher les emails dans les pages admin | FIX5-FR6 | Moyenne |

---

#### FIX-11.1: Afficher les emails dans les pages admin Face et Producer

**Description:** Ajouter l'affichage de l'email utilisateur dans les pages de détail admin des Faces et des Producers. Les données sont déjà disponibles via l'API — seul l'ajout du rendu frontend est nécessaire.

**Acceptance Criteria:**
- L'email de la Face est affiché dans `AdminFaceDetailPage.vue` dans la section métadonnées ou informations personnelles
- L'email du Producer est affiché dans `AdminProducerDetailPage.vue` dans la section métadonnées
- L'email est affiché avec une icône Mail et est cliquable (lien `mailto:`)
- Si l'email n'est pas disponible (relation user non chargée), afficher "—"
- Aucun changement backend nécessaire — les données sont déjà retournées par l'API
