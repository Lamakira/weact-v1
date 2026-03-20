# Décisions — Workflow de Paiement des Missions

> Décisions prises lors d'une session de conception (Party Mode) le 2026-03-20.

---

## Contexte

Le workflow de paiement des missions implique plusieurs acteurs (un Producer, N Faces) et doit garantir la sécurité des deux parties. Cette session a clarifié les règles métier fondamentales avant implémentation.

---

## Décisions prises

### 1. Budget de la mission = rémunération par Face

- Le champ `budget` de la mission représente **le montant proposé à chaque Face**, pas le total.
- Il est **indépendant** du `tarif_journalier` de la Face. Ce sont deux données distinctes :
  - `budget` (Mission) → ce que le Producer propose
  - `tarif_journalier` (Face) → ce que la Face demande habituellement
- Une Face peut très bien postuler à une mission dont le budget est inférieur à son tarif habituel.
- **Action UX :** Renommer le champ "Budget" en **"Rémunération par Face"** dans le formulaire de publication et ajouter un texte d'aide : *"Indiquez le montant que vous proposez à chaque Face pour cette mission."*

---

### 2. Déclencheur du paiement = confirmation de sélection

Le paiement **ne se déclenche pas** à l'acceptation individuelle d'une candidature. Il se déclenche quand le Producer **confirme sa sélection finale** de Faces.

**Flow complet :**

| Étape | Acteur | Action |
|---|---|---|
| Publication | Producer | Mission en ligne, budget par Face visible |
| Candidatures | Faces | Postulent librement |
| Sélection | Producer | Choisit N faces parmi les candidatures |
| **Confirmation de sélection** | Producer | **Paiement déclenché** (N × budget + commission) |
| Mission | Système | Débloquée uniquement si paiement validé |
| Libération des fonds | Fin de mission | Fonds reversés aux Faces |

---

### 3. Flexibilité sur le nombre de Faces retenues

- Le Producer peut retenir **moins** de Faces que le nombre initialement indiqué (ex: 3 sur 5 prévues).
- Le paiement s'ajuste au **nombre réel de Faces retenues**.
- Le Producer n'est pas bloqué s'il ne trouve pas assez de profils correspondants.

---

### 4. Paiement obligatoire avant début de mission

- La mission ne peut pas passer en statut `in_progress` si le paiement n'est pas validé.
- Cette contrainte doit être **enforcée côté backend** (pas seulement côté frontend).
- Les fonds sont placés en **escrow** jusqu'à la fin de la mission.

---

### 5. Statut intermédiaire à introduire

Le modèle `Mission` a actuellement les statuts : `draft`, `published`, `closed`, `completed`.

Un statut intermédiaire est nécessaire entre la sélection et le démarrage :

- **`selection_confirmed`** ou **`pending_payment`** — sélection finalisée, en attente de paiement

---

### 6. Récapitulatif de paiement avant confirmation

Avant de valider la sélection et déclencher le paiement, le Producer doit voir un **récapitulatif clair** :

```
Faces retenues : 3
Budget par Face : 25 000 XOF
Sous-total : 75 000 XOF
Commission plateforme : X XOF
Total à payer : XX 000 XOF
```

---

## Ce qui n'est PAS dans scope

- Négociation de tarif entre Producer et Face (pas de système de contre-offre)
- Le `BookingService` existant est un workflow différent (1-to-1), il ne couvre pas les missions multi-faces

---

## Prochaines étapes

1. **Plan mode** — concevoir le workflow d'exécution détaillé
2. **Implémentation** — backend (statuts, paiement escrow) + frontend (UI sélection + récapitulatif)
