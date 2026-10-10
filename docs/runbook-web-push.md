# Runbook : notifications web push

Les notifications push (Android Chrome, iPhone en web app installée) reposent sur des clés VAPID côté serveur. Sans clés, le push est silencieusement désactivé : la bascule et l'invite sont masquées, aucun envoi, aucune erreur.

## Activer en production

1. Générer les clés sur le serveur (une seule fois, ne jamais les régénérer ensuite : cela invalide tous les abonnements existants) :

   ```bash
   cd backend
   php artisan webpush:vapid --show
   ```

2. Renseigner `backend/.env` :

   ```
   VAPID_SUBJECT=mailto:contact@weact.bj
   VAPID_PUBLIC_KEY=<clé publique>
   VAPID_PRIVATE_KEY=<clé privée>
   ```

   La clé publique est servie au frontend par `GET /api/v1/push/public-key` : aucune variable `VITE_` à ajouter.

3. Migrer, recharger la config et redémarrer les workers :

   ```bash
   php artisan migrate --force      # crée push_subscriptions
   php artisan config:cache
   php artisan event:clear
   php artisan queue:restart
   ```

4. Déployer le frontend. `public/sw.js` doit être servi à la racine (`/sw.js`), sans cache long (`Cache-Control: no-cache`) pour que les mises à jour du worker soient prises en compte. `manifest.webmanifest` et `/icons/*` sont des fichiers statiques.

Les envois passent par la file `database` (job `SendWebPush`, déclenché après commit) : un worker doit tourner.

## Ce qui déclenche un push

- Chaque notification in-app dont le type est dans `App\Support\Push\PushAllowlist::ALLOWED`. Un test (`PushAllowlistTest`) échoue si un nouveau type de notification n'est ni autorisé ni explicitement exclu.
- Un nouveau message de conversation, au plus un push par conversation et par destinataire toutes les 5 minutes.

Les abonnements expirés (réponse 404/410 du service push) sont supprimés automatiquement. La déconnexion et la suppression de compte retirent l'appareil.

## Tester

Android (Chrome) :
1. Ouvrir WeAct connecté, cliquer la cloche, activer « Notifications sur cet appareil » (ou « Activer » sur l'invite du tableau de bord) et accepter la permission.
2. Provoquer un événement (ex. un Producteur envoie un booking à la Face), écran verrouillé ou onglet fermé.
3. Toucher la notification : WeAct s'ouvre sur l'écran concerné.

iPhone (iOS 16.4 minimum) :
1. Dans Safari, Partager puis « Sur l'écran d'accueil ».
2. Ouvrir WeAct depuis l'icône d'écran d'accueil (pas depuis Safari), se connecter, activer la bascule.
3. Mêmes étapes de test qu'Android. Dans Safari non installé, la bascule affiche la consigne d'installation.

Envoi manuel de contrôle (sans passer par un événement métier) :

```bash
php artisan tinker
>>> app(\App\Services\Push\WebPushService::class)->sendToUser(\App\Models\User::find(ID), ['title' => 'Test', 'body' => 'Notification de test', 'url' => '/', 'tag' => 'test', 'ttl' => 60]);
```

## Dépannage

- Rien n'arrive : vérifier `VAPID_*` (`php artisan tinker`, `config('webpush.vapid.public_key')`), qu'un worker tourne, et `storage/logs` (message « Web push failed »).
- Bascule absente : navigateur sans push, clés absentes, ou iPhone non installé (message d'explication affiché dans ce dernier cas).
- Permission bloquée : réactiver les notifications dans les réglages du site du navigateur, puis revenir à la bascule.
