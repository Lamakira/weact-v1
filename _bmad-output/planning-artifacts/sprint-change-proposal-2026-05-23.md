# Sprint Change Proposal — 2026-05-23

**Workflow:** correct-course
**Triggered by:** Amakira (post-FP-2.7 review feedback)
**Scope classification:** **Moderate** — backlog reorganization + story rework, no PRD pivot, no architecture overhaul
**Epic affected:** FEATURE-FP-2 (Face Premium Subscription v2)

---

## Section 1 — Issue Summary

Two product/UX écarts identifiés après l'implémentation et la code review FP-2.7 (story marquée `done` le 2026-05-23 après 11 patches appliqués) :

### Issue A — Aucun moyen de reprendre un paiement Fedapay interrompu

Quand une Face initie un paiement et que la session Fedapay est interrompue pour quelconque raison (onglet fermé sans payer, refresh, navigation, drop réseau), la row backend `pending_payment` reste mais le frontend n'a aucun moyen de relancer le checkout. La bannière "Votre paiement est en cours de confirmation" propose seulement "Vérifier maintenant" (qui poll le status) — **pas de bouton "Continuer le paiement"** qui réouvrirait l'URL Fedapay. La Face est bloquée jusqu'au timeout backend de la row pending ou jusqu'à une annulation admin.

**Lien avec le defer D3 du code review FP-2.7** : ce defer cataloguait spécifiquement le cas popup-blocked ; le feedback élargit à *tous* les cas d'interruption (pas seulement le blocage navigateur). La solution est commune.

### Issue B — Les 4 tier cards complètes dans la page de profil sont verbeuses et mal placées

`SubscriptionPanel.vue` rend les 4 `TierCard` + 1 `TierChangeModal` directement dans `/face/profile`. Cette surface :
- Prend ~600px de hauteur dans la page de profil (verbose).
- Affiche les 4 offres complètes avec leur matrice de capabilities à chaque visite de profil (information overload pour la majorité des sessions où la Face ne change pas de plan).
- **Duplique** la surface qui doit vivre sur la future page publique `/pricing` (FP-2.13).

**Intention produit** (verbatim du feedback) : « dans la page de profil, on montre les détails de l'abonnement actuel de la face de manière minimaliste (Gemini MCP) avec un bouton "Changer de plan" qui va rediriger vers la page publique où la face va sélectionner son nouveau plan voulu et procéder au paiement ».

**Conflit avec l'épic actuel** : FP-2.13 est défini comme « static marketing copy — does NOT consume the FP-2.3 status API ». Or l'intention produit demande que `/pricing` héberge le payment flow authentifié. Le scope FP-2.13 doit s'élargir, OU une story sœur FP-2.13.1 doit naître (même pattern que FP-2.2 → FP-2.2.1 et FP-2.7 → FP-2.7.1).

### Evidence — état actuel du code

- `frontend/src/features/face/components/SubscriptionPanel.vue` (~310 lignes) : owns 4 `TierCard` + `TierChangeModal` + état modal + composables paiement + statut + auth.
- `frontend/src/views/PricingView.vue` : legacy (producer monthly), pas encore replaced par le hi-fi handoff.
- Route `/pricing` existe déjà (`router/index.ts:90`) mais sert le legacy.
- `useSubscriptionPayment.initiatePayment` consigne `checkout_url` uniquement en mémoire de la composable — perdu dès qu'un poll s'arrête, qu'un composant se démonte ou qu'un refresh se produit.

---

## Section 2 — Impact Analysis

### Epic impact

| Epic | Avant | Après proposition |
|---|---|---|
| FEATURE-FP-2 (Face Premium Subscription v2) | en cours, FP-2.7 done | **en cours** ; FP-2.7 rétrogradée + FP-2.13.1 ajoutée |

### Story impact

| Story | Statut avant | Statut après | Action |
|---|---|---|---|
| `feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow` | done (2026-05-23) | **ready-for-dev** (scope redéfini) | revert + rewrite spec (minimaliste + resume-pending + redirect) |
| `feature-fp-2-7-2-face-profile-minimalist-and-resume-pending` | n/a | n/a (NON créée — fusionnée dans FP-2.7 rework) | n/a |
| `feature-fp-2-13-public-pricing-page-refonte` | backlog | **backlog** (scope inchangé : page statique hi-fi) | aucune action sur statut ; reste prérequis visuel |
| `feature-fp-2-13-1-pricing-page-authenticated-payment-flow` | n/a | **backlog** (nouvelle story) | créée |

### Artifact conflicts

| Artifact | Conflit | Action |
|---|---|---|
| `_bmad-output/planning-artifacts/epics-face-premium-subscription-v2.md` | FP-2.7 description embed "4 tier cards + upgrade/downgrade flow"; FP-2.13 description "static marketing — does NOT consume the FP-2.3 status API" | Mise à jour : FP-2.7 redéfini minimaliste + redirect ; FP-2.13 inchangée ; nouvelle section FP-2.13.1 ajoutée |
| `_bmad-output/implementation-artifacts/feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow.md` | Story spec décrit l'implémentation faite (24 fichiers, 4 tier cards rendered in profile) | Spec rewritten pour le nouveau scope minimaliste (architecture, ACs, file inventory, skeletons) |
| `_bmad-output/implementation-artifacts/feature-fp-2-13-1-...md` | n/a | Nouvelle story à créer ultérieurement via `bmad-create-story` |
| `_bmad-output/implementation-artifacts/sprint-status.yaml` | FP-2.7 marquée done | Revert à `ready-for-dev` ; FP-2.13.1 ajoutée comme backlog |
| `_bmad-output/implementation-artifacts/deferred-work.md` | D3 (popup-blocked row orpheline) demande une story dédiée | D3 absorbé par le scope FP-2.7 rework (resume-pending mécanique) ; entrée mise à jour ou retirée |

### Technical impact

- **Code à préserver** (utilisable par FP-2.13.1 + FP-2.7 rework) : `types.ts` (block FP-2 subscription), `services/faceApi.ts` (3 méthodes subscription), `composables/useSubscriptionStatus.ts`, `composables/useSubscriptionPayment.ts` (avec patches P1-P11 appliqués), `tierPresentation.ts`, `components/TierCard.vue`, `components/TierChangeModal.vue`, et tous les fichiers photo album (`PhotoAlbumGrid.vue`, `AlbumPhotoUpload.vue`, `usePhotoAlbum.ts`) qui sont orthogonaux à la redéfinition.
- **Code à rewrite** : `components/SubscriptionPanel.vue` (de 4-cards orchestrator → minimalist current-state card avec CTA + bouton resume-pending). Son spec test sera à updater (≥8 nouveaux tests pour le minimalist + le resume-pending + le redirect).
- **Code à créer** (FP-2.13.1) : `PricingView.vue` rewrite (post-FP-2.13) avec branchement auth-aware, consommation `useSubscriptionStatus` + `useSubscriptionPayment`, mount des `TierCard` + `TierChangeModal` existants, gestion du `?plan=` query param.
- **Patches P1-P11 du code review FP-2.7** : tous restent valides — touchent les composables payment/status (P3/P4/P5/P11), le panneau (P1/P2/P7/P8), le modal (P9), les tests (P10). P6 (Free guard dans `onSelectTier`) et P9 (Escape on modal) seront migrés vers FP-2.13.1 puisque la logique modale y déménage.

---

## Section 3 — Recommended Approach

**Selected path: Hybrid (Direct Adjustment + new Story)** — combine option 1 (modify existing story scope) + option 4 (add new sibling story).

### Path summary

1. **Revert + rewrite FP-2.7** scope to "minimalist profile-page subscription card + resume-pending payment". Conserve le code orthogonal (composables, types, tierPresentation, photo album). Rewrite seulement `SubscriptionPanel.vue` + ses tests.
2. **Create FP-2.13.1** comme nouvelle story sœur de FP-2.13, scope = "authenticated payment flow on /pricing". Consomme TierCard / TierChangeModal / useSubscriptionPayment depuis FP-2.7 (composants partagés, redomiciliés conceptuellement).
3. **Keep FP-2.13** static (scope inchangé) — c'est le prérequis visuel sur lequel FP-2.13.1 ajoute la couche auth.

### Sequencing & dependencies

```
FP-2.13 (static /pricing visual)
   ↓
FP-2.13.1 (auth payment flow on /pricing)
   ↓
FP-2.7 rework (minimalist profile + redirect to /pricing + resume-pending)
```

**Bridge interim** (si FP-2.13.x ne peuvent pas être faits avant FP-2.7 rework) : le bouton "Changer de plan" de la nouvelle SubscriptionPanel peut router vers `/pricing` quoi qu'il arrive — la première période servira la legacy `PricingView.vue` (utilisateur voit *quelque chose* même si pas encore la nouvelle expérience). Pas idéal mais non bloquant.

### Effort estimate

| Story | Estimate | Risk |
|---|---|---|
| FP-2.7 rework | ~1 jour (rewrite SubscriptionPanel.vue + Gemini MCP design + ~10 tests + resume-pending via sessionStorage) | Low |
| FP-2.13.1 | ~1-2 jours (auth-aware composables, query param, ~15 tests, intégration TierCard / TierChangeModal) | Medium (race conditions auth/unauth) |
| FP-2.13 (déjà backlog) | ~1 jour (replace PricingView.vue par hi-fi handoff + corrections content + ~12 tests) | Low |

**Total impact:** ~3-4 jours de travail réparti sur 2 nouvelles stories + 1 rework.

### Rationale

- **Pas de rollback de code** : tous les fichiers FP-2.7 existants restent utilisables. On déplace de la consommation, pas du code.
- **Pattern BMAD cohérent** : split FP-2.13 → FP-2.13 + FP-2.13.1 mirroite FP-2.2 → FP-2.2 + FP-2.2.1 (2026-05-21) et FP-2.7 → FP-2.7 + FP-2.7.1 (2026-05-22).
- **Respecte l'intention produit** : page publique = sélection + paiement (authentifié), profil = état + redirect.
- **MVP non impacté** : le PRD FP-2 reste atteint via cette restructuration ; juste un partitionnement différent.
- **Patches P1-P11 préservés** : touchent les composables qui survivent + les blocs `pending banner` / `payment-waiting banner` qui restent dans la nouvelle SubscriptionPanel minimaliste. Aucune perte d'effort.

---

## Section 4 — Detailed Change Proposals

### 4.1 — Story FP-2.7 (rework)

**File:** `_bmad-output/implementation-artifacts/feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow.md`

**Action:** Status `done` → `ready-for-dev` ; spec rewrite via `bmad-create-story` (ou rewrite manuel).

**New title (proposed):** `FEATURE-FP-2.7: Face Profile: Minimalist Subscription Card + Resume-Pending Payment + Redirect to /pricing`

**New scope (verbatim ACs to embed in the rewritten story):**

1. Frontend-only story (zero backend changes).
2. `SubscriptionPanel.vue` is rewritten as a minimalist current-subscription card (designed via Gemini MCP — design tokens to match the FP-2.13 hi-fi handoff for visual consistency).
3. The minimalist card displays: current tier name + tier badge (e.g., Crown for Élite), expiry date (formatted `toLocaleDateString('fr-FR')` for active tiers), and an inline status indicator (active / expired / cancelled / failed / pending / free).
4. A primary CTA "Changer de plan" (or "Choisir un abonnement" for Free Faces) redirects to `/pricing` via `router.push('/pricing')` (or `router.push({ name: 'pricing', query: { plan: targetTier } })` if a deep-link is preselected — to be confirmed during dev).
5. For Faces with a `pending_payment` row (statusValue OR cta-all-false detection per the P2 logic kept from the previous FP-2.7): the panel shows a pending banner with TWO buttons — "Vérifier maintenant" (the existing manual verify) AND **"Continuer le paiement"** which reopens the stored Fedapay `checkout_url` in a new tab. The URL is stashed in `sessionStorage` keyed by `subscription_id` at `initiatePayment` time. Cleanup on confirm/cancel/expiry.
6. The "Continuer le paiement" button is hidden if no stored URL exists for the active pending row (graceful degradation across sessions or browser-close).
7. The 4 `TierCard` components are **NOT** rendered in `/face/profile`. They are migrated to FP-2.13.1 (`/pricing`).
8. `TierChangeModal` is **NOT** rendered in `/face/profile`. It moves to FP-2.13.1.
9. `useSubscriptionStatus`, `useSubscriptionPayment`, `tierPresentation`, and the photo album work (`PhotoAlbumGrid`, `AlbumPhotoUpload`, `usePhotoAlbum`) are unchanged — they remain in `frontend/src/features/face/...` and are consumed by both the minimalist profile panel AND the `/pricing` page (FP-2.13.1).
10. All previous code-review patches P1-P11 are preserved (touched files: `useSubscriptionPayment.ts`, the minimalist `SubscriptionPanel.vue`, `TierChangeModal.vue`, `useSubscriptionPayment.spec.ts`).
11. ≥ 10 new/updated frontend tests covering the minimalist card render, the CTA route navigation, the "Continuer le paiement" button (with sessionStorage stash/restore), and the resume-pending flow.

**Resume-pending mécanique (sessionStorage detail):**

- At `initiatePayment` success (after `window.open` returns a valid window), store `sessionStorage.setItem('weact:pending-checkout:{subscription_id}', JSON.stringify({ url: checkout_url, expiresAt: now + 24h }))`.
- At panel mount, when status shows a pending row: read the stored URL keyed by `current.id` (the pending subscription_id from FP-2.3 if exposed — else fall back to a generic key `weact:pending-checkout:latest` populated at initiate time).
- "Continuer le paiement" button: `window.open(storedUrl, '_blank', 'noopener,noreferrer')` ; on success → start the polling loop (same as initiate) ; on null → show the popup-blocked error.
- On `paymentState='confirmed'` watch: clear the stored entry.
- On manual `reset()` or unmount: keep the entry (preserve resume across mount cycles within a session).

**Out of scope (defensive non-scope to document in the story):**

- No backend change (no new endpoint, no FP-2.5 modification).
- No persistence beyond sessionStorage (deferred to a backend hardening story — see deferred-work.md D3).
- No video UI changes (still owned by FP-2.7.1).
- No tier cards or modal in the profile page (owned by FP-2.13.1).

---

### 4.2 — New Story FP-2.13.1 (to create)

**File:** `_bmad-output/implementation-artifacts/feature-fp-2-13-1-pricing-page-authenticated-payment-flow.md` (to be drafted via `bmad-create-story` post-approval)

**Title (proposed):** `FEATURE-FP-2.13.1: /pricing Page Authenticated Payment Flow — Tier Selection + Fedapay Checkout for Logged-in Faces`

**High-level scope:**

1. Frontend-only story (zero backend changes — consumes FP-2.3 status + FP-2.5 payment + the static `/pricing` from FP-2.13).
2. Extends the static `PricingView.vue` (delivered by FP-2.13) with auth-aware behavior:
   - For unauthenticated visitors: existing FP-2.13 register CTAs (`/register/face?plan=…`).
   - For authenticated Faces: replaces the register CTAs with tier selection CTAs that open the existing `TierChangeModal`.
3. Consumes `useSubscriptionStatus` to highlight the current tier visually + compute relations (current / upgrade / downgrade / unavailable) — same logic that was in the old FP-2.7 `SubscriptionPanel`.
4. Drives the payment flow via `useSubscriptionPayment.initiatePayment(plan)` — opens Fedapay, polls, redirects back to `/face/profile` on confirm (or stays on `/pricing` and shows a confirmation banner).
5. Handles the `?plan=starter|pro|elite` query param to preselect a tier card (deep-link UX).
6. Reuses (does NOT duplicate) `TierCard.vue`, `TierChangeModal.vue`, `tierPresentation.ts` — they remain in `frontend/src/features/face/components/` and are imported here.
7. ≥ 15 new frontend tests covering: unauth → register CTAs, auth → tier CTAs, auth + active Pro → Pro card highlighted, auth + click Élite → modal upgrade mode, auth + confirm → initiatePayment dispatch, ?plan=pro → Pro card preselected, etc.

**Depends on:** FP-2.13 (page visuelle ready) + FP-2.7 rework (composants TierCard / TierChangeModal stables — déjà cas).

---

### 4.3 — Epic file update

**File:** `_bmad-output/planning-artifacts/epics-face-premium-subscription-v2.md`

**Change 1** — FP-2.7 description rewrite (line ~270):

**OLD:**
```
#### FEATURE-FP-2.7: Face UI: 4 tier cards + upgrade/downgrade flow + photo locked states

**Description:** Refactor FP-1.7 SubscriptionCard. Display 4 tier cards with prices + benefits comparison. Active tier highlighted. Upgrade CTA → loss-of-days confirmation modal → payment flow. Downgrade CTA same. ...
```

**NEW:**
```
#### FEATURE-FP-2.7: Face Profile UI — Minimalist Subscription Card + Resume-Pending + Redirect to /pricing

**Description:** Frontend-only refactor of the Face profile page (`/face/profile`) subscription surface. Replace the FP-1.7 SubscriptionCard with a minimalist current-subscription card (Gemini MCP for visual design, reusing FP-2.13 hi-fi tokens for consistency) showing the active tier + expiry + status indicator. Adds a primary CTA "Changer de plan" that redirects to `/pricing` (FP-2.13 / FP-2.13.1) for tier comparison + payment flow. Adds a "Continuer le paiement" button to the pending-payment banner that reopens the Fedapay checkout URL stashed in sessionStorage at initiate time — closes the UX gap where an interrupted payment leaves the Face stuck. Composables, types, tierPresentation, TierCard, TierChangeModal, and the photo album work are preserved (composables consumed by both this profile panel and FP-2.13.1's /pricing page).

**Split note (2026-05-23 correct-course):** the 4 tier cards + upgrade/downgrade flow + payment Fedapay flow are split out to FEATURE-FP-2.13.1 — the public /pricing page is the natural authoritative home for plan selection + payment, not the profile editor. FP-2.7 keeps only the in-profile minimalist surface + the redirect CTA + the resume-pending mechanic.

**Acceptance Criteria (draft):**
- Frontend-only.
- Minimalist current-subscription card via Gemini MCP, design tokens consistent with FP-2.13 hi-fi.
- "Changer de plan" CTA → `router.push('/pricing')`.
- "Continuer le paiement" button on pending banner reads sessionStorage and reopens Fedapay tab.
- Photo album tier-aware preserved (locked states, max_album_photos from capabilities).
- ≥ 10 new/updated frontend tests.
```

**Change 2** — FP-2.13 description (line ~370) — append a paragraph after the existing description:

**NEW (append):**
```
**Split note (2026-05-23 correct-course):** the authenticated payment flow on `/pricing` (tier selection for logged-in Faces, payment initiation, modal interaction) is split out to FEATURE-FP-2.13.1. FP-2.13 stays as the static visual base — pre-auth marketing copy with register CTAs.
```

**Change 3** — insert new section after FP-2.13 (post line ~393):

**NEW:**
```
---

#### FEATURE-FP-2.13.1: /pricing Page Authenticated Payment Flow

**Description:** Frontend-only story split out of FEATURE-FP-2.13 (2026-05-23 correct-course). Extends the static `/pricing` page (delivered by FP-2.13) with auth-aware behavior: for unauthenticated visitors the page renders register CTAs (FP-2.13 default), for authenticated Faces the page renders tier-selection CTAs that open `TierChangeModal` and drive `useSubscriptionPayment.initiatePayment(plan)`. Reuses TierCard / TierChangeModal / useSubscriptionStatus / useSubscriptionPayment / tierPresentation (all delivered by FP-2.7). Handles the `?plan=` query param for deep-link preselection.

**Context — why this is a separate story:** the original FP-2.7 placed the tier cards + change modal + payment flow in `/face/profile`. Sprint change 2026-05-23 moved this surface to the public `/pricing` page so the profile page can be minimalist + uncluttered. FP-2.13 stays static (the visual base); FP-2.13.1 is the auth-aware layer.

**Acceptance Criteria (draft):**
- Frontend-only.
- Unauth: register CTAs (FP-2.13 default).
- Auth Face: replaces CTAs with tier-selection openers; consumes FP-2.3 status; current tier highlighted via `ring-2 ring-[#198496]`; click → TierChangeModal (upgrade / downgrade / renew / activate per the relation rules).
- Auth Face + click Confirm → `useSubscriptionPayment.initiatePayment(plan)` opens Fedapay + polls.
- `?plan=starter|pro|elite` query param preselects the corresponding card (opens modal if logged in, scrolls to card if not).
- ≥ 15 new frontend tests.

**Depends on:** FEATURE-FP-2.13 (static page) + FEATURE-FP-2.7 (composables + TierCard/TierChangeModal preserved).
```

---

### 4.4 — Sprint-status.yaml update

**File:** `_bmad-output/implementation-artifacts/sprint-status.yaml`

**Change 1** — FP-2.7 status revert (line ~573):

**OLD:**
```yaml
  feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow: done
```

**NEW:**
```yaml
  feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow: ready-for-dev
```

Plus add a tracking comment at top:
```
# (story feature-fp-2-7 reverted to ready-for-dev on 2026-05-23 via correct-course — scope redefined to minimalist profile card + resume-pending + redirect to /pricing ; the 4 tier cards + upgrade/downgrade flow + Fedapay flow split out to new FEATURE-FP-2.13.1. Previous FP-2.7 implementation (24 files committed in working tree, 11 patches applied during code review) is partially preserved: composables, types, tierPresentation, TierCard, TierChangeModal, photo album work all remain in `frontend/src/features/face/...` for consumption by FP-2.13.1 + the new minimalist FP-2.7 ; only `SubscriptionPanel.vue` will be rewritten. See planning-artifacts/sprint-change-proposal-2026-05-23.md.)
```

**Change 2** — insert FP-2.13.1 entry (after FP-2.13):

**OLD:**
```yaml
  feature-fp-2-13-public-elite-badge: backlog
  feature-fp-2-13-public-pricing-page-refonte: backlog
  feature-fp-2-14-...
```

**NEW:**
```yaml
  feature-fp-2-13-public-elite-badge: backlog
  feature-fp-2-13-public-pricing-page-refonte: backlog
  feature-fp-2-13-1-pricing-page-authenticated-payment-flow: backlog
  feature-fp-2-14-...
```

*(Note: the actual entry name / position in the file may vary — to be reconciled at update-time.)*

---

### 4.5 — deferred-work.md update

**File:** `_bmad-output/implementation-artifacts/deferred-work.md`

**Action:** annotate the D3 entry to note that it's now in-scope for FP-2.7 rework (the resume-pending sessionStorage mechanic addresses the popup-blocked case as a subset).

**APPEND (under the FP-2.7 section):**

```
**Update 2026-05-23 (correct-course)** : D3 popup-blocked devient in-scope du rework FP-2.7 — la mécanique sessionStorage + bouton "Continuer le paiement" couvre le cas popup-blocked comme sous-cas du flow "paiement interrompu". L'entrée D3 reste documentée comme défer **partiel** : la robustesse complète (URL Fedapay régénérée, persistance cross-session) reste un hardening backend FP-2.5/2.8.
```

---

### 4.6 — Patches FP-2.7 (P1-P11) preservation

**Action:** aucune action requise. Tous les patches restent dans le working tree et survivent au rework :

| Patch | Fichier | Survie au rework |
|---|---|---|
| P1 (self-fetch on mount) | SubscriptionPanel.vue | ✅ s'applique au minimalist panel |
| P2 (hasPendingPayment) | SubscriptionPanel.vue | ✅ même logique pour le pending banner |
| P3 (refresh tolerance) | useSubscriptionPayment.ts | ✅ inchangé |
| P4 (async race guard) | useSubscriptionPayment.ts | ✅ inchangé |
| P5 (double-click guard) | useSubscriptionPayment.ts | ✅ inchangé |
| P6 (Free guard in onSelectTier) | SubscriptionPanel.vue (current) | ↪ migre vers FP-2.13.1 (modal vit là-bas) |
| P7 (waiting v-else-if pending) | SubscriptionPanel.vue | ✅ même logique pour le minimalist |
| P8 (planLabel fallback) | SubscriptionPanel.vue | ✅ même logique pour le minimalist |
| P9 (Escape guard) | TierChangeModal.vue | ↪ migre conceptuellement vers FP-2.13.1 (modal vit là-bas), fichier inchangé |
| P10 (test for P4) | useSubscriptionPayment.spec.ts | ✅ inchangé |
| P11 (manual error feedback) | useSubscriptionPayment.ts | ✅ inchangé |

---

## Section 5 — Implementation Handoff

### Scope classification

**Moderate** — requires backlog reorganization + story rework + epic file update. No PRD pivot. No architecture overhaul.

### Handoff plan

| Action | Owner | Deliverable |
|---|---|---|
| Update `epics-face-premium-subscription-v2.md` (FP-2.7 rewrite + FP-2.13 split note + new FP-2.13.1 section) | Scrum Master / PM | Mis à jour ce sprint |
| Revert FP-2.7 sprint-status entry + add FP-2.13.1 + add tracking comment | Scrum Master | Mis à jour ce sprint |
| Annotate deferred-work.md D3 entry | Scrum Master | Mis à jour ce sprint |
| Rewrite FP-2.7 story file via `bmad-create-story` ou rewrite manuel | Scrum Master | Story FP-2.7 prête en `ready-for-dev` |
| Draft FP-2.13.1 story file via `bmad-create-story` | Scrum Master | Story FP-2.13.1 prête en `backlog` (puis `ready-for-dev` quand FP-2.13 termine) |
| Implement FP-2.13 (déjà backlog) | Dev | Page `/pricing` statique hi-fi |
| Implement FP-2.13.1 | Dev | Auth payment flow on `/pricing` |
| Implement FP-2.7 rework | Dev | Profile minimalist + resume-pending + redirect |

### Success criteria

- Le user voit son abonnement actuel de manière minimaliste sur `/face/profile`.
- Le bouton "Changer de plan" amène à `/pricing` où la sélection + le paiement se font.
- Si un paiement Fedapay est interrompu, le user voit un bouton "Continuer le paiement" qui réouvre l'URL Fedapay (dans la même session navigateur).
- Aucune régression sur les tests FP-2.7 actuels (164 fichiers / 2162 tests).
- L'expérience reste cohérente visuellement avec le hi-fi handoff FP-2.13.

### Risks & mitigations

- **Risk** : FP-2.13 + FP-2.13.1 + FP-2.7 rework = 3 stories séquentielles, ~3-4 jours total. Si urgent, le bridge interim (FP-2.7 redirect vers legacy `/pricing`) débloque immédiatement le profile minimaliste sans attendre FP-2.13.
- **Risk** : la mécanique sessionStorage du resume-pending est fragile cross-session (logout, browser close, sessionStorage clearing). **Mitigation** : entry deferred-work.md (FP-2.5/2.8 hardening backend pour persistance robuste).
- **Risk** : intégration `TierCard` / `TierChangeModal` dans `/pricing` peut révéler des couplages non-anticipés (ex: dépendances de style propres au layout profil). **Mitigation** : tests visuels ciblés sur FP-2.13.1.

---

## Approval

**Status:** awaiting user approval (review and approve, or revise).

**Outstanding decisions:**

- Confirmer le pattern `FP-2.13 → FP-2.13.1 → FP-2.7 rework` (vs. alternative : élargir FP-2.13 pour inclure auth, sans split).
- Confirmer le bridge interim (FP-2.7 redirect vers legacy `/pricing` si FP-2.13.x pas encore done).
- Confirmer le scope du resume-pending (sessionStorage frontend-only, backend hardening reporté).

---

*Generated by `bmad-correct-course` workflow at 2026-05-23.*
