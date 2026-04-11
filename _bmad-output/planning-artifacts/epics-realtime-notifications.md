---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 1
totalStories: 4
project_name: 'WEACT - Notifications Temps Réel'
user_name: 'Lamakira'
date: '2026-04-11'
---

# WEACT - Notifications Temps Réel - Epic Breakdown

## Overview

La cloche de notifications utilise un polling 30s sans WebSocket. Les notifications ne s'affichent pas en temps réel — l'utilisateur doit changer de page ou attendre le prochain tick. L'infrastructure Reverb/Echo est déjà en place (utilisée par le chat booking) mais n'est pas branchée sur les notifications.

## Requirements Inventory

### Functional Requirements

RT-FR1: Le badge de notifications doit se mettre à jour instantanément quand une nouvelle notification est créée
RT-FR2: Si le dropdown est ouvert, la nouvelle notification doit apparaître en tête de liste sans refresh
RT-FR3: Le système doit fonctionner pour les Faces ET les Producers (même composant)
RT-FR4: Un fallback doit garantir la fiabilité si le WebSocket déconnecte (refetch au focus, reconnect)

### Non-Functional Requirements

RT-NFR1: L'Observer backend doit broadcaster afterCommit pour éviter de pousser une notif avant validation de la transaction
RT-NFR2: Le store Pinia doit être la source unique de vérité pour unreadCount et la liste
RT-NFR3: Le polling 30s permanent doit être supprimé une fois le WebSocket en place

## Epic & Story Breakdown

---

### Epic RT-1: Notifications Temps Réel via Reverb/Echo

**Goal:** Rendre les notifications instantanées en branchant le badge et le dropdown sur le WebSocket Reverb/Echo déjà en place dans le projet.

**Priority:** Haute — UX dégradée pour tous les utilisateurs (Face + Producer).

**Contexte technique:**
- Echo/Reverb configuré dans `frontend/src/plugins/echo.ts`, utilisé pour le chat booking
- Les notifications sont créées via `Notification::create()` dispersé dans ~10 listeners
- `NotificationBell.vue` gère son propre état local + polling 30s
- `NotificationsDropdown.vue` fetch au montage uniquement
- `App.vue` utilise `:key="route.path"` ce qui remonte les layouts à chaque navigation

#### Stories

| ID | Story | FRs | Priority | Dépendance |
|----|-------|-----|----------|------------|
| RT-1.1 | Backend broadcast et observer | RT-FR1, RT-NFR1 | Haute | — |
| RT-1.2 | Store frontend + abonnement Echo | RT-FR1, RT-FR2, RT-FR3, RT-NFR2 | Haute | RT-1.1 |
| RT-1.3 | Refactor UI cloche/dropdown | RT-FR2, RT-NFR2, RT-NFR3 | Haute | RT-1.2 |
| RT-1.4 | Fallback et non-régression | RT-FR4, RT-NFR3 | Moyenne | RT-1.3 |

---

#### RT-1.1: Backend broadcast et observer

**Description:** Créer un event `NotificationCreated` broadcasté sur le canal privé `user.{id}` et centraliser son émission via un `NotificationObserver` sur le model `Notification`. L'observer doit utiliser `afterCommit` pour garantir que le broadcast n'est envoyé qu'après validation effective de la transaction.

**Acceptance Criteria:**
- Un event `NotificationCreated` existe et implémente `ShouldBroadcast`
- L'event est broadcasté sur le canal privé `user.{user_id}` (le destinataire de la notification)
- Un `NotificationObserver` est enregistré et dispatch `NotificationCreated` dans son hook `created`
- Le broadcast utilise `afterCommit` (via `ShouldBroadcastNow` avec `$afterCommit = true` ou via l'option sur l'event)
- Le payload contient les champs utiles : `id`, `type`, `message`, `url`, `created_at`
- Tous les `Notification::create()` existants dans les listeners déclenchent automatiquement le broadcast via l'observer (pas de modification des listeners)
- Le canal `user.{id}` est autorisé dans `channels.php` (ou l'authentification de canal existante est étendue)

---

#### RT-1.2: Store frontend + abonnement Echo

**Description:** Créer un store Pinia `useNotificationStore` qui centralise l'état des notifications (`unreadCount`, `items`) et s'abonne au canal Echo privé `user.{userId}`. Le store doit être la source unique de vérité.

**Acceptance Criteria:**
- Un store Pinia `useNotificationStore` expose : `unreadCount`, `items`, `isLoading`, `fetchUnreadCount()`, `fetchNotifications()`, `markAsRead(id)`, `markAllAsRead()`, `subscribe()`, `unsubscribe()`
- `subscribe()` écoute le canal `echo.private(\`user.${userId}\`)` pour l'event `NotificationCreated`
- À la réception d'un event : `unreadCount` s'incrémente, et si `items` est chargé (dropdown ouvert), la notification est insérée en tête
- Le store est initialisé une seule fois au login (pas à chaque remount de composant)
- `unsubscribe()` se détache du canal au logout

---

#### RT-1.3: Refactor UI cloche/dropdown

**Description:** Refactorer `NotificationBell.vue` et `NotificationsDropdown.vue` pour consommer le store Pinia au lieu de gérer leur propre état local et polling.

**Acceptance Criteria:**
- `NotificationBell.vue` lit `unreadCount` depuis le store (plus de `ref` local, plus de `fetchUnreadCount` local, plus de `setInterval`)
- `NotificationsDropdown.vue` lit `items` depuis le store et appelle `fetchNotifications()` du store au montage
- Le polling 30s (`setInterval(fetchUnreadCount, 30000)`) est supprimé
- Le comportement UX reste identique : badge animé, dropdown toggle, mark as read, mark all as read
- Les events `@notification-read` et `@all-read` passent par le store
- Le composant fonctionne pour Face ET Producer (même `DashboardHeader`)

---

#### RT-1.4: Fallback et non-régression

**Description:** Ajouter des fallbacks pour garantir la fiabilité quand le WebSocket est indisponible, et vérifier l'absence de régressions.

**Acceptance Criteria:**
- Un refetch `fetchUnreadCount()` est déclenché au `window.focus` (l'utilisateur revient sur l'onglet)
- Un refetch est déclenché à la reconnexion Echo (event `reconnect`)
- Un polling de sécurité lent (toutes les 2-5 minutes) est en place comme filet de sécurité
- Les notifications Face fonctionnent en temps réel (booking reçu, candidature acceptée, etc.)
- Les notifications Producer fonctionnent en temps réel (booking accepté, candidature reçue, etc.)
- Aucune régression sur les fonctionnalités existantes : mark as read, mark all as read, dropdown navigation, badge count
