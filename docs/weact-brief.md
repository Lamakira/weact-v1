📋 WEACT - Brief Technique FINAL pour BMad Method
1️⃣ FONCTIONNALITÉS MVP (Mois 1)
🌐 Espace Public (Internaute non connecté)
Pages accessibles :

Landing page (simplifiée : Hero + Switch Face/Producteur + Comment ça marche + CTA)
Liste des Faces publiques (grille avec photos, noms, ville, catégories)
Profil public d'une Face (photos, vidéos, bio, tarifs, expériences)
Liste des Missions publiques (titre, budget, date, lieu, producteur)
Détail d'une Mission publique
Pages : Inscription Face / Inscription Producteur / Connexion


👤 Espace Face (Utilisateur connecté)
Inscription Face

Noms & prénoms
Username
Email
Mot de passe

Profil Face (informations complémentaires)

Photo de profil
Album photos (4 photos max)
Vidéo de présentation
Vidéo d'acting
Courte description (bio)
Ville, Quartier, Pays
Taille, Poids
Catégorie (acteur/influenceur/créateur/mannequin/figurant)
Niche (beauté, nourriture, découverte, mode)
Expériences professionnelles
Tarif horaire et journalier
Disponibilité (toggle Disponible/Indisponible)

Dashboard Face
KPIs :

Nombre de missions : En attente / Acceptées / En cours / Terminées
Wallet (montant disponible) - Interface présente mais fonctionnalité inactive pour MVP
Graphiques de stats (évolution candidatures, missions acceptées par mois, etc.)
Statut profil (complet ou incomplet)
Bouton rapide "Voir les missions"

Fonctionnalités Face

Voir toutes les missions disponibles (liste avec filtres : ville, budget min/max, date, type de mission)
Détail d'une mission
Postuler à une mission (message de motivation optionnel)
Voir "Mes missions" avec statuts :

En attente (postulé mais pas encore accepté)
Acceptées (le producteur a accepté ma candidature)
En cours (mission confirmée, en attente de réalisation)
Terminées


Confirmer une mission (validation finale après acceptation par producteur)
Chat avec Producteur (débloqué uniquement si mission acceptée)
Noter un producteur (après mission terminée)


🎬 Espace Producteur (Utilisateur connecté)
Inscription Producteur

Email
Mot de passe
Catégorie : Agence (nom agence) ou Particulier (noms & prénoms)

Profil Producteur

Photo de profil
Nom (agence ou personne)
Bio courte

Dashboard Producteur
KPIs :

Nombre de missions : Publiées / En cours / Clôturées
Nombre de candidatures reçues (total)
Nombre de Faces avec qui il a travaillé
Note globale (moyenne des notes reçues)
Stats avancées (taux d'acceptation candidatures, missions terminées dans les délais, etc.)

Fonctionnalités Producteur

Voir toutes les Faces (grille avec filtres : ville, catégorie, niche, budget horaire/journalier, taille, poids)
Voir profil détaillé d'une Face (avec vidéos)
Publier une mission avec :

Titre
Description
Date de tournage
Profil recherché (texte libre)
Budget
Date limite de candidature
Nombre de Faces voulu
Type de mission (publicité, film, court-métrage, clip musical, etc.)
Genre voulu (homme/femme/les deux)
Lieu du tournage
Durée du tournage


Voir "Mes missions" (liste)
Pour chaque mission : voir les candidatures reçues
Accepter ou Refuser une candidature
Chat avec Face (débloqué après acceptation de la candidature)
Noter une Face (après mission terminée)


🛠️ Espace Administration
Connexion Admin

Email
Mot de passe

Fonctionnalités Admin

Dashboard admin : stats globales (nombre Faces, Producteurs, Missions actives/terminées)
Gestion utilisateurs (CRUD Total) :

Faces : Créer, Lire, Modifier, Supprimer
Producteurs : Créer, Lire, Modifier, Supprimer
Admins : Créer, Lire, Modifier, Supprimer


Visualiser tous les profils détaillés (Faces avec vidéos, Producteurs avec missions)
Visualiser toutes les missions publiées
Modération basique (activer/désactiver un compte utilisateur)


2️⃣ FONCTIONNALITÉS V2 (Post-MVP)
💰 Paiement & Wallet

Intégration Mobile Money (MTN MoMo, Moov Money via Fedapay)
Wallet interne pour les Faces
Système de retrait vers Mobile Money
Gestion des commissions (15% Producteur + 15% Face)
Paiement sécurisé avec escrow (argent bloqué jusqu'à validation mission)
Historique des transactions détaillé

📄 Contrats & Légal

Génération automatique de contrats de cession de droits d'images (PDF)
Signature électronique (validation par checkbox horodatée)
Stockage sécurisé des contrats signés
Téléchargement des contrats par Producteur
Templates de contrats personnalisables (admin)

🔔 Notifications

Notifications push (web)
Email notifications (candidature reçue, mission acceptée, etc.)
SMS pour événements critiques (paiement reçu, mission confirmée)

🛡️ Sécurité & Vérification

KYC (Know Your Customer) pour les Faces
Vérification d'identité (upload CNI)
Vérification téléphone obligatoire (OTP SMS)
Vérification email obligatoire
Système anti-spam/faux profils

🚨 Gestion des Litiges

Interface dédiée pour signaler un litige (Face ou Producteur)
Dashboard Admin pour gérer les litiges (conversation tripartite)
Actions admin : remboursement / validation / médiation
Déblocage d'argent selon décision admin
Historique des litiges résolus

🎯 Booking Direct

Producteur peut booker directement une Face (sans mission publique)
Formulaire de booking (date, durée, type de contenu, budget)
Paiement immédiat à la réservation
Chat débloqué automatiquement

🌟 Features Marketing & Premium

Mise en avant payante des Faces (boost profil en homepage)
Abonnements premium pour Producteurs (accès à filtres avancés)
Recommandations intelligentes (ML - Faces similaires, missions recommandées)

💬 Messagerie Avancée

Chat temps réel (WebSockets via Laravel Echo + Pusher)
Partage de fichiers (images, PDFs)
Indicateurs "En train d'écrire..."
Historique complet des conversations

📊 Analytics & Reporting

Dashboard admin complet avec graphiques avancés
Rapports mensuels (CA, nombre de missions, taux de conversion)
Export de données (CSV, Excel)
Logs d'activité détaillés (audit trail)

🌍 Expansion & Multilangue

Interface en Français, Anglais (+ Fon ?)
Géolocalisation avancée (carte interactive des Faces/Missions)
API publique pour partenaires (développeurs tiers)

🔐 Connexion & Auth Avancés

Connexion avec Google (Laravel Socialite)
Connexion avec Facebook
Authentification à deux facteurs (2FA)


3️⃣ STACK TECHNIQUE
Backend

Framework : Laravel 11
Base de données : MySQL
Authentification : Laravel Sanctum (API tokens)
Queue System : Laravel Queue (Redis ou Database driver)
Storage : Local pour MVP (photos/vidéos) → DigitalOcean Spaces pour V2 (CDN)

Frontend

Framework : Vue 3 (Composition API)
Langage : TypeScript (optionnel mais recommandé)
State Management : Pinia
Routing : Vue Router
Styling : Tailwind CSS
HTTP Client : Axios
Animations : Transitions CSS basiques pour MVP → GSAP ou VueUse Motion pour V2

Outils de développement

Version Control : Git + GitHub
Package Manager : npm ou pnpm
Build Tool (Frontend) : Vite
API Testing : Postman ou Insomnia
Code Quality : ESLint + Prettier (frontend), PHP CS Fixer (backend)

ASSETS & RESSOURCES DISPONIBLES
Maquettes Figma complètes
Les pages suivantes ont des maquettes haute-fidélité prêtes :

Landing page Face (Hero + sections complètes)
Landing page Producteur (Hero + sections complètes)
Header (navigation, comportement scroll, états actifs - exactement comme spécifié)
Footer (liens, informations légales)
Page "Trouver une face" (structure et layout)
Page "Missions" (structure et layout)

Design system défini :

Couleur primaire : #198496 (Teal/Cyan)
Typographie : Inter ou similaire (sans-serif moderne)
Composants : Boutons, cards, formulaires
Switch personnalisé Face/Producteur (avec flèches organiques)

Note technique : Les maquettes Figma utilisent React (générées via Figma Make) mais seront converties en Vue 3 pendant le développement.
Références visuelles (captures d'écran)
Pour les pages suivantes, des captures d'écran d'un site de référence seront fournies comme inspiration de structure :

Dashboards (Face et Producteur)
Pages profils
Pages de messagerie
Autres pages internes

Approche : Claude Code analysera les captures pour reproduire la structure et l'UX tout en appliquant le design system WEACT.
Lien Figma
https://www.figma.com/design/Y8DJzNp5ztw2z65LiV3XdI/WEACT-Desgin-Files?node-id=0-1&p=f&t=HFG1y9vRQ2OX5Kl1-0


4️⃣ CONTRAINTES SPÉCIFIQUES
Contexte géographique

Public cible : Bénin (Cotonou, Porto-Novo, Parakou, etc.)
Langue : Français uniquement pour MVP
Devise : Franc CFA (XOF) pour les tarifs et budgets

Contraintes techniques MVP

Paiement : Architecture prête pour intégration (models Wallet, Transactions) mais fonctionnalité inactive pour MVP. Paiement simulé en interface.
Vidéos : Upload et affichage vidéos dès le MVP (présentation + acting)
Messagerie : Pas de temps réel pour MVP (polling ou refresh manuel) → Temps réel en V2
Responsive Design : Mobile-first obligatoire (majorité du trafic attendu sur mobile)

Contraintes légales (à prévoir V2)

Consulter un juriste béninois pour valider les templates de contrats de cession de droits d'images
Respect RGPD et lois locales sur la protection des données personnelles
Conditions Générales d'Utilisation (CGU) et Politique de Confidentialité obligatoires


5️⃣ INTÉGRATIONS FUTURES (V2)
Paiement Mobile Money

Provider recommandé : Fedapay (agrégateur local)

Supporte MTN Mobile Money et Moov Money
API REST documentée en français
Webhooks pour confirmation de paiement
Payout API pour retraits



Notifications

Email : Mailgun, SendGrid, ou Amazon SES
SMS : API locale béninoise (à identifier) ou Twilio (international)

Stockage Vidéos & CDN

Option 1 : DigitalOcean Spaces (S3-compatible, économique)
Option 2 : AWS S3 + CloudFront (plus cher mais plus performant)
Optimisation vidéos : Transcodage automatique (formats multiples, compression)

Search & Filters Avancés

Meilisearch (open-source, facile à intégrer avec Laravel Scout)
Ou Algolia (payant mais très performant)


6️⃣ TIMELINE

MVP : 4 semaines (1 mois)
V2 : 8-12 semaines après validation MVP (incluant intégrations paiement, contrats, etc.)


7️⃣ PRIORITÉS MVP (Ordre de valeur métier)
Priorité CRITIQUE (P0) :

Auth + Inscription Face/Producteur
Profils Face complets (avec vidéos)
CRUD Missions (Producteur)
Système de candidatures (Face postule, Producteur accepte/refuse)
Chat basique (après acceptation candidature)

Priorité HAUTE (P1) :

Dashboard Face (stats + graphiques)
Dashboard Producteur (stats + graphiques)
Notation (Face ↔ Producteur)
Confirmation de mission (workflow complet)
Filtres avancés (recherche Faces/Missions)

Priorité MOYENNE (P2) :

Landing page (simplifiée)
Espace Admin (CRUD utilisateurs)
Pages publiques (profil Face public, mission publique)
Responsive mobile

Priorité BASSE (P3) :

Animations et polish UI
Optimisations performance


8️⃣ RISQUES IDENTIFIÉS
Risques Techniques

Upload et stockage vidéos : Taille des fichiers, bande passante, temps d'upload

Mitigation : Limiter taille vidéos (max 50MB), compression côté client, progress bar


Performance base de données : Requêtes lentes avec beaucoup de données

Mitigation : Indexation appropriée, pagination, caching (Redis en V2)


Intégration Mobile Money (V2) : API Fedapay, gestion webhooks, réconciliation comptable

Mitigation : Tester en mode sandbox dès le début, logs détaillés, retry logic



Risques Métier

Adoption utilisateurs : Marché naissant, éducation nécessaire

Mitigation : Marketing ciblé, tutos vidéo, support réactif


Confiance plateforme : Paiements, données personnelles sensibles

Mitigation : Certificat SSL, mentions légales claires, système de notation transparent


Litiges Face/Producteur : Désaccords sur qualité travail, paiements

Mitigation : Contrats clairs (V2), process de médiation (V2), modération admin



Risques Planning

Sous-estimation complexité : Fonctionnalités MVP ambitieuses pour 4 semaines

Mitigation : Développement agile, réajustement hebdomadaire, priorisation stricte


Disponibilité développeur : Nuits blanches = risque de burnout, baisse qualité code

Mitigation : Pauses régulières, code reviews (même solo), utilisation intensive de Claude Code pour accélérer




9️⃣ WORKFLOW MÉTIER MVP (Diagramme conceptuel)
Workflow Mission Complète (MVP) :
1. Producteur publie une mission
   ↓
2. Face voit la mission dans "Toutes les missions"
   ↓
3. Face postule (message motivation optionnel)
   ↓
4. Producteur voit la candidature dans "Mes missions > [Mission] > Candidatures"
   ↓
5. Producteur ACCEPTE la candidature
   ↓
6. ✅ Statut candidature = "Acceptée"
   ↓
7. ✅ Chat débloqué entre Face et Producteur
   ↓
8. Face CONFIRME la mission (validation finale)
   ↓
9. ✅ Statut mission = "En cours"
   ↓
10. Mission se déroule (tournage)
   ↓
11. Producteur marque mission comme "Terminée"
   ↓
12. ✅ Notation débloquée (Face note Producteur, Producteur note Face)
   ↓
13. ✅ Statut mission = "Terminée"
Contrainte importante :

Chat accessible UNIQUEMENT si candidature acceptée (pas avant)
Notation accessible UNIQUEMENT si mission terminée (pas avant)


🔟 SPÉCIFICATIONS VIDÉOS (MVP)
Types de vidéos Face :

Vidéo de présentation (30s à 1min)

Face se présente, parle de son expérience, sa motivation


Vidéo d'acting (30s à 1min)

Face montre ses talents d'acteur (monologue, improvisation, etc.)



Contraintes techniques :

Formats acceptés : MP4, MOV, AVI
Taille max par vidéo : 50 MB
Résolution max : 1080p (Full HD)
Durée max : 2 minutes par vidéo
Upload : Barre de progression, validation côté client avant envoi

Affichage :

Lecteur vidéo HTML5 natif (balise <video>)
Contrôles : play/pause, volume, fullscreen
Thumbnail auto-généré (première frame de la vidéo)
