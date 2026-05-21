# Handoff — Page Abonnements / Pricing (Faces)

## Overview
Refonte de la page `/pricing` de WeAct pour accueillir la **nouvelle grille tarifaire à 4 paliers** dédiée aux Faces (talents) : Découverte (gratuit) · Starter (12 000 FCFA/an) · Pro (25 000 FCFA/an) · Élite (40 000 FCFA/an).

La page contient quatre sections :
1. **Hero** — accroche + sous-titre de contexte
2. **Pricing Cards** — 4 cartes côte à côte avec barre de progression "ladder" et carte Élite en noir
3. **Tableau comparatif** — fonctionnalités détaillées groupées par catégorie (Portfolio / Missions & revenus / Visibilité / Support)
4. **FAQ** + **Footer CTA**

## About the Design Files
Les fichiers de ce bundle sont des **références de design**, pas du code prêt à copier-coller en production.

- `PricingView.vue` à la racine est **la cible** : une implémentation Vue 3 + TypeScript + Tailwind CSS écrite selon les conventions du codebase WeAct existant (`weact-v1`). Elle est conçue pour **remplacer le fichier actuel** `frontend/src/views/PricingView.vue`.
- Les fichiers dans `references/` sont des prototypes HTML/React qui démontrent le rendu final et le comportement responsive. Ils servent de **source de vérité visuelle** mais ne doivent pas être intégrés tels quels.

## Fidelity
**High-fidelity (hifi)** — pixel-perfect.

Les couleurs, typographies, espacements, rayons et ombres sont définitifs. Le composant `PricingView.vue` est prêt à intégrer ; le développeur n'a qu'à vérifier les routes des CTA et déposer le fichier.

## Files in this bundle

| Fichier | Rôle |
|---|---|
| `PricingView.vue` | **Composant final** Vue 3 + TS + Tailwind, à déposer dans `frontend/src/views/PricingView.vue` |
| `references/Pricing Abonnements Final.html` | Prototype HTML/React de la page intégrée — référence visuelle desktop |
| `references/Pricing Vue Preview.html` | Aperçu responsive du composant Vue (mobile / tablette / desktop) |
| `references/final.jsx` | Code source React des sections (cards + table + FAQ) — référence d'implémentation |
| `references/data.js` | Données structurées : tiers, comparison rows |
| `references/abonnement-weact-spec.txt` | Spécifications business des 4 paliers (source produit) |

## Integration

```bash
# Depuis la racine du codebase weact-v1 :
cp design_handoff_pricing/PricingView.vue frontend/src/views/PricingView.vue
```

La route `/pricing` existe déjà dans `frontend/src/router/index.ts` — pas de configuration supplémentaire requise.

**Dépendances déjà présentes dans le projet :**
- Vue 3 (Composition API + `<script setup lang="ts">`)
- Tailwind CSS 4.1
- `lucide-vue-next` (icônes `Check`, `ChevronDown`, `Crown`, `Minus`)
- `vue-router` (composant `RouterLink`)

Aucune nouvelle dépendance n'est à installer.

---

## Sections — détail

### 1. Hero

- **Layout** : centré, max-width `max-w-3xl`, padding vertical responsif (`pt-16 sm:pt-20 lg:pt-24`, `pb-12`)
- **Eyebrow** : `text-xs font-semibold text-[#198496] uppercase tracking-[0.14em]` — copie : *"Abonnements Faces · Tarifs 2026"*
- **H1** : `text-3xl sm:text-4xl lg:text-[44px] font-bold text-[#0F1419]` — copie : *"Plus tu montes, plus tu décroches."*
- **Paragraphe** : `text-base text-gray-500 leading-relaxed max-w-xl mx-auto`

### 2. Pricing Cards

**Breakpoints :**
- `< 640px` : 1 colonne, chaque carte arrondie + bordure individuelle
- `640–1024px` : 2 colonnes
- `≥ 1024px` : 4 colonnes **jointes** dans un container `rounded-2xl overflow-hidden` avec bordures internes

**Layout commun à chaque carte** :
- Padding : `p-7`
- Background : `bg-white` (sauf Élite : `bg-[#0F1419] text-white`)
- Bordure droite entre colonnes en desktop : `lg:border-r lg:border-gray-200` (sauf dernière)

**Anatomie verticale d'une carte** :
1. **Ladder indicator** (signature visuelle) — 4 segments `w-5 h-[3px] rounded-full`, remplis jusqu'au palier actuel
   - Actif : `bg-[#198496]` (ou `bg-white` sur Élite)
   - Inactif : `bg-gray-200` (ou `bg-gray-700` sur Élite)
2. **Nom du palier + badge** — `text-xl font-bold tracking-tight`
   - Badge "Populaire" sur Pro : `text-[9px] font-bold tracking-[0.12em] uppercase text-white bg-[#198496] px-1.5 py-0.5 rounded`
   - Badge "VIP" sur Élite remplacé par une icône `Crown` 14×14 blanche
3. **Tagline** — `text-sm leading-snug mb-6 min-h-[38px]` (`text-gray-400` sur Élite, `text-gray-500` sinon)
4. **Prix** — `text-[38px] font-bold tracking-tight leading-none` + suffixe "FCFA" en `text-sm font-medium`
   - Sous-titre : `text-xs mt-1.5` — *"Offre découverte"* ou *"par an · sans engagement"*
5. **CTA principal** — `w-full text-center text-sm font-semibold py-2.5 px-4 rounded-md mb-7`
   - Pro : `bg-[#198496] text-white hover:bg-[#146c7a]`
   - Élite : `bg-white text-[#0F1419] hover:bg-gray-100`
   - Autres : `text-[#198496] border border-[#198496] hover:bg-[#198496]/5`
6. **Section "Inclus"** — eyebrow `text-[11px] font-bold uppercase tracking-[0.10em]`
7. **Liste de features** — `<ul class="flex flex-col gap-3 flex-grow">`
   - Item : `<li class="flex items-start gap-2.5">` avec icône `Check` (`h-4 w-4 mt-0.5`) + texte `text-sm leading-snug`
   - Sur Élite : check blanc, texte `text-gray-300` (ou `text-white font-semibold` si `f.highlight === true`)

**Features mises en exergue (`highlight: true`) sur Élite** :
- Commission réduite à 5% (au lieu de 10%)
- Badge "VIP / Elite" sur le profil
- Mise en avant Prioritaire Absolue

### 3. Tableau comparatif

- **Container** : `bg-[#FAFAFA] py-20` (zone alternée)
- **Table** : `table-fixed min-w-[760px]` dans un wrapper `overflow-x-auto` pour mobile
- **Colonnes** : 32% / 17% / 17% / 17% / 17%

**Header (sticky-like) par tier** :
- Background `bg-[#FAFAFA]` sauf colonne Élite : `bg-[#0F1419] text-white`
- Badge "Populaire" sur Pro / "VIP" sur Élite : positionnés en `absolute top-3.5 right-3.5`
- Prix `text-[22px] font-bold` + suffixe `text-[11px]`
- Bouton "Choisir" `inline-block px-3.5 py-1.5 text-xs font-semibold rounded-md` (3 variantes selon tier)

**Groupes de lignes** :
- Header de groupe : ligne `colspan="4"` en `bg-gray-50` + `text-[11px] font-bold uppercase tracking-[0.14em] text-[#198496]`
- La 5ᵉ cellule (colonne Élite) garde le fond `bg-[#0F1419]` pour continuité visuelle

**Cellules** :
- `boolean true` → icône `Check` (teal ou blanc sur Élite)
- `boolean false` → icône `Minus` `text-gray-300`
- `string` → `<span class="font-medium">{value}</span>`
- Bordure inférieure : `border-b border-gray-100` (ou `border-gray-800` sur Élite)

### 4. FAQ

- **Container** : `max-w-3xl mx-auto px-6 py-20`
- Liste d'items avec `border border-gray-200 rounded-lg bg-white overflow-hidden`
- Bouton de toggle : `flex items-center justify-between px-5 py-4` + icône `ChevronDown` qui pivote
- Corps : `text-sm text-gray-600 leading-relaxed border-t border-gray-100 pt-3`
- Le premier item est ouvert par défaut (`isOpen: true`)

### 5. Footer CTA

- **Container parent** : `bg-[#FAFAFA] pb-20` (continuation de la zone du tableau)
- **Carte** : `bg-white border border-gray-200 rounded-2xl p-10 text-center shadow-sm max-w-3xl mx-auto`
- Deux CTAs côte à côte (stack en mobile via `flex-col sm:flex-row`)

---

## State management

Réactivité Vue minimale, tout en local :

```ts
const faqs = ref<FAQItem[]>([...])
const toggleFaq = (index: number) => {
  const faq = faqs.value[index]
  if (faq) faq.isOpen = !faq.isOpen
}
```

Aucun store Pinia, aucun appel API. Les données sont statiques (constantes du fichier).

---

## Design Tokens

### Couleurs
| Token | Hex | Usage |
|---|---|---|
| Brand teal | `#198496` | Accent principal, CTAs, checks, tracking d'éléments actifs |
| Teal hover | `#146c7a` | Hover du CTA solide |
| Dark | `#0F1419` | Carte Élite, header Élite du tableau, H1 |
| Gray 900 | `#111827` | Titres |
| Gray 700 | `#374151` | Texte features |
| Gray 500 | `#6B7280` | Sous-titres |
| Gray 400 | `#9CA3AF` | Légendes, eyebrows secondaires |
| Gray 300 | `#D1D5DB` | Icônes "non inclus" (Minus) |
| Gray 200 | `#E5E7EB` | Bordures |
| Gray 100 | `#F3F4F6` | Séparateurs |
| Gray 50 | `#F9FAFB` | Header de groupe dans le tableau |
| Surface alt | `#FAFAFA` | Section tableau + footer CTA |

### Typographie
- Famille : **Inter** (déjà chargée dans le projet, weights 400/500/600/700)
- Hero H1 : `44px / 700 / -0.025em / 1.1`
- Section H2 : `30px / 700 / -0.025em`
- Prix dans cards : `38px / 700 / -0.025em / 1`
- Prix dans table : `22px / 700 / -0.02em`
- Nom de tier : `20px / 700 / -0.01em`
- Body : `14px / 400 / 1.5`
- Eyebrow : `12px / 600 / uppercase / 0.14em`
- Badge : `9px / 700 / uppercase / 0.12em`

### Espacement
- Container max-width : `max-w-7xl` (1280px)
- Padding horizontal sections : `px-6`
- Gap entre features : `gap-3`
- Padding interne cartes : `p-7`

### Border radius
- Cartes en mobile : `rounded-2xl`
- Container cartes en desktop : `rounded-2xl overflow-hidden`
- Boutons : `rounded-md`
- FAQ items : `rounded-lg`
- Badges : `rounded` (4px)

### Ombres
- Container de cartes desktop : `shadow-[0_4px_32px_-16px_rgba(15,20,25,0.10)]`
- Footer CTA : `shadow-sm`

---

## Data structure

### Tiers (4 paliers)

```ts
interface PricingTier {
  key: 'decouverte' | 'starter' | 'pro' | 'elite'
  name: string
  tagline: string
  priceLabel: string  // "0", "12 000", "25 000", "40 000"
  isFree: boolean
  description: string
  cta: string
  ctaTo: string       // ex: "/register/face?plan=pro"
  badge: string | null  // "Populaire" | "VIP" | null
  features: { text: string; included: boolean; highlight?: boolean }[]
}
```

### Comparison rows

```ts
interface ComparisonRow {
  name: string
  decouverte: boolean | string
  starter: boolean | string
  pro: boolean | string
  elite: boolean | string
}
```

Groupes : `Portfolio` · `Missions & revenus` · `Visibilité` · `Support`.

---

## Routes à brancher

Le composant utilise `<RouterLink>` avec les routes suivantes — **à vérifier / créer côté router** :

| Route | Utilisée par |
|---|---|
| `/register/face` | CTA Découverte + CTA "Créer mon profil gratuit" |
| `/register/face?plan=starter` | CTA Starter (carte + tableau) |
| `/register/face?plan=pro` | CTA Pro (carte + tableau) |
| `/register/face?plan=elite` | CTA Élite (carte + tableau) |
| `/contact` | CTA "Nous contacter" du footer |

Si ces routes n'existent pas encore dans `router/index.ts`, le développeur doit soit les créer, soit ajuster les `to` pour pointer vers les routes existantes.

---

## Responsive behavior

| Breakpoint | Comportement |
|---|---|
| `< 640px` (mobile) | Cards en 1 colonne stack, chaque carte arrondie et bordée individuellement. Tableau scrollable horizontalement (`min-w-[760px]`). Footer CTA stack vertical. |
| `640–1024px` (tablette) | Cards en grille 2×2. Tableau scrollable horizontalement. |
| `≥ 1024px` (desktop) | Cards en 4 colonnes jointes (single container `rounded-2xl overflow-hidden`). Tableau pleine largeur. Footer CTA en row. |

---

## Accessibility

- `:aria-expanded="faq.isOpen"` sur les boutons d'accordéon FAQ
- Tous les liens sont des `<RouterLink>` (navigation client SPA)
- Contraste vérifié : texte blanc sur `#0F1419` (Élite) > 16:1 ; teal `#198496` sur blanc > 4.5:1

---

## Notes business critiques

1. **Le bouton "Postuler" doit être bloqué pour les comptes Découverte** sur les missions UGC (logique côté autre feature, mais cette page est leur paywall principal)
2. **Tri des profils dans la recherche producteur** : Élite → Pro → Starter → Découverte (logique algorithme, pas UI)
3. **Commission réduite Élite** : 5% au lieu de 10% sur les missions rémunérées en argent — argument fort de l'offre, mis en exergue dans la carte
4. **Tarifs annuels** uniquement, paiement unique. Pas de mensualisation prévue pour le moment.

---

## Source produit
Voir `references/abonnement-weact-spec.txt` pour les spécifications brutes des 4 offres telles que définies par le produit.
