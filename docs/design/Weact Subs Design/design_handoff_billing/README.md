# Handoff — Onglet « Facturation & Abonnement » (dashboard Face)

## Overview
Nouvel onglet du dashboard Face permettant à un talent de **gérer son abonnement WeAct**. La page comporte trois sections :

1. **Abonnement en cours** — plan actuel (nom + badge de tier + pastille de statut), montant annuel, dates de souscription/fin, barre de temps restant, note de renouvellement, et bouton **« Changer de plan »** aligné à droite.
2. **Historique des abonnements** — liste de lignes repliables ; chaque ligne affiche plan / période / montant / statut + **« Voir détails »** qui expand/collapse les détails de facturation (dates, moyen de paiement, référence transaction, reçu PDF).
3. **Annuler le plan** — titre + texte explicatif + bouton outline rouge **« Annuler »**, qui ouvre une **modal de confirmation**. Une fois annulé, la section passe en état « Abonnement annulé » avec un bouton « Réactiver ».

## About the design files
- `FaceBillingPage.vue` (racine du bundle) est le **composant Vue 3 + TypeScript + Tailwind prêt à intégrer**, écrit selon les conventions du codebase `weact-v1`.
- `references/Facturation Abonnement.html` est le **prototype de référence visuelle** (React/HTML, avec un shell de dashboard et un panneau de Tweaks pour prévisualiser les états). Il ne s'intègre pas tel quel — il sert à valider le rendu et le comportement.

## Fidelity
**High-fidelity (hifi)** — pixel-perfect. Couleurs, typographies, espacements et interactions sont définitifs.

## Prérequis dans le codebase
- Vue 3 (`<script setup lang="ts">`), Tailwind CSS 4.1, `lucide-vue-next`, `vue-router` — **déjà présents**.
- **Dépend du composant `WBadge.vue`** (badge de tier WeAct, livré séparément dans `design_handoff_elite_badge`). Il doit exister à `frontend/src/components/ui/WBadge.vue`. Si tu ne l'intègres pas, retire les `<WBadge>` du composant ou remplace-les par un simple libellé.

---

## Intégration — 3 étapes

### 1. Déposer le composant

```bash
cp design_handoff_billing/FaceBillingPage.vue frontend/src/pages/face/billing/FaceBillingPage.vue
```

### 2. Ajouter la route enfant

Dans `frontend/src/router/index.ts`, à l'intérieur des enfants de la route `/face` (le bloc `FaceLayout`, là où sont déjà `face-dashboard`, `face-wallet`, etc.), ajouter :

```ts
{
  path: 'billing',
  name: 'face-billing',
  component: () => import('../pages/face/billing/FaceBillingPage.vue'),
  meta: { title: 'Facturation & Abonnement - WEACT' },
},
```

### 3. Ajouter l'entrée de menu dans la sidebar

Dans `frontend/src/pages/face/FaceLayout.vue`, le tableau `sidebarItems` contient déjà une entrée « Tarifs » → `/pricing`. Ajouter l'entrée Facturation (l'icône `CreditCard` est **déjà importée** dans ce fichier) :

```ts
const sidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/face/dashboard' },
  { label: 'Voir les missions', icon: Briefcase, to: '/face/missions' },
  { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures' },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/face/bookings' },
  { label: 'Messages', icon: MessageCircle, to: '/face/messages' },
  { label: 'Portefeuille', icon: Wallet, to: '/face/wallet' },
  { label: 'Facturation', icon: CreditCard, to: '/face/billing' }, // ← AJOUT
  { label: 'Mon profil', icon: User, to: '/face/profile' },
]
```

> Remarque : l'entrée « Tarifs » (`/pricing`) actuelle peut être conservée ou fusionnée avec « Facturation ». À ton choix produit — le bouton « Changer de plan » de la page Facturation redirige déjà vers `/pricing`.

---

## Branchement API (à faire — données actuellement mockées)

Le composant contient des données factices dans deux `ref` : `currentSubscription` et `history`. À remplacer par les vrais appels. Endpoints suggérés (à aligner avec le backend Laravel) :

| Action | Endpoint suggéré | Effet |
|---|---|---|
| Charger l'abonnement courant | `GET /face/subscription` | remplit `currentSubscription` |
| Charger l'historique | `GET /face/subscriptions/history` | remplit `history` |
| Annuler | `POST /face/subscription/cancel` | passe `status` à `cancelled`, `autoRenew` à `false` |
| Réactiver | `POST /face/subscription/reactivate` | repasse `status` à `active` |
| Télécharger un reçu | `GET /face/subscriptions/{id}/receipt` | PDF |

> Côté admin, des endpoints de gestion d'abonnement Face existent déjà (`adminFaceSubscriptionsApi` : activate / extend / cancel / correct). Le modèle de données côté Face peut s'en inspirer (champs `status`, `plan`, `expires_at`, `paid_amount`, `currency`, `audits`).

Les fonctions `goToChangePlan()`, `confirmCancel()`, `reactivate()` contiennent des `TODO` aux bons emplacements.

---

## Structure de données

```ts
type Tier = 'decouverte' | 'starter' | 'pro' | 'elite'
type SubStatus = 'active' | 'expiring' | 'cancelled'
type HistoryStatus = 'expired' | 'cancelled' | 'completed'

interface CurrentSubscription {
  tier: Tier
  status: SubStatus
  startDate: string  // ISO 8601
  endDate: string    // ISO 8601
  autoRenew: boolean
}

interface HistoryItem {
  id: string
  tier: Tier
  startDate: string
  endDate: string
  status: HistoryStatus
  method: string     // ex: "Mobile Money · MTN"
  ref: string        // référence transaction
}
```

Les prix par tier sont dans la constante `TIER_META` (alignée sur la grille `/pricing`) :
Découverte 0 · Starter 12 000 · Pro 25 000 · Élite 40 000 FCFA/an.

---

## Comportement & états

- **Statut « actif »** : pastille verte, barre teal, note de renouvellement (selon `autoRenew`).
- **Statut « expire bientôt »** : pastille + barre orange.
- **Statut « annulé »** : pastille rouge, barre orange, message d'avertissement ; la section 3 affiche « Abonnement annulé » + bouton « Réactiver ».
- **Historique** : une seule ligne ouverte à la fois (`openId`). Cliquer une ligne ouverte la referme.
- **Modal d'annulation** : rendue via `<Teleport to="body">`, fermable par clic sur l'overlay, la croix, ou « Garder mon plan ». « Confirmer l'annulation » appelle `confirmCancel()`.
- La barre de temps restant calcule `remainingDays` et `progressPct` à partir de `startDate`/`endDate` et de la date courante.

---

## Design tokens

| Token | Valeur | Usage |
|---|---|---|
| Brand teal | `#198496` | Accents, CTA « Changer de plan », barre active, liens |
| Teal hover | `#146c7a` | Hover CTA solide |
| Dark | `#0F1419` | Titres, badge Élite |
| Rouge action | `#DC2626` / `text-red-600` | Bouton Annuler, modal de confirmation |
| Vert statut | `#10B981` / bg `#ECFDF5` | Pastille « Actif » |
| Orange statut | `#EA580C` / bg `#FFF7ED` | « Expire bientôt » / « Annulé » (barre + alerte) |
| Surface carte | `#FFFFFF` + `border-gray-200` + `shadow-sm` + `rounded-2xl` | Toutes les cartes |
| Tuile interne | `bg-gray-50/60` + `rounded-xl` | Blocs montant/dates |
| Libellés | `text-[11px] font-medium text-gray-500 uppercase tracking-wider` | Labels de champs |

Typo : **Inter** (400–800). Titre de page `text-2xl sm:text-3xl font-bold tracking-tight`.

---

## Responsive
- La grille montant/dates passe de 3 colonnes (`sm:grid-cols-3`) à 1 colonne en mobile.
- Les lignes d'historique masquent le montant inline et le label « Voir détails » sous `sm` (le chevron reste).
- Les détails d'historique passent de 2 colonnes à 1 colonne en mobile (label/valeur en ligne).
- Les en-têtes de section avec bouton à droite utilisent `flex-wrap` pour repasser à la ligne si nécessaire.

---

## Accessibility
- Boutons d'historique : `:aria-expanded` synchronisé avec l'état ouvert.
- Badge `WBadge` : `role="img"` + `aria-label`.
- Modal fermable au clic extérieur ; pensez à ajouter le focus-trap + fermeture sur `Échap` lors de l'intégration (pattern déjà utilisé ailleurs dans le codebase, cf. `design-system.md`).

---

## Source
Prototype validé : `references/Facturation Abonnement.html` (ouvrir et utiliser le panneau **Tweaks** en haut à droite pour basculer entre les états plan/statut/renouvellement).
