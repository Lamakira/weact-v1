#!/bin/bash
# =============================================================================
# WeAct — Synchronise la branche `dev` sur `main` après un merge de PR
# - À lancer depuis n'importe quel dossier du repo
# - Aligne `dev` sur `origin/main` (efface la divergence due au rebase/squash merge)
# - Pousse le résultat avec --force-with-lease (sûr face aux pushes concurrents)
#
# Usage : ./scripts/sync-dev-to-main.sh
# =============================================================================
set -euo pipefail

DEV_BRANCH="dev"
MAIN_BRANCH="main"
REMOTE="origin"

red()    { printf "\033[1;31m%s\033[0m\n" "$1"; }
green()  { printf "\033[1;32m%s\033[0m\n" "$1"; }
yellow() { printf "\033[1;33m%s\033[0m\n" "$1"; }
blue()   { printf "\033[1;34m%s\033[0m\n" "$1"; }

# --- 1. Vérifier qu'on est dans un repo git ---------------------------------
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    red "Erreur : ce script doit être lancé depuis un repo git."
    exit 1
fi

# Se placer à la racine du repo pour que tous les chemins soient déterministes.
cd "$(git rev-parse --show-toplevel)"

# --- 2. Vérifier que les branches existent en remote ------------------------
git fetch --quiet "$REMOTE"

if ! git show-ref --verify --quiet "refs/remotes/$REMOTE/$DEV_BRANCH"; then
    red "Erreur : la branche $REMOTE/$DEV_BRANCH n'existe pas."
    exit 1
fi
if ! git show-ref --verify --quiet "refs/remotes/$REMOTE/$MAIN_BRANCH"; then
    red "Erreur : la branche $REMOTE/$MAIN_BRANCH n'existe pas."
    exit 1
fi

# --- 3. Vérifier l'arbre de travail propre ----------------------------------
if ! git diff --quiet || ! git diff --cached --quiet; then
    red "Erreur : changements non committés détectés."
    yellow "Commit, stash ou jette tes modifications avant de lancer la synchro."
    git status --short
    exit 1
fi

# --- 4. Afficher l'état avant ----------------------------------------------
read -r BEHIND AHEAD < <(git rev-list --left-right --count "$REMOTE/$MAIN_BRANCH...$REMOTE/$DEV_BRANCH")
blue "État courant : $REMOTE/$DEV_BRANCH est $AHEAD ahead / $BEHIND behind par rapport à $REMOTE/$MAIN_BRANCH."

if [[ "$AHEAD" == "0" && "$BEHIND" == "0" ]]; then
    green "Déjà synchronisé. Rien à faire."
    exit 0
fi

# --- 5. Confirmation utilisateur (sauf si SYNC_DEV_AUTO_CONFIRM=1) ---------
if [[ "${SYNC_DEV_AUTO_CONFIRM:-0}" != "1" ]]; then
    yellow ""
    yellow "Cette opération va :"
    yellow "  1. Faire 'git reset --hard $REMOTE/$MAIN_BRANCH' sur $DEV_BRANCH (réécrit l'historique local)."
    yellow "  2. 'git push --force-with-lease' vers $REMOTE/$DEV_BRANCH (réécrit l'historique distant)."
    yellow ""
    yellow "Cette opération est sûre si tu es seul à committer sur $DEV_BRANCH."
    yellow "Si un collaborateur a commité sur $DEV_BRANCH entre temps, --force-with-lease abandonnera."
    yellow ""
    read -r -p "Continuer ? (yes/N) " RESPONSE
    if [[ "$RESPONSE" != "yes" ]]; then
        red "Annulé par l'utilisateur."
        exit 1
    fi
fi

# --- 6. Bascule vers dev (préserve la branche initiale pour la restituer) --
INITIAL_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$INITIAL_BRANCH" != "$DEV_BRANCH" ]]; then
    blue "Basculement temporaire de $INITIAL_BRANCH vers $DEV_BRANCH..."
    git checkout "$DEV_BRANCH"
fi

# --- 7. Reset hard puis push force-with-lease ------------------------------
blue "Reset $DEV_BRANCH sur $REMOTE/$MAIN_BRANCH..."
git reset --hard "$REMOTE/$MAIN_BRANCH"

blue "Push force-with-lease vers $REMOTE/$DEV_BRANCH..."
git push --force-with-lease "$REMOTE" "$DEV_BRANCH"

# --- 8. Restaurer la branche initiale si elle était autre que dev ---------
if [[ "$INITIAL_BRANCH" != "$DEV_BRANCH" ]]; then
    blue "Retour sur la branche initiale $INITIAL_BRANCH..."
    git checkout "$INITIAL_BRANCH"
fi

# --- 9. Vérification finale ------------------------------------------------
read -r BEHIND AHEAD < <(git rev-list --left-right --count "$REMOTE/$MAIN_BRANCH...$REMOTE/$DEV_BRANCH")
if [[ "$AHEAD" == "0" && "$BEHIND" == "0" ]]; then
    green "Synchronisation terminée. $DEV_BRANCH est maintenant aligné sur $MAIN_BRANCH."
else
    red "Synchronisation incomplète : $AHEAD ahead / $BEHIND behind. Vérifie manuellement."
    exit 1
fi
