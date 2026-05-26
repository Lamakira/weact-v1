# Handoff — Badge de tier WeAct (Élite / Pro)

## Overview
Remplacement du badge "Élite VIP" actuel par un système de badge **propriétaire WeAct** : un **burst scalloped** (forme inspirée des badges "verified" des grands réseaux sociaux comme Instagram, X, TikTok) contenant un **monogramme W blanc** au centre. Le badge se décline en deux tiers :

- **Élite** : fond noir `#0F1419` (membres abonnés Élite à 40 000 FCFA/an)
- **Pro** : fond teal `#198496` (membres abonnés Pro à 25 000 FCFA/an)

Les paliers Découverte (gratuit) et Starter (12 000 FCFA/an) n'ont **pas de badge** — leur absence est elle-même un signal.

## About the design files
Les fichiers dans `references/` sont des **prototypes HTML/React** qui montrent le rendu final et permettent de tester le badge à toutes les tailles et dans tous les contextes d'usage. Ils ne sont pas à intégrer tels quels.

Le fichier `WBadge.vue` à la racine est le **composant Vue 3 + TypeScript prêt à intégrer** dans le codebase WeAct.

## Fidelity
**High-fidelity (hifi)** — pixel-perfect. Le composant Vue est prêt à déposer dans `frontend/src/components/ui/` et à importer là où il faut.

## Files in this bundle

| Fichier | Rôle |
|---|---|
| `WBadge.vue` | **Composant final** Vue 3 + TS, à déposer dans `frontend/src/components/ui/WBadge.vue` |
| `references/Badge V13 Systeme.html` | Prototype : échelle 14→80px + contextes d'usage |
| `references/Elite Badge Variations.html` | Prototype : 16 variations explorées (référence) |
| `references/badge-v13-system.jsx` | Code source React de la version retenue |

## Integration

```bash
# Depuis la racine du codebase weact-v1 :
cp design_handoff_elite_badge/WBadge.vue frontend/src/components/ui/WBadge.vue
```

Puis remplacer l'usage actuel du badge dans le composant de profil talent (probablement `frontend/src/views/TalentProfileView.vue` ou similaire) :

```vue
<!-- AVANT (à remplacer) -->
<div class="bg-dark text-white rounded-full px-3 py-1 flex items-center gap-1">
  <Crown class="w-3 h-3" />
  Élite VIP
</div>

<!-- APRÈS -->
<script setup lang="ts">
import WBadge from '@/components/ui/WBadge.vue'
// ... reste du script
</script>

<template>
  <div class="flex items-center gap-3">
    <h1 class="text-3xl font-bold text-gray-900">{{ talent.name }}</h1>
    <WBadge
      v-if="talent.subscription_tier === 'elite'"
      tier="elite"
      :size="22"
      title="Membre Élite"
    />
    <WBadge
      v-else-if="talent.subscription_tier === 'pro'"
      tier="pro"
      :size="22"
      title="Membre Pro"
    />
  </div>
</template>
```

Aucune nouvelle dépendance n'est nécessaire (le composant utilise uniquement SVG natif + Vue).

---

## API du composant

```ts
interface Props {
  tier?: 'elite' | 'pro'  // défaut: 'elite'
  size?: number           // taille en pixels, défaut: 20
  title?: string          // texte accessibilité, défaut auto selon tier
}
```

### Exemples

```vue
<!-- Badge Élite par défaut (20px) -->
<WBadge tier="elite" />

<!-- Badge Pro en grande taille -->
<WBadge tier="pro" :size="40" />

<!-- Badge personnalisé avec tooltip custom -->
<WBadge tier="elite" :size="14" title="Élite — Top profil WeAct" />
```

---

## Tailles recommandées par contexte

| Contexte | Taille |
|---|---|
| Avatar inline (commentaires, messages) | **14px** |
| Inline avec nom dans une liste compacte | **14px** |
| Card de talent (listing) | **20–22px** |
| En-tête de profil (à côté du nom H1) | **24–28px** |
| Mise en avant marketing / hero | **40–56px** |
| Page dédiée / célébration de membre | **80px+** |

**Note technique** : sous 18px, le composant passe automatiquement à un burst de **6 pointes** (au lieu de 8) pour éviter le bruit visuel.

---

## Design tokens

| Token | Valeur | Usage |
|---|---|---|
| Élite fill | `#0F1419` | Fond du burst pour Élite |
| Pro fill | `#198496` | Fond du burst pour Pro |
| Lettre W | `#FFFFFF` | Monogramme |
| Police lettre W | `Inter` / weight 800 / letter-spacing -0.04em | Resserrement optique du W |
| Burst 8 pointes (≥18px) | outerR=11.5 / innerR=10 | Bumps doux et lisibles |
| Burst 6 pointes (<18px) | outerR=11.5 / innerR=10.2 | Simplifié pour petites tailles |
| viewBox SVG | `0 0 24 24` | Constant, le navigateur scale via width/height |

---

## Contextes d'usage (voir `references/Badge V13 Systeme.html`)

1. **En-tête de profil talent** — badge à 26px à côté du H1 "Amakira"
2. **Carte de talent dans listings** — overlay sur la photo (top-right) + inline avec le nom
3. **Liste compacte / résultats de recherche** — badge 14px inline après le nom
4. **Avatar dans messages / commentaires** — badge 14px en overlay sur le coin bas-droit de l'avatar, avec un halo blanc de 1.5px pour contraste

---

## Règles d'usage

✅ **À faire :**
- Toujours afficher le badge à côté du nom du talent (en-tête, listings, messages)
- Respecter la hiérarchie de couleurs : noir = Élite, teal = Pro
- Utiliser `title` pour un tooltip clair ("Membre Élite" / "Membre Pro")
- Tailler le badge en fonction du contexte (voir tableau)

❌ **À éviter :**
- N'ajoute **pas** de badge pour les paliers Découverte ou Starter
- N'ajoute **pas** de bordure, ombre ou effet supplémentaire autour du badge
- Ne change pas les couleurs : un Élite reste noir, un Pro reste teal
- Ne mets pas le badge ailleurs qu'à proximité du nom (ce n'est pas un sticker décoratif)

---

## Accessibility

- Le composant rend un `<svg>` avec `role="img"` et `aria-label` automatique selon le tier
- Le `<title>` SVG sert également de tooltip natif au survol souris
- Le badge ne contient aucune information critique non répliquée ailleurs — il **renforce** une distinction visuelle qui doit aussi être communiquée par d'autres moyens (filtres, libellés textuels) pour les utilisateurs qui ne le voient pas

---

## Logique de tri (cohérence cross-feature)

Pour rappel, dans le tableau comparatif `/pricing`, la hiérarchie de mise en avant est :

1. **Élite** — Prioritaire Absolue (tout en haut)
2. **Pro** — Premium
3. **Starter** — Boostée
4. **Découverte** — Standard

Le badge V13 (noir = Élite, teal = Pro) doit **renforcer visuellement cette hiérarchie** dans tous les listings de talents.

---

## Source
Le badge V13 a été choisi parmi 16 variations explorées dans `references/Elite Badge Variations.html`. Les autres directions (V1 à V16) restent disponibles si l'équipe veut comparer.
