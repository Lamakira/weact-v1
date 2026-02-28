---
stepsCompleted: [1, 2, 3, 4, 5, 6]
inputDocuments:
  - docs/weact-brief.md
  - _bmad-output/project-context.md
date: 2026-02-27
author: Lamakira
project: WEACT - Direct Booking & Payment
status: complete
---

# Product Brief: WEACT - Direct Booking & Payment

## Executive Summary

WEACT est une plateforme béninoise de mise en relation entre **Faces** (acteurs, influenceurs, mannequins, figurants) et **Producteurs** (agences, particuliers). Aujourd'hui, la seule façon pour un Producteur de travailler avec une Face passe par le **workflow Mission** : publier une mission publique, attendre les candidatures, examiner les profils, accepter, confirmer — un processus lourd quand le Producteur sait déjà exactement avec qui il veut travailler.

La fonctionnalité **Direct Booking & Payment** résout ce problème en permettant aux Producteurs de **réserver directement une Face** depuis son profil public, avec un système de **paiement sécurisé par escrow** et des commissions intégrées.

**Modèle économique :** Commission de 15% côté Face + 15% côté Producteur sur chaque réservation, avec paiement via **Mobile Money** (MTN, Moov, Celtiis) à travers **Fedapay**. L'argent est bloqué en escrow jusqu'à la confirmation de réalisation par les deux parties.

**Opportunité marché :** Aucun concurrent direct au Bénin ne propose un tel système. WEACT bénéficie d'un avantage de premier entrant sur ce marché naissant.

## Core Vision

### Le problème

Le workflow Mission actuel impose un processus en plusieurs étapes (publication → candidature → examen → acceptation → confirmation) qui est **disproportionné** pour les Producteurs qui ont déjà identifié la Face idéale. Un Producteur qui découvre une Face sur la plateforme et souhaite travailler avec elle immédiatement se retrouve contraint de créer une mission publique — une friction qui ralentit les connexions et peut décourager l'utilisation de la plateforme.

### La solution

Le **Booking Direct** offre un canal parallèle et complémentaire au workflow Mission :

1. Le Producteur **parcourt les profils** et trouve la Face idéale
2. Il **initie une réservation** directement (date, durée, type de contenu)
3. La Face **accepte ou refuse** la demande
4. Le Producteur **paie** via Mobile Money (montant + commissions)
5. La **messagerie se débloque** pour coordonner les détails
6. La mission se réalise, les **deux parties confirment**
7. La Face **reçoit le paiement** dans son wallet interne sous 24h

### Pourquoi maintenant

- **Liberté pour les Producteurs** : Ceux qui savent exactement ce qu'ils veulent peuvent agir immédiatement, sans passer par un processus de publication/candidature
- **Revenus accrus pour la plateforme** : Chaque booking génère une transaction immédiate avec double commission (30% total), là où le workflow Mission ne monétise pas encore
- **Sécurité par l'escrow** : Le paiement bloqué en séquestre protège les deux parties — la Face est assurée d'être payée, le Producteur est protégé si la Face ne se présente pas
- **Premier entrant au Bénin** : Aucun concurrent local ne propose de plateforme de réservation directe pour talents créatifs avec paiement intégré
- **Coexistence des deux flux** : Le Booking Direct et le workflow Mission fonctionnent indépendamment, offrant la flexibilité maximale aux utilisateurs

## Target Users

### Primary Users

#### 1. Le Producteur — "Celui qui sait ce qu'il veut"

**Profil :** Aussi bien une agence structurée (projets réguliers, équipe dédiée) qu'un particulier indépendant (projets ponctuels). Le point commun : il a un besoin précis et souvent urgent.

**Contexte :** Il a un projet — clip musical, publicité, shooting photo, court-métrage, ou tout autre type de production. Il sait quel profil il cherche, soit parce qu'il a parcouru les Faces sur WEACT, soit parce qu'il connaît déjà la Face d'une collaboration précédente.

**Problème actuel :** Le workflow Mission l'oblige à publier une offre publique et attendre que les bons profils postulent — sans garantie qu'ils le feront. Face à l'urgence, ce processus est un frein qui peut le pousser à chercher hors plateforme.

**Motivation pour le Booking Direct :** Agir immédiatement. Trouver la Face idéale, la réserver, payer, et coordonner les détails — le tout sans détour.

**Moment "aha!" :** Variable selon le Producteur — découvrir le bouton "Booker" sur un profil, recevoir une acceptation rapide de la Face, ou voir le chat se débloquer pour lancer la coordination.

#### 2. La Face — "Le talent prêt à saisir l'opportunité"

**Profil :** Acteur, influenceur, mannequin, figurant, créateur ou modèle photo. Aussi bien très actif sur la plateforme (profil complet, statut disponible) que plus passif — le Booking Direct peut réactiver des Faces qui attendent les opportunités plutôt que de postuler activement.

**Contexte :** Elle reçoit une demande de booking directe d'un Producteur, avec les détails de la mission (date, durée, rémunération).

**Critères de décision :** Le timing (est-elle disponible ?), la réputation du Producteur (notes, historique), et le gain potentiel — financier et en visibilité si la mission est réussie.

**Motivation :** Recevoir des opportunités sans effort de candidature. Être payée de manière sécurisée via l'escrow.

**Moment "aha!" :** La notification d'une demande de booking (quelqu'un veut travailler avec moi !) et le moment où le paiement tombe dans le wallet après mission accomplie.

### Secondary Users

#### 3. L'Admin WEACT — "Le garant de confiance"

**Rôle :** N'intervient pas dans le flux normal du booking direct. Son rôle se limite à la **gestion des litiges** — quand un différend survient entre un Producteur et une Face (contestation de qualité, non-présentation, désaccord sur la réalisation).

**Interaction :** Accède à un tableau de bord des litiges, examine les éléments (booking, paiement, échanges), et prend une décision (remboursement, validation, médiation).

### User Journey

**Flux Producteur :**
1. **Découverte** — Parcourt la liste des Faces ou retrouve un profil connu
2. **Décision** — Consulte le profil, les tarifs, la disponibilité, les notes
3. **Booking** — Clique "Booker", renseigne date/durée/type, envoie la demande
4. **Attente** — La Face examine et accepte (ou refuse)
5. **Paiement** — Paie via Mobile Money (montant + commissions) → escrow
6. **Coordination** — Chat débloqué, échange des détails logistiques
7. **Réalisation** — La mission se déroule
8. **Confirmation** — Confirme la bonne réalisation → paiement libéré à la Face
9. **Évaluation** — Note la Face

**Flux Face :**
1. **Notification** — Reçoit une demande de booking avec les détails
2. **Évaluation** — Consulte le profil et la réputation du Producteur, vérifie sa disponibilité
3. **Décision** — Accepte ou refuse la demande
4. **Coordination** — Chat débloqué après paiement du Producteur, échange des détails
5. **Réalisation** — Effectue la mission
6. **Confirmation** — Confirme la bonne réalisation
7. **Paiement** — Reçoit le montant dans son wallet interne sous 24h
8. **Évaluation** — Note le Producteur

## Success Metrics

### Métriques de succès utilisateur

**Producteur :**
- **Taux de récurrence** — Le Producteur revient booker régulièrement après sa première réservation (indicateur principal de valeur perçue)
- **Taux de complétion du flux** — Proportion de bookings initiés qui arrivent jusqu'à la confirmation finale
- **Satisfaction post-booking** — Notes attribuées aux Faces après mission

**Face :**
- **Nombre de bookings reçus** — Volume de demandes directes reçues (attractivité du profil)
- **Montant gagné via le wallet** — Revenus cumulés générés par le Booking Direct (valeur financière)
- **Taux d'acceptation** — Proportion de demandes acceptées vs refusées (adéquation offre/demande)

### Business Objectives

*Cibles chiffrées à définir par le porteur de projet.*

- **Volume de bookings réalisés** — Nombre total de bookings complétés (3 mois / 12 mois)
- **Volume de transactions** — Montant total transité via l'escrow (indicateur de traction financière)
- **Taux d'adoption** — Proportion de Producteurs actifs qui utilisent le Booking Direct vs le workflow Mission uniquement
- **Revenus commissions** — Total des commissions (15% Face + 15% Producteur) générées par les bookings

### Key Performance Indicators

| KPI | Description | Mesure |
|-----|-------------|--------|
| Taux de récurrence Producteur | Producteurs qui réalisent 2+ bookings | % de Producteurs ayant booké au moins 2 fois |
| Bookings reçus par Face | Volume moyen de demandes par Face active | Nombre moyen / Face / mois |
| Revenus wallet Face | Gains moyens par Face via Booking Direct | Montant moyen XOF / Face / mois |
| Taux d'acceptation Face | Demandes acceptées vs total reçues | % acceptation |
| Taux de complétion | Bookings complétés vs bookings payés | % complétion |
| Taux d'annulation | Bookings annulés (par Producteur ou Face) | % annulation |
| Temps de réponse Face | Délai entre demande et acceptation/refus | Durée moyenne |
| Taux de litiges | Litiges ouverts vs bookings complétés | % litiges |
| Volume transactionnel | Montant total transité via escrow | XOF / mois |
| Revenus commissions | Commissions collectées sur les bookings | XOF / mois |
| Taux d'adoption Booking Direct | Producteurs utilisant le Booking vs Mission uniquement | % du total Producteurs actifs |
| Notes moyennes post-booking | Satisfaction mutuelle après mission | Note moyenne /5 |

## MVP Scope

### Core Features

1. **Formulaire de booking** — Le Producteur initie une réservation depuis le profil public d'une Face (date, durée, type de contenu). Prix calculé automatiquement à partir des tarifs horaire/journalier de la Face (minimum 4h).
2. **Acceptation / Refus par la Face** — La Face reçoit la demande, consulte les détails et le profil du Producteur, puis accepte ou refuse. Raison obligatoire si refus après paiement.
3. **Paiement Mobile Money via Fedapay** — Après acceptation, le Producteur paie via MTN, Moov ou Celtiis. Le montant (tarif Face + commissions) est bloqué en escrow jusqu'à confirmation de réalisation.
4. **Commissions automatiques** — 15% prélevé sur la Face + 15% prélevé sur le Producteur, calculés et appliqués automatiquement à chaque transaction.
5. **Chat débloqué après paiement** — La messagerie entre Producteur et Face s'active uniquement après le paiement réussi, pour coordonner les détails logistiques.
6. **Double confirmation de réalisation** — Les deux parties confirment la bonne réalisation. Si le Producteur ne confirme pas dans les 72h, auto-complétion automatique.
7. **Wallet interne Face** — La Face reçoit le paiement (moins commission) dans son wallet interne sous 24h après confirmation. Retrait vers Mobile Money disponible.
8. **Notation mutuelle** — Après booking complété, le Producteur note la Face et la Face note le Producteur. Annulation par la Face entraîne -1 étoile sur sa note moyenne.
9. **Politique d'annulation** — Producteur annule avant acceptation → remboursement total. Producteur annule après acceptation → 15% retenus par WEACT. No-show Producteur → pas de remboursement.

### Out of Scope for MVP

- **Gestion des litiges** — Reportée en V2 (dashboard admin, médiation, remboursement sur litige)
- **Modification de date** — Le Producteur ne peut pas modifier la date après envoi de la demande (simplification MVP)
- **Notifications push / SMS** — Pas de notifications temps réel pour le MVP
- **Boost / mise en avant payante** — Pas de système de promotion payante des profils
- **Négociation de prix** — Le prix est fixe, basé sur les tarifs affichés de la Face
- **Booking de Face "Indisponible"** — Impossible de booker une Face dont le statut est "Indisponible"

### MVP Success Criteria

- Des bookings sont réalisés de bout en bout (demande → paiement → réalisation → confirmation → versement)
- Les Producteurs reviennent utiliser le Booking Direct après une première expérience
- Les transactions Mobile Money via Fedapay fonctionnent sans friction
- Le système d'escrow sécurise correctement les fonds jusqu'à confirmation
- Les deux flux (Booking Direct et workflow Mission) coexistent sans conflit

### Future Vision

- **Gestion des litiges** — Interface admin dédiée pour résoudre les différends (médiation, remboursement, déblocage escrow)
- **Modification de date** — Le Producteur peut proposer un changement de date après booking, soumis à validation de la Face
- **Notifications avancées** — Push web et SMS pour les événements critiques (demande reçue, paiement confirmé, mission imminente)
- **Calendrier de disponibilité** — Les Faces peuvent bloquer des créneaux pour éviter les demandes sur des dates indisponibles
- **Booking récurrent** — Re-booker une Face avec qui on a déjà travaillé en un clic (pré-remplissage des détails)
