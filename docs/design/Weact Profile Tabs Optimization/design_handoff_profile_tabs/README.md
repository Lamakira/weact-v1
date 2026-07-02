# Handoff : Refonte de « Mon profil » en onglets groupés (Face)

## Vue d'ensemble

Cette refonte transforme la page **« Mon profil »** de la Face (talent) — actuellement une longue
colonne de ~13 sections empilées — en une **interface à onglets groupés** : une barre d'onglets
horizontale (familles) alignée en haut de la colonne de droite, à hauteur de la carte « Photo de
mise en avant », plus une **navigation latérale verticale** des sections à l'intérieur de chaque
famille (master/detail).

L'objectif est double :
1. **Regrouper** les sections par intention (elles avaient été ajoutées au fil du développement, sans ordre logique).
2. **Alléger** la lecture : on ne voit qu'une section à la fois, dans un flow naturel.

> ⚠️ **C'est un refactor, pas une réécriture.** Tous les composants de formulaire existent déjà
> dans le codebase (`PersonalInfoForm.vue`, `BioLocationForm.vue`, `TarifsForm.vue`,
> `ProfileCompletionIndicator.vue`, etc.). **Aucun composant de formulaire n'est à recréer.** Le
> travail se concentre presque entièrement dans **`src/pages/face/ProfileEditPage.vue`** :
> on change la mise en page (onglets + nav latérale) et la navigation de complétion (changement
> d'onglet au lieu de `scrollIntoView`). Le `<script setup>` — composables, handlers `@save`,
> states de chargement — reste **quasiment inchangé**.

---

## À propos des fichiers de design

Les fichiers de ce bundle (`Mon Profil — Tabs.html` + `profile-tabs/*`) sont des **références de
design réalisées en HTML/React** : un prototype montrant l'apparence et le comportement visés.
**Ce ne sont pas des fichiers de production à copier-coller.** La tâche est de **recréer ce design
dans l'environnement Vue 3 + TypeScript + Tailwind existant**, en réutilisant les composants et
conventions du codebase WeAct.

Le prototype utilise des données fictives (Imrane Sani, etc.) ; les vraies données viennent des
composables existants.

## Fidélité

**Haute-fidélité (hifi).** Couleurs, typographie, espacements, rayons, états hover/active et
interactions sont définitifs et doivent être respectés au pixel près, **en réutilisant les classes
Tailwind et tokens du codebase** (`text-teal-600`, `rounded-2xl`, `shadow-sm`, `border-gray-100`…).
Le prototype reste volontairement dans le langage visuel WeAct actuel.

> Note : seule la **Direction B** (onglets soulignés + navigation latérale) a été retenue.
> Le prototype ne contient que cette direction.

---

## Structure cible : familles → sections

Quatre familles (onglets horizontaux), chacune contenant ses sections (navigation latérale).
Chaque section réutilise **un composant existant** :

### 1. Profil  (icône : user)
| Section (nav latérale) | Composant existant | Notes |
|---|---|---|
| Infos perso | `BasicInfoSection.vue` | nom, prénom, nom d'utilisateur (anciennement tout en haut) |
| Identité | `PersonalInfoForm.vue` | sexe, date de naissance, nationalité, pays, WhatsApp (`#section-personal-info`) |
| Caractéristiques physiques | `PhysicalCharacteristicsForm.vue` | taille, poids |
| Langues parlées | `LanguesTagInput.vue` | (`#section-langues`) |
| Bio & Localisation | `BioLocationForm.vue` | bio, ville, pays (`#section-bio-location`) |

### 2. Portfolio  (icône : image)
| Section | Composant existant | Notes |
|---|---|---|
| Album photos | `PhotoAlbumGrid.vue` + `AlbumPhotoUpload.vue` | grille + upload + input fichier caché |
| Vidéo de présentation | `PresentationVideoUpload.vue` | (`#section-presentation-video`) |
| Vidéo d'acting | `FaceVideoUpload.vue` `type="acting"` | (`#section-acting-video`) |
| Vidéo UGC | `FaceVideoUpload.vue` `type="ugc"` | (`#section-ugc-video`) |

> Le prototype regroupait les vidéos en une seule entrée « Vidéos » par simplification visuelle.
> **Garde les 3 sections vidéo distinctes** : `presentation_video` et `acting_video` sont des
> critères de complétion séparés côté backend. Tu peux les présenter comme 3 items de nav latérale
> sous Portfolio, ou un item « Vidéos » qui empile les trois — au choix, mais ne fusionne pas la logique.

### 3. Carrière  (icône : briefcase)
| Section | Composant existant | Notes |
|---|---|---|
| Catégorie & Niche | `CategoryNicheForm.vue` | (`#section-category-niche`) |
| Expériences | `ExperiencesList.vue` | garde `ref="experiencesListRef"` |
| **Tarif** | `TarifsForm.vue` | **déplacé** depuis la sidebar vers un onglet (demande explicite) |

### 4. Compte  (icône : shield)
| Section | Composant existant | Notes |
|---|---|---|
| Email & mot de passe | `EmailChangeForm.vue` + `PasswordChangeForm.vue` | les deux dans une même section, séparés par un filet |
| Mes données personnelles | `DataPrivacySection.vue` | anciennement en bas de page |

---

## Colonne de gauche (sidebar sticky)

Reste à gauche, sticky (`lg:sticky lg:top-6`), **allégée**. Cartes conservées :

1. **Photo de mise en avant** — `ProfilePhotoUpload` (inchangé, `#section-profile-photo`).
2. **Complétion** — `ProfileCompletionIndicator` `variant="full"` (voir section dédiée ci-dessous). **À garder intacte.**
3. **Statut** — fusion de :
   - `AvailabilityToggle` (Disponibilité)
   - Tarif en **lecture seule** (le formulaire d'édition vit désormais dans l'onglet Carrière → Tarif)
   - `RatingDisplay` (Ma note)
4. **Résumé** — petite liste `<dl>` dérivée de `profile` (sexe, âge, taille, nationalité, ville, langues). *Optionnel* — peut remplacer l'ancienne carte « Informations » (nom/prénom/pseudo).

> Le **Tarif** ne doit plus être éditable dans la sidebar (pour éviter deux points d'édition
> contradictoires). La carte sidebar n'affiche que la valeur ; l'édition se fait dans l'onglet.

---

## La carte « Complétion » (à conserver telle quelle)

Le composant `ProfileCompletionIndicator` (`variant="full"`) existe déjà et affiche exactement ce
qu'il faut : titre, « Complétez votre profil » + pourcentage + barre de progression, puis
**« ÉLÉMENTS MANQUANTS »** sous forme de lignes cliquables (icône ⚠ + libellé). **Ne pas le
simplifier.** Il émet déjà `@click-item="handleCompletionItemClick"`.

**Seul changement** : aujourd'hui `handleCompletionItemClick` fait un `scrollIntoView` vers une
ancre `#section-…`. En version onglets, il doit **activer le bon onglet + la bonne section**.

### Mapping actuel (à remplacer)
```ts
// AVANT — ProfileEditPage.vue ~ligne 586
function handleCompletionItemClick(itemKey: string): void {
  const map = {
    profile_photo:      'section-profile-photo',
    presentation_video: 'section-presentation-video',
    acting_video:       'section-acting-video',
    bio:                'section-bio-location',
    ville:              'section-bio-location',
    langues:            'section-langues',
    categorie:          'section-category-niche',
    tarifs:             'section-tarifs',
    whatsapp_number:    'section-personal-info',
  }
  const sectionId = map[itemKey]
  if (sectionId) document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
```

### Mapping cible (vers onglets)
```ts
// APRÈS — chaque clé pointe vers (famille, section)
const COMPLETION_TO_TAB: Record<string, { family: string; section: string }> = {
  profile_photo:      { family: 'profil',    section: 'photo' },     // sidebar → ouvre ProfilePhotoUpload (ou highlight)
  presentation_video: { family: 'portfolio', section: 'video-pres' },
  acting_video:       { family: 'portfolio', section: 'video-acting' },
  bio:                { family: 'profil',    section: 'bio' },
  ville:              { family: 'profil',    section: 'bio' },
  langues:            { family: 'profil',    section: 'langues' },
  categorie:          { family: 'carriere',  section: 'categorie' },
  tarifs:             { family: 'carriere',  section: 'tarif' },
  whatsapp_number:    { family: 'profil',    section: 'identite' },
}

function handleCompletionItemClick(itemKey: string): void {
  const target = COMPLETION_TO_TAB[itemKey]
  if (!target) return
  activeFamily.value = target.family
  activeSectionByFamily.value[target.family] = target.section
}
```
> `profile_photo` reste dans la sidebar (pas d'onglet) — soit l'ignorer, soit faire flasher/scroller
> la carte photo. À toi de voir ; les autres clés ouvrent l'onglet correspondant.

---

## État (state management) à ajouter dans ProfileEditPage.vue

Seules **deux** refs sont à ajouter ; tout le reste (composables, données, handlers `@save`) ne change pas.

```ts
const activeFamily = ref<'profil' | 'portfolio' | 'carriere' | 'compte'>('profil')

// On mémorise la dernière section ouverte par famille, pour revenir au bon endroit
const activeSectionByFamily = ref<Record<string, string>>({
  profil:    'infos',
  portfolio: 'album',
  carriere:  'categorie',
  compte:    'securite',
})

const activeSection = computed(() => activeSectionByFamily.value[activeFamily.value])
function selectSection(id: string) { activeSectionByFamily.value[activeFamily.value] = id }
```

Le rendu des sections passe de « toutes empilées » à **conditionnel** :
- `v-show` (recommandé) si tu veux garder l'état/scroll interne des formulaires montés.
- `v-if` si tu préfères ne monter que la section active (plus léger, mais re-monte à chaque visite).

> ⚠️ `ExperiencesList` utilise `ref="experiencesListRef"` (appelé via `resetFormStates()`).
> Si tu choisis `v-if`, garde la section Expériences montée ou re-vérifie que la ref existe avant l'appel.

---

## Structure du markup (cible)

```
<div> page "Mon profil" (titre inchangé)
  <div class="flex flex-col lg:flex-row gap-6">

    <!-- SIDEBAR (lg:w-80, sticky) -->
    <aside class="lg:w-80 flex-shrink-0">
      <div class="lg:sticky lg:top-6 space-y-6">
        [Carte Photo de mise en avant]
        [Carte Complétion — ProfileCompletionIndicator variant=full]
        [Carte Statut — Availability + Tarif(lecture seule) + Rating]
        [Carte Résumé (optionnel)]
      </div>
    </aside>

    <!-- COLONNE PRINCIPALE -->
    <div class="flex-1 min-w-0">
      [Message succès (inchangé)]

      <!-- Barre d'onglets familles (soulignés) -->
      <nav role="tablist" class="flex gap-1 border-b border-slate-200 px-1">
        <button v-for="f in families" role="tab" :aria-selected=… @click="activeFamily=f.id">
          <icon/> {{ f.label }}
        </button>
      </nav>

      <!-- Panneau master/detail -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="grid grid-cols-[224px_minmax(0,1fr)]">
          <!-- Nav latérale des sections de la famille active -->
          <nav class="border-r border-gray-100 p-3.5 space-y-0.5">
            <button v-for="s in activeFamilySections" :class="active?…" @click="selectSection(s.id)">
              <icon/> {{ s.label }} <span v-if="!s.complete" class="dot"/>
            </button>
          </nav>
          <!-- Contenu de la section active -->
          <div class="flex flex-col">
            <div class="p-6 md:p-7"> … en-tête (h2 + sous-titre) + <component existant> … </div>
            <!-- la barre Enregistrer reste celle de chaque composant existant -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

> **Sauvegarde par section** : on garde le comportement actuel — chaque composant
> (`BioLocationForm`, `TarifsForm`…) émet son propre `@save` et possède son propre bouton
> « Enregistrer ». Pas de changement de logique de sauvegarde.

---

## Interactions & comportement

- **Clic onglet famille** → change `activeFamily` ; la nav latérale affiche les sections de cette famille ; le contenu affiche `activeSectionByFamily[famille]` (mémorisé).
- **Clic section (nav latérale)** → change la section active de la famille courante.
- **Changement d'onglet** : pas de transition lourde — bascule instantanée (voir note technique ci-dessous).
- **Pastille « à compléter »** : petit point ambré (`#d97706`) sur les items de nav dont la section est incomplète. Source de vérité = critères de complétion backend.
- **Hover** : onglet inactif → couleur teal ; item de nav inactif → fond `teal-50`.
- **Responsive** : sous `lg`, la sidebar passe au-dessus (déjà géré par `flex-col lg:flex-row`). La nav latérale des sections peut passer en barre horizontale scrollable ou en `<select>` sur mobile.
- **(Optionnel, non demandé)** Avertissement « modifications non enregistrées » au changement d'onglet : pas implémenté dans le prototype. À discuter si souhaité.

### ⚠️ Note technique importante (bug rencontré dans le prototype)
Dans le prototype, mettre une `transition: all` (ou une transition sur `background-color`/`color`
basée sur des `var()`) sur les onglets/items provoquait un **gel de la peinture** lors du changement
de classe active (l'état actif ne suivait pas le clic). **En Vue + Tailwind tu n'auras
probablement pas ce souci**, mais si tu observes un onglet actif « collé », **retire la transition
sur la couleur/fond** des éléments qui togglent leur classe active (bascule instantanée). C'est le
choix fait dans le prototype final.

---

## Design tokens

Identiques au codebase (rien de nouveau) :

| Token | Valeur | Usage |
|---|---|---|
| Teal primaire | `#198496` (`--color-weact`, `text-teal-600`) | onglet/section actif, accents, barre de complétion |
| Teal foncé (hover bouton) | `#167686` | `:hover` bouton primaire |
| Teal 50 | `#e6f4f6` | fond item de nav actif, fond hover |
| Ambre | `#d97706` | pastille « à compléter », badge « à ajouter » |
| Encre | `#0f172a` (slate-900) | titres |
| Texte secondaire | `#64748b` (slate-500) | sous-titres, labels |
| Bordure carte | `#f1f5f9` (gray-100) | `border-gray-100` |
| Bordure input | `#d1d5db` (gray-300) | champs |
| Rayon carte | `1rem` (`rounded-2xl`) | cartes, panneau |
| Rayon contrôle | `10–12px` | boutons, onglets, items |
| Ombre carte | `shadow-sm` | toutes les cartes |
| Police | Inter (system-ui fallback) | toute la page |

Tailles : titre page `text-2xl/bold` ; titre section (h2) `text-lg/semibold` ; sous-titre `text-sm text-slate-500` ; libellé onglet `~14px/600` ; item nav `~13.5px/600`.

---

## Composants concernés (récap codebase)

**À réorganiser (existants, inchangés en interne) :**
`BasicInfoSection.vue`, `PersonalInfoForm.vue`, `PhysicalCharacteristicsForm.vue`,
`LanguesTagInput.vue`, `BioLocationForm.vue`, `PhotoAlbumGrid.vue`, `AlbumPhotoUpload.vue`,
`PresentationVideoUpload.vue`, `FaceVideoUpload.vue`, `CategoryNicheForm.vue`,
`ExperiencesList.vue`, `TarifsForm.vue`, `EmailChangeForm.vue`, `PasswordChangeForm.vue`,
`DataPrivacySection.vue`, `ProfilePhotoUpload.vue`, `AvailabilityToggle.vue`,
`RatingDisplay`, `ProfileCompletionIndicator.vue`.

**À créer (petits, présentationnels) :**
- `ProfileTabs.vue` *(optionnel)* — la barre d'onglets familles + nav latérale, si tu veux extraire la coquille hors de `ProfileEditPage.vue`. Sinon, tout peut vivre inline dans la page.

**À modifier :**
- `src/pages/face/ProfileEditPage.vue` — layout (onglets + nav latérale + rendu conditionnel) et `handleCompletionItemClick`. C'est l'essentiel du travail.

---

## Fichiers de référence (dans ce bundle)

- `Mon Profil — Tabs.html` — point d'entrée du prototype (ouvre-le dans un navigateur).
- `profile-tabs/core.jsx` — icônes + modèle de données + **structure des familles/sections** (la source de vérité pour l'ordre et le regroupement).
- `profile-tabs/sections.jsx` — contenu/champs de chaque section (référence visuelle des formulaires).
- `profile-tabs/leftrail.jsx` — sidebar (identité, **carte Complétion** + éléments manquants, Statut, Résumé).
- `profile-tabs/variantB.jsx` — **la coquille à onglets retenue** (onglets soulignés + nav latérale). C'est le composant à reproduire.
- `profile-tabs/fields.jsx` — primitives de formulaire du prototype (champs, chips, toggle, SaveBar).
- `profile-tabs/profile.css` — tous les styles (tokens, onglets, nav, champs, cartes).
- `design-canvas.jsx` — utilitaire de présentation (canvas pan/zoom) ; **non pertinent** pour l'implémentation.

> Pour voir la coquille retenue, ouvre le HTML : c'est la **Direction B**.

---

## Captures d'écran (dossier `screenshots/`)

Aperçus haute-définition de chaque famille/section, pour référence visuelle :

| Fichier | État montré |
|---|---|
| `01-profil-infos.png` | Profil → Infos perso (vue d'ensemble : sidebar + onglets + nav latérale) |
| `02-profil-bio.png` | Profil → Bio & Localisation |
| `03-portfolio-album.png` | Portfolio → Album photos |
| `04-portfolio-videos.png` | Portfolio → Vidéos (présentation / acting / UGC) |
| `05-carriere-categorie.png` | Carrière → Catégorie & Niche (chips sélectionnables) |
| `06-carriere-tarif.png` | Carrière → Tarif (champ + décomposition Journée / Demi-journée) |
| `07-compte-securite.png` | Compte → Email & mot de passe |
| `08-compte-donnees.png` | Compte → Mes données personnelles (export + zone de suppression) |

La capture `01` montre la mise en page complète : sidebar gauche (Photo, **Complétion** + éléments manquants, **Statut** = Disponibilité / Tarif en lecture seule / Note) et la colonne de droite (onglets familles soulignés + navigation latérale des sections + panneau + barre Enregistrer).
