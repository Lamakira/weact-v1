---
stepsCompleted: [1, 2, 3, 4, 7, 8, 9, 10, 11]
inputDocuments:
  - path: "docs/weact-brief.md"
    type: "product-brief"
    loaded: true
documentCounts:
  briefs: 1
  research: 0
  brainstorming: 0
  projectDocs: 0
workflowType: 'prd'
lastStep: 8
projectType: 'greenfield'
---

# Product Requirements Document - WEACT

**Author:** User
**Date:** 2026-01-06

## Executive Summary

WEACT est une plateforme web de mise en relation professionnelle entre **Faces** (acteurs, mannequins, influenceurs, créateurs de contenu, figurants) et **Producteurs** (agences ou particuliers) au Bénin.

La plateforme répond à un besoin critique du marché béninois de production audiovisuelle : l'absence d'un outil centralisé et professionnel pour connecter talents et producteurs. Les producteurs peinent à identifier des talents qualifiés avec portfolios vérifiables, tandis que les Faces manquent d'une vitrine professionnelle et d'un canal fiable pour recevoir des opportunités légitimes.

**Utilisateurs cibles:**
- **Faces:** Acteurs, mannequins, influenceurs, créateurs, figurants basés au Bénin (Cotonou, Porto-Novo, Parakou)
- **Producteurs:** Agences de production et particuliers pour publicités, films, courts-métrages, clips musicaux
- **Admins:** Équipe WEACT pour modération et gestion de la plateforme

### Ce qui rend WEACT unique

WEACT n'est pas un simple "Upwork pour acteurs" mais une **plateforme de confiance professionnelle** conçue spécifiquement pour le marché béninois:

1. **Écosystème de confiance mutuelle:** Système de notation bidirectionnel où Faces et Producteurs se notent mutuellement, créant une réputation vérifiable pour tous les acteurs.

2. **Protection contre le spam:** Chat conditionnel débloqué UNIQUEMENT après acceptation d'une candidature, protégeant les Faces des sollicitations non professionnelles.

3. **Engagement mutuel garanti:** Validation en 2 étapes (Producteur accepte → Face confirme) assurant l'engagement des deux parties avant le démarrage.

4. **Flexibilité financière:** Wallet intégré permettant aux Faces d'accumuler leurs revenus et de retirer selon leurs besoins, avec commission transparente (15% Face + 15% Producteur).

5. **Évaluation vidéo du talent:** 2 types de vidéos obligatoires (présentation + acting) permettant une évaluation réelle des capacités, au-delà des photos statiques.

6. **Gestion proactive des litiges (V2):** Process de médiation intégré avec intervention admin, pas seulement un système de notation passif.

7. **First Mover Advantage:** Aucune plateforme locale structurée n'existe actuellement au Bénin pour ce marché.

8. **Paiements locaux natifs (V2):** Intégration Mobile Money (MTN MoMo, Moov Money via Fedapay) avec devise XOF et contrats de cession de droits d'images.

## Project Classification

| Attribut | Valeur |
|----------|--------|
| **Type technique** | Web App (SPA Vue 3 + API Laravel 12) |
| **Domaine** | Marketplace / Talent Platform |
| **Complexité** | Medium (workflow multi-rôle) |
| **Contexte** | Greenfield - nouveau projet |
| **Marché cible** | Bénin (francophone, XOF) |

**Stack technique:**
- **Frontend:** Vue 3 (Composition API) + TypeScript + Pinia + Tailwind CSS
- **Backend:** Laravel 12 + MySQL + Sanctum (API tokens)
- **Paiements V2:** Fedapay (MTN MoMo, Moov Money)

## Success Criteria

### User Success

**Face - Moments de succès:**
- Première candidature envoyée dans les 24h suivant l'inscription
- Première mission acceptée et confirmée
- Premier paiement reçu via la plateforme (V2)
- Note moyenne >4/5 après 3 missions

**Face - Métriques mesurables:**
- Temps inscription → première candidature: <24h
- Taux de candidatures acceptées: >30%
- Taux de complétion profil (photos + 2 vidéos): >80%
- Missions répétées avec même producteur: indicateur de confiance

**Producteur - Moments de succès:**
- Publication de la première mission en <15 minutes
- Réception de candidatures qualifiées avec portfolios vidéo
- Cast complet trouvé pour une mission
- Mission terminée avec notation positive mutuelle

**Producteur - Métriques mesurables:**
- Temps pour publier première mission: <15 min
- Candidatures par mission: 8-10 en moyenne
- Temps pour accepter une candidature: <48h
- Taux de missions terminées avec succès: >80%
- Taux de retour (2ème mission publiée): >40%

### Business Success

**Objectifs 3 mois post-lancement (MVP):**

| Métrique | Objectif |
|----------|----------|
| Faces inscrites (profil complet) | 150 |
| Faces actives (≥1 candidature) | 100 |
| Producteurs inscrits | 30 |
| Producteurs actifs (≥1 mission) | 15 |
| Missions publiées | 25-30 |
| Missions complétées | 15-20 |
| Taux conversion candidature | >30% |
| Note moyenne plateforme | >4/5 |
| Ratio Face/Producteur | ~5:1 |

**Objectifs 12 mois (V2 avec paiements):**

| Métrique | Objectif |
|----------|----------|
| Faces actives | 300+ |
| Producteurs actifs | 80-100 |
| Missions/mois | 150-200 |
| Budget moyen mission | 50,000 XOF (~75€) |
| Volume transactions/mois | 7,500,000 XOF (~11,400€) |
| CA mensuel (30% commission) | 2,250,000 XOF (~3,420€) |
| CA annuel | ~27M XOF (~41,000€) |
| Parts de marché Cotonou | 40-50% |

### Technical Success

- **Performance:** Temps de chargement <2s sur mobile 4G
- **Disponibilité:** Uptime >99% (hors maintenance planifiée)
- **Upload vidéo:** Succès >95% pour fichiers <50MB
- **Responsive:** 100% fonctionnel sur mobile (cible principale)
- **Sécurité:** Zéro fuite de données personnelles

### Measurable Outcomes

**Validation du concept (MVP):**
- ✅ Des Faces postulent activement aux missions
- ✅ Des Producteurs publient des missions et recrutent
- ✅ Le workflow complet fonctionne jusqu'à la notation
- ✅ Les utilisateurs reviennent (rétention)

**Validation marché (12 mois):**
- ✅ Volume de transactions prouve la demande
- ✅ Parts de marché significatives à Cotonou
- ✅ Expansion géographique viable

## Product Scope

### MVP - Minimum Viable Product (P0-P1)

**Must-have pour lancement:**
- Authentification + Inscriptions (Face/Producteur)
- Profils Face complets (photos, 2 vidéos, bio, tarifs)
- Profils Producteur (photo, bio, catégorie)
- Publication et gestion de missions
- Système de candidatures complet
- Chat basique (débloqué après acceptation)
- Dashboards avec KPIs essentiels
- Notation bidirectionnelle (après mission terminée)
- Confirmation de mission en 2 étapes

### Growth Features (Post-MVP / V2)

**Priorité haute:**
- Paiement Mobile Money (Fedapay: MTN MoMo, Moov Money)
- Wallet Face avec retraits
- Système de commissions (15%+15%)
- Contrats de cession de droits (PDF)
- Notifications (push web, email)

**Priorité moyenne:**
- Landing page optimisée avec switch Face/Producteur
- Espace Admin complet (CRUD utilisateurs)
- Gestion des litiges
- Filtres avancés de recherche

### Vision (Future)

- Booking direct (sans mission publique)
- Recommandations ML (Faces similaires, missions suggérées)
- Interface multilangue (Français, Anglais, Fon?)
- API publique pour partenaires
- Connexion sociale (Google, Facebook)
- 2FA pour sécurité renforcée

## User Journeys

### Journey 1: Aminata Dossou - De l'ombre à la lumière (Face)

Aminata est une jeune femme de 24 ans à Cotonou qui jongle entre son travail de vendeuse dans une boutique de mode et ses rêves d'actrice. Elle a participé à quelques castings locaux et fait de la figuration pour un clip musical, mais elle peine à trouver des opportunités régulières. Les "producteurs" qui la contactent sur Instagram sont souvent des arnaqueurs ou des personnes aux intentions douteuses.

Un soir, après une énième sollicitation suspecte sur WhatsApp, elle découvre WEACT via une publication Facebook d'une amie mannequin. Intriguée par la promesse d'une plateforme sécurisée, elle s'inscrit et passe 30 minutes à créer son profil : ses plus belles photos de shooting, une vidéo de présentation où elle parle de sa passion pour le jeu, et une vidéo d'acting où elle interprète un monologue dramatique.

Deux jours plus tard, elle reçoit sa première notification : une agence de publicité cherche une jeune femme pour une publicité de télécom. Elle lit les détails de la mission - budget de 75,000 XOF, tournage d'une journée à Akpakpa - et postule avec un message personnalisé.

Le lendemain, elle voit le statut "Acceptée" et le chat se débloque. Elle peut enfin échanger directement avec le producteur - une vraie agence avec un profil vérifié et des notes positives. Elle confirme sa disponibilité, et la mission passe en "En cours".

Le jour du tournage se passe bien. À la fin, le producteur marque la mission comme "Terminée" et lui donne 5 étoiles. Trois mois plus tard, Aminata a complété 8 missions, sa note moyenne est de 4.8/5, et WEACT a transformé ses rêves en carrière.

**Capabilities révélées:**
- Inscription et création de profil complet (photos + 2 vidéos)
- Découverte et filtrage des missions disponibles
- Système de candidature avec message personnalisé
- Notifications de changement de statut
- Chat conditionnel après acceptation
- Confirmation de mission (2 étapes)
- Système de notation post-mission
- Dashboard avec historique des missions

### Journey 2a: Studio 229 - L'agence qui professionnalise son casting (Producteur Agence)

Studio 229 est une agence de publicité établie à Cotonou. Kofi, le directeur de production, passe habituellement des heures à contacter des talents via son réseau personnel. Pour une campagne nationale nécessitant 15 figurants et 2 acteurs principaux, il décide de tester WEACT.

Il crée le compte de l'agence en 10 minutes, ajoute le logo et une description. Il publie sa première mission avec tous les détails : budget, durée, lieu, profil recherché. En moins de 24 heures, il reçoit 12 candidatures. La différence ? Chaque profil a des vidéos - il peut voir le jeu d'acteur avant même de rencontrer les personnes.

Il accepte 3 candidats, le chat se débloque automatiquement. Il coordonne les horaires, les Faces confirment leur engagement. Le jour J, tout le monde est là, préparé.

Six mois plus tard, Studio 229 a publié 25 missions sur WEACT. Kofi a constitué une "shortlist" de 20 Faces fiables. Son temps de casting a été divisé par 3.

**Capabilities révélées:**
- Inscription Producteur (type Agence avec logo)
- Publication de mission avec champs complets
- Réception et gestion des candidatures
- Accès aux profils complets avec vidéos
- Acceptation/Refus de candidatures
- Chat avec Faces acceptées
- Marquage mission terminée
- Notation des Faces

### Journey 2b: Éric - Le réalisateur indépendant qui se structure (Producteur Particulier)

Éric Hounkanrin, 32 ans, est réalisateur freelance spécialisé dans les clips musicaux. Passionné mais désorganisé, il découvre WEACT quand un client lui demande "de nouveaux visages".

Il s'inscrit en tant que Particulier avec juste son nom et une bio. Il poste une mission simple avec un budget modeste. Il est surpris par la diversité des profils - des styles qu'il n'aurait jamais trouvés dans son réseau. Les vidéos de présentation lui permettent de voir l'énergie et le style.

La vraie révélation vient du système de notation. Avant d'accepter une candidature, il vérifie les notes et commentaires. Une Face avec 4.9/5 et 15 missions ? Il peut lui faire confiance.

**Capabilities révélées:**
- Inscription Producteur (type Particulier)
- Publication de mission simplifiée
- Filtrage des candidatures par notes/expérience
- Consultation des avis des autres producteurs
- Construction d'un historique de collaborations

### Journey 3: Fatou - La découverte qui mène à l'action (Internaute → Producteur)

Fatou, community manager à Porto-Novo, cherche "une vraie personne locale" pour une vidéo. Elle google "mannequin Bénin" et tombe sur WEACT.

Sans s'inscrire, elle navigue sur la page "Trouver une Face". Elle peut voir les photos, villes, catégories. Elle filtre par ville et type. Les profils publics montrent les infos essentielles.

Elle repère 3 profils intéressants. Pour voir plus de détails et contacter ces Faces, elle clique sur "Devenir Producteur". En 5 minutes, elle a accès aux profils complets.

**Capabilities révélées:**
- Pages publiques accessibles sans connexion
- Profils Face publics (infos limitées)
- Filtres de recherche publics
- Call-to-action vers inscription
- Conversion visiteur → Producteur

### Journey 4: Raoul - Le gardien de la confiance (Admin)

Raoul fait partie de l'équipe WEACT. Chaque matin, il consulte son dashboard Admin avec les KPIs clés : Faces actives, Producteurs, missions en cours.

Il remarque une inscription suspecte et désactive le compte en attendant vérification. Il consulte les signalements, vérifie les historiques, et maintient la qualité de l'écosystème.

Il génère des rapports hebdomadaires : taux de conversion, nouvelles inscriptions, performers. Son rôle est crucial : maintenir la confiance qui fait la valeur de WEACT.

**Capabilities révélées:**
- Dashboard Admin avec KPIs globaux
- CRUD utilisateurs (Faces, Producteurs, Admins)
- Visualisation profils détaillés
- Modération (activer/désactiver comptes)
- Visualisation de toutes les missions
- Génération de rapports/stats

### Journey Requirements Summary

| Parcours | Capabilities clés |
|----------|-------------------|
| **Face** | Inscription, Profil (photos/vidéos), Découverte missions, Candidature, Chat, Confirmation, Notation, Dashboard |
| **Producteur Agence** | Inscription Agence, Publication mission, Gestion candidatures, Chat, Notation, Historique |
| **Producteur Particulier** | Inscription Particulier, Publication simplifiée, Filtrage par notes |
| **Internaute** | Pages publiques, Profils limités, Recherche/filtres, Conversion |
| **Admin** | Dashboard KPIs, CRUD users, Modération, Visualisation, Rapports |

## Web App Specific Requirements

### Project-Type Overview

WEACT est une **Single Page Application (SPA)** construite avec Vue 3 et Tailwind CSS, consommant une API REST Laravel 12. L'architecture est optimisée pour une expérience mobile-first sur le marché béninois où la majorité du trafic provient de smartphones.

### Technical Architecture Considerations

**Architecture Frontend:**
- **Framework:** Vue 3 avec Composition API
- **State Management:** Pinia
- **Routing:** Vue Router (client-side)
- **Styling:** Tailwind CSS
- **Build Tool:** Vite
- **HTTP Client:** Axios

**Architecture Backend:**
- **Framework:** Laravel 12
- **Base de données:** MySQL
- **Authentification:** Laravel Sanctum (API tokens)
- **Storage:** Local (MVP) → DigitalOcean Spaces (V2)

### Browser Support Matrix

| Navigateur | Version minimum | Priorité |
|------------|-----------------|----------|
| Chrome Mobile | 2 dernières versions | Haute |
| Safari iOS | 2 dernières versions | Haute |
| Samsung Internet | 2 dernières versions | Haute |
| Chrome Desktop | 2 dernières versions | Moyenne |
| Firefox | 2 dernières versions | Basse |
| Edge | 2 dernières versions | Basse |

**Note:** IE11 non supporté. Focus sur les navigateurs mobiles modernes.

### Responsive Design Strategy

**Approche:** Mobile-first obligatoire

| Breakpoint | Cible | Priorité |
|------------|-------|----------|
| < 640px | Smartphones (portrait) | Critique |
| 640-768px | Smartphones (landscape) / Petites tablettes | Haute |
| 768-1024px | Tablettes | Moyenne |
| > 1024px | Desktop | Basse |

**Principes:**
- Tous les composants conçus mobile-first
- Touch targets minimum 44x44px
- Navigation adaptative (hamburger menu mobile, nav bar desktop)
- Formulaires optimisés pour clavier mobile

### Performance Targets

| Métrique | Objectif MVP | Mesure |
|----------|--------------|--------|
| First Contentful Paint | < 1.5s | 4G mobile |
| Time to Interactive | < 3s | 4G mobile |
| Largest Contentful Paint | < 2s | 4G mobile |
| Bundle size (initial) | < 300KB gzipped | - |
| Image lazy loading | Oui | - |
| Video streaming | Progressive | - |

**Optimisations prévues:**
- Code splitting par route
- Lazy loading des composants lourds
- Compression images côté client avant upload
- Thumbnails auto-générés pour vidéos

### SEO Strategy

**Niveau:** Basique (pages publiques essentielles)

| Page | SEO | Approche |
|------|-----|----------|
| Landing page | Oui | Meta tags complets, structured data |
| Liste Faces publiques | Oui | Meta tags dynamiques |
| Profil Face public | Oui | Open Graph pour partage social |
| Liste Missions | Oui | Meta tags basiques |
| Pages authentifiées | Non | noindex, nofollow |

**Implémentation:**
- vue-meta pour gestion des meta tags
- Open Graph tags pour partage Facebook/LinkedIn
- Sitemap.xml pour pages publiques
- robots.txt approprié

### Accessibility Level

**Niveau cible:** WCAG 2.1 Level A (Standard)

| Critère | Implémentation |
|---------|----------------|
| Alt text images | Obligatoire sur toutes les images |
| Navigation clavier | Tab navigation fonctionnelle |
| Contrastes | Ratio 4.5:1 minimum pour texte |
| Focus visible | Outline visible sur focus |
| Labels formulaires | Tous les inputs avec labels |
| Messages d'erreur | Annoncés aux lecteurs d'écran |

### Real-time Strategy

**MVP:** Polling/Refresh manuel
- Chat: Bouton "Actualiser" + polling toutes les 30s
- Notifications: Badge avec compteur, refresh au changement de page
- Statuts missions: Refresh manuel

**V2:** WebSockets (Laravel Echo + Pusher)
- Chat temps réel avec indicateur "en train d'écrire"
- Notifications push instantanées
- Mise à jour statuts en temps réel

### Implementation Considerations

**Sections non pertinentes (skipped):**
- native_features (pas d'app native)
- cli_commands (pas d'interface CLI)

**Dépendances clés:**
- Frontend: vue@3, pinia, vue-router, axios, tailwindcss
- Backend: laravel@11, sanctum, intervention/image
- Dev: vite, eslint, prettier

## Project Scoping & Phased Development

### MVP Strategy & Philosophy

**MVP Approach:** Experience MVP
Délivrer l'expérience utilisateur clé (workflow Mission complet) avec les fonctionnalités essentielles, tout en éduquant le marché via du contenu.

**Justification:**
- Marché béninois à éduquer (first mover)
- Besoin de prouver la valeur avant intégration paiements
- Focus sur la confiance et la qualité des interactions

**Resource Requirements:**
- 1-2 développeurs full-stack (Laravel + Vue)
- 1 designer UI/UX (temps partiel ou maquettes Figma existantes)
- Timeline: 4 semaines MVP

### MVP Feature Set (Phase 1)

**Core User Journeys Supported:**
- ✅ Journey Face (Aminata) - complet
- ✅ Journey Producteur Agence (Studio 229) - complet
- ✅ Journey Producteur Particulier (Éric) - complet
- ✅ Journey Internaute (Fatou) - conversion vers inscription
- ✅ Journey Admin (Raoul) - modération et CRUD basique

**Must-Have Capabilities (P0 - Critique):**

| Feature | Justification |
|---------|---------------|
| Auth + Inscriptions | Sans ça, pas d'utilisateurs |
| Profils Face (photos + 2 vidéos) | Différenciateur clé - évaluation vidéo |
| Profils Producteur | Identité et confiance |
| CRUD Missions | Cœur du business |
| Système candidatures | Workflow principal |
| Chat conditionnel | Différenciateur - protection spam |
| Confirmation 2 étapes | Différenciateur - engagement mutuel |

**Should-Have Capabilities (P1 - Important):**

| Feature | Justification |
|---------|---------------|
| Dashboards Face/Producteur | KPIs et engagement |
| Notation bidirectionnelle | Écosystème de confiance |
| Filtres recherche Faces/Missions | UX essentielle |
| Module Ressources/Blog (basique) | Éducation marché |

**Could-Have Capabilities (P2 - Nice-to-have):**

| Feature | Justification |
|---------|---------------|
| Landing page optimisée | Marketing/conversion |
| Espace Admin complet | Ops internes |
| Pages publiques SEO | Acquisition organique |
| Ressources/Blog (avancé) | Catégories, rich content |

### Module Ressources/Blog

**Objectif:** Éduquer le marché béninois sur les bonnes pratiques de casting et de présentation professionnelle.

**Fonctionnalités Admin:**
- CRUD articles (Créer, Lire, Modifier, Supprimer)
- Éditeur rich text pour contenu
- Upload images/captures d'écran
- Catégorisation (ex: "Conseils Face", "Guide Producteur", "Actualités")
- Gestion statut: Brouillon / Publié

**Fonctionnalités Public:**
- Liste articles publiés avec pagination
- Lecture article complet
- Filtrage par catégorie
- Accessible sans connexion (SEO)

**Exemples de contenu prévu:**
- "Comment réussir sa vidéo d'acting en 5 étapes"
- "Le guide du casting parfait pour producteurs"
- "Optimiser son profil Face pour plus de missions"
- "Comprendre le workflow WEACT"

**Priorité:** P1-P2 (MVP basique, enrichissement V2)

### Post-MVP Features (Phase 2 - V2)

**Paiements & Finance:**
- Intégration Fedapay (MTN MoMo, Moov Money)
- Wallet Face avec historique
- Système retraits
- Commissions automatiques (15% + 15%)

**Contrats & Légal:**
- Génération PDF contrats cession droits
- Signature électronique (checkbox horodatée)
- Stockage sécurisé contrats

**Communication:**
- WebSockets temps réel (Laravel Echo + Pusher)
- Notifications push web
- Notifications email
- Indicateur "en train d'écrire"

**Modération avancée:**
- Gestion litiges avec médiation
- Signalements utilisateurs
- Actions admin (remboursement, blocage)

### Expansion Features (Phase 3 - Vision)

- Booking direct (sans mission publique)
- Recommandations ML (Faces similaires)
- Interface multilangue (Français, Anglais, Fon)
- API publique partenaires
- Connexion sociale (Google, Facebook)
- 2FA sécurité renforcée
- Application mobile native (optionnel)

### Risk Mitigation Strategy

**Technical Risks:**

| Risque | Probabilité | Mitigation |
|--------|-------------|------------|
| Upload vidéos lent (50MB) | Haute | Compression client, progress bar, limite taille |
| Performance DB avec scale | Moyenne | Indexation, pagination, caching Redis V2 |
| Intégration Fedapay V2 | Moyenne | Sandbox testing dès MVP, logs détaillés |

**Market Risks:**

| Risque | Probabilité | Mitigation |
|--------|-------------|------------|
| Adoption lente | Moyenne | Module Ressources pour éducation, marketing ciblé |
| Confiance plateforme | Haute | Notation bidirectionnelle, modération active |
| Concurrence future | Basse (first mover) | Construire communauté loyale rapidement |

**Resource Risks:**

| Risque | Probabilité | Mitigation |
|--------|-------------|------------|
| Timeline 4 semaines serrée | Haute | Priorisation stricte P0, report P2 si nécessaire |
| 1 dev solo | Moyenne | Claude Code pour accélérer, scope réductible |

## Functional Requirements

### Gestion des Utilisateurs

- FR1: Un visiteur peut créer un compte Face avec nom, prénom, username, email et mot de passe
- FR2: Un visiteur peut créer un compte Producteur avec email, mot de passe et catégorie (Agence ou Particulier)
- FR3: Un Producteur Agence peut renseigner le nom de son agence lors de l'inscription
- FR4: Un Producteur Particulier peut renseigner ses noms et prénoms lors de l'inscription
- FR5: Un utilisateur enregistré peut se connecter avec email et mot de passe
- FR6: Un utilisateur connecté peut se déconnecter
- FR7: Un utilisateur peut réinitialiser son mot de passe via email
- FR8: Un Admin peut créer des comptes Admin supplémentaires

### Gestion des Profils Face

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

### Gestion des Profils Producteur

- FR22: Un Producteur peut ajouter une photo de profil
- FR23: Un Producteur peut renseigner sa bio courte
- FR24: Un Producteur Agence peut ajouter le logo de l'agence

### Gestion des Missions

- FR25: Un Producteur peut publier une mission avec titre, description, date de tournage, profil recherché, budget, date limite de candidature, nombre de Faces voulu, type de mission, genre voulu, lieu et durée
- FR26: Un Producteur peut modifier une mission qu'il a publiée
- FR27: Un Producteur peut supprimer une mission qu'il a publiée
- FR28: Un Producteur peut consulter la liste de ses missions avec leurs statuts
- FR29: Un Producteur peut clôturer une mission (arrêter les candidatures)
- FR30: Un Producteur peut marquer une mission comme terminée
- FR31: Une Face peut consulter la liste des missions disponibles
- FR32: Une Face peut filtrer les missions par ville, budget min/max, date et type de mission
- FR33: Une Face peut consulter le détail complet d'une mission

### Gestion des Candidatures

- FR34: Une Face peut postuler à une mission avec un message de motivation optionnel
- FR35: Une Face peut consulter la liste de ses candidatures avec leurs statuts (En attente, Acceptée, En cours, Terminée)
- FR36: Un Producteur peut consulter les candidatures reçues pour chacune de ses missions
- FR37: Un Producteur peut consulter le profil complet d'un candidat (incluant vidéos)
- FR38: Un Producteur peut accepter une candidature
- FR39: Un Producteur peut refuser une candidature
- FR40: Une Face peut confirmer une mission après acceptation de sa candidature (validation 2 étapes)
- FR41: Une Face reçoit une notification lors du changement de statut de sa candidature

### Messagerie

- FR42: Une Face peut échanger des messages avec un Producteur après acceptation de sa candidature
- FR43: Un Producteur peut échanger des messages avec une Face dont la candidature a été acceptée
- FR44: Un utilisateur peut consulter l'historique de ses conversations
- FR45: Un utilisateur peut actualiser manuellement ses messages

### Système de Notation

- FR46: Une Face peut noter un Producteur après la fin d'une mission (1 à 5 étoiles)
- FR47: Un Producteur peut noter une Face après la fin d'une mission (1 à 5 étoiles)
- FR48: Un utilisateur peut consulter la note moyenne d'une Face
- FR49: Un utilisateur peut consulter la note moyenne d'un Producteur
- FR50: Un utilisateur peut consulter les avis reçus par une Face ou un Producteur

### Dashboard Face

- FR51: Une Face peut consulter ses KPIs : missions en attente, acceptées, en cours, terminées
- FR52: Une Face peut consulter le solde de son wallet (interface présente, fonctionnalité inactive MVP)
- FR53: Une Face peut consulter des graphiques d'évolution (candidatures, missions par mois)
- FR54: Une Face peut accéder rapidement à la liste des missions disponibles depuis son dashboard

### Dashboard Producteur

- FR55: Un Producteur peut consulter ses KPIs : missions publiées, en cours, clôturées
- FR56: Un Producteur peut consulter le nombre total de candidatures reçues
- FR57: Un Producteur peut consulter le nombre de Faces avec qui il a travaillé
- FR58: Un Producteur peut consulter sa note globale moyenne
- FR59: Un Producteur peut consulter des stats avancées (taux d'acceptation, missions terminées dans les délais)

### Module Ressources/Blog

- FR60: Un Admin peut créer un article avec titre, contenu rich text et images
- FR61: Un Admin peut catégoriser un article (Conseils Face, Guide Producteur, Actualités)
- FR62: Un Admin peut définir le statut d'un article (Brouillon ou Publié)
- FR63: Un Admin peut modifier un article existant
- FR64: Un Admin peut supprimer un article
- FR65: Un visiteur peut consulter la liste des articles publiés
- FR66: Un visiteur peut lire un article complet
- FR67: Un visiteur peut filtrer les articles par catégorie

### Administration

- FR68: Un Admin peut consulter un dashboard avec KPIs globaux (Faces, Producteurs, Missions actives/terminées)
- FR69: Un Admin peut créer, lire, modifier et supprimer des comptes Face
- FR70: Un Admin peut créer, lire, modifier et supprimer des comptes Producteur
- FR71: Un Admin peut créer, lire, modifier et supprimer des comptes Admin
- FR72: Un Admin peut consulter les profils détaillés (Faces avec vidéos, Producteurs avec missions)
- FR73: Un Admin peut consulter toutes les missions publiées
- FR74: Un Admin peut activer ou désactiver un compte utilisateur

### Accès Public

- FR75: Un visiteur peut consulter la landing page avec présentation de la plateforme
- FR76: Un visiteur peut utiliser le switch Face/Producteur sur la landing page
- FR77: Un visiteur peut consulter la liste des Faces publiques (photo, nom, ville, catégorie)
- FR78: Un visiteur peut consulter un profil Face public (infos limitées)
- FR79: Un visiteur peut filtrer les Faces par ville, catégorie, niche
- FR80: Un visiteur peut consulter la liste des missions publiques (titre, budget, date, lieu)
- FR81: Un visiteur peut consulter le détail d'une mission publique
- FR82: Un visiteur peut accéder aux pages d'inscription Face ou Producteur

## Non-Functional Requirements

### Performance

| Exigence | Cible MVP | Mesure |
|----------|-----------|--------|
| **NFR-P1**: Temps de chargement page | < 2s | Réseau 4G mobile |
| **NFR-P2**: Time to Interactive | < 3s | Réseau 4G mobile |
| **NFR-P3**: First Contentful Paint | < 1.5s | Réseau 4G mobile |
| **NFR-P4**: Taille bundle initial | < 300KB | Gzipped |
| **NFR-P5**: Upload vidéo (50MB) | < 60s | Réseau 4G avec progress bar |
| **NFR-P6**: Temps de réponse API | < 300ms | 95e percentile |
| **NFR-P7**: Rendu liste 50 items | < 500ms | Avec pagination |

**Optimisations requises:**
- Code splitting par route Vue Router
- Lazy loading des composants lourds (vidéos, graphiques)
- Compression images côté client avant upload
- Thumbnails auto-générés pour vidéos
- Pagination côté serveur (20 items par page)

### Sécurité

| Exigence | Description |
|----------|-------------|
| **NFR-S1**: Authentification | Tokens JWT via Laravel Sanctum avec expiration 24h |
| **NFR-S2**: Mots de passe | Hashage bcrypt, minimum 8 caractères, 1 majuscule, 1 chiffre |
| **NFR-S3**: Protection CSRF | Token CSRF sur tous les formulaires |
| **NFR-S4**: Transmission données | HTTPS obligatoire (TLS 1.2+) |
| **NFR-S5**: Validation inputs | Validation côté serveur de tous les inputs utilisateur |
| **NFR-S6**: Uploads fichiers | Validation MIME type, scan malware basique, limite taille |
| **NFR-S7**: Accès données | Un utilisateur ne peut accéder qu'à ses propres données sensibles |
| **NFR-S8**: Rate limiting | 60 requêtes/minute par IP pour prévenir le brute force |
| **NFR-S9**: Sessions | Invalidation session à la déconnexion, timeout 2h inactivité |

**Protection des données personnelles:**
- Photos et vidéos stockées avec UUID (non prédictibles)
- Données sensibles (email, téléphone) non exposées publiquement
- Logs anonymisés après 90 jours
- Possibilité de supprimer son compte et ses données (RGPD)

### Scalabilité

| Exigence | Cible MVP | Cible 12 mois |
|----------|-----------|---------------|
| **NFR-SC1**: Utilisateurs simultanés | 50 | 200 |
| **NFR-SC2**: Faces enregistrées | 200 | 500+ |
| **NFR-SC3**: Producteurs enregistrés | 50 | 150+ |
| **NFR-SC4**: Missions actives simultanées | 30 | 100 |
| **NFR-SC5**: Stockage médias | 10GB | 100GB |

**Architecture prête pour la croissance:**
- Base de données MySQL avec indexation appropriée
- Pagination sur toutes les listes
- Caching Redis prévu en V2
- Migration vers CDN (DigitalOcean Spaces) prévue en V2

### Fiabilité & Disponibilité

| Exigence | Cible |
|----------|-------|
| **NFR-R1**: Uptime | > 99% (hors maintenance planifiée) |
| **NFR-R2**: Fenêtre maintenance | Nuit (22h-6h WAT) avec notification 24h avant |
| **NFR-R3**: Recovery Time Objective (RTO) | < 4h |
| **NFR-R4**: Backup base de données | Quotidien, rétention 7 jours |
| **NFR-R5**: Backup médias | Hebdomadaire (MVP), quotidien (V2) |
| **NFR-R6**: Succès upload vidéo | > 95% pour fichiers < 50MB |

### Accessibilité

| Exigence | Niveau |
|----------|--------|
| **NFR-A1**: Conformité WCAG | 2.1 Level A |
| **NFR-A2**: Alt text images | Obligatoire sur toutes les images |
| **NFR-A3**: Navigation clavier | Tab navigation fonctionnelle |
| **NFR-A4**: Contraste couleurs | Ratio 4.5:1 minimum pour texte |
| **NFR-A5**: Focus visible | Outline visible sur tous les éléments focusables |
| **NFR-A6**: Labels formulaires | Tous les inputs avec labels associés |
| **NFR-A7**: Messages d'erreur | Annoncés aux lecteurs d'écran |
| **NFR-A8**: Touch targets | Minimum 44x44px sur mobile |

### Intégration (V2)

| Exigence | Description |
|----------|-------------|
| **NFR-I1**: Fedapay API | Intégration Mobile Money (MTN MoMo, Moov Money) |
| **NFR-I2**: Webhooks paiement | Réception et traitement < 5s |
| **NFR-I3**: Email transactionnel | Mailgun/SendGrid avec templates |
| **NFR-I4**: CDN médias | DigitalOcean Spaces (S3-compatible) |
| **NFR-I5**: WebSockets | Laravel Echo + Pusher pour temps réel |

**Fallback et résilience:**
- Retry logic sur appels API externes (3 tentatives avec backoff)
- Queue pour tâches asynchrones (emails, notifications)
- Mode dégradé si service externe indisponible

