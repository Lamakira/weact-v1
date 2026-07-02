# Handoff: WeAct — Module UGC (User Generated Content)

## Overview
Ce module ajoute à la marketplace WeAct un système complet de **contenu généré par les talents (UGC)** : un Producteur (marque/agence) offre un produit physique — éventuellement avec une rémunération — à une Face (talent) en échange de vidéos (Unboxing + Avis). WeAct prélève une commission de **10 % de la valeur déclarée du produit, avec un plancher de 2 500 FCFA**, payée via FedaPay / MTN MoMo / Moov Money.

Le module couvre deux côtés + une vue système :
- **Producteur** : booking UGC direct, création de mission (appel à projets), paiement de commission, confirmation d'expédition + tracking, validation des livrables vidéo.
- **Face** : découverte des missions (avec paywall pour les non-abonnés), détail + acceptation, espace de suivi avec chronomètres, notifications de deadline, page de suspension.
- **Workflow système** : tunnel anti-arnaque en 6 étapes avec suspension automatique sur dépassement de chrono.

## About the Design Files
Les fichiers de ce bundle sont des **références de design réalisées en HTML/React (via Babel in-browser)** — des prototypes montrant l'apparence et le comportement souhaités, **pas du code de production à copier tel quel**. La tâche est de **recréer ces designs dans le codebase existant `weact-v1/frontend`**, qui est en **Vue 3 + TypeScript + Tailwind CSS + lucide-vue-next**, en suivant ses patterns établis (voir `frontend/src/features/*` et `frontend/src/components/*`).

Concrètement, les composants finaux doivent vivre dans :
- `frontend/src/features/booking/components/` (booking UGC, expédition, validation)
- `frontend/src/features/mission/components/` (création mission UGC)
- `frontend/src/features/face/components/` (suivi, notifications, suspension côté Face)
- `frontend/src/views/` (pages : découverte missions UGC, détail mission UGC)

## Fidelity
**High-fidelity (hifi).** Couleurs, typographie, espacements et interactions sont définitifs et alignés sur `weact-v1/design-system.md`. Recréer l'UI fidèlement avec les composants/utilitaires Tailwind existants. Les seules zones « lofi » volontaires sont les **placeholders d'imagerie rayés** (produits, vidéos) — à remplacer par les vrais médias / lecteurs vidéo du codebase.

> **Note sur les variations :** chaque écran est proposé en **2 directions** — `A` (safe, proche de l'existant) et `B` (bold, plus poussé). **Le choix final de direction par écran est à confirmer avec le designer/PO avant implémentation.** La doc ci-dessous décrit les deux ; implémenter celle retenue.

---

## Design Tokens
Repris **strictement** de `weact-v1/design-system.md`. Ne pas inventer de nouvelles couleurs.

### Couleurs
| Token | Valeur | Usage |
|---|---|---|
| Primary (teal) | `#198496` | CTAs, accents, liens, états actifs |
| Primary hover | `#146c7a` | Hover des CTAs primaires |
| Primary light bg | `rgba(25,132,150,0.05)` → `0.10` | Fonds subtils, surbrillances |
| Primary ring | `rgba(25,132,150,0.20)` | Focus ring des inputs |
| Dark | `#0F1419` | Texte fort, fonds sombres (viewer, hero, takeover) |
| Footer/navy | `#101828` | (existant) |
| Text dark | `gray-900` | Titres |
| Text medium | `gray-700` | Corps |
| Text light | `gray-500` / `gray-400` | Secondaire, hints |
| Border | `gray-200` | Bordures par défaut |
| Border light | `gray-100` | Dividers |
| Background app | `#FAFAF7` (dashboard) / `bg-white` | Fonds |

### Couleurs sémantiques (états chrono & statuts — nouvelles, dérivées)
| État | Couleur | Déclencheur |
|---|---|---|
| Chrono OK | `#198496` (teal) | progress < 0.4 |
| Chrono attention | `#F59E0B` (ambre) | 0.4 ≤ progress < 0.6 |
| Chrono urgent | `#EA580C` (orange) | 0.6 ≤ progress < 0.85 |
| Chrono critique | `#DC2626` (rouge) | progress ≥ 0.85 |
| Expédié / shipped | `#1D4ED8` | statut booking |
| Reçu / received | `#7C3AED` | statut booking |
| Livré / completed | `#059669` | statut booking |
| Suspension | `#DC2626` | dépassement de deadline |

`progress` = fraction du temps écoulé entre le démarrage du chrono et la deadline (0 = vient de démarrer, 1 = deadline atteinte).

### Typographie
- Famille : **Inter** (web). Mono pour placeholders/refs/tracking : `JetBrains Mono` (ou la mono du codebase).
- Échelle utilisée : titres `text-xl`/`text-2xl font-bold`, sections `text-base/text-sm font-semibold`, corps `text-xs/text-sm`, micro-labels `text-[10px]/text-[11px]`, kicker `text-[10px] font-bold uppercase tracking-widest`.

### Rayons & ombres
| Élément | Valeur |
|---|---|
| Boutons, inputs, tiles | `rounded-md` |
| Cards | `rounded-lg` / `rounded-xl` |
| Bottom sheet mobile, hero | `rounded-2xl` / `rounded-t-3xl` |
| Ombre card | `shadow-sm` (subtile) ; `shadow-lg`/`shadow-xl` pour modals |
| Pills/badges | `rounded-full` |

### Espacements
Container dashboard padding `p-8` (airy) ou `p-4` (compact). Gaps internes `gap-2`/`gap-3`/`gap-4`. Sidebar dashboard `w-56`. Rails de droite `320–380px`.

---

## Composants partagés (à factoriser en premier)
Ces primitives sont réutilisées partout — voir `shared.jsx` pour les implémentations React de référence.

1. **`ChronoRing`** — anneau de compte à rebours SVG. Props : `progress` (0–1), `size`, `stroke`, `label`, `sublabel`, `danger`. Couleur interpolée teal→ambre→orange→rouge selon `progress`. Transition `stroke-dashoffset .6s, stroke .4s`. SVG tourné `-90deg`. **C'est le composant pivot de tout le parcours Face.**
2. **`ChronoBadge`** — pill linéaire avec icône `timer`, même logique de couleur. Tailles `sm`/`lg`.
3. **`StatusPill`** — pastille de statut booking (pending/paid/accepted/shipped/received/delivered/completed/overdue/suspended), point coloré + label.
4. **`CompensationToggle`** — toggle segmenté **Produit seul ⇄ Produit + Argent**, chaque option avec sub-label (« 2 vidéos fixes » / « Nb vidéos libre »). Pilote l'affichage dynamique du formulaire.
5. **`CommissionBreakdown`** — récap : valeur produit, (rémunération Face si hybride), commission WeAct (`Math.max(2500, round(valeur*0.10))`), total à payer. Mention « 10% min. 2 500 ».
6. **`BookingTimeline`** — 6 étapes, en variante **verticale** (`BookingTimelineV`) et **horizontale** (`BookingTimelineH`). Props : `current` (1–6), `overdue`.
7. **`ReassuranceCard`** — carte « Comment WeAct protège votre envoi » (4 garanties anti-arnaque).
8. **`PayTile`** — tuile de méthode de paiement (MTN / Moov / Carte FedaPay).
9. **`DashChrome`** — shell dashboard Producteur (sidebar `w-56` + topbar + main). Item nav UGC mis en avant (badge teal).
10. **`PhoneFrame` / `MobileTopBar` / `MobileTabBar`** — chrome mobile pour les écrans Face.
11. **`StripePh`** — placeholder rayé monospacé pour imagerie (à remplacer par vrais médias).
12. **Boutons** : `BtnPrimary` (teal), `BtnOutline` (teal border), `BtnGhost` (gris). Inputs : `Field` + `TextInput` + focus ring teal.

---

## Écrans / Vues

### 1. Formulaire Booking UGC dynamique (Producteur)
**Purpose** : configurer une dotation UGC directe vers une Face précise et payer la commission.
**Champs dynamiques** (apparaissent quand type de contenu = « UGC ») :
- `Type de compensation` : `CompensationToggle` (product | hybrid).
- `Nom du produit à offrir` (texte).
- `Valeur marchande` (numérique, FCFA) — **base du calcul de commission**.
- `Nombre de vidéos` : si **product** → **figé à 2** (mention « 1 Unboxing + 1 Avis », champ verrouillé) ; si **hybrid** → **éditable** + champ `Montant de la rémunération Face`.
- `CommissionBreakdown` recalculé en live. CTA « Payer la commission · X FCFA ».
- Bandeau de réassurance bas de formulaire (« Paiement sécurisé · Remboursé si refus »).

**Variations** :
- **1A (safe)** : sheet single-colonne façon `BookingFormSheet.vue` existant, section UGC sur fond teal très léger.
- **1B (bold)** : split 2 colonnes (formulaire numéroté 01/02/03 à gauche + **rail d'aperçu live** sombre `#0F1419` + `ReassuranceCard` à droite). En hybride avec N>2 vidéos, lignes de brief vidéo additionnelles générées.

### 2. Création mission UGC — appel à projets (Producteur)
**Purpose** : publier une offre UGC ouverte (N Faces). Même bloc dotation que le booking ; **le paiement de la commission est obligatoire pour publier**.
**Variations** :
- **2A (safe)** : single-page, sections empilées (Brief / Dotation UGC / Cible / Récap commission).
- **2B (bold)** : **stepper vertical** 4 étapes (Type → Brief & livrables → Cible → Publication) + corps central + rail récap droite (aperçu mission + `CommissionBreakdown` + `ReassuranceCard`). Les 2 livrables (Unboxing 7j / Avis 14j) sont décrits dans un encart teal. Chips de critères (lumière naturelle, voix off FR…).

### 3. Tunnel paiement commission (Producteur)
**Purpose** : régler la commission WeAct. **Étape 1/6 du workflow.** Le paiement valide l'envoi du booking / la publication de la mission.
**Méthodes** : MTN MoMo, Moov Money, Carte bancaire (FedaPay). Note de sécurité : « la commission n'est encaissée qu'après acceptation par la Face ».
**Variations** :
- **3A (safe)** : modal centré → liste `PayTile` → état succès (REF `WACT-BOOK-XXXX`, CTA suivre / nouveau).
- **3B (bold)** : checkout pleine page split — **récap sombre à gauche** (produit, valeur, total commission, « fonds bloqués jusqu'à acceptation ») + **saisie provider + entrée PIN USSD à droite** (ex. `*880*4500#`, spinner « En attente de confirmation MTN… »).

### 4. Confirmation expédition + tracking (Producteur)
**Purpose** : après acceptation de la Face, confirmer l'envoi du produit. **Étape 3/6.** Le chrono ne démarre que quand la Face clique « Produit reçu ».
**Champs** : transporteur (Gozem / DHL / Chronopost / Autre), numéro de suivi (visible par la Face), date/heure, notes.
**Variations** :
- **4A (safe)** : panneau formulaire inline sous une `BookingTimelineH` (current=3), produit en tête, footer avec rappel « le chrono démarrera quand Aïcha clique Produit reçu ».
- **4B (bold)** : split — formulaire (transporteurs en cards à icône) à gauche + **aperçu carte/pin d'adresse** (grille SVG simple) + adresse vérifiée KYC + encart sombre « ce qui se passe ensuite » à droite.

### 5. Validation des livrables (Producteur)
**Purpose** : visionner et valider/rejeter/demander retouche sur chaque vidéo. SLA 48h. Valider la vidéo 1 déclenche le chrono Avis (14j).
**Variations** :
- **5A (safe)** : 2 panes — liste des livrables en attente (avec `ChronoBadge`) à gauche + preview vidéo (lecteur placeholder) + checklist de conformité + zone note/retouche à droite.
- **5B (bold)** : **viewer immersif sombre** plein écran (`#0A0E12`) — topbar (face/produit/ref + `ChronoBadge` lg), vidéo style téléphone au centre, panneau brief + conformité auto + note Face à droite, **barre d'action bas** (Demander retouche / Rejeter / Valider · Livrable 2 →).

### 6. Découverte missions UGC + paywall (Face — mobile)
**Purpose** : parcourir les missions UGC. **Accès réservé aux abonnés Starter+** ; les non-abonnés sont poussés vers `/pricing`.
**Variations** :
- **6A (safe)** : liste visible, **bandeau d'abonnement sticky** en haut, **cartes de mission verrouillées individuellement** (cadenas + flou léger) sauf la 1ère « Recommandé ». Chips de filtres. `MobileTabBar` actif sur Missions.
- **6B (bold)** : **hero teaser** (12 nouvelles missions) + **carte paywall pleine** (2 plans Starter 2 500 / Pro 5 000, CTA « Voir les abonnements ») + **liste en aperçu flouté** (`LockedOverlay`). « Continuer en aperçu » discret.

### 7. Détail mission UGC + acceptation (Face — mobile)
**Purpose** : voir le détail et accepter (engagement sur les délais).
**Contenu** : stats produit/cash/vidéos, brief, **liste des livrables** (Unboxing 7j, etc.), bloc producteur vérifié. CTA sticky « Accepter ».
**Variations** :
- **7A (safe)** : scroll classique, header image, 3 stat-cards, CTA sticky bas (message + Accepter).
- **7B (bold)** : **full-bleed** sombre + nav flottante + **bottom sheet** (3 stats, **2 mini `ChronoRing` à 0** montrant les chronos à venir 7j/14j, CTA « Accepter · Recevoir le produit » + mention engagement/règles).

### 8. Espace de suivi Face — Produit reçu → chronos → upload (mobile)
**Purpose** : suivre le booking après acceptation ; déclencher « Produit reçu » ; uploader les livrables sous chrono. **Les chronos sont omniprésents.**
**Variations** :
- **8A (safe)** : `BookingTimelineV` (current=5) + **carte étape courante** avec `ChronoRing` (ex. 3j restants) + **dropzone upload** + rappel orange « dépassement = suspension automatique ».
- **8B (bold)** : **hero countdown** — gros `ChronoRing` (140px) « Vidéo 1 · Unboxing · 3j · 08h14m » + deadline absolue + CTA upload sombre + mini-timeline horizontale + preview du prochain chrono (Avis 14j).

### 9. États de notification deadlines (Face — mobile)
**Purpose** : inbox des notifications avec **escalade visuelle** des deadlines (info teal → urgent orange → critique rouge).
**Variations** :
- **9A (safe)** : inbox listée, chips de filtres, items avec icône colorée + chip d'état (ex. « 24h restantes » orange, « 4h critiques » rouge), point non-lu teal, fonds teintés selon urgence.
- **9B (bold)** : **hero d'alerte critique** (dégradé rouge) avec `ChronoRing danger` 4h + CTA « Uploader maintenant » + reste des notifs en liste sobre dessous.

### 10. Page suspension / abonnement bloqué (Face — mobile)
**Purpose** : informer de la suspension auto (deadline dépassée), expliquer pourquoi et comment réactiver.
**Variations** :
- **10A (safe)** : bandeau rouge « Compte suspendu », profil grisé, bloc « Pourquoi » (vidéo manquante + abonnement bloqué), bloc « Comment réactiver » en 3 étapes (terminer la mission jusqu'à J+30, faire appel, réactivation 24h), CTAs support/terminer.
- **10B (bold)** : **takeover sombre dramatique** plein écran — badge pulsant « Compte suspendu », titre fort, ligne de stats (1 deadline manquée / 11 UGC réussies / 24h réactivation), CTAs « Terminer la mission en retard » + « Faire appel ».

### 11. Workflow système — 6 étapes (vue interne / doc)
**Purpose** : visualiser le tunnel anti-arnaque et les déclencheurs. **À refléter dans l'UI et la doc technique.**
**Les 6 étapes** : 1) Paiement (Producteur, FedaPay/MTN/Moov) → 2) Acceptation (Face, commission encaissée) → 3) Expédition (Producteur, tracking) → 4) Réception (Face, « Produit reçu » = **déclencheur des chronos**) → 5) Unboxing (Face upload + Producteur valide, **chrono 7j**) → 6) Avis (Face upload + Producteur valide → clôture, **chrono 14j**).
**Branche d'exception** : dépassement chrono → **compte Face suspendu + abonnement bloqué + Producteur notifié + remplacement proposé**.
**Variations** :
- **11A (safe)** : flux horizontal clair (light) avec lanes de rôle P/F, légende d'états, encart rouge « branche d'exception ».
- **11B (bold)** : **swim-lanes verticales sombres** (colonnes Étape / Producteur / Face / Chrono) + 2 encarts « Sécurité Producteur » (teal) / « Sanction Face » (rouge).

---

## Interactions & Behavior
- **Toggle compensation** : `product` → champ vidéos verrouillé à 2, pas de champ rémunération ; `hybrid` → vidéos éditable + champ rémunération visible ; le `CommissionBreakdown` se recalcule.
- **Calcul commission** : `commission = Math.max(2500, Math.round(valeurProduit * 0.10))`. Toujours en FCFA, formaté `toLocaleString('fr-FR')`.
- **Démarrage des chronos** : déclenché uniquement par l'action Face « Produit reçu » (étape 4). Chrono 1 = 7j (Unboxing). Chrono 2 = 14j (Avis), démarre après validation du livrable 1.
- **Escalade chrono** : la couleur de `ChronoRing`/`ChronoBadge` suit `progress` (voir tokens). Transition douce.
- **Suspension auto** : si un chrono atteint 1.0 sans upload validé → bascule UI vers l'écran 10 + blocage de l'accès aux missions (écran 6 repasse en paywall/bloqué).
- **Paiement** : sélection provider → (carte → redirection FedaPay ; MTN/Moov → flux USSD/PIN) → succès avec référence.
- **Validation vidéo** : Valider / Rejeter / Demander retouche. Valider L1 → démarre chrono L2 ; Valider L2 → clôture booking (statut completed).
- **Paywall** : tout tap sur mission verrouillée (Face non-abonnée) → CTA vers `/pricing`.
- **Transitions** : `transition-colors` par défaut ; `transition-all duration-200` pour les états ; respecter `prefers-reduced-motion`.
- **Responsive** : écrans Face = mobile-first (prototypés en 360px de large). Écrans Producteur = desktop (≥1024px, `lg:`), avec repli mobile à prévoir.

## State Management
Suivre les patterns Pinia/composables existants (`features/*/composables`, `features/*/services`). État clé à prévoir :
- `compensationType: 'product' | 'hybrid'`, `productValue`, `payAmount`, `videoCount`.
- `bookingStatus` (enum 6 étapes + overdue/suspended), `currentStep`, timestamps `receivedAt`, `deadline1`, `deadline2`.
- `commission` (dérivé). `paymentProvider`, `paymentRef`, `paymentState`.
- `tracking` (transporteur, numéro), `deliverables[]` (url vidéo, statut validation, note).
- Côté Face : `subscriptionTier` (Découverte/Starter/Pro/Élite) pour le paywall ; `accountStatus` (active/suspended).
- Données réelles via les services/API existants (réutiliser `booking/services`, `mission/services`).

## Assets
- **Icônes** : `lucide-vue-next` (déjà au codebase). Icônes utilisées : `video, timer, shield-check, package, package-check, box, credit-card, check, check-circle, x, x-circle, chevron-left/right, upload-cloud, play, lock, ban, alert-triangle, alert-octagon, bell, wallet, briefcase, message-circle, map-pin, star, receipt, info, replace, zap, eye, rotate-ccw, bike, truck, plane, filter, bookmark, share-2, more-vertical, home, calendar-check, user, building-2, layout-dashboard, sparkles, signal, wifi, battery-full`.
- **Imagerie** (produits, vidéos UGC) : placeholders rayés dans les maquettes → à remplacer par les vrais uploads / lecteur vidéo du codebase.
- **Logos paiement** : MTN / Moov / FedaPay (tuiles texte dans la maquette ; intégrer les vrais logos).
- **Polices** : Inter (déjà chargée côté app).

## Files (références de design dans ce bundle)
- `index.html` — point d'entrée : monte le design canvas (11 écrans × 2 variations).
- `shared.jsx` — **toutes les primitives** (ChronoRing, ChronoBadge, StatusPill, CompensationToggle, CommissionBreakdown, BookingTimeline V/H, ReassuranceCard, PayTile, DashChrome, PhoneFrame, boutons, champs…). **À lire en premier.**
- `producer.jsx` — écrans Producteur 1–5 (×2 variations).
- `face.jsx` — écrans Face 6–10 (×2 variations).
- `workflow.jsx` — diagramme workflow (écran 11, ×2 variations).
- `app.jsx` — assemblage canvas + mapping écran→artboard.
- `design-canvas.jsx`, `tweaks-panel.jsx` — outils de présentation (NON pertinents pour l'implémentation produit).

### Pour visualiser les designs
Ouvrir `index.html` dans un navigateur. Pan/zoom sur le canvas ; double-clic (bouton ⤢) sur un artboard pour le voir en plein écran.

### Référence codebase
- `weact-v1/design-system.md` — source de vérité des tokens, header, footer, patterns d'accessibilité.
- `weact-v1/frontend/src/features/booking/components/` — patterns existants (BookingFormSheet, BookingTimeline, BookingStatusBadge, PaymentOverlay…).
- `weact-v1/frontend/src/views/PricingView.vue` — cohérence visuelle des cartes/CTA.
