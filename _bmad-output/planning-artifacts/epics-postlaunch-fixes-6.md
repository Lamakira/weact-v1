---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 4
totalStories: 7
project_name: 'WEACT - Correctifs Post-Lancement Sprint 8'
user_name: 'Lamakira'
date: '2026-04-10'
---

# WEACT - Correctifs Post-Lancement Sprint 8 - Epic Breakdown

## Overview

Correctifs et améliorations remontés le 10/04/2026. Couvre : formulaire de booking (mention trompeuse, champ lieu manquant, coûts d'annulation inversés), affichage des libellés dans l'historique transactions, navigation (hint WhatsApp + écran blanc mission Producer), et compteur missions profil public Producer.

## Requirements Inventory

### Functional Requirements

FIX6-FR1: La mention "Le montant final sera confirmé après acceptation de la Face" doit être retirée du formulaire de booking
FIX6-FR2: Le formulaire de booking doit inclure un champ "Lieu de tournage" (même select villes Bénin que les missions)
FIX6-FR3: Lors de l'annulation d'un booking par la Face, les coûts affichés doivent correspondre au point de vue de la Face, pas du Producer
FIX6-FR4: L'historique des transactions doit afficher "Mission : {titre}" sans l'ID (idem pour les bookings)
FIX6-FR5: Le clic sur le bandeau hint WhatsApp doit naviguer vers la page profil ET scroller/focus vers le champ WhatsApp
FIX6-FR6: La page `/producer/missions/:uuid` ne doit pas afficher un écran blanc
FIX6-FR7: Le profil public du Producer doit afficher le nombre correct de missions publiées

## Epic & Story Breakdown

---

### Epic FIX-15: Booking — Formulaire & Annulation

**Goal:** Corriger trois problèmes UX dans le flow de booking : mention trompeuse dans le formulaire, absence du champ lieu de tournage, et affichage des mauvais coûts d'annulation pour la Face.

**Priority:** Haute — impact direct sur l'expérience booking et la prise de décision de la Face.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-15.1 | Retirer la mention "montant final sera confirmé" du formulaire booking | FIX6-FR1 | Haute |
| FIX-15.2 | Ajouter le champ lieu de tournage au formulaire de booking | FIX6-FR2 | Haute |
| FIX-15.3 | Afficher les coûts d'annulation du point de vue de la Face | FIX6-FR3 | Haute |

---

#### FIX-15.1: Retirer la mention "montant final sera confirmé" du formulaire de booking

**Description:** Le formulaire de booking affiche actuellement la mention "Le montant final sera confirmé après acceptation de la Face", ce qui est trompeur. Cette mention doit être supprimée.

**Acceptance Criteria:**
- La mention "Le montant final sera confirmé après acceptation de la Face" n'apparaît plus dans le formulaire de booking (`BookingFormSheet.vue` ou composant équivalent)
- Le reste du formulaire de booking reste inchangé
- Aucun impact sur le calcul ou l'affichage des montants

---

#### FIX-15.2: Ajouter le champ lieu de tournage au formulaire de booking

**Description:** Le formulaire de booking ne permet pas au Producer de renseigner le lieu de tournage, alors que c'est une information capitale dans la décision de la Face d'accepter ou refuser le booking. Ajouter un champ select avec les villes du Bénin (même composant `BENIN_CITY_OPTIONS` utilisé pour les missions).

**Acceptance Criteria:**
- Un champ "Lieu de tournage" (select) apparaît dans le formulaire de booking
- Le select utilise la même liste de villes du Bénin (`BENIN_CITY_OPTIONS` de `shared/constants/beninCities.ts`)
- Le lieu est stocké dans la table `bookings` (migration si nécessaire pour ajouter la colonne `lieu`)
- Le lieu est affiché dans le détail du booking côté Face et côté Producer
- Le lieu est affiché dans la notification de booking envoyée à la Face
- La validation backend accepte une valeur nullable (le lieu peut ne pas être renseigné pour les bookings existants)

---

#### FIX-15.3: Afficher les coûts d'annulation du point de vue de la Face

**Description:** Quand la Face veut annuler un booking, le détail des coûts qui lui est affiché correspond au breakdown du Producer (celui qui a payé). La Face doit voir uniquement les coûts qui la concernent (montant qu'elle aurait reçu, pénalités éventuelles), pas la décomposition Producer (montant total + commission plateforme).

**Acceptance Criteria:**
- Lors de l'annulation par la Face, le détail des coûts affiché reflète le point de vue Face : montant qu'elle aurait perçu, pénalité éventuelle
- Le détail des coûts du Producer (montant payé, commission plateforme, etc.) n'est plus visible par la Face
- Le Producer continue de voir ses propres coûts lorsqu'il annule de son côté
- L'API retourne les bonnes informations selon le rôle de l'utilisateur connecté

---

### Epic FIX-16: Affichage transactions — Libellés

**Goal:** Corriger le format d'affichage des transactions dans l'historique wallet pour retirer l'ID de mission/booking inutile.

**Priority:** Moyenne — cosmétique mais améliore la lisibilité.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-16.1 | Formater les libellés transactions sans l'ID | FIX6-FR4 | Moyenne |

---

#### FIX-16.1: Formater les libellés transactions sans l'ID

**Description:** Dans l'historique des transactions de la Face (et du Producer), les libellés affichent "Mission #{id_mission} : {titre_de_la_mission}". L'ID n'apporte aucune valeur à l'utilisateur. Le format doit devenir "Mission : {titre_de_la_mission}". Même correction pour les bookings.

**Acceptance Criteria:**
- Les transactions liées aux missions affichent "Mission : {titre}" au lieu de "Mission #{id} : {titre}"
- Les transactions liées aux bookings affichent "Booking : {titre}" au lieu de "Booking #{id} : {titre}"
- La correction s'applique dans le wallet Face ET le wallet Producer
- Les transactions existantes sont affichées avec le nouveau format (le changement est au niveau de l'affichage, pas des données stockées)

---

### Epic FIX-17: Navigation & Routing

**Goal:** Corriger deux problèmes de navigation : le hint WhatsApp qui ne scroll pas vers le champ, et l'écran blanc sur la page mission Producer.

**Priority:** Haute — l'écran blanc est bloquant pour le Producer.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-17.1 | Hint WhatsApp — scroll vers le champ exact | FIX6-FR5 | Moyenne |
| FIX-17.2 | Corriger l'écran blanc `/producer/missions/:uuid` | FIX6-FR6 | Haute |

---

#### FIX-17.1: Hint WhatsApp — scroll vers le champ exact

**Description:** Le bandeau hint WhatsApp (implémenté dans FIX-9.2) redirige actuellement vers `/face/profile` sans cibler le champ WhatsApp. Le CTA doit naviguer vers la page profil ET scroller automatiquement vers le champ WhatsApp avec focus.

**Acceptance Criteria:**
- Le clic sur le CTA du bandeau WhatsApp navigue vers `/face/profile` avec un hash ou query param (ex: `/face/profile#whatsapp` ou `/face/profile?focus=whatsapp`)
- La page profil détecte le paramètre et scrolle automatiquement vers le champ WhatsApp
- Le champ WhatsApp reçoit le focus après le scroll
- Le scroll est smooth (animation fluide)
- Si le champ WhatsApp est dans une section repliable, la section est automatiquement dépliée

---

#### FIX-17.2: Corriger l'écran blanc `/producer/missions/:uuid`

**Description:** Quand un Producer accède à la page de détail d'une mission via URL directe (ex: `/producer/missions/dc11f7bf-4227-4d0a-bba1-3b588f996a40`), un écran blanc s'affiche au lieu du contenu de la mission. Le problème peut venir d'un crash silencieux dans le composant, d'une route mal configurée, ou d'une erreur de chargement des données.

**Acceptance Criteria:**
- La page `/producer/missions/:uuid` affiche correctement le détail de la mission
- L'accès par URL directe (navigation, refresh, lien partagé) fonctionne
- Si la mission n'existe pas ou n'appartient pas au Producer, une page 404 ou erreur explicite s'affiche (pas un écran blanc)
- Aucune erreur JavaScript dans la console lors du chargement normal

---

### Epic FIX-18: Profil public Producer — Compteur missions

**Goal:** Corriger la mise à jour du compteur de missions publiées sur le profil public du Producer.

**Priority:** Moyenne — donnée affichée incorrecte mais pas bloquant.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-18.1 | Mettre à jour le compteur missions publiées du profil public Producer | FIX6-FR7 | Moyenne |

---

#### FIX-18.1: Mettre à jour le compteur missions publiées du profil public Producer

**Description:** Le profil public du Producer affiche un compteur de missions publiées qui ne se met pas à jour correctement. Le compteur doit refléter en temps réel le nombre de missions publiées (statut `published`) du Producer.

**Acceptance Criteria:**
- Le profil public du Producer affiche le nombre correct de missions publiées
- Le compteur se met à jour quand le Producer publie une nouvelle mission
- Le compteur se met à jour quand une mission est fermée ou supprimée
- La valeur est calculée dynamiquement (count des missions `published`) et non cachée de manière obsolète
- L'API `PublicProducerProfileResource` (ou équivalent) retourne la bonne valeur
