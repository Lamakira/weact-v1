# Handoff : Détail mission UGC — disposition « tout visible » (desktop + mobile)

## Overview
Refonte de la page **détail d'une mission UGC** côté Face (`FaceMissionDetailPage.vue`, état `isUgc === true`).

Problème actuel : la page est une **colonne unique `max-w-4xl`** empilée verticalement (badges → titre → stats → brief → livrables → détails → profil → producteur → bouton « Postuler »). Le bouton **« Postuler à cette mission »** se retrouve tout en bas, obligeant l'utilisateur à scroller toute la page avant de pouvoir candidater — alors qu'il reste **beaucoup d'espace horizontal vide** (plusieurs champs sont masqués pour l'UGC : tournage, lieu, budget, durée).

Objectif : exploiter l'espace horizontal pour que la zone d'action (« Postuler ») soit **visible sans scroller**.
- **Desktop** : passage en **2 colonnes**, avec une **carte d'action latérale collante (sticky)** qui contient les stats, les infos clés, le producteur et le bouton « Postuler ».
- **Mobile** : colonne unique, mais bouton « Postuler » dans une **barre d'action fixée en bas** (toujours visible pendant le scroll).

## About the Design Files
Les fichiers de ce bundle (`*.dc.html`) sont des **références de design réalisées en HTML/Tailwind** — des prototypes montrant l'apparence et le comportement souhaités, **pas du code de production à copier tel quel**.

La tâche est de **recréer ces maquettes dans le codebase existant** : c'est une app **Vue 3 (`<script setup>`) + Tailwind CSS + lucide-vue-next**, et le fichier cible est `frontend/src/pages/face/mission/FaceMissionDetailPage.vue`. Réutilisez les composants et patterns déjà en place (voir « Composants existants à réutiliser »). Ne réintroduisez pas le HTML brut.

## Fidelity
**High-fidelity (hifi).** Couleurs, typographie, espacements et classes Tailwind sont finaux et alignés sur le design system WEACT. Les maquettes utilisent volontairement les **mêmes classes Tailwind** que le codebase pour faciliter le portage. Recréez l'UI au pixel près avec les composants existants.

> Note : les maquettes chargent Tailwind via le Play CDN et la police Inter via Google Fonts uniquement pour la prévisualisation autonome. Dans l'app, utilisez la configuration Tailwind et la police déjà en place — ne pas ajouter le CDN ni l'import de police.

---

## Screens / Views

### 1. Desktop — Détail mission UGC (`Mission UGC Detail.dc.html`)

**Purpose** : le Face consulte une mission UGC et postule. Le CTA « Postuler » doit être au-dessus de la ligne de flottaison.

**Layout**
- Conteneur : `max-w-7xl mx-auto px-6` (au lieu de l'actuel `max-w-4xl`).
- En-tête de page **pleine largeur** au-dessus de la grille : breadcrumb « Retour aux missions UGC », rangée de badges, titre `<h1>`, ligne meta (date + producteur).
- Sous l'en-tête : grille **`grid grid-cols-1 lg:grid-cols-12 gap-6 items-start`**.
  - **Colonne gauche (contenu)** : `lg:col-span-7 xl:col-span-8`, `space-y-5`. Contient, dans l'ordre : Brief → Livrables → Détails → Profil recherché.
  - **Colonne droite (action)** : `<aside class="lg:col-span-5 xl:col-span-4">` contenant un wrapper **`sticky top-6 space-y-4`**. Contient : carte d'action (stats + infos clés + bouton Postuler) puis carte producteur.
- `items-start` sur la grille est indispensable pour que `sticky` fonctionne (sinon les colonnes s'étirent à la même hauteur).

> Dans la maquette autonome, le breakpoint est `md:` pour que la prévisualisation étroite montre déjà 2 colonnes. **Dans l'app, utilisez `lg:`** (≥1024px) : en dessous, on retombe sur la version mobile.

**Composants (colonne gauche)**
- **Brief** : carte `rounded-2xl border border-gray-100 bg-white p-6 shadow-sm`. Titre `text-sm font-semibold text-gray-900 mb-2.5`. Corps `text-sm text-gray-600 leading-relaxed whitespace-pre-line`. (Reprend la carte « Brief » existante, libellée « Brief » en UGC.)
- **Livrables** (`UgcDeliverablesPreview`) : carte `rounded-2xl border border-primary/15 bg-primary/[0.03] p-5`. Sur-titre `text-[10px] font-bold uppercase tracking-widest text-primary` = « Livrables — chronos déclenchés à la réception du produit ». Deux items en `grid sm:grid-cols-2 gap-3`, chacun `rounded-xl border border-gray-200 bg-white p-3` avec un `ChronoRing` (anneau gris, label « 7j » / « 14j ») et le texte. Ligne « + N vidéo(s) supplémentaire(s) ».
- **Détails de la mission** : carte blanche. Pour l'UGC, **seuls 3 champs** sont pertinents → grille `grid grid-cols-2 sm:grid-cols-3 gap-3` : Faces recherchées, Genre recherché, Date limite. Chaque tuile `rounded-xl bg-gray-50 p-3.5` avec une pastille d'icône `p-2 rounded-lg bg-primary/10 text-primary` (icônes lucide `Users`, `UserCheck`, `Target`), label `text-[10px] uppercase tracking-wider text-gray-500`, valeur `text-sm font-medium text-gray-900`.
- **Profil recherché** : carte blanche identique au Brief.

**Composants (colonne droite — carte d'action `rounded-2xl border border-gray-100 bg-white p-5 shadow-sm`)**
- **Stats UGC** (`UgcMissionStats`) en haut : `grid grid-cols-3 gap-2`. Tuiles « Produit » et « Cash » = `rounded-xl border border-primary/15 bg-primary/5 p-2.5 text-center` ; tuile « Vidéos » = `rounded-xl bg-primary p-2.5 text-center` (texte blanc). Label `text-[9px] font-bold uppercase tracking-widest`, valeur `text-sm font-bold`. (La colonne « Cash » n'apparaît que si `montant_remuneration != null`.)
- **Infos clés** : `<dl>` `space-y-2 text-sm`, lignes `flex items-center justify-between` (terme gris `text-gray-500`, valeur `font-medium text-gray-900`) : Faces recherchées, Genre, Date limite.
- Séparateur `h-px w-full bg-gray-100`.
- **Bouton « Postuler à cette mission »** : `w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary/90` (`hover:bg-[#146c7a]`). **Important** : ce bouton remplace l'ancienne « Apply Section » de bas de page ; tous les états existants (déjà postulé / email non vérifié / genre inconnu / genre incompatible / mission fermée / engagé UGC / reconfirmation) doivent être déplacés **dans cette carte**, pas dupliqués.
- Sous-texte rassurance `text-xs text-gray-400 text-center` : « Candidature gratuite · réponse du producteur sous 72h ».
- **Carte producteur** (sous la carte d'action, dans le même `sticky` wrapper) : `rounded-2xl border border-gray-100 bg-white p-5 shadow-sm`. Avatar 44px (`h-11 w-11`, fallback initiale `bg-primary/10 text-primary`), nom, `RatingDisplay` (étoile ambre `#fbbf24`, note, « (32 avis) »), ligne « Producteur vérifié — dotation réglée via WeAct » `text-xs font-medium text-primary` avec icône `ShieldCheck`.

### 2. Mobile — Détail mission UGC (`Mission UGC Detail Mobile.dc.html`)

**Purpose** : même page sur téléphone. Impossible de tout caser au-dessus de la ligne de flottaison → le CTA reste accessible via une **barre fixe en bas**.

**Layout** (référence dessinée dans un cadre 390×844)
- En-tête d'app : flèche retour + « Mission UGC ».
- **Zone scrollable** entre l'en-tête et la barre du bas, `px-4 space-y-4`, dans cet ordre : badges + titre → stats (3 colonnes) → **carte producteur compacte** (remontée tôt comme signal de confiance) → Brief → Livrables (items empilés `space-y-2`) → Détails (liste `<dl>` compacte) → Profil.
- **Barre d'action fixe en bas** : `fixed`/`sticky` `inset-x-0 bottom-0`, `bg-white/95 backdrop-blur border-t border-gray-100 px-4 pt-3 pb-7` (padding bas = safe-area). Contient le bouton pleine largeur « Postuler à cette mission » (`rounded-xl py-3.5`) + sous-texte « Candidature gratuite · réponse sous 72h ». La zone de contenu réserve un padding bas équivalent pour ne pas être masquée.

**Différences clés vs desktop**
- Stats en `grid-cols-3` pleine largeur.
- Producteur en carte horizontale compacte avec badge « Vérifié » abrégé.
- Détails en liste `<dl>` plutôt qu'en grille de tuiles.
- CTA en barre fixe basse plutôt qu'en sidebar sticky.

---

## Interactions & Behavior
- **« Postuler à cette mission »** : ouvre `ApplyToMissionModal` (`openApplyModal()`), comme aujourd'hui. Comportement et garde-fous **inchangés** — seule la **position** du bloc change (sidebar desktop / barre fixe mobile).
- **États de la zone d'action** (logique existante, à conserver) : déjà postulé (bannière + suivi `UgcFaceTrackingCard` / bandeau engagé / annuler), email non vérifié (renvoyer l'email), contexte genre indisponible (bouton désactivé), incompatibilité genre (bouton désactivé), reconfirmation UGC (`accepted → confirmed`), mission fermée. Tous rendus **à l'emplacement du CTA**.
- **Sticky (desktop)** : `position: sticky; top: 1.5rem` sur le wrapper de la sidebar ; exige `items-start` sur la grille parente.
- **Producteur cliquable** : `router-link` vers `/producers/{slug}` si `slug` présent, sinon bloc statique (logique existante conservée).
- **Hover** : boutons primaires `hover:bg-[#146c7a]` ; liens/zones `transition-colors`.
- **Responsive** : `< lg` → layout mobile (colonne unique + barre fixe basse) ; `≥ lg` → 2 colonnes + sidebar sticky.

## State Management
Aucune nouvelle donnée ni nouvel état. La refonte est **purement présentationnelle (template)**. Tous les `computed`/`ref` existants (`isUgc`, `hasApplied`, `canApply`, `ugcEngaged`, `canReconfirmUgc`, `isGenderMismatch`, `mission.is_accepting_candidatures`, etc.) restent identiques. On réorganise le markup de la branche `v-else-if="mission"` ; on ne touche pas au `<script setup>`.

## Design Tokens
- **Primary** : `#198496` (Tailwind `primary`) · **Primary hover** : `#146c7a`
- **Primary subtil** : `bg-primary/5`, `bg-primary/10`, `bg-primary/[0.03]`, bordures `border-primary/15`
- **Texte** : titres `text-gray-900` · corps `text-gray-600`/`text-gray-700` · secondaire `text-gray-500` · faible `text-gray-400`
- **Surfaces** : cartes `bg-white` · fonds de tuile `bg-gray-50` · page `bg-gray-50`
- **Bordures** : `border-gray-100` (cartes/dividers), `border-gray-200` (items livrables)
- **Étoile note** : `#fbbf24` (amber-400) · **Succès** : `green-50/200/700` · **Alerte** : `amber-50/200/600/700/800`
- **Rayons** : cartes `rounded-2xl` (desktop) / `rounded-xl` (mobile + tuiles) · boutons `rounded-lg` (desktop) / `rounded-xl` (mobile)
- **Ombres** : `shadow-sm` sur les cartes
- **Typo** : Inter. Titre page `text-2xl md:text-3xl font-bold` · titres de carte `text-sm font-semibold` · corps `text-sm` (desktop) / `text-[13px]` (mobile) · labels `text-[10px]`/`text-[9px] uppercase tracking-widest`
- **Espacements** : grille `gap-6` · sidebar `space-y-4` · colonne gauche `space-y-5` · padding cartes `p-6` (desktop) / `p-4` (mobile) · sticky `top-6`

## Assets
- **Icônes** : `lucide-vue-next` (déjà dans le codebase) — `ChevronLeft`/`ArrowLeft`, `Users`, `UserCheck`, `Target`, `Calendar`, `Wallet`, `Clock`, `Star`, `ShieldCheck`, `CheckCircle`. (Dans les maquettes elles sont inline en SVG pour l'autonomie ; côté app utiliser les composants lucide.)
- **ChronoRing** : composant existant `@/components/ugc` (anneau de progression). Réutiliser tel quel.
- Aucune image bitmap. Avatar producteur = `<img>` si `profile_photo_thumbnail_url`, sinon initiale.

## Composants existants à réutiliser
- `UgcMissionStats` (`@/features/mission/components`) — grille Produit/Cash/Vidéos. À placer dans la carte d'action (desktop) / en haut du scroll (mobile).
- `UgcDeliverablesPreview` (`@/features/mission/components`) — bloc livrables.
- `RatingDisplay` — note producteur.
- `UgcFaceTrackingCard`, `ApplyToMissionModal`, `ConfirmModal` — inchangés.

## Files
Maquettes de référence (dans ce bundle) :
- `Mission UGC Detail.dc.html` — variante desktop (2 colonnes + sidebar sticky).
- `Mission UGC Detail Mobile.dc.html` — variante mobile (colonne unique + barre d'action fixe).

Fichier cible dans le codebase :
- `frontend/src/pages/face/mission/FaceMissionDetailPage.vue` — réorganiser le markup de la branche `v-else-if="mission"` (lignes ~483–860). `<script setup>` inchangé.
