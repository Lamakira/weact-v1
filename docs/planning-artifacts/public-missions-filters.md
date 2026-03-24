# Plan — Filtres sur la page publique des missions (`/missions`)

## Contexte

La page `/missions` (accessible sans authentification) affiche la liste paginée des missions publiées mais ne propose aucun filtre. La version connectée "Face" (`/face/missions`) dispose déjà de filtres complets via `FilterMissionsRequest`, `useFaceMissions`, et `MissionFiltersPanel`. Ce plan réutilise au maximum l'existant.

**Filtres à implémenter (par priorité) :**
1. Type de mission (`type_mission`)
2. Lieu (`lieu`)
3. Budget (`budget_min` / `budget_max`)

---

## Ce qui existe déjà (à réutiliser)

| Élément | Fichier | Statut |
|---|---|---|
| Validation filtres | `backend/app/Http/Requests/Mission/FilterMissionsRequest.php` | Existe — valide `lieu`, `budget_min`, `budget_max`, `type_mission` |
| Enum types de mission | `backend/app/Enums/MissionType.php` | Existe — `publicite`, `film`, `court_metrage`, `clip_musical`, `autre` |
| Composable URL sync | `frontend/src/features/mission/composables/useMissionFilters.ts` | Existe — `initFromUrl()` / `syncToUrl()` |
| Type `MissionFilters` | `frontend/src/features/mission/types/mission.ts` | Existe — `lieu`, `budget_min`, `budget_max`, `type_mission` |

---

## Phase 1 — Backend

### 1.1 Mettre à jour `ListPublicMissionsRequest`

**Fichier :** `backend/app/Http/Requests/Api/V1/Public/ListPublicMissionsRequest.php`

Ajouter les règles de validation des 3 filtres (copier depuis `FilterMissionsRequest`) :

```php
public function rules(): array
{
    return [
        'page'        => ['sometimes', 'integer', 'min:1'],
        'per_page'    => ['sometimes', 'integer', 'min:1', 'max:30'],
        'type_mission' => ['sometimes', 'nullable', Rule::enum(MissionType::class)],
        'lieu'         => ['sometimes', 'nullable', 'string', 'max:100'],
        'budget_min'   => ['sometimes', 'nullable', 'integer', 'min:0'],
        'budget_max'   => ['sometimes', 'nullable', 'integer', 'min:0', 'gte:budget_min'],
    ];
}
```

Ajouter helper `getFilters(): array` (même pattern que `FilterMissionsRequest`) :
```php
public function getFilters(): array
{
    return array_filter([
        'type_mission' => $this->input('type_mission'),
        'lieu'         => $this->input('lieu'),
        'budget_min'   => $this->input('budget_min'),
        'budget_max'   => $this->input('budget_max'),
    ], fn($v) => $v !== null && $v !== '');
}
```

---

### 1.2 Mettre à jour `Public\MissionController@index`

**Fichier :** `backend/app/Http/Controllers/Api/V1/Public/MissionController.php`

Appliquer les filtres à la query Eloquent (même logique que `Face\MissionController@index`) :

```php
public function index(ListPublicMissionsRequest $request): JsonResponse
{
    $filters = $request->getFilters();

    $query = Mission::published()
        ->with(['producer.user'])
        ->withCount('ratings')
        ->withAvg('ratings', 'rating');

    // Filtres
    if (!empty($filters['type_mission'])) {
        $query->where('type_mission', $filters['type_mission']);
    }
    if (!empty($filters['lieu'])) {
        $query->where('lieu', 'LIKE', '%' . $filters['lieu'] . '%');
    }
    if (isset($filters['budget_min'])) {
        $query->where('budget', '>=', $filters['budget_min']);
    }
    if (isset($filters['budget_max'])) {
        $query->where('budget', '<=', $filters['budget_max']);
    }

    $missions = $query->latest()->paginate($request->getPerPage());

    return response()->json([
        'data'    => PublicMissionResource::collection($missions),
        'meta'    => [...],  // pagination meta identique à l'existant
        'message' => 'Missions récupérées avec succès',
    ]);
}
```

**Note :** Réutiliser exactement le pattern de pagination et de resource déjà en place dans ce contrôleur.

---

## Phase 2 — Frontend

### 2.1 Mettre à jour `publicMissionsApi.ts`

**Fichier :** `frontend/src/features/public/services/publicMissionsApi.ts`

Ajouter le type `PublicMissionFilters` et passer les filtres à l'appel API :

```ts
export interface PublicMissionFilters {
  type_mission?: string
  lieu?: string
  budget_min?: number
  budget_max?: number
}

export async function fetchPublicMissions(
  page = 1,
  perPage = 15,
  filters: PublicMissionFilters = {},
): Promise<PublicMissionsResponse> {
  const params: Record<string, unknown> = { page, per_page: perPage }

  if (filters.type_mission) params.type_mission = filters.type_mission
  if (filters.lieu)         params.lieu = filters.lieu
  if (filters.budget_min != null) params.budget_min = filters.budget_min
  if (filters.budget_max != null) params.budget_max = filters.budget_max

  const response = await publicApiClient.get('/missions', { params })
  return response.data
}
```

---

### 2.2 Mettre à jour le composable `usePaginatedMissions`

**Fichier :** `frontend/src/features/public/composables/usePaginatedMissions.ts`

Ajouter l'état des filtres, la synchronisation URL, et réinitialiser à la page 1 lors d'un changement de filtre :

```ts
// Ajouter
import { useRoute, useRouter } from 'vue-router'
import type { PublicMissionFilters } from '../services/publicMissionsApi'

// Nouvel état
const filters = ref<PublicMissionFilters>({})
const hasActiveFilters = computed(() =>
  Object.values(filters.value).some((v) => v !== undefined && v !== '' && v !== null)
)

// Depuis l'URL au montage
onMounted(() => {
  const query = route.query
  filters.value = {
    type_mission: (query.type_mission as string) || undefined,
    lieu:         (query.lieu as string) || undefined,
    budget_min:   query.budget_min ? Number(query.budget_min) : undefined,
    budget_max:   query.budget_max ? Number(query.budget_max) : undefined,
  }
  loadPage(Number(query.page) || 1)
})

// Synchroniser URL à chaque changement
function updateFilters(newFilters: PublicMissionFilters): void {
  filters.value = newFilters
  currentPage.value = 1
  router.replace({ query: buildQuery() })  // même pattern que useMissionFilters
  fetchMissions()
}

// Exposer
return { ..., filters, hasActiveFilters, updateFilters }
```

---

### 2.3 Créer `PublicMissionFiltersBar.vue`

**Fichier :** `frontend/src/features/public/components/PublicMissionFiltersBar.vue`

Barre horizontale avec 3 contrôles. S'inspirer de `FilterBar.vue` (faces) pour le style, et de `MissionFiltersPanel.vue` pour les champs.

**Structure UI :**
```
[ Type de mission ▼ ]  [ Lieu... 🔍 ]  [ Budget min — max XOF ]  [ Réinitialiser ]
```

**Props :**
```ts
interface Props {
  currentFilters: PublicMissionFilters
  missionTypes: Array<{ value: string; label: string }>
}
```

**Émit :** `filter-change(filters: PublicMissionFilters)`

**Détail des champs :**

| Champ | Type | Comportement |
|---|---|---|
| `type_mission` | `<select>` ou dropdown | Options depuis `MissionType` enum — à injecter depuis le parent via props `missionTypes` |
| `lieu` | `<input type="text">` | Debounce 400ms avant d'émettre |
| `budget_min` | `<input type="number">` | Valeur minimale 0, placeholder "Min XOF" |
| `budget_max` | `<input type="number">` | Valeur minimale 0, placeholder "Max XOF" |
| Bouton reset | `<button>` | Visible uniquement si un filtre est actif — émet `filter-change({})` |

**Types de mission à passer depuis le parent :**
```ts
const missionTypes = [
  { value: 'publicite',     label: 'Publicité' },
  { value: 'film',          label: 'Film' },
  { value: 'court_metrage', label: 'Court-métrage' },
  { value: 'clip_musical',  label: 'Clip musical' },
  { value: 'autre',         label: 'Autre' },
]
```

---

### 2.4 Mettre à jour `PublicMissionsView.vue`

**Fichier :** `frontend/src/views/PublicMissionsView.vue`

1. Importer `PublicMissionFiltersBar` et l'afficher entre le header et la liste
2. Passer `missionTypes` en prop statique (pas d'appel API nécessaire)
3. Passer `filters` du composable en `currentFilters`
4. Écouter `@filter-change` → appeler `updateFilters()`
5. Afficher le compte de résultats en tenant compte des filtres actifs :
   - Sans filtres : `X missions disponibles`
   - Avec filtres : `X missions trouvées` + bouton reset inline

**Différence d'empty state selon filtre actif :**
- Sans filtre actif : `"Il n'y a pas encore de missions publiées sur la plateforme."`
- Avec filtre actif : `"Aucune mission ne correspond à vos critères."` + bouton `"Réinitialiser les filtres"`

---

## Fichiers à modifier

| Fichier | Nature |
|---|---|
| `backend/app/Http/Requests/Api/V1/Public/ListPublicMissionsRequest.php` | Ajouter validation filtres |
| `backend/app/Http/Controllers/Api/V1/Public/MissionController.php` | Appliquer filtres à la query |
| `frontend/src/features/public/services/publicMissionsApi.ts` | Ajouter `PublicMissionFilters`, passer au call |
| `frontend/src/features/public/composables/usePaginatedMissions.ts` | Ajouter état filtres + URL sync |
| `frontend/src/views/PublicMissionsView.vue` | Intégrer la barre de filtres |

## Fichiers à créer

| Fichier | Nature |
|---|---|
| `frontend/src/features/public/components/PublicMissionFiltersBar.vue` | Nouveau composant filtres |

---

## Tests à ajouter / mettre à jour

- `backend/tests/Feature/Mission/` — ajouter cas de test dans un nouveau fichier `PublicBrowseMissionsTest.php` (calqué sur `FaceBrowseMissionsTest.php`) :
  - Filtre `type_mission` retourne uniquement les missions du bon type
  - Filtre `lieu` fait une recherche partielle insensible à la casse
  - Filtres `budget_min` / `budget_max` appliquent la fourchette correctement
  - Combinaison de plusieurs filtres simultanés
  - Paramètres invalides retournent 422
- `frontend/src/views/__tests__/PublicMissionsView.spec.ts` — mettre à jour les tests existants pour couvrir le rendu conditionnel de la barre de filtres et les empty states filtrés

---

## Points d'attention pour l'agent implémenteur

1. **`FilterMissionsRequest` n'est PAS réutilisé directement** — `ListPublicMissionsRequest` est indépendant et doit rester sans auth. Ne pas étendre `FilterMissionsRequest`.
2. **Pas de filtre `date_tournage`** — non demandé dans cette version, à ne pas ajouter.
3. **Les types de mission côté frontend sont statiques** — pas besoin d'un endpoint `/filter-options` pour les missions (contrairement aux faces). Les valeurs sont fixes dans l'enum `MissionType`.
4. **URL sync** — réutiliser le pattern de `useMissionFilters.ts` déjà en place, ne pas réinventer.
5. **Debounce sur `lieu`** — 400ms pour éviter les appels à chaque frappe, même pattern que `FilterBar.vue` des faces.
6. **Reset de page** — tout changement de filtre doit remettre `currentPage` à 1.
