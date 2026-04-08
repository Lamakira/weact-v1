---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]
inputDocuments:
  - path: "_bmad-output/planning-artifacts/product-brief-WEACT-booking-2026-02-27.md"
    type: "product-brief"
    loaded: true
  - path: "_bmad-output/planning-artifacts/product-brief-WEACT-2026-01-07.md"
    type: "product-brief"
    loaded: true
  - path: "docs/planning-artifacts/prd.md"
    type: "prd"
    loaded: true
  - path: "docs/planning-artifacts/architecture.md"
    type: "architecture"
    loaded: true
  - path: "docs/weact-brief.md"
    type: "product-brief"
    loaded: true
  - path: "_bmad-output/project-context.md"
    type: "project-context"
    loaded: true
documentCounts:
  briefs: 3
  research: 0
  brainstorming: 0
  projectDocs: 3
workflowType: 'prd'
lastStep: 1
projectType: 'brownfield'
date: '2026-02-27'
project_name: 'WEACT - Direct Booking & Payment'
user_name: 'Lamakira'
---

# Product Requirements Document - WEACT - Direct Booking & Payment

**Author:** Lamakira
**Date:** 2026-02-27

## Executive Summary

WEACT est une plateforme web béninoise de mise en relation entre Faces (acteurs, influenceurs, mannequins, figurants, créateurs) et Producteurs (agences, particuliers). La plateforme dispose déjà d'un workflow Mission complet (publication → candidature → acceptation → confirmation → notation).

La fonctionnalité **Direct Booking & Payment** ajoute un canal parallèle permettant aux Producteurs de réserver directement une Face depuis son profil public, avec paiement sécurisé par escrow via Mobile Money (MTN, Moov, Celtiis) à travers Fedapay.

**Utilisateurs cibles :**
- **Producteurs** (agences et particuliers) — besoin de booker rapidement sans passer par la publication de mission
- **Faces** (acteurs, influenceurs, mannequins, etc.) — reçoivent des opportunités directes sans effort de candidature
- **Admin WEACT** — gestion des litiges (V2)

### What Makes This Special

1. **Premier entrant au Bénin** — Aucun concurrent local ne propose de plateforme de réservation directe pour talents créatifs avec paiement intégré
2. **Escrow sécurisé** — L'argent est bloqué jusqu'à confirmation mutuelle de réalisation, protégeant les deux parties
3. **Double commission transparente** — 15% Face + 15% Producteur, calculée automatiquement à chaque transaction
4. **Coexistence des flux** — Le Booking Direct et le workflow Mission fonctionnent indépendamment, offrant la flexibilité maximale
5. **Wallet interne** — Les Faces accumulent leurs revenus et retirent via Mobile Money selon leurs besoins

## Project Classification

| Attribut | Valeur |
|----------|--------|
| **Type technique** | Web App (extension SPA Vue 3 + API Laravel 12) |
| **Domaine** | Fintech (paiement, escrow, wallet, commissions) |
| **Complexité** | Haute (flux de paiement multi-parties, escrow, intégration Fedapay) |
| **Contexte** | Brownfield — extension de la plateforme WEACT existante |

**Stack technique existant :**
- **Frontend :** Vue 3 (Composition API) + TypeScript + Pinia + Tailwind CSS 4.1
- **Backend :** Laravel 12 + MySQL + Sanctum (API tokens)
- **Paiements :** Fedapay (MTN MoMo, Moov Money, Celtiis)

## Success Criteria

### User Success

**Producteur :**
- Complète un booking de bout en bout (demande → paiement → confirmation) lors de sa première utilisation
- Revient booker une 2ème Face dans les 30 jours suivant son premier booking
- Le flux de booking prend moins de 5 minutes (du clic "Booker" au paiement)
- Reçoit une réponse (acceptation/refus) de la Face dans un délai raisonnable

**Face :**
- Reçoit sa première demande de booking après avoir complété son profil et affiché ses tarifs
- Le paiement arrive dans le wallet interne sous 24h après confirmation mutuelle
- Le retrait vers Mobile Money se fait sans friction
- Accepte des bookings régulièrement (indicateur d'adéquation offre/demande)

### Business Success

*Cibles chiffrées à définir par le porteur de projet.*

- **Volume de bookings complétés** — nombre total par mois (3 mois / 12 mois)
- **Volume transactionnel** — montant XOF transité via escrow par mois
- **Taux d'adoption** — % de Producteurs actifs utilisant le Booking Direct
- **Revenus commissions** — total XOF des commissions (15% Face + 15% Producteur) par mois
- **Taux de récurrence** — % de Producteurs qui réalisent 2+ bookings

### Technical Success

- **Paiement Fedapay** — taux de succès des transactions > 95%
- **Escrow** — zéro perte de fonds, intégrité garantie à chaque étape du flux
- **Auto-complétion 72h** — le job planifié se déclenche correctement et libère les fonds
- **Performance** — temps de chargement < 2s sur mobile 4G, temps de réponse API < 300ms
- **Coexistence** — aucune régression sur le workflow Mission existant
- **Sécurité** — validation CSRF, rate limiting, protection des données financières

### Measurable Outcomes

**Validation du concept :**
- ✅ Des bookings sont réalisés de bout en bout sans intervention manuelle
- ✅ Les Producteurs reviennent utiliser le Booking Direct
- ✅ Les transactions Mobile Money via Fedapay fonctionnent sans friction
- ✅ L'escrow sécurise correctement les fonds jusqu'à confirmation
- ✅ Les deux flux (Booking Direct + Mission) coexistent sans conflit
- ✅ Les Faces retirent leurs gains via Mobile Money avec succès

## Product Scope

### MVP - Minimum Viable Product

1. **Formulaire de booking** — Producteur réserve depuis le profil Face (date, durée, type). Prix calculé automatiquement (tarif horaire/journalier, minimum 4h)
2. **Acceptation / Refus Face** — Notification + décision. Raison obligatoire si refus après paiement
3. **Paiement Mobile Money via Fedapay** — MTN, Moov, Celtiis. Montant bloqué en escrow
4. **Commissions automatiques** — 15% Face + 15% Producteur calculés à chaque transaction
5. **Chat débloqué après paiement** — Messagerie activée uniquement après paiement réussi
6. **Double confirmation** — Les deux parties confirment. Auto-complétion 72h si Producteur ne confirme pas
7. **Wallet interne Face** — Réception paiement sous 24h après confirmation. Retrait Mobile Money
8. **Notation mutuelle** — Post-booking. Annulation Face → -1 étoile sur note moyenne
9. **Politique d'annulation** — Avant acceptation → remboursement total. Après acceptation → 15% WEACT. No-show → pas de remboursement

### Growth Features (Post-MVP)

- **Gestion des litiges** — Dashboard admin, médiation, remboursement sur litige
- **Modification de date** — Producteur propose un changement, Face valide
- **Notifications avancées** — Push web + SMS pour événements critiques

### Vision (Future)

- **Calendrier de disponibilité** — Faces bloquent des créneaux pour éviter les demandes sur dates indisponibles
- **Booking récurrent** — Re-booker une Face en un clic (pré-remplissage)
- **Analytics avancés** — Tableaux de bord dédiés au Booking Direct (revenus, tendances, top Faces)

## User Journeys

### Journey 1: Kofi Adjovi — Le booking express (Producteur - Happy Path)

Kofi dirige une petite agence de production à Cotonou. Un client vient de le contacter en urgence : il faut tourner une publicité pour une marque de boisson locale dans 5 jours. Kofi sait exactement quel profil il cherche — une jeune femme énergique, style influenceuse, avec de l'expérience devant la caméra.

Il ouvre WEACT et parcourt la liste des Faces. En filtrant par catégorie "influenceuse" et ville "Cotonou", il repère Aïcha — profil complet, vidéo d'acting convaincante, note de 4.7/5 sur 12 missions, statut "Disponible", tarif journalier de 50 000 XOF. C'est exactement ce qu'il cherche.

Il clique sur "Booker", remplit le formulaire en 2 minutes : date du tournage dans 5 jours, durée d'une journée, type "publicité". Le système calcule automatiquement le montant : 50 000 XOF + 7 500 XOF de commission Producteur = 57 500 XOF total. Il envoie la demande.

Le lendemain matin, notification : Aïcha a accepté. Kofi lance le paiement via MTN MoMo — 57 500 XOF débités, fonds bloqués en escrow. Immédiatement, le chat se débloque. Il envoie l'adresse du lieu de tournage, les horaires et le brief créatif. Aïcha confirme qu'elle sera là à 8h.

Le jour J, le tournage se passe parfaitement. Le soir même, Kofi ouvre WEACT et confirme la bonne réalisation. 24 heures plus tard, Aïcha reçoit 42 500 XOF dans son wallet (50 000 - 7 500 de commission Face). Kofi note Aïcha 5 étoiles. Aïcha note Kofi 5 étoiles.

Deux semaines plus tard, le même client rappelle Kofi pour un nouveau tournage. Cette fois, Kofi n'hésite pas — il retourne directement sur le profil d'Aïcha et la rebooke en moins d'une minute.

**Capabilities révélées :**
- Parcours et filtrage des Faces depuis la liste publique
- Bouton "Booker" sur le profil Face (visible uniquement pour Producteurs connectés)
- Formulaire de booking (date, durée, type de contenu)
- Calcul automatique du montant (tarif Face + commission Producteur)
- Notification d'acceptation/refus à la Face
- Paiement Mobile Money (MTN MoMo) via Fedapay avec escrow
- Déblocage automatique du chat après paiement
- Confirmation de réalisation par le Producteur
- Libération des fonds escrow → wallet Face (moins commission)
- Notation mutuelle post-booking
- Rebooking rapide d'une Face connue

### Journey 2: Aïcha Hounkpatin — L'opportunité qui tombe du ciel (Face - Happy Path)

Aïcha est influenceuse et actrice à Cotonou. Elle a un profil complet sur WEACT — 4 photos, vidéo de présentation, vidéo d'acting, bio détaillée, tarifs affichés (25 000 XOF/heure, 50 000 XOF/jour). Elle a fait 12 missions via le workflow classique et a une note de 4.7/5. Mais ces derniers temps, les missions publiées ne correspondent pas à son profil, et elle se retrouve à attendre.

Un mardi après-midi, elle reçoit une notification : "Kofi Adjovi (Studio 229) souhaite vous booker pour une publicité le 15 mars". Elle ouvre la demande et voit les détails : tournage d'une journée, publicité pour une marque de boisson, 50 000 XOF. Elle consulte le profil de Kofi — agence notée 4.8/5, 25 missions publiées, des avis positifs des autres Faces.

Elle vérifie son calendrier — le 15 mars est libre. Elle accepte la demande. Quelques heures plus tard, le paiement de Kofi est confirmé et le chat s'ouvre. Kofi lui envoie l'adresse, les horaires et le brief. Elle se prépare.

Le tournage se passe bien. Le soir, elle voit que Kofi a confirmé la réalisation. Le lendemain, 42 500 XOF apparaissent dans son wallet WEACT (50 000 - 7 500 de commission). Elle décide d'accumuler encore un peu avant de retirer. Après 3 bookings directs en un mois, son wallet affiche 120 000 XOF. Elle lance un retrait vers son compte Moov Money — les fonds arrivent en quelques minutes.

Le Booking Direct a changé sa dynamique sur WEACT : elle n'attend plus que les missions correspondent à son profil, les Producteurs viennent directement la chercher.

**Capabilities révélées :**
- Réception de notification de demande de booking
- Consultation des détails du booking (date, durée, type, montant)
- Consultation du profil et de la réputation du Producteur demandeur
- Acceptation ou refus de la demande
- Chat débloqué après paiement du Producteur
- Confirmation de réalisation par la Face
- Réception du paiement dans le wallet interne (montant - commission)
- Consultation du solde wallet
- Retrait vers Mobile Money (Moov Money)
- Historique des bookings directs

### Journey 3: Éric Hounkanrin — L'annulation de dernière minute (Producteur - Edge Case)

Éric est réalisateur freelance. Il a booké Sébastien, un acteur noté 4.5/5, pour un clip musical prévu samedi — tarif journalier de 40 000 XOF. Sébastien a accepté, Éric a payé 46 000 XOF (40 000 + 6 000 de commission), le chat est débloqué, tout est calé.

Jeudi soir, le musicien annule le clip. Éric n'a plus besoin de Sébastien. Il va dans "Mes bookings", trouve la réservation et clique "Annuler". Le système lui affiche : "Annulation après acceptation — 15% du montant (6 900 XOF) seront retenus par WEACT. Vous serez remboursé 39 100 XOF. Confirmer ?"

Éric confirme. Le remboursement de 39 100 XOF est initié vers son compte MTN MoMo. WEACT conserve 6 900 XOF. Sébastien reçoit une notification : "Le booking d'Éric Hounkanrin a été annulé." Il n'est pas pénalisé — c'est le Producteur qui a annulé, pas lui.

Trois semaines plus tard, le musicien relance le projet. Éric rebooke Sébastien — cette fois, tout se passe jusqu'au bout.

**Capabilities révélées :**
- Annulation d'un booking par le Producteur après acceptation
- Calcul et affichage des frais d'annulation (15% WEACT)
- Confirmation d'annulation avec récapitulatif financier
- Remboursement partiel vers Mobile Money du Producteur
- Rétention de la commission WEACT sur annulation
- Notification d'annulation à la Face (sans pénalité)
- Distinction claire : annulation Producteur ≠ annulation Face (pas de pénalité pour la Face)
- Historique des bookings annulés

### Journey Requirements Summary

| Parcours | Capabilities clés |
|----------|-------------------|
| **Producteur - Happy Path** | Filtrage Faces, formulaire booking, calcul prix, paiement Fedapay, escrow, chat, confirmation, notation, rebooking |
| **Face - Happy Path** | Notification booking, consultation détails/profil Producteur, acceptation/refus, chat, confirmation, wallet, retrait Mobile Money |
| **Producteur - Annulation** | Annulation post-acceptation, calcul frais 15%, remboursement partiel, notification Face, historique |

## Domain-Specific Requirements

### Fintech — Conformité & Vue d'ensemble réglementaire

WEACT opère dans le domaine fintech au Bénin (zone UEMOA) avec un modèle d'escrow et de wallet interne. La conformité repose principalement sur Fedapay, l'agrégateur de paiement Mobile Money, qui détient les agréments nécessaires pour le traitement des transactions.

### Key Domain Concerns

#### 1. Regional Compliance (Bénin / UEMOA)

- **Fedapay comme tiers de confiance** — Fedapay gère la conformité réglementaire côté paiement Mobile Money (MTN, Moov, Celtiis). WEACT n'a pas besoin d'un agrément BCEAO propre.
- **Wallet interne = solde applicatif** — Le wallet Face est un solde comptable dans la base WEACT, pas de la monnaie électronique au sens réglementaire. Les fonds réels transitent via Fedapay.
- **Plafonds de transaction** — Les plafonds existants des opérateurs Mobile Money et de Fedapay s'appliquent. Pas de plafond custom côté WEACT pour le MVP.

#### 2. Security Standards

- **Sécurité applicative standard** — HTTPS, CSRF protection, rate limiting, Sanctum API tokens
- **Aucune donnée bancaire stockée** — WEACT ne stocke ni numéros de carte, ni identifiants de compte Mobile Money
- **Pas d'audit de sécurité externe** requis pour le MVP

#### 3. Audit Requirements

- **Traçabilité complète** — Historique non-modifiable de toutes les transactions (escrow, commissions, remboursements, retraits) conservé en base de données
- **Pas de reporting externe** — Aucune obligation de reporting fiscal ou anti-blanchiment pour le MVP
- **Logs applicatifs** — Journalisation des événements financiers critiques (paiement, libération escrow, retrait)

#### 4. Fraud Prevention

- **Pas de KYC formel** pour le MVP — les utilisateurs sont identifiés par leur compte WEACT
- **Plafonds Fedapay/Mobile Money** — les limites de transaction des opérateurs servent de garde-fou naturel
- **Détection de fraude avancée** — reportée post-MVP (analyse comportementale, alertes, blocage automatique)

#### 5. Data Protection

- **Séparation des responsabilités** — Fedapay gère les données de paiement sensibles. WEACT stocke uniquement : montants, statuts, références de transaction, timestamps
- **Pas de données bancaires en base** — aucun numéro de compte, token de paiement ou credential financier stocké côté WEACT
- **Conservation des données** — historique transactionnel conservé indéfiniment pour traçabilité

### Implementation Considerations

- **Dépendance Fedapay** — La fiabilité du système de paiement dépend entièrement de Fedapay. Prévoir une gestion robuste des erreurs et timeouts Fedapay
- **Idempotence des transactions** — Chaque opération financière (paiement, remboursement, retrait) doit être idempotente pour éviter les doublons en cas de retry
- **Réconciliation** — Mécanisme de réconciliation entre le solde wallet WEACT et les transactions Fedapay pour détecter toute incohérence
- **Évolutivité réglementaire** — L'architecture doit permettre l'ajout futur de KYC, reporting et conformité avancée sans refonte majeure

## Innovation & Novel Patterns

### Detected Innovation Areas

L'innovation de WEACT Direct Booking réside dans la **combinaison inédite** de trois éléments dans le marché béninois :

1. **Booking direct de talents créatifs** — Aucune plateforme locale ne permet de réserver un acteur, influenceur ou mannequin en ligne avec un flux structuré
2. **Escrow sécurisé via Mobile Money** — Le paiement bloqué en séquestre, mécanisme courant en e-commerce international, est appliqué pour la première fois au marché des talents créatifs en Afrique de l'Ouest
3. **Double commission transparente** — Modèle économique clair (15% + 15%) intégré au flux, là où le marché local fonctionne encore en négociation informelle et paiement cash

### Market Context & Competitive Landscape

- **Marché local (Bénin)** — Aucun concurrent direct. Les castings et bookings se font via WhatsApp, bouche-à-oreille, et contacts directs. Pas de plateforme structurée avec paiement intégré
- **Marché régional (Afrique de l'Ouest)** — Quelques plateformes émergentes au Nigeria et en Côte d'Ivoire, mais sans escrow ni intégration Mobile Money locale
- **Référents internationaux** — Fiverr, Upwork, Cameo opèrent sur des modèles similaires (escrow + commission) mais ne ciblent pas ce segment ni ce marché

### Validation Approach

- **Validation par l'usage** — Le MVP lui-même est le test. Si des bookings sont réalisés de bout en bout sans intervention manuelle, le concept est validé
- **Métriques clés** — Taux de récurrence des Producteurs (reviennent-ils booker ?) et volume transactionnel (les montants augmentent-ils ?)
- **Signal fort** — Les Faces reçoivent des bookings directs qu'elles n'auraient pas reçus via le workflow Mission classique

### Risk Mitigation

- **Risque d'adoption** — Les Producteurs et Faces sont habitués au cash et WhatsApp. Mitigation : le flux doit être plus simple et plus rapide que l'alternative informelle
- **Risque de confiance** — L'escrow est un concept nouveau pour les utilisateurs locaux. Mitigation : communication claire sur la sécurité des fonds à chaque étape
- **Risque de liquidité** — Peu de transactions au démarrage. Mitigation : le workflow Mission existant maintient l'engagement même si le Booking Direct démarre lentement

## Web App Specific Requirements

### Project-Type Overview

WEACT est une SPA (Single Page Application) Vue 3 + Vue Router, avec une API Laravel 12 en backend. Le Booking Direct s'intègre comme extension de l'application existante, sans changement d'architecture fondamental.

### Technical Architecture Considerations

#### Browser Matrix

| Navigateur | Support | Notes |
|------------|---------|-------|
| Chrome Mobile | Prioritaire | Navigateur dominant au Bénin |
| Chrome Desktop | Complet | |
| Firefox | Complet | |
| Safari (iOS) | Complet | |
| Edge | Complet | |
| IE | Non supporté | |

#### Responsive Design

- **Mobile-first** — L'usage principal est sur mobile (4G). Toutes les pages du flux booking doivent être optimisées pour les écrans < 400px
- **Breakpoints** — Tailwind CSS 4.1 (déjà en place) : sm (640px), md (768px), lg (1024px)
- **Touch-friendly** — Boutons et zones d'interaction dimensionnés pour le tactile (min 44x44px)

#### Performance Targets

| Métrique | Cible | Contexte |
|----------|-------|----------|
| First Contentful Paint | < 2s | Sur mobile 4G Bénin |
| Time to Interactive | < 3s | Sur mobile 4G Bénin |
| API Response Time | < 300ms | Hors appels Fedapay |
| Fedapay Callback | < 10s | Dépend de l'opérateur Mobile Money |

#### SEO Strategy

- **Pages publiques** — Profils Faces publics indexables (déjà en place via routes publiques)
- **Pages authentifiées** — Formulaire booking, wallet, chat, dashboard : non indexées (SPA derrière auth)
- **Pas de SSR** — Pas de Server-Side Rendering pour le MVP. Les profils publics sont déjà gérés

#### Accessibility Level

- **Niveau basique** — Contraste suffisant, navigation clavier fonctionnelle, labels sur les inputs
- **Pas de conformité WCAG formelle** pour le MVP
- **Focus visible** — Indicateurs de focus sur les éléments interactifs du flux de paiement

### Real-Time Communication

- **Technologie** — Laravel Reverb (serveur WebSocket officiel Laravel, self-hosted)
- **Usage** — Chat entre Producteur et Face après paiement du booking
- **Infrastructure** — Hébergé sur le même VPS (2 vCPU, 8 GB RAM, 100 GB NVMe) que l'application
- **Frontend** — Laravel Echo + Vue 3 Composition API
- **Scalabilité** — Solution self-hosted sans limites de messages, adaptée au VPS existant

### Implementation Considerations

- **Coexistence brownfield** — Le Booking Direct s'ajoute aux routes, composants et stores existants sans modifier le workflow Mission
- **Lazy loading** — Les pages du flux booking sont chargées en lazy loading (Vue Router) pour ne pas impacter le bundle initial
- **Optimisation mobile** — Compression des images, pagination des listes, skeleton loaders pour les temps de chargement sur réseau lent

## Project Scoping & Phased Development

### MVP Strategy & Philosophy

**MVP Approach:** Revenue MVP — Le flux complet de booking avec paiement et commissions est indispensable dès le lancement. Chaque booking génère des revenus (30% de commissions).

**Resource Requirements:** 1 développeur full-stack (Laravel + Vue 3). Pas de designer dédié (Tailwind + shadcn-vue). Infrastructure : VPS existant (2 vCPU, 8 GB RAM).

### MVP Feature Set (Phase 1)

**Core User Journeys Supported:**
- Producteur Happy Path (Kofi) — booking de bout en bout
- Face Happy Path (Aïcha) — réception, acceptation, paiement, retrait
- Producteur Annulation (Éric) — annulation avec remboursement partiel

**Must-Have Capabilities (9 features):**

| # | Feature | Justification MVP |
|---|---------|-------------------|
| 1 | Formulaire de booking | Sans ça, pas de booking |
| 2 | Acceptation / Refus Face | La Face doit pouvoir décider |
| 3 | Paiement Mobile Money (Fedapay) | Sans paiement, pas de revenus |
| 4 | Commissions automatiques (15%+15%) | Modèle économique de la plateforme |
| 5 | Chat après paiement (Reverb) | Coordination logistique indispensable |
| 6 | Double confirmation + auto-72h | Sécurisation du flux escrow |
| 7 | Wallet interne Face | La Face doit recevoir ses gains |
| 8 | Notation mutuelle | Confiance et réputation |
| 9 | Politique d'annulation | Protection des deux parties |

### Post-MVP Features

**Phase 2 — Growth:**
- Gestion des litiges (dashboard admin, médiation, remboursement)
- Modification de date post-booking (soumise à validation Face)
- Notifications avancées (push web + SMS pour événements critiques)

**Phase 3 — Expansion:**
- Calendrier de disponibilité (Faces bloquent des créneaux)
- Booking récurrent (re-booker en un clic)
- Analytics avancés (revenus, tendances, top Faces)

### Risk Mitigation Strategy

**Technical Risks:**

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Fiabilité Fedapay | Paiements échoués → frustration | Retry automatique, gestion robuste des erreurs, idempotence |
| Intégrité escrow | Perte de fonds | Tests exhaustifs du state machine, réconciliation automatique |
| Auto-complétion 72h | Job planifié qui ne se déclenche pas | Laravel Scheduler + monitoring, fallback manuel admin |
| Reverb WebSocket | Chat non fonctionnel | Fallback polling si Reverb down, health check |

**Market Risks:**

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Faible adoption | Peu de bookings | Le workflow Mission maintient l'engagement existant |
| Résistance au paiement en ligne | Préférence cash | UX fluide Mobile Money, communication escrow claire |
| Confiance escrow | Utilisateurs méfiants | Transparence à chaque étape, notifications de statut |

**Resource Risks:**

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Développeur unique | Délais si indisponible | Code bien structuré, tests, documentation |
| VPS limité | Performance sous charge | Monitoring, optimisation lazy loading, Reverb léger |
| Dépendance Fedapay | API indisponible | Mode dégradé informatif, pas de perte de données |

## Functional Requirements

### 1. Booking Management

- FR1: Producteur can browse and filter the list of available Faces (by category, location, availability)
- FR2: Producteur can view a Face's complete public profile (photos, videos, bio, tariffs, ratings, availability status)
- FR3: Producteur can initiate a booking request from a Face's profile (date, duration, content type)
- FR4: System calculates the total booking amount automatically (Face tariff based on hourly/daily rate + Producer commission)
- FR5: Face can receive and view incoming booking requests with full details (date, duration, type, amount, Producer profile)
- FR6: Face can accept or refuse a booking request
- FR7: Face must provide a mandatory reason when refusing a booking request after payment
- FR8: Producteur can view the list of their bookings with statuses (pending, accepted, paid, completed, cancelled)
- FR9: Face can view the list of their bookings with statuses (pending, accepted, paid, completed, cancelled)
- FR10: System enforces a minimum booking duration of 4 hours
- FR11: System prevents booking a Face whose availability status is "Indisponible"

### 2. Payment & Escrow

- FR12: Producteur can pay for an accepted booking via Mobile Money (MTN MoMo, Moov Money, Celtiis) through Fedapay
- FR13: System blocks the payment amount in escrow until booking completion is confirmed
- FR14: System calculates and applies commissions automatically (15% deducted from Face + 15% charged to Producer)
- FR15: Both parties can confirm booking completion (double confirmation)
- FR16: System auto-completes a booking after 72 hours if the Producer has not confirmed
- FR17: System releases escrow funds to Face wallet within 24 hours after mutual confirmation
- FR18: Each financial operation is idempotent (no duplicate transactions on retry)
- FR19: System maintains a complete, non-modifiable audit trail of all financial transactions (escrow, commissions, refunds, withdrawals)

### 3. Wallet & Withdrawals

- FR20: Face can view their wallet balance
- FR21: Face can withdraw funds from their wallet to their Mobile Money account
- FR22: Face can view their wallet transaction history (payments received, withdrawals)

### 4. Messaging

- FR23: Chat between Producer and Face is unlocked only after successful payment
- FR24: Producer and Face can exchange messages in real-time within the booking chat
- FR25: Each booking has its own dedicated chat conversation

### 5. Rating & Reputation

- FR26: Producer can rate a Face after booking completion (star rating)
- FR27: Face can rate a Producer after booking completion (star rating)
- FR28: System automatically deducts 1 star from Face's average rating when Face cancels a booking
- FR29: Face's average rating and ratings count are displayed on their public profile

### 6. Notifications

- FR30: Face receives a notification when a new booking request arrives
- FR31: Producer receives a notification when a Face accepts or refuses their booking request
- FR32: Producer receives a notification when payment is confirmed and chat is unlocked
- FR33: Both parties receive a notification when the other party confirms booking completion
- FR34: Face receives a notification when payment is credited to their wallet
- FR35: Face receives a notification when a booking is cancelled by the Producer

### 7. Cancellation & Refunds

- FR36: Producer can cancel a booking before Face acceptance (full refund)
- FR37: Producer can cancel after Face acceptance with transparent financial handling (accepted: no financial operation, paid: 10% retained by WEACT, 90% refunded)
- FR38: System processes refunds to the Producer's Mobile Money account
- FR39: System does not refund Producer in case of Producer no-show
- FR40: Face is not penalized (no rating impact) when a Producer cancels

## Non-Functional Requirements

### Performance

| Critère | Cible | Contexte |
|---------|-------|----------|
| First Contentful Paint | < 2s | Mobile 4G Bénin |
| Time to Interactive | < 3s | Mobile 4G Bénin |
| API Response Time | < 300ms | Endpoints internes (hors Fedapay) |
| Chat Message Delivery | < 500ms | Via Reverb WebSocket |
| Fedapay Payment Callback | < 10s | Dépend de l'opérateur Mobile Money |
| Concurrent WebSocket Connections | 100+ | Chats actifs simultanés |

### Security

- NFR-S1: Toutes les communications client-serveur transitent via HTTPS (TLS 1.2+)
- NFR-S2: Authentification API via Laravel Sanctum tokens avec expiration
- NFR-S3: Protection CSRF sur toutes les requêtes mutantes
- NFR-S4: Rate limiting sur les endpoints sensibles (paiement, login, booking)
- NFR-S5: Aucune donnée bancaire ou credential Mobile Money stockée côté WEACT
- NFR-S6: Les montants financiers et statuts d'escrow ne sont modifiables que par le système (jamais par input utilisateur direct)
- NFR-S7: Validation côté serveur de tous les montants calculés (ne jamais faire confiance au frontend pour les calculs financiers)
- NFR-S8: Journalisation des événements financiers critiques (paiement, libération escrow, retrait, remboursement)

### Reliability

- NFR-R1: Zéro perte de fonds — l'intégrité de l'escrow est garantie à chaque transition d'état
- NFR-R2: Idempotence de toutes les opérations financières (paiement, remboursement, retrait)
- NFR-R3: Le job d'auto-complétion 72h se déclenche de manière fiable via Laravel Scheduler
- NFR-R4: En cas d'échec Fedapay, le système conserve l'état et permet un retry sans duplication
- NFR-R5: Mécanisme de réconciliation entre soldes wallet WEACT et transactions Fedapay
- NFR-R6: Disponibilité cible : 99% uptime (hors maintenance planifiée)

### Integration

- NFR-I1: Intégration Fedapay pour paiement Mobile Money (MTN MoMo, Moov Money, Celtiis)
- NFR-I2: Gestion des webhooks Fedapay (callback de confirmation/échec de paiement)
- NFR-I3: Intégration Fedapay pour les retraits wallet vers Mobile Money
- NFR-I4: Laravel Reverb pour le chat temps réel (WebSocket self-hosted)
- NFR-I5: Fallback gracieux si Fedapay ou Reverb est indisponible (message d'erreur informatif, pas de perte de données)

### Scalability

- NFR-SC1: L'architecture supporte la charge d'un MVP sur VPS unique (2 vCPU, 8 GB RAM)
- NFR-SC2: Lazy loading des pages Vue Router pour limiter le bundle initial
- NFR-SC3: Pagination de toutes les listes (bookings, transactions, messages)
- NFR-SC4: L'architecture permet une migration future vers un serveur dédié ou cloud sans refonte
