---
stepsCompleted: [1, 2, 3, 4]
validationStatus: 'complete'
totalStories: 95
totalEpics: 13
frCoverage: '82/82'
inputDocuments:
  - path: "docs/planning-artifacts/prd.md"
    type: "prd"
  - path: "docs/planning-artifacts/architecture.md"
    type: "architecture"
  - path: "docs/weact-brief.md"
    type: "product-brief"
  - path: "_bmad-output/project-context.md"
    type: "project-context"
---

# WEACT - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for WEACT, decomposing the requirements from the PRD, Architecture, and Project Context into implementable stories.

## Requirements Inventory

### Functional Requirements

**Gestion des Utilisateurs (FR1-FR8)**
- FR1: Un visiteur peut créer un compte Face avec nom, prénom, username, email et mot de passe
- FR2: Un visiteur peut créer un compte Producteur avec email, mot de passe et catégorie (Agence ou Particulier)
- FR3: Un Producteur Agence peut renseigner le nom de son agence lors de l'inscription
- FR4: Un Producteur Particulier peut renseigner ses noms et prénoms lors de l'inscription
- FR5: Un utilisateur enregistré peut se connecter avec email et mot de passe
- FR6: Un utilisateur connecté peut se déconnecter
- FR7: Un utilisateur peut réinitialiser son mot de passe via email
- FR8: Un Admin peut créer des comptes Admin supplémentaires

**Gestion des Profils Face (FR9-FR21)**
- FR9: Une Face peut ajouter une photo de profil
- FR10: Une Face peut gérer un album de photos (jusqu'à 4 photos)
- FR11: Une Face peut uploader une vidéo de présentation (max 50MB, 2 min)
- FR12: Une Face peut uploader une vidéo d'acting (max 50MB, 2 min)
- FR13: Une Face peut renseigner sa bio courte
- FR14: Une Face peut renseigner sa localisation (ville, quartier, pays)
- FR15: Une Face peut renseigner ses caractéristiques physiques (taille, poids)
- FR16: Une Face peut sélectionner sa catégorie (acteur/influenceur/créateur/mannequin/figurant)
- FR17: Une Face peut sélectionner sa niche (beauté, nourriture, découverte, mode)
- FR18: Une Face peut renseigner ses expériences professionnelles
- FR19: Une Face peut définir ses tarifs (horaire et journalier en XOF)
- FR20: Une Face peut basculer son statut de disponibilité (Disponible/Indisponible)
- FR21: Une Face peut consulter le niveau de complétion de son profil

**Gestion des Profils Producteur (FR22-FR24)**
- FR22: Un Producteur peut ajouter une photo de profil
- FR23: Un Producteur peut renseigner sa bio courte
- FR24: Un Producteur Agence peut ajouter le logo de l'agence

**Gestion des Missions (FR25-FR33)**
- FR25: Un Producteur peut publier une mission avec titre, description, date de tournage, profil recherché, budget, date limite de candidature, nombre de Faces voulu, type de mission, genre voulu, lieu et durée
- FR26: Un Producteur peut modifier une mission qu'il a publiée
- FR27: Un Producteur peut supprimer une mission qu'il a publiée
- FR28: Un Producteur peut consulter la liste de ses missions avec leurs statuts
- FR29: Un Producteur peut clôturer une mission (arrêter les candidatures)
- FR30: Un Producteur peut marquer une mission comme terminée
- FR31: Une Face peut consulter la liste des missions disponibles
- FR32: Une Face peut filtrer les missions par ville, budget min/max, date et type de mission
- FR33: Une Face peut consulter le détail complet d'une mission

**Gestion des Candidatures (FR34-FR41)**
- FR34: Une Face peut postuler à une mission avec un message de motivation optionnel
- FR35: Une Face peut consulter la liste de ses candidatures avec leurs statuts (En attente, Acceptée, En cours, Terminée)
- FR36: Un Producteur peut consulter les candidatures reçues pour chacune de ses missions
- FR37: Un Producteur peut consulter le profil complet d'un candidat (incluant vidéos)
- FR38: Un Producteur peut accepter une candidature
- FR39: Un Producteur peut refuser une candidature
- FR40: Une Face peut confirmer une mission après acceptation de sa candidature (validation 2 étapes)
- FR41: Une Face reçoit une notification lors du changement de statut de sa candidature

**Messagerie (FR42-FR45)**
- FR42: Une Face peut échanger des messages avec un Producteur après acceptation de sa candidature
- FR43: Un Producteur peut échanger des messages avec une Face dont la candidature a été acceptée
- FR44: Un utilisateur peut consulter l'historique de ses conversations
- FR45: Un utilisateur peut actualiser manuellement ses messages

**Système de Notation (FR46-FR50)**
- FR46: Une Face peut noter un Producteur après la fin d'une mission (1 à 5 étoiles)
- FR47: Un Producteur peut noter une Face après la fin d'une mission (1 à 5 étoiles)
- FR48: Un utilisateur peut consulter la note moyenne d'une Face
- FR49: Un utilisateur peut consulter la note moyenne d'un Producteur
- FR50: Un utilisateur peut consulter les avis reçus par une Face ou un Producteur

**Dashboard Face (FR51-FR54)**
- FR51: Une Face peut consulter ses KPIs : missions en attente, acceptées, en cours, terminées
- FR52: Une Face peut consulter le solde de son wallet (interface présente, fonctionnalité inactive MVP)
- FR53: Une Face peut consulter des graphiques d'évolution (candidatures, missions par mois)
- FR54: Une Face peut accéder rapidement à la liste des missions disponibles depuis son dashboard

**Dashboard Producteur (FR55-FR59)**
- FR55: Un Producteur peut consulter ses KPIs : missions publiées, en cours, clôturées
- FR56: Un Producteur peut consulter le nombre total de candidatures reçues
- FR57: Un Producteur peut consulter le nombre de Faces avec qui il a travaillé
- FR58: Un Producteur peut consulter sa note globale moyenne
- FR59: Un Producteur peut consulter des stats avancées (taux d'acceptation, missions terminées dans les délais)

**Module Ressources/Blog (FR60-FR67)**
- FR60: Un Admin peut créer un article avec titre, contenu rich text et images
- FR61: Un Admin peut catégoriser un article (Conseils Face, Guide Producteur, Actualités)
- FR62: Un Admin peut définir le statut d'un article (Brouillon ou Publié)
- FR63: Un Admin peut modifier un article existant
- FR64: Un Admin peut supprimer un article
- FR65: Un visiteur peut consulter la liste des articles publiés
- FR66: Un visiteur peut lire un article complet
- FR67: Un visiteur peut filtrer les articles par catégorie

**Administration (FR68-FR74)**
- FR68: Un Admin peut consulter un dashboard avec KPIs globaux (Faces, Producteurs, Missions actives/terminées)
- FR69: Un Admin peut créer, lire, modifier et supprimer des comptes Face
- FR70: Un Admin peut créer, lire, modifier et supprimer des comptes Producteur
- FR71: Un Admin peut créer, lire, modifier et supprimer des comptes Admin
- FR72: Un Admin peut consulter les profils détaillés (Faces avec vidéos, Producteurs avec missions)
- FR73: Un Admin peut consulter toutes les missions publiées
- FR74: Un Admin peut activer ou désactiver un compte utilisateur

**Accès Public (FR75-FR82)**
- FR75: Un visiteur peut consulter la landing page avec présentation de la plateforme
- FR76: Un visiteur peut utiliser le switch Face/Producteur sur la landing page
- FR77: Un visiteur peut consulter la liste des Faces publiques (photo, nom, ville, catégorie)
- FR78: Un visiteur peut consulter un profil Face public (infos limitées)
- FR79: Un visiteur peut filtrer les Faces par ville, catégorie, niche
- FR80: Un visiteur peut consulter la liste des missions publiques (titre, budget, date, lieu)
- FR81: Un visiteur peut consulter le détail d'une mission publique
- FR82: Un visiteur peut accéder aux pages d'inscription Face ou Producteur

### Non-Functional Requirements

**Performance (NFR-P1 to NFR-P7)**
- NFR-P1: Temps de chargement page < 2s sur réseau 4G mobile
- NFR-P2: Time to Interactive < 3s sur réseau 4G mobile
- NFR-P3: First Contentful Paint < 1.5s sur réseau 4G mobile
- NFR-P4: Taille bundle initial < 300KB gzipped
- NFR-P5: Upload vidéo (50MB) < 60s avec progress bar
- NFR-P6: Temps de réponse API < 300ms (95e percentile)
- NFR-P7: Rendu liste 50 items < 500ms avec pagination

**Sécurité (NFR-S1 to NFR-S9)**
- NFR-S1: Authentification via tokens JWT Laravel Sanctum avec expiration 24h
- NFR-S2: Mots de passe hashage bcrypt, minimum 8 caractères, 1 majuscule, 1 chiffre
- NFR-S3: Protection CSRF sur tous les formulaires
- NFR-S4: Transmission données HTTPS obligatoire (TLS 1.2+)
- NFR-S5: Validation côté serveur de tous les inputs utilisateur
- NFR-S6: Uploads fichiers validation MIME type, scan malware basique, limite taille
- NFR-S7: Un utilisateur ne peut accéder qu'à ses propres données sensibles
- NFR-S8: Rate limiting 60 requêtes/minute par IP
- NFR-S9: Sessions invalidation à la déconnexion, timeout 2h inactivité

**Scalabilité (NFR-SC1 to NFR-SC5)**
- NFR-SC1: 50 utilisateurs simultanés (MVP), 200 (12 mois)
- NFR-SC2: 200 Faces enregistrées (MVP), 500+ (12 mois)
- NFR-SC3: 50 Producteurs enregistrés (MVP), 150+ (12 mois)
- NFR-SC4: 30 missions actives simultanées (MVP), 100 (12 mois)
- NFR-SC5: 10GB stockage médias (MVP), 100GB (12 mois)

**Fiabilité (NFR-R1 to NFR-R6)**
- NFR-R1: Uptime > 99% (hors maintenance planifiée)
- NFR-R2: Fenêtre maintenance nuit (22h-6h WAT) avec notification 24h avant
- NFR-R3: Recovery Time Objective (RTO) < 4h
- NFR-R4: Backup base de données quotidien, rétention 7 jours
- NFR-R5: Backup médias hebdomadaire (MVP), quotidien (V2)
- NFR-R6: Succès upload vidéo > 95% pour fichiers < 50MB

**Accessibilité (NFR-A1 to NFR-A8)**
- NFR-A1: Conformité WCAG 2.1 Level A
- NFR-A2: Alt text obligatoire sur toutes les images
- NFR-A3: Tab navigation fonctionnelle
- NFR-A4: Contraste couleurs ratio 4.5:1 minimum pour texte
- NFR-A5: Focus visible sur tous les éléments focusables
- NFR-A6: Tous les inputs avec labels associés
- NFR-A7: Messages d'erreur annoncés aux lecteurs d'écran
- NFR-A8: Touch targets minimum 44x44px sur mobile

**Intégration V2 (NFR-I1 to NFR-I5)**
- NFR-I1: Intégration Fedapay Mobile Money (MTN MoMo, Moov Money)
- NFR-I2: Webhooks paiement réception et traitement < 5s
- NFR-I3: Email transactionnel Mailgun/SendGrid avec templates
- NFR-I4: CDN médias DigitalOcean Spaces (S3-compatible)
- NFR-I5: WebSockets Laravel Echo + Pusher pour temps réel

### Additional Requirements

**From Architecture - Starter Template (CRITICAL for Epic 1 Story 1):**
- Custom Fresh Initialization required (no existing starter matches Laravel 12 + Vue 3 + Tailwind 4.1)
- Initialization Commands:
  ```bash
  # Backend: Laravel 12
  composer create-project laravel/laravel backend
  cd backend && composer require laravel/sanctum
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

  # Frontend: Vue 3 + Vite + Tailwind 4
  npm create vue@latest frontend -- --typescript --router --pinia
  cd frontend && npm install -D tailwindcss@latest @tailwindcss/vite
  ```

**From Architecture - Implementation Sequence:**
1. Project initialization (Laravel + Vue scaffolding)
2. Database schema + migrations
3. Authentication system (Sanctum + Polymorphic users)
4. Core API resources with envelope format
5. Frontend scaffolding with routing
6. Feature modules (Auth → Profiles → Missions → Candidatures → Chat)

**From Architecture - Critical Technical Decisions:**
- Polymorphic User Architecture: User → userable (Face/Producer), Admin separate
- API Response Format: Envelope `{data, meta, message}` for success, `{error: {code, message, details}}` for errors
- Form Requests for all API validation
- API Resources for all model responses
- VeeValidate + Zod for frontend form validation
- Pinia for state management
- Feature-based organization: `/features/{domain}/`

**From Architecture - Infrastructure Requirements:**
- Monorepo structure: `/backend` (Laravel) + `/frontend` (Vue)
- DigitalOcean VPS ($6) with Nginx
- GitHub Actions CI/CD
- Local storage for MVP → DigitalOcean Spaces for V2

**From Project Context - Critical Business Rules:**
- Chat access: Only unlocked AFTER candidature is accepted (never before)
- Ratings: Only possible AFTER mission is marked "Terminée" (completed)
- Mission confirmation: Face must confirm after Producer accepts (2-step validation)
- Wallet: UI present but functionality inactive for MVP
- User can be either Face OR Producer, never both (polymorphic exclusive)
- Admin is a separate model, not a User role

**From Brief - MVP Priorities:**
- P0 (Critical): Auth, Profils Face (vidéos), CRUD Missions, Candidatures, Chat conditionnel
- P1 (Important): Dashboards, Notation, Filtres avancés
- P2 (Nice-to-have): Landing page, Admin CRUD, Pages publiques

### FR Coverage Map

| FR | Epic | Description |
|----|------|-------------|
| FR1 | Epic 2 | Face registration |
| FR2 | Epic 2 | Producer registration |
| FR3 | Epic 2 | Producer Agence name |
| FR4 | Epic 2 | Producer Particulier name |
| FR5 | Epic 2 | Login |
| FR6 | Epic 2 | Logout |
| FR7 | Epic 2 | Password reset |
| FR8 | Epic 13 | Admin account creation |
| FR9 | Epic 3 | Face photo de profil |
| FR10 | Epic 3 | Face album photos |
| FR11 | Epic 3 | Face vidéo présentation |
| FR12 | Epic 3 | Face vidéo acting |
| FR13 | Epic 3 | Face bio |
| FR14 | Epic 3 | Face localisation |
| FR15 | Epic 3 | Face caractéristiques physiques |
| FR16 | Epic 3 | Face catégorie |
| FR17 | Epic 3 | Face niche |
| FR18 | Epic 3 | Face expériences |
| FR19 | Epic 3 | Face tarifs |
| FR20 | Epic 3 | Face disponibilité toggle |
| FR21 | Epic 3 | Face complétion profil |
| FR22 | Epic 4 | Producer photo profil |
| FR23 | Epic 4 | Producer bio |
| FR24 | Epic 4 | Producer logo agence |
| FR25 | Epic 5 | Mission publication |
| FR26 | Epic 5 | Mission modification |
| FR27 | Epic 5 | Mission suppression |
| FR28 | Epic 5 | Mission liste producteur |
| FR29 | Epic 5 | Mission clôture |
| FR30 | Epic 5 | Mission terminée |
| FR31 | Epic 5 | Mission liste Face |
| FR32 | Epic 5 | Mission filtres |
| FR33 | Epic 5 | Mission détail |
| FR34 | Epic 6 | Candidature postuler |
| FR35 | Epic 6 | Candidature liste Face |
| FR36 | Epic 6 | Candidature liste Producteur |
| FR37 | Epic 6 | Candidature profil complet |
| FR38 | Epic 6 | Candidature accepter |
| FR39 | Epic 6 | Candidature refuser |
| FR40 | Epic 6 | Candidature confirmation 2 étapes |
| FR41 | Epic 6 | Candidature notification |
| FR42 | Epic 7 | Chat Face vers Producteur |
| FR43 | Epic 7 | Chat Producteur vers Face |
| FR44 | Epic 7 | Chat historique |
| FR45 | Epic 7 | Chat actualisation |
| FR46 | Epic 8 | Notation Face vers Producteur |
| FR47 | Epic 8 | Notation Producteur vers Face |
| FR48 | Epic 8 | Notation moyenne Face |
| FR49 | Epic 8 | Notation moyenne Producteur |
| FR50 | Epic 8 | Avis consultation |
| FR51 | Epic 9 | Dashboard Face KPIs |
| FR52 | Epic 9 | Dashboard Face Wallet UI |
| FR53 | Epic 9 | Dashboard Face graphiques |
| FR54 | Epic 9 | Dashboard Face accès missions |
| FR55 | Epic 10 | Dashboard Producteur KPIs |
| FR56 | Epic 10 | Dashboard Producteur candidatures |
| FR57 | Epic 10 | Dashboard Producteur collaborateurs |
| FR58 | Epic 10 | Dashboard Producteur note |
| FR59 | Epic 10 | Dashboard Producteur stats avancées |
| FR60 | Epic 12 | Blog création article |
| FR61 | Epic 12 | Blog catégorisation |
| FR62 | Epic 12 | Blog statut article |
| FR63 | Epic 12 | Blog modification |
| FR64 | Epic 12 | Blog suppression |
| FR65 | Epic 12 | Blog liste publique |
| FR66 | Epic 12 | Blog lecture article |
| FR67 | Epic 12 | Blog filtre catégorie |
| FR68 | Epic 13 | Admin dashboard KPIs |
| FR69 | Epic 13 | Admin CRUD Face |
| FR70 | Epic 13 | Admin CRUD Producteur |
| FR71 | Epic 13 | Admin CRUD Admin |
| FR72 | Epic 13 | Admin profils détaillés |
| FR73 | Epic 13 | Admin missions |
| FR74 | Epic 13 | Admin activation/désactivation |
| FR75 | Epic 11 | Landing page |
| FR76 | Epic 11 | Switch Face/Producteur |
| FR77 | Epic 11 | Liste Faces publiques |
| FR78 | Epic 11 | Profil Face public |
| FR79 | Epic 11 | Filtres Faces publiques |
| FR80 | Epic 11 | Liste missions publiques |
| FR81 | Epic 11 | Détail mission publique |
| FR82 | Epic 11 | Accès inscription |

## Epic List

### Epic 1: Project Initialization
Users can begin development with a properly configured monorepo, database schema, and authentication foundation.

**FRs covered:** Infrastructure (from Architecture)
**Implementation Notes:** Custom fresh initialization (Laravel 12 + Vue 3 + Tailwind 4.1), Sanctum setup, database migrations, polymorphic User model structure.

---

### Epic 2: Authentication & User Accounts
Visitors can register as Face or Producer, login, logout, and reset their password.

**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7
**Implementation Notes:** Polymorphic auth (User → Face/Producer), Sanctum tokens, password reset via email.

---

### Epic 3: Face Profile & Portfolio
Faces can create a complete professional profile with photos, videos, bio, tarifs, and availability status.

**FRs covered:** FR9, FR10, FR11, FR12, FR13, FR14, FR15, FR16, FR17, FR18, FR19, FR20, FR21
**Implementation Notes:** Video upload (50MB max), profile completion indicator, availability toggle.

---

### Epic 4: Producer Profile
Producers can establish their platform identity with photo, bio, and agency branding.

**FRs covered:** FR22, FR23, FR24
**Implementation Notes:** Supports both Agency (logo) and Particulier types.

---

### Epic 5: Mission Management
Producers can publish and manage missions; Faces can discover and filter available opportunities.

**FRs covered:** FR25, FR26, FR27, FR28, FR29, FR30, FR31, FR32, FR33
**Implementation Notes:** Full CRUD, status workflow (Published → Closed → Completed), filters by city/budget/date/type.

---

### Epic 6: Candidature Workflow
Faces can apply to missions; Producers can review, accept/reject; 2-step confirmation ensures mutual commitment.

**FRs covered:** FR34, FR35, FR36, FR37, FR38, FR39, FR40, FR41
**Implementation Notes:** Status machine (En attente → Acceptée → Confirmée → En cours → Terminée), video profile access for review.

---

### Epic 7: Messaging System
Matched parties (after candidature acceptance) can communicate securely via chat.

**FRs covered:** FR42, FR43, FR44, FR45
**Implementation Notes:** Conditional unlock, polling for MVP (30s), conversation history.

---

### Epic 8: Rating & Reviews
After mission completion, both parties can rate each other, building a trust-based reputation.

**FRs covered:** FR46, FR47, FR48, FR49, FR50
**Implementation Notes:** 1-5 stars, only available after mission "Terminée", bidirectional.

---

### Epic 9: Face Dashboard
Faces can track their mission activity, wallet (UI only), and career analytics.

**FRs covered:** FR51, FR52, FR53, FR54
**Implementation Notes:** KPIs by status, wallet UI (inactive MVP), charts, quick mission access.

---

### Epic 10: Producer Dashboard
Producers can monitor their hiring activity, collaborations, and performance metrics.

**FRs covered:** FR55, FR56, FR57, FR58, FR59
**Implementation Notes:** Mission stats, candidature totals, collaborator count, rating display, advanced analytics.

---

### Epic 11: Public Access & Discovery
Visitors can discover Faces and Missions without logging in, and convert to registration.

**FRs covered:** FR75, FR76, FR77, FR78, FR79, FR80, FR81, FR82
**Implementation Notes:** Landing page with Face/Producer switch, public lists with filters, limited public profiles, SEO meta tags.

---

### Epic 12: Blog & Resources
Admins can publish educational content; visitors can read articles to learn about the platform.

**FRs covered:** FR60, FR61, FR62, FR63, FR64, FR65, FR66, FR67
**Implementation Notes:** Rich text editor, categories, draft/published status, public access.

---

### Epic 13: Administration
Admins can monitor platform KPIs, manage all users, and moderate the community.

**FRs covered:** FR8, FR68, FR69, FR70, FR71, FR72, FR73, FR74
**Implementation Notes:** Admin dashboard, full CRUD for all user types, account activation/deactivation.

---

## Detailed Stories by Epic

## Epic 1: Project Initialization

### Story 1.1: Initialize Laravel Backend

As a **developer**,
I want **a Laravel 12 backend project with Sanctum configured**,
So that **I can build the API with proper authentication support**.

**Acceptance Criteria:**

**Given** an empty project directory
**When** I run the Laravel initialization commands
**Then** a `/backend` directory is created with Laravel 12
**And** Laravel Sanctum is installed and configured
**And** the `.env.example` contains required environment variables
**And** the database connection is configurable via environment

---

### Story 1.2: Initialize Vue Frontend

As a **developer**,
I want **a Vue 3 frontend project with TypeScript, Pinia, and Tailwind CSS 4.1**,
So that **I can build the SPA with modern tooling**.

**Acceptance Criteria:**

**Given** the backend is initialized
**When** I run the Vue initialization commands
**Then** a `/frontend` directory is created with Vue 3 + TypeScript
**And** Pinia and Vue Router are installed
**And** Tailwind CSS 4.1 is configured with @tailwindcss/vite
**And** the design system primary color #198496 is defined
**And** `npm run dev` starts the development server

---

### Story 1.3: Configure Monorepo Development Environment

As a **developer**,
I want **concurrent development scripts and shared configuration**,
So that **I can run both servers simultaneously during development**.

**Acceptance Criteria:**

**Given** both backend and frontend are initialized
**When** I configure the root package.json
**Then** `npm run dev` starts both Laravel and Vue dev servers
**And** `.env.example` files exist at root and in each project
**And** `.gitignore` properly excludes node_modules, vendor, and .env files
**And** README.md documents the setup process

---

### Story 1.4: Create Core Database Schema

As a **developer**,
I want **the core database migrations for User, Face, Producer, and Admin models**,
So that **the polymorphic authentication system has its foundation**.

**Acceptance Criteria:**

**Given** Laravel is configured with database connection
**When** I run `php artisan migrate`
**Then** `users` table is created with: id, email, password, userable_type, userable_id, timestamps
**And** `faces` table is created with basic columns (id, timestamps) for the polymorphic relationship
**And** `producers` table is created with: id, type (agency/particulier), timestamps
**And** `admins` table is created with: id, name, email, password, timestamps
**And** the polymorphic `userable` relationship is properly indexed

---

## Epic 2: Authentication & User Accounts

### Story 2.1: Face Registration

As a **visitor**,
I want **to create a Face account with my name, username, email, and password**,
So that **I can access the platform as a talent seeking opportunities**.

**Acceptance Criteria:**

**Given** I am on the Face registration page
**When** I submit valid registration data (nom, prénom, username, email, password)
**Then** a User record is created with userable_type = 'Face'
**And** a Face record is created and linked to the User
**And** I receive an authentication token
**And** I am redirected to the Face dashboard
**And** my password is hashed with bcrypt

**Given** I submit an email that already exists
**When** the form is submitted
**Then** I see an error message "Cet email est déjà utilisé"

**Given** I submit a password with less than 8 characters
**When** the form is submitted
**Then** I see an error message about password requirements (8+ chars, 1 uppercase, 1 number)

**(FR1)**

---

### Story 2.2: Producer Registration

As a **visitor**,
I want **to create a Producer account choosing Agency or Individual type**,
So that **I can access the platform to find talents for my projects**.

**Acceptance Criteria:**

**Given** I am on the Producer registration page
**When** I select "Agence" and submit email, password, and agency name
**Then** a User record is created with userable_type = 'Producer'
**And** a Producer record is created with type = 'agency' and agency_name populated
**And** I receive an authentication token

**Given** I select "Particulier" and submit email, password, first name, and last name
**When** the form is submitted
**Then** a Producer record is created with type = 'particulier' and first_name/last_name populated

**Given** I try to register with an email already used by a Face
**When** the form is submitted
**Then** I see an error message "Cet email est déjà utilisé"

**(FR2, FR3, FR4)**

---

### Story 2.3: User Login

As a **registered user**,
I want **to login with my email and password**,
So that **I can access my account and platform features**.

**Acceptance Criteria:**

**Given** I am on the login page
**When** I submit valid email and password
**Then** I receive a Sanctum authentication token
**And** I am redirected to my role-specific dashboard (Face or Producer)
**And** the token is stored securely in the frontend

**Given** I submit incorrect credentials
**When** the form is submitted
**Then** I see an error message "Email ou mot de passe incorrect"
**And** I remain on the login page

**Given** I have exceeded 5 login attempts in 1 minute
**When** I try to login again
**Then** I see a rate limit message and must wait

**(FR5)**

---

### Story 2.4: User Logout

As a **logged-in user**,
I want **to logout from my account**,
So that **my session is securely terminated**.

**Acceptance Criteria:**

**Given** I am logged in
**When** I click the logout button
**Then** my Sanctum token is revoked on the server
**And** my local token storage is cleared
**And** I am redirected to the login page
**And** I cannot access protected routes without logging in again

**(FR6)**

---

### Story 2.5: Password Reset

As a **user who forgot my password**,
I want **to reset my password via email**,
So that **I can regain access to my account**.

**Acceptance Criteria:**

**Given** I am on the forgot password page
**When** I submit my registered email
**Then** a password reset email is sent with a secure token
**And** I see a confirmation message "Email envoyé"

**Given** I click the reset link in my email
**When** I submit a new valid password
**Then** my password is updated
**And** I am redirected to the login page with success message

**Given** I try to use an expired or invalid reset token
**When** I submit a new password
**Then** I see an error message "Lien expiré ou invalide"

**(FR7)**

---

### Story 2.6: Authentication Frontend Integration

As a **user**,
I want **the frontend to properly handle authentication state**,
So that **I have a seamless login/logout experience with protected routes**.

**Acceptance Criteria:**

**Given** I am not logged in
**When** I try to access a protected route (e.g., /dashboard)
**Then** I am redirected to the login page

**Given** I am logged in as a Face
**When** I try to access a Producer-only route
**Then** I am redirected to my Face dashboard

**Given** my token expires during a session
**When** I make an API request
**Then** I am redirected to login with message "Session expirée"

**And** the auth store (Pinia) properly tracks login state
**And** the navigation shows appropriate menu items based on role

---

## Epic 3: Face Profile & Portfolio

### Story 3.1: Face Profile Photo Upload

As a **Face**,
I want **to upload and manage my profile photo**,
So that **producers can see my face when browsing talents**.

**Acceptance Criteria:**

**Given** I am logged in as a Face on my profile page
**When** I upload a profile photo (JPG, PNG, max 5MB)
**Then** the image is stored and associated with my profile
**And** a thumbnail is generated
**And** my profile displays the new photo

**Given** I upload an invalid file type
**When** the upload is processed
**Then** I see an error "Format non supporté (JPG, PNG uniquement)"

**Given** I already have a profile photo
**When** I upload a new one
**Then** the old photo is replaced

**(FR9)**

---

### Story 3.2: Face Photo Album Management

As a **Face**,
I want **to manage an album of up to 4 portfolio photos**,
So that **I can showcase my versatility to producers**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I upload portfolio photos
**Then** I can add up to 4 photos to my album
**And** each photo is validated (JPG/PNG, max 5MB)
**And** photos display in a grid on my profile

**Given** I have 4 photos and try to add another
**When** I attempt the upload
**Then** I see an error "Maximum 4 photos atteint"

**Given** I want to remove a photo
**When** I click delete on a portfolio photo
**Then** the photo is removed and the slot becomes available

**(FR10)**

---

### Story 3.3: Face Presentation Video Upload

As a **Face**,
I want **to upload a presentation video where I introduce myself**,
So that **producers can see my personality and speaking ability**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I upload a presentation video (MP4/MOV/AVI, max 50MB, max 2 min)
**Then** the video is stored with a progress bar showing upload status
**And** a thumbnail is auto-generated from the first frame
**And** the video is playable on my profile

**Given** I upload a video larger than 50MB
**When** the validation runs
**Then** I see an error "Vidéo trop volumineuse (max 50MB)"

**Given** the upload is in progress
**When** I view the upload status
**Then** I see a progress bar with percentage

**(FR11)**

---

### Story 3.4: Face Acting Video Upload

As a **Face**,
I want **to upload an acting video demonstrating my talent**,
So that **producers can evaluate my acting capabilities**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I upload an acting video (MP4/MOV/AVI, max 50MB, max 2 min)
**Then** the video is stored separately from the presentation video
**And** a thumbnail is auto-generated
**And** both videos are clearly labeled on my profile

**Given** I want to replace my acting video
**When** I upload a new one
**Then** the old video is replaced

**(FR12)**

---

### Story 3.5: Face Bio and Location

As a **Face**,
I want **to add my bio and location information**,
So that **producers know who I am and where I'm based**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I enter my bio (max 500 characters)
**Then** the bio is saved and displayed on my profile

**Given** I select my location (ville, quartier, pays)
**When** I save my profile
**Then** my location is displayed (e.g., "Cotonou, Akpakpa, Bénin")

**Given** I enter a bio exceeding 500 characters
**When** I try to save
**Then** I see a character count warning

**(FR13, FR14)**

---

### Story 3.6: Face Physical Characteristics

As a **Face**,
I want **to enter my physical characteristics (height, weight)**,
So that **producers can filter and find talents matching their requirements**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I enter my height (in cm) and weight (in kg)
**Then** the values are saved and displayed on my profile

**Given** I enter invalid values (negative or unrealistic)
**When** I try to save
**Then** I see validation errors

**(FR15)**

---

### Story 3.7: Face Category and Niche Selection

As a **Face**,
I want **to select my category and niche**,
So that **producers can find me based on my specialization**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I select my category from: acteur, influenceur, créateur, mannequin, figurant
**Then** my category is saved and displayed

**When** I select my niche from: beauté, nourriture, découverte, mode
**Then** my niche is saved and displayed

**Given** these are dropdown/select fields
**When** I view my public profile
**Then** category and niche are shown as badges or tags

**(FR16, FR17)**

---

### Story 3.8: Face Professional Experiences

As a **Face**,
I want **to list my professional experiences**,
So that **producers can see my track record**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I add an experience (title, description, year)
**Then** the experience is added to my list

**Given** I have multiple experiences
**When** I view my profile
**Then** experiences are displayed in chronological order (newest first)

**Given** I want to edit or delete an experience
**When** I use the edit/delete actions
**Then** the experience is updated or removed

**(FR18)**

---

### Story 3.9: Face Tarifs (Rates)

As a **Face**,
I want **to set my hourly and daily rates in XOF**,
So that **producers know my pricing upfront**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I enter my tarif_horaire and tarif_journalier (in XOF)
**Then** the rates are saved as integers

**Given** I view my profile
**When** rates are displayed
**Then** they show formatted currency (e.g., "75 000 XOF/jour")

**Given** I enter non-numeric values
**When** I try to save
**Then** I see validation errors

**(FR19)**

---

### Story 3.10: Face Availability Toggle

As a **Face**,
I want **to toggle my availability status**,
So that **I can indicate when I'm open to new opportunities**.

**Acceptance Criteria:**

**Given** I am on my dashboard or profile
**When** I toggle my availability to "Disponible"
**Then** my profile shows a green "Disponible" badge
**And** I appear in search results for available Faces

**When** I toggle to "Indisponible"
**Then** my profile shows a grey "Indisponible" badge
**And** my visibility in search results is affected accordingly

**(FR20)**

---

### Story 3.11: Face Profile Completion Indicator

As a **Face**,
I want **to see how complete my profile is**,
So that **I know what information is missing to attract producers**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view my profile completion status
**Then** I see a percentage or progress bar

**Given** my profile is missing required fields
**When** I view the completion indicator
**Then** I see which fields are incomplete (e.g., "Ajoutez une vidéo de présentation")

**Given** all required fields are filled (photo, 2 videos, bio, location, category, tarifs)
**When** I view completion
**Then** I see "Profil complet" (100%)

**(FR21)**

---

## Epic 4: Producer Profile

### Story 4.1: Producer Profile Photo

As a **Producer**,
I want **to upload my profile photo**,
So that **Faces can identify me and my brand**.

**Acceptance Criteria:**

**Given** I am logged in as a Producer on my profile page
**When** I upload a profile photo (JPG, PNG, max 5MB)
**Then** the image is stored and associated with my profile
**And** a thumbnail is generated
**And** my profile displays the new photo

**(FR22)**

---

### Story 4.2: Producer Bio

As a **Producer**,
I want **to write a bio describing myself or my agency**,
So that **Faces understand who they might work with**.

**Acceptance Criteria:**

**Given** I am on my profile edit page
**When** I enter my bio (max 500 characters)
**Then** the bio is saved and displayed on my profile

**(FR23)**

---

### Story 4.3: Agency Logo Upload

As a **Producer Agence**,
I want **to upload my agency logo**,
So that **my brand is professionally represented on the platform**.

**Acceptance Criteria:**

**Given** I am logged in as a Producer with type "Agence"
**When** I upload a logo (JPG, PNG, max 2MB)
**Then** the logo is stored and displayed on my profile
**And** the logo appears alongside my agency name

**Given** I am a Particulier
**When** I view my profile edit page
**Then** the logo upload option is not available

**(FR24)**

---

### Story 4.4: Producer Profile Display

As a **Face or visitor**,
I want **to view a Producer's complete profile**,
So that **I can evaluate their credibility before applying to their missions**.

**Acceptance Criteria:**

**Given** I am viewing a Producer's profile
**When** the page loads
**Then** I see their photo, name (or agency name), bio, and logo (if agency)
**And** I see their average rating (if they have completed missions)
**And** I see how many missions they have posted

---

## Epic 5: Mission Management

### Story 5.1: Create Mission Database Schema

As a **developer**,
I want **the missions table with all required fields**,
So that **Producers can publish detailed casting calls**.

**Acceptance Criteria:**

**Given** the database migration runs
**When** the missions table is created
**Then** it includes: id, producer_id, titre, description, date_tournage, profil_recherche, budget, date_limite_candidature, nombre_faces_voulu, type_mission, genre_voulu, lieu, duree, status, timestamps
**And** foreign key to producers table is properly indexed
**And** status defaults to 'draft'

---

### Story 5.2: Publish Mission

As a **Producer**,
I want **to publish a mission with all required details**,
So that **Faces can discover and apply to my casting call**.

**Acceptance Criteria:**

**Given** I am logged in as a Producer
**When** I fill out the mission form with all required fields
**Then** the mission is created with status "published"
**And** I am redirected to my missions list
**And** the mission appears in public listings

**(FR25)**

---

### Story 5.3: Edit Mission

As a **Producer**,
I want **to modify a mission I have published**,
So that **I can update details or correct mistakes**.

**Acceptance Criteria:**

**Given** I am viewing my mission
**When** I click "Modifier"
**Then** I see the edit form pre-filled with current values

**Given** I update any field and save
**When** the form is submitted
**Then** the mission is updated
**And** I see a success message

**(FR26)**

---

### Story 5.4: Delete Mission

As a **Producer**,
I want **to delete a mission I have published**,
So that **I can remove opportunities I no longer need**.

**Acceptance Criteria:**

**Given** I am viewing my mission
**When** I click "Supprimer"
**Then** I see a confirmation dialog

**Given** I confirm deletion
**When** the action is processed
**Then** the mission no longer appears in listings
**And** I see a success message

**(FR27)**

---

### Story 5.5: Producer Missions List

As a **Producer**,
I want **to see all my missions with their statuses**,
So that **I can manage my casting calls effectively**.

**Acceptance Criteria:**

**Given** I am on my "Mes missions" page
**When** the page loads
**Then** I see a list of all my missions
**And** each mission shows: titre, date_tournage, budget, status, nombre de candidatures

**(FR28)**

---

### Story 5.6: Close Mission

As a **Producer**,
I want **to close a mission to stop accepting new candidatures**,
So that **I can focus on reviewing existing applicants**.

**Acceptance Criteria:**

**Given** I am viewing my published mission
**When** I click "Clôturer les candidatures"
**Then** the mission status changes to "closed"
**And** the mission no longer accepts new candidatures

**(FR29)**

---

### Story 5.7: Mark Mission as Completed

As a **Producer**,
I want **to mark a mission as completed after the work is done**,
So that **the workflow is properly concluded and ratings can be submitted**.

**Acceptance Criteria:**

**Given** I have a mission with accepted candidatures
**When** I click "Marquer comme terminée"
**Then** the mission status changes to "completed"
**And** rating is now enabled for this mission

**(FR30)**

---

### Story 5.8: Face Browse Available Missions

As a **Face**,
I want **to browse all available missions**,
So that **I can find opportunities that match my profile**.

**Acceptance Criteria:**

**Given** I am logged in as a Face
**When** I navigate to "Missions disponibles"
**Then** I see a paginated list of published missions

**(FR31)**

---

### Story 5.9: Face Filter Missions

As a **Face**,
I want **to filter missions by city, budget, date, and type**,
So that **I can find relevant opportunities quickly**.

**Acceptance Criteria:**

**Given** I am on the missions list
**When** I apply filters (ville, budget min/max, date, type)
**Then** the list updates to show only matching missions

**(FR32)**

---

### Story 5.10: Face View Mission Detail

As a **Face**,
I want **to view the complete details of a mission**,
So that **I can decide whether to apply**.

**Acceptance Criteria:**

**Given** I click on a mission from the list
**When** the detail page loads
**Then** I see all mission information
**And** I see a "Postuler" button if the mission is open

**(FR33)**

---

## Epic 6: Candidature Workflow

### Story 6.1: Create Candidature Database Schema

As a **developer**,
I want **the candidatures table with proper status workflow**,
So that **the application and confirmation process can be tracked**.

**Acceptance Criteria:**

**Given** the database migration runs
**When** the candidatures table is created
**Then** it includes: id, face_id, mission_id, message_motivation, status, timestamps
**And** status enum includes: pending, accepted, confirmed, in_progress, completed, rejected

---

### Story 6.2: Face Apply to Mission

As a **Face**,
I want **to apply to a mission with an optional motivation message**,
So that **I can express my interest and stand out to the Producer**.

**Acceptance Criteria:**

**Given** I am viewing a published mission detail
**When** I click "Postuler" and submit
**Then** a candidature record is created with status "pending"
**And** I see a success message "Candidature envoyée"

**(FR34)**

---

### Story 6.3: Face View My Candidatures

As a **Face**,
I want **to see all my candidatures with their current status**,
So that **I can track my applications and upcoming work**.

**Acceptance Criteria:**

**Given** I am on my "Mes candidatures" page
**When** the page loads
**Then** I see all my candidatures grouped by status

**(FR35)**

---

### Story 6.4: Producer View Mission Candidatures

As a **Producer**,
I want **to see all candidatures received for each of my missions**,
So that **I can review applicants and make selections**.

**Acceptance Criteria:**

**Given** I am viewing my mission detail
**When** I click on "Candidatures" section
**Then** I see a list of all candidatures for this mission

**(FR36)**

---

### Story 6.5: Producer View Candidate Full Profile

As a **Producer**,
I want **to view a candidate's complete profile including videos**,
So that **I can evaluate their talent before accepting**.

**Acceptance Criteria:**

**Given** I am viewing candidatures for my mission
**When** I click on a Face's name
**Then** I see the Face's complete profile with both videos playable

**(FR37)**

---

### Story 6.6: Producer Accept Candidature

As a **Producer**,
I want **to accept a Face's candidature**,
So that **they know they are selected and can confirm their participation**.

**Acceptance Criteria:**

**Given** I am viewing a pending candidature
**When** I click "Accepter"
**Then** the candidature status changes to "accepted"
**And** the chat becomes available between us

**(FR38)**

---

### Story 6.7: Producer Reject Candidature

As a **Producer**,
I want **to reject a Face's candidature**,
So that **they know they were not selected**.

**Acceptance Criteria:**

**Given** I am viewing a pending candidature
**When** I click "Refuser" and confirm
**Then** the candidature status changes to "rejected"

**(FR39)**

---

### Story 6.8: Face Confirm Mission (2-Step Validation)

As a **Face**,
I want **to confirm my participation after my candidature is accepted**,
So that **the Producer knows I'm committed to the mission**.

**Acceptance Criteria:**

**Given** my candidature has status "accepted"
**When** I click "Confirmer ma participation"
**Then** the candidature status changes to "in_progress"
**And** I see a success message "Participation confirmée"

**(FR40)**

---

### Story 6.9: Face Candidature Status Notifications

As a **Face**,
I want **to be notified when my candidature status changes**,
So that **I can respond quickly to opportunities**.

**Acceptance Criteria:**

**Given** a Producer accepts or rejects my candidature
**When** the status changes
**Then** I see an in-app notification

**(FR41)**

---

## Epic 7: Messaging System

### Story 7.1: Create Messaging Database Schema

As a **developer**,
I want **the conversations and messages tables**,
So that **chat functionality can be stored and retrieved**.

**Acceptance Criteria:**

**Given** the database migration runs
**When** the tables are created
**Then** `conversations` and `messages` tables are properly structured

---

### Story 7.2: Conditional Chat Unlock

As a **system**,
I want **chat to be automatically unlocked when a candidature is accepted**,
So that **only matched parties can communicate**.

**Acceptance Criteria:**

**Given** a Producer accepts a candidature
**When** the status changes to "accepted"
**Then** a conversation record is created

**(FR42, FR43 prerequisite)**

---

### Story 7.3: Face Send Message to Producer

As a **Face**,
I want **to send messages to a Producer after my candidature is accepted**,
So that **I can coordinate details about the mission**.

**Acceptance Criteria:**

**Given** my candidature has been accepted
**When** I send a message
**Then** the message appears in the conversation

**(FR42)**

---

### Story 7.4: Producer Send Message to Face

As a **Producer**,
I want **to send messages to a Face whose candidature I accepted**,
So that **I can provide instructions and coordinate the mission**.

**Acceptance Criteria:**

**Given** I have accepted a candidature
**When** I send a message
**Then** the message appears in the conversation

**(FR43)**

---

### Story 7.5: View Conversation History

As a **user**,
I want **to view the complete history of my conversations**,
So that **I can reference past discussions**.

**Acceptance Criteria:**

**Given** I have an active conversation
**When** I open the chat
**Then** I see all previous messages in chronological order

**(FR44)**

---

### Story 7.6: Manual Message Refresh

As a **user**,
I want **to manually refresh my messages**,
So that **I can see new messages without page reload**.

**Acceptance Criteria:**

**Given** I am in a conversation
**When** I click "Actualiser"
**Then** new messages are fetched and displayed

**(FR45)**

---

### Story 7.7: Conversations List View

As a **user**,
I want **to see all my conversations in one place**,
So that **I can easily navigate between different chats**.

**Acceptance Criteria:**

**Given** I navigate to "Messages"
**When** the page loads
**Then** I see a list of all my conversations with unread counts

---

## Epic 8: Rating & Reviews

### Story 8.1: Create Ratings Database Schema

As a **developer**,
I want **the ratings table to store bidirectional reviews**,
So that **both Faces and Producers can be rated**.

**Acceptance Criteria:**

**Given** the database migration runs
**When** the ratings table is created
**Then** it supports polymorphic ratings with score and comment

---

### Story 8.2: Face Rate Producer After Mission

As a **Face**,
I want **to rate a Producer after the mission is completed**,
So that **other Faces can see the Producer's reputation**.

**Acceptance Criteria:**

**Given** a mission I participated in is "Terminée"
**When** I submit a rating (1-5 stars, optional comment)
**Then** the rating is saved

**(FR46)**

---

### Story 8.3: Producer Rate Face After Mission

As a **Producer**,
I want **to rate a Face after the mission is completed**,
So that **other Producers can see the Face's professionalism**.

**Acceptance Criteria:**

**Given** I have marked a mission as "Terminée"
**When** I submit a rating for a Face
**Then** the rating is saved

**(FR47)**

---

### Story 8.4: Display Face Average Rating

As a **user**,
I want **to see a Face's average rating**,
So that **I can evaluate their reputation**.

**Acceptance Criteria:**

**Given** a Face has received ratings
**When** I view their profile
**Then** I see their average rating and review count

**(FR48)**

---

### Story 8.5: Display Producer Average Rating

As a **user**,
I want **to see a Producer's average rating**,
So that **I can evaluate their trustworthiness**.

**Acceptance Criteria:**

**Given** a Producer has received ratings
**When** I view their profile
**Then** I see their average rating

**(FR49)**

---

### Story 8.6: View Reviews List

As a **user**,
I want **to see all reviews received by a Face or Producer**,
So that **I can read detailed feedback**.

**Acceptance Criteria:**

**Given** I am viewing a profile
**When** I access the reviews section
**Then** I see all reviews with rater name, stars, comment, and date

**(FR50)**

---

## Epic 9: Face Dashboard

### Story 9.1: Face Dashboard KPIs

As a **Face**,
I want **to see my mission KPIs on my dashboard**,
So that **I can track my activity at a glance**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** the page loads
**Then** I see KPI cards for missions by status

**(FR51)**

---

### Story 9.2: Face Wallet UI (Inactive)

As a **Face**,
I want **to see my wallet balance on the dashboard**,
So that **I'm aware of the payment feature coming in V2**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view the wallet section
**Then** I see "0 XOF" with "Bientôt disponible" message

**(FR52)**

---

### Story 9.3: Face Dashboard Charts

As a **Face**,
I want **to see charts showing my activity evolution**,
So that **I can visualize my career progress over time**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view the analytics section
**Then** I see charts for candidatures and missions per month

**(FR53)**

---

### Story 9.4: Face Quick Access to Missions

As a **Face**,
I want **quick access to available missions from my dashboard**,
So that **I can find opportunities without navigating away**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I click "Voir les missions"
**Then** I am taken to the missions list

**(FR54)**

---

### Story 9.5: Face Dashboard Layout

As a **Face**,
I want **a well-organized dashboard layout**,
So that **I can access all important information easily**.

**Acceptance Criteria:**

**Given** I am on my Face dashboard
**When** the page loads
**Then** I see KPIs, wallet, charts, and quick actions in a responsive layout

---

## Epic 10: Producer Dashboard

### Story 10.1: Producer Dashboard KPIs

As a **Producer**,
I want **to see my mission KPIs on my dashboard**,
So that **I can track my casting activity**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** the page loads
**Then** I see KPI cards for missions by status

**(FR55)**

---

### Story 10.2: Producer Total Candidatures Received

As a **Producer**,
I want **to see the total number of candidatures I've received**,
So that **I can measure interest in my missions**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view the candidatures KPI
**Then** I see total candidatures across all missions

**(FR56)**

---

### Story 10.3: Producer Collaborators Count

As a **Producer**,
I want **to see how many unique Faces I've worked with**,
So that **I can track my network growth**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view the collaborators section
**Then** I see count of unique Faces from completed missions

**(FR57)**

---

### Story 10.4: Producer Rating Display

As a **Producer**,
I want **to see my average rating on my dashboard**,
So that **I can monitor my reputation**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view the rating section
**Then** I see my average rating and review count

**(FR58)**

---

### Story 10.5: Producer Advanced Stats

As a **Producer**,
I want **to see advanced statistics about my hiring activity**,
So that **I can optimize my casting process**.

**Acceptance Criteria:**

**Given** I am on my dashboard
**When** I view advanced stats
**Then** I see acceptance rate, completed missions, average response time

**(FR59)**

---

### Story 10.6: Producer Dashboard Layout

As a **Producer**,
I want **a well-organized dashboard layout**,
So that **I can manage my casting activities efficiently**.

**Acceptance Criteria:**

**Given** I am on my Producer dashboard
**When** the page loads
**Then** I see all KPIs and quick actions in a responsive layout

---

## Epic 11: Public Access & Discovery

### Story 11.0a: Public Header Refactoring

As a **visitor**,
I want **a responsive, well-designed header**,
So that **I can navigate the site easily on any device**.

**Acceptance Criteria:**

**Given** I visit any public page on mobile, tablet, or desktop
**When** the page loads
**Then** I see a responsive header with logo, navigation, and mobile menu

**Given** I am on mobile (< 768px)
**When** I tap the menu icon
**Then** I see a mobile navigation menu with all links

**Given** I am a visitor or logged-in user
**When** I view the header
**Then** I see appropriate CTAs (Login/Register for visitors, Dashboard for users)

**(Identified in Epic 10 Retrospective - prerequisite for Landing Page)**

---

### Story 11.0b: Public Footer Creation

As a **visitor**,
I want **a footer with useful links and information**,
So that **I can find legal pages, contact info, and social links**.

**Acceptance Criteria:**

**Given** I visit any public page
**When** I scroll to the bottom
**Then** I see a footer with navigation links, legal pages, and social links

**Given** I want to find legal information
**When** I look at the footer
**Then** I see links to Terms of Service, Privacy Policy, and other legal pages

**Given** I want to connect on social media
**When** I look at the footer
**Then** I see links to WEACT social media profiles

**(Identified in Epic 10 Retrospective - prerequisite for Landing Page)**

---

### Story 11.1: Landing Page

As a **visitor**,
I want **to see a landing page presenting WEACT**,
So that **I understand the platform's value proposition**.

**Acceptance Criteria:**

**Given** I visit the WEACT homepage
**When** the page loads
**Then** I see hero, switch toggle, how-it-works, and CTAs

**(FR75)**

---

### Story 11.2: Landing Page Face/Producer Switch

As a **visitor**,
I want **to toggle between Face and Producer perspectives**,
So that **I see content relevant to my interest**.

**Acceptance Criteria:**

**Given** I am on the landing page
**When** I click the switch
**Then** the content updates to the selected perspective

**(FR76)**

---

### Story 11.3: Public Faces List

As a **visitor**,
I want **to browse a list of Faces without logging in**,
So that **I can preview the talent pool**.

**Acceptance Criteria:**

**Given** I navigate to "/faces"
**When** the page loads
**Then** I see a grid of Face cards with limited info

**(FR77)**

---

### Story 11.4: Public Face Profile (Limited)

As a **visitor**,
I want **to view a Face's public profile with limited information**,
So that **I can evaluate if I want to register**.

**Acceptance Criteria:**

**Given** I click on a Face card
**When** the profile loads
**Then** I see limited info with CTA to register for full access

**(FR78)**

---

### Story 11.5: Public Faces Filters

As a **visitor**,
I want **to filter Faces by city, category, and niche**,
So that **I can find relevant talents**.

**Acceptance Criteria:**

**Given** I am on the public Faces list
**When** I apply filters
**Then** the list updates to show matching Faces

**(FR79)**

---

### Story 11.6: Public Missions List

As a **visitor**,
I want **to browse available missions without logging in**,
So that **I can see opportunities before registering**.

**Acceptance Criteria:**

**Given** I navigate to "/missions"
**When** the page loads
**Then** I see a list of published missions

**(FR80)**

---

### Story 11.7: Public Mission Detail

As a **visitor**,
I want **to view a mission's full details without logging in**,
So that **I can evaluate the opportunity**.

**Acceptance Criteria:**

**Given** I click on a mission
**When** the detail page loads
**Then** I see full info with CTA to login to apply

**(FR81)**

---

### Story 11.8: Registration Access Points

As a **visitor**,
I want **clear paths to register as Face or Producer**,
So that **I can easily join the platform**.

**Acceptance Criteria:**

**Given** I am on any public page
**When** I want to register
**Then** I find clear CTAs to Face or Producer registration

**(FR82)**

---

### Story 11.9: Public Faces Search

As a **visitor**,
I want **to search for talents by name or keyword on the public Faces list**,
So that **I can quickly find a specific talent without scrolling through all results**.

**Acceptance Criteria:**

**Given** I am on the `/faces` page
**When** I type at least 2 characters in the search input
**Then** the list updates to show only Faces matching my query (debounced, case-insensitive partial match across name, username, and bio)

**(Added post-planning: search complement to FR79 filters)**

---

## Epic 12: Blog & Resources

### Story 12.1: Create Articles Database Schema

As a **developer**,
I want **the articles table for blog content**,
So that **admins can publish educational resources**.

**Acceptance Criteria:**

**Given** the database migration runs
**When** the articles table is created
**Then** it includes title, slug, content, category, status, timestamps

---

### Story 12.2: Admin Create Article

As an **Admin**,
I want **to create a new article with rich text and images**,
So that **I can publish educational content**.

**Acceptance Criteria:**

**Given** I am logged in as Admin
**When** I create an article with all fields
**Then** the article is saved with selected status

**(FR60)**

---

### Story 12.3: Admin Categorize Article

As an **Admin**,
I want **to assign a category to each article**,
So that **content is organized**.

**Acceptance Criteria:**

**Given** I am creating/editing an article
**When** I select a category
**Then** the article is categorized

**(FR61)**

---

### Story 12.4: Admin Set Article Status

As an **Admin**,
I want **to set an article as Draft or Published**,
So that **I can control visibility**.

**Acceptance Criteria:**

**Given** I set status to "Publié"
**When** I save
**Then** the article becomes publicly visible

**(FR62)**

---

### Story 12.5: Admin Edit Article

As an **Admin**,
I want **to modify an existing article**,
So that **I can update content**.

**Acceptance Criteria:**

**Given** I edit an article
**When** I save changes
**Then** the article is updated

**(FR63)**

---

### Story 12.6: Admin Delete Article

As an **Admin**,
I want **to delete an article**,
So that **I can remove outdated content**.

**Acceptance Criteria:**

**Given** I delete an article
**When** I confirm
**Then** the article is removed

**(FR64)**

---

### Story 12.7: Public Articles List

As a **visitor**,
I want **to browse published articles**,
So that **I can learn about the platform**.

**Acceptance Criteria:**

**Given** I navigate to "/ressources"
**When** the page loads
**Then** I see a paginated list of published articles

**(FR65)**

---

### Story 12.8: Public Article Reading

As a **visitor**,
I want **to read a full article**,
So that **I can learn from the content**.

**Acceptance Criteria:**

**Given** I click on an article
**When** the page loads
**Then** I see the full rich text content

**(FR66)**

---

### Story 12.9: Public Articles Filter by Category

As a **visitor**,
I want **to filter articles by category**,
So that **I can find relevant content**.

**Acceptance Criteria:**

**Given** I select a category filter
**When** the list updates
**Then** only articles in that category are shown

**(FR67)**

---

## Epic 13: Administration

### Story 13.1: Admin Authentication

As an **Admin**,
I want **to login to a separate admin interface**,
So that **I can access management tools**.

**Acceptance Criteria:**

**Given** I navigate to "/admin/login"
**When** I submit valid admin credentials
**Then** I am authenticated and redirected to admin dashboard

---

### Story 13.2: Admin Create Other Admins

As an **Admin**,
I want **to create additional admin accounts**,
So that **team members can help manage the platform**.

**Acceptance Criteria:**

**Given** I create a new admin
**When** I submit the form
**Then** the new admin can login

**(FR8)**

---

### Story 13.3: Admin Dashboard KPIs

As an **Admin**,
I want **to see global platform KPIs**,
So that **I can monitor platform health**.

**Acceptance Criteria:**

**Given** I am on the admin dashboard
**When** the page loads
**Then** I see total Faces, Producers, missions active/completed

**(FR68)**

---

### Story 13.4: Admin CRUD Faces

As an **Admin**,
I want **to create, read, update, and delete Face accounts**,
So that **I can manage Face users**.

**Acceptance Criteria:**

**Given** I navigate to Faces management
**When** I perform CRUD operations
**Then** Face accounts are created/updated/deleted accordingly

**(FR69)**

---

### Story 13.5: Admin CRUD Producers

As an **Admin**,
I want **to create, read, update, and delete Producer accounts**,
So that **I can manage Producer users**.

**Acceptance Criteria:**

**Given** I navigate to Producers management
**When** I perform CRUD operations
**Then** Producer accounts are managed accordingly

**(FR70)**

---

### Story 13.6: Admin CRUD Admins

As an **Admin**,
I want **to manage other admin accounts**,
So that **I can control platform access**.

**Acceptance Criteria:**

**Given** I navigate to Admins management
**When** I perform CRUD operations
**Then** Admin accounts are managed (cannot delete own account)

**(FR71)**

---

### Story 13.7: Admin View Detailed Profiles

As an **Admin**,
I want **to view complete profiles of any user**,
So that **I can review content and handle issues**.

**Acceptance Criteria:**

**Given** I view a Face or Producer detail
**When** the page loads
**Then** I see all profile info including videos and activity

**(FR72)**

---

### Story 13.8: Admin View All Missions

As an **Admin**,
I want **to view all missions on the platform**,
So that **I can monitor activity**.

**Acceptance Criteria:**

**Given** I navigate to Missions in admin
**When** the page loads
**Then** I see all missions with filtering options

**(FR73)**

---

### Story 13.9: Admin Activate/Deactivate Accounts

As an **Admin**,
I want **to activate or deactivate user accounts**,
So that **I can moderate the platform**.

**Acceptance Criteria:**

**Given** I deactivate an account
**When** the user tries to login
**Then** they see "Votre compte a été désactivé"

**(FR74)**

---

### Story 13.10: Admin Dashboard Layout

As an **Admin**,
I want **a comprehensive admin interface**,
So that **I can efficiently manage all platform aspects**.

**Acceptance Criteria:**

**Given** I am logged in as Admin
**When** I view the interface
**Then** I see sidebar navigation with all management sections
