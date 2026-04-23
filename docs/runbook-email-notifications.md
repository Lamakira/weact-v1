# Runbook — Notifications email (infrastructure SMTP prod)

**Audience :** opérateurs WEACT qui déploient en production.

**Scope :** configurer et valider le driver SMTP de production pour que les 5 emails FIX-24.2 → FIX-24.6 (cycle mission / booking / wallet) arrivent réellement en boîte mail utilisateur, pas seulement dans les logs.

---

## 1. Scope & Audience

Ce runbook couvre :

- La bascule du driver mail de `log` (défaut dev) vers `smtp` (prod).
- La procédure de configuration des 3 providers SMTP recommandés (Brevo, Mailgun, AWS SES).
- Les enregistrements DNS obligatoires (SPF / DKIM / DMARC) pour éviter que les mails WEACT partent en spam.
- Le worker de queue Laravel qui consomme la file `jobs` (emails queued via `Mail::queue(...)`).
- La procédure de test post-déploiement avec `tinker` + `Mail::raw`.
- Les 3 leviers de rollback en cas d'incident (spam, bounce rate élevé, SMTP cassé).

Ce runbook **ne couvre pas** :

- L'implémentation des 5 mailables (FIX-24.2 à FIX-24.6 — chacune a sa propre story).
- La mise en place d'un monitoring alerting `failed_jobs > N` (backlog ops séparé).
- La migration des 5 mailables existantes (`BookingCancelledMail`, `ContactFormMail`, `WithdrawalApprovedMail`, `WithdrawalRejectedMail`, `WithdrawalRequestSubmittedMail`) vers `BaseMail` — décision produit FIX-24.1 AC #3.

---

## 2. État actuel (dev)

En environnement de développement, le driver par défaut est `log` :

```env
MAIL_MAILER=log
```

Résultat : tous les emails partent dans `storage/logs/laravel.log` et ne quittent jamais la machine. C'est adéquat pour tout le développement applicatif, mais **insuffisant pour valider la mise en production**.

### Tester localement avec un vrai SMTP sandbox (optionnel)

Pour tester les emails dans un client mail réel sans envoyer de vrais mails à des utilisateurs, **Mailtrap** est le standard. Créer un compte gratuit sur `mailtrap.io`, récupérer les credentials du sandbox, puis renseigner dans `backend/.env` (**pas** `.env.example`) :

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<token-fourni-par-mailtrap>
MAIL_PASSWORD=<token-fourni-par-mailtrap>
MAIL_FROM_ADDRESS=noreply@weact.bj
MAIL_FROM_NAME=WEACT
```

Alternatives self-hosted : **MailHog** ou **Mailpit** (containers Docker) — même principe, UI web locale qui capture tous les emails sortants.

---

## 3. Config prod — 3 options SMTP

### Option A — Brevo (ex-Sendinblue) **[recommandé]**

- **Prérequis :** compte Brevo, domaine d'envoi `weact.bj` vérifié via le dashboard Brevo (DKIM + SPF configurés automatiquement après ajout du domaine dans l'UI).
- **Limite gratuite :** 300 emails / jour. Largement suffisant pour le volume WEACT post-lancement (< 100 emails/jour attendus).
- **Coût au-delà :** offre Lite à partir de ~7 €/mois pour 20 000 emails/mois.
- **Conformité :** société européenne, serveurs UE, RGPD natif.
- **Variables `.env` :**

  ```env
  MAIL_MAILER=smtp
  MAIL_SCHEME=smtp
  MAIL_HOST=smtp-relay.brevo.com
  MAIL_PORT=587
  MAIL_USERNAME=<login-smtp-fourni-par-brevo>
  MAIL_PASSWORD=<clef-smtp-fournie-par-brevo>
  MAIL_FROM_ADDRESS=noreply@weact.bj
  MAIL_FROM_NAME=WEACT
  ```

  > **Note** : Laravel 12 lit `MAIL_SCHEME` (pas `MAIL_ENCRYPTION` — obsolète). `MAIL_SCHEME=smtp` sur le port 587 active STARTTLS automatiquement (comportement Symfony Mailer). Pour un provider qui exige SSL direct sur le port 465, utiliser `MAIL_SCHEME=smtps` + `MAIL_PORT=465`.

- **Doc officielle :** voir la documentation Brevo « SMTP relay » sur le site brevo.com.

### Option B — Mailgun

- **Prérequis :** compte Mailgun, domaine `weact.bj` ajouté, DKIM + SPF publiés.
- **Limite gratuite :** historiquement 5 000 emails / mois, vérifier la tarification courante avant de choisir.
- **Coût au-delà :** pay-as-you-go (~0,80 € / 1 000 emails).
- **Conformité :** option de région UE disponible lors de la création du domaine (important pour le Code du Numérique Bénin qui privilégie UE / Afrique de l'Ouest).
- **Variables `.env` :**

  ```env
  MAIL_MAILER=smtp
  MAIL_SCHEME=smtp
  MAIL_HOST=smtp.eu.mailgun.org
  MAIL_PORT=587
  MAIL_USERNAME=postmaster@mg.weact.bj
  MAIL_PASSWORD=<clef-smtp-mailgun>
  MAIL_FROM_ADDRESS=noreply@weact.bj
  MAIL_FROM_NAME=WEACT
  ```

- **Doc officielle :** voir la documentation Mailgun « SMTP credentials » sur le site mailgun.com.

### Option C — AWS SES

- **Prérequis :** compte AWS, région choisie (eu-west-3 Paris recommandée pour la latence + conformité), domaine `weact.bj` vérifié, sortie du sandbox SES (demande explicite à AWS support, 24-48 h).
- **Limite :** quota initial sandbox 200 emails / 24 h ; après sortie du sandbox, 50 000 emails / jour d'entrée de jeu.
- **Coût :** ~0,10 USD / 1 000 emails.
- **Conformité :** configurable région UE, mais le provisioning IAM + gestion des credentials rotatifs ajoute une complexité significative.
- **Variables `.env` :**

  ```env
  MAIL_MAILER=smtp
  MAIL_SCHEME=smtp
  MAIL_HOST=email-smtp.eu-west-3.amazonaws.com
  MAIL_PORT=587
  MAIL_USERNAME=<access-key-id-ses-smtp>
  MAIL_PASSWORD=<secret-access-key-ses-smtp>
  MAIL_FROM_ADDRESS=noreply@weact.bj
  MAIL_FROM_NAME=WEACT
  ```

- **Doc officielle :** voir la documentation AWS SES « SMTP credentials » sur le site docs.aws.amazon.com.

### Recommandation produit

**Brevo** pour le lancement. Raisons :

1. Offre gratuite 300 mails/jour qui couvre largement le volume initial.
2. Setup DKIM/SPF en 10 minutes via l'UI, pas de sandbox bloquant.
3. Serveurs UE, conformité RGPD native sans configuration spécifique.
4. Pas de verrouillage fournisseur — si le volume explose, bascule vers Mailgun pay-as-you-go en quelques minutes (même protocole SMTP, juste changer `MAIL_HOST` + credentials).

**Pourquoi pas SES en premier choix** : complexité AWS (IAM, région, demande de sortie de sandbox) disproportionnée pour un volume < 100 emails/jour. SES devient intéressant à partir de 10 000 emails/jour et une équipe ops familière d'AWS.

---

## 4. DNS obligatoire pour la prod

Sans ces 3 enregistrements, les emails WEACT partiront en spam chez Gmail / Outlook / Yahoo. Les publier **avant** la bascule `MAIL_MAILER=smtp`.

### SPF

Enregistrement **TXT** sur le domaine d'envoi (ex. `weact.bj` ou `mg.weact.bj` selon le provider) :

```
v=spf1 include:<provider-spf> ~all
```

Exemples par provider :

- Brevo : `v=spf1 include:spf.brevo.com ~all`
- Mailgun : `v=spf1 include:mailgun.org ~all`
- SES (eu-west-3) : `v=spf1 include:amazonses.com ~all`

### DKIM

Enregistrement **CNAME** ou **TXT** fourni par le provider (2 à 3 entrées pour Brevo/Mailgun, 3 CNAME pour SES). Les valeurs exactes sont affichées dans le dashboard du provider après validation du domaine — copier-coller directement.

### DMARC

Enregistrement **TXT** sur `_dmarc.weact.bj` :

```
v=DMARC1; p=quarantine; rua=mailto:ops@weact.bj
```

- **`p=quarantine`** au démarrage : les mails qui échouent SPF/DKIM partent en spam chez le destinataire, mais ne sont pas rejetés → buffer en cas de faux positif.
- **Passer à `p=reject`** après 2 semaines sans faux positif détecté via les rapports `rua`.
- **`rua=mailto:ops@weact.bj`** : l'adresse qui reçoit les rapports DMARC agrégés (quotidiens). Peut être une adresse interne ou un service dédié comme Postmark DMARC Digests.

### Vérification

L'adresse `MAIL_FROM_ADDRESS` doit être sur un domaine où SPF **et** DKIM sont configurés. `noreply@weact.bj` OK. `noreply@gmail.com` **KO** (impossible de configurer SPF/DKIM sur un domaine qu'on ne possède pas).

Outils de vérification post-publication :

- `dig +short TXT weact.bj` → doit retourner la ligne `v=spf1 ...`.
- `dig +short TXT _dmarc.weact.bj` → doit retourner la ligne `v=DMARC1 ...`.
- Envoi d'un email de test vers Gmail → ouvrir le mail → menu « Afficher l'original » → chercher `dkim=pass`, `spf=pass`, `dmarc=pass`.

---

## 5. Queue worker

Les emails WEACT sont queued (via `Mail::to(...)->queue(...)`), pas envoyés synchrones. Le worker doit tourner en prod pour consommer la file.

### Connexion queue

```env
QUEUE_CONNECTION=database
```

Déjà positionné dans `backend/.env.example`. La table `jobs` vit dans MySQL — pas besoin de Redis au volume courant. La migration qui crée cette table est **déjà versionnée** dans le repo (`backend/database/migrations/0001_01_01_000002_create_jobs_table.php`). Elle s'applique donc automatiquement avec la commande standard de déploiement :

```bash
php artisan migrate --force
```

> **Ne pas** exécuter `php artisan queue:table` sur le serveur : cette commande créerait une **migration snowflake** en plus de celle déjà tracked, avec potentiellement un timestamp conflictuel. Si la migration versionnée est bien appliquée, la table `jobs` existe — vérifier via `SHOW TABLES LIKE 'jobs';` en cas de doute.

### Commande worker

```bash
php artisan queue:work database --queue=default --tries=3 --backoff=60 --timeout=120 --sleep=3 --max-time=3600
```

- `--tries=3 --backoff=60` : 3 tentatives espacées de 60 s. Après 3 échecs, le job va dans `failed_jobs`.
- `--timeout=120` : un job qui dépasse 120 s est tué.
- `--max-time=3600` : le worker redémarre après 1 h → protection contre les memory leaks Laravel connus.
- `--sleep=3` : 3 s d'attente entre 2 checks de la file si vide.

### Supervisor

Le worker doit tourner sous `supervisor` pour redémarrer automatiquement après crash ou reboot. Créer `/etc/supervisor/conf.d/weact-queue-worker.conf` :

```ini
[program:weact-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/weact/backend/artisan queue:work database --queue=default --tries=3 --backoff=60 --timeout=120 --sleep=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=<DEPLOY_USER>
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/weact-queue-worker.log
stopwaitsecs=3600
```

Remplacer `<DEPLOY_USER>` par l'utilisateur système qui possède `/var/www/weact/backend` (typiquement `www-data` ou `deploy`). Adapter le chemin `/var/www/weact/backend` si la topologie prod est différente.

Recharger Supervisor :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start weact-queue-worker:*
sudo supervisorctl status weact-queue-worker:*
```

Le VPS prod fait déjà tourner un programme Supervisor pour `php artisan reverb:start` (cf. stack realtime notifications). Ajouter ce second `[program:...]` à côté, pas de conflit.

### Jobs échoués

- `php artisan queue:failed` — lister les jobs qui ont épuisé leurs 3 tentatives.
- `php artisan queue:retry <id>` — relancer un job précis (ex. après correctif SMTP).
- `php artisan queue:retry all` — relancer tous les jobs échoués.
- `php artisan queue:flush` — vider la table `failed_jobs`. **Destructif** — ne faire qu'après analyse, jamais en réflexe.

---

## 6. Procédure de test post-déploiement

À exécuter après chaque bascule `MAIL_MAILER=log` → `MAIL_MAILER=smtp` ou après un changement de provider.

1. **SSH prod.**

2. **Envoi de test via tinker :**

   ```bash
   php artisan tinker --execute="Mail::raw('Test WEACT SMTP — ' . now(), fn (\$m) => \$m->to('ops@weact.bj')->subject('Test SMTP prod'));"
   ```

3. **Vérifier la réception** dans la boîte `ops@weact.bj` en moins de 2 minutes. Si rien n'arrive :
   - Vérifier `tail -f storage/logs/laravel.log` — une exception SMTP y apparaîtra.
   - Vérifier `SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;` — si le job a échoué, la raison y est loggée.

4. **Inspecter les logs applicatifs :**

   ```bash
   tail -n 50 /var/www/weact/backend/storage/logs/laravel.log
   ```

   Aucune exception `Symfony\Component\Mailer\Exception\*` ne doit apparaître.

5. **Inspecter l'état de la queue :**

   ```bash
   php artisan tinker --execute="echo 'jobs: ' . DB::table('jobs')->count() . PHP_EOL . 'failed: ' . DB::table('failed_jobs')->count();"
   ```

   - `jobs` : 0 ou 1 (un job en cours de traitement est normal).
   - `failed_jobs` : 0. Si > 0, lire la ligne la plus récente et corriger avant de relancer.

6. **Valider les en-têtes DKIM / SPF / DMARC** sur un email reçu côté Gmail :
   - Ouvrir l'email → menu à 3 points → « Afficher l'original ».
   - Chercher les 3 lignes dans les en-têtes :

     ```
     Authentication-Results: mx.google.com;
         dkim=pass ...
         spf=pass ...
         dmarc=pass ...
     ```

   - Si **une seule** des 3 est `fail` ou `neutral` → ne pas passer la prod en `p=reject` DMARC tant que ce n'est pas corrigé.

---

## 7. Rollback / coupe-circuit

3 leviers en ordre de gravité croissante.

### 7.1 Léger — bascule driver vers `log`

**Quand utiliser :** spam rate modéré signalé par les utilisateurs, ou bounce rate entre 2 % et 5 %, ou incident non critique mais qu'on veut investiguer à froid.

```env
MAIL_MAILER=log
```

Puis `php artisan config:clear` + recharger le worker (`supervisorctl restart weact-queue-worker:*`).

**Impact :** plus aucun mail ne sort. Les emails dispatchés via `Mail::queue(...)` sont consommés par le worker, partent dans `storage/logs/laravel.log` à la place du réseau. Les notifications in-app temps réel (Reverb) restent intactes — les utilisateurs continuent de recevoir les notifications dans l'UI.

### 7.2 Moyen — arrêt du worker

**Quand utiliser :** SMTP provider HS (panne Brevo/Mailgun/SES temporaire), ou incident où on veut préserver les jobs pour les rejouer plus tard sans les exécuter maintenant.

```bash
sudo supervisorctl stop weact-queue-worker:*
```

**Impact :** les jobs s'empilent dans la table `jobs` mais ne sont plus exécutés. Une fois l'incident résolu :

```bash
sudo supervisorctl start weact-queue-worker:*
```

Les jobs en attente partent immédiatement (ordre FIFO). Surveiller `failed_jobs` pendant la reprise.

### 7.3 Hard — bloquer le domaine d'envoi au niveau provider

**Quand utiliser :** bounce rate > 5 % (liste d'emails sales, typo dans `MAIL_FROM_ADDRESS`, domaine blacklisté), ou complaint rate > 0,1 % (spam signalé par Gmail / utilisateurs).

- **Brevo :** dashboard → Senders & IP → désactiver l'IP d'envoi.
- **Mailgun :** dashboard → Sending → Domains → pause domain.
- **SES :** AWS console → SES → Sending statistics → pause sending.

Se référer à la doc officielle du provider pour la procédure exacte de pause. En parallèle, diagnostiquer pourquoi tant de bounces / spam (liste importée depuis une source externe non qualifiée ? typo ? domaine du `FROM` mal configuré DKIM ?).

### Seuils d'alerte à monitorer

| Métrique | Seuil | Signal |
| --- | --- | --- |
| Bounce rate | > 2 % | Anormal — investiguer la qualité de la base emails |
| Complaint rate | > 0,1 % | Seuil spam Gmail / Outlook — risque de blacklist |
| `failed_jobs` sur 24 h | > 10 | Infra SMTP cassée ou provider en panne |
| Délai moyen entre dispatch et delivery | > 5 min | Worker saturé ou provider lent |

La mise en place d'un monitoring automatique de ces métriques est **hors scope** de FIX-24.1 — backlog ops dédié.

---

## 8. Liens vers les 5 emails du cycle

Ce runbook sert aussi d'index pour les 5 stories d'implémentation des mailables WEACT. Les stories ne sont pas encore créées au moment où ce runbook est livré — ce sont des pointeurs vers le plan d'épique.

- **FIX-24.2 — Email « Face sélectionnée après paiement Producer »** (voir épique `_bmad-output/planning-artifacts/epics-postlaunch-fixes-9.md` §FIX-24.2). Hook dans `applySelectionOutcomesOnPaid` du webhook FedaPay `Paid`.
- **FIX-24.3 — Email « Participation Face confirmée »** vers le Producer (voir épique `_bmad-output/planning-artifacts/epics-postlaunch-fixes-9.md` §FIX-24.3). Hook dans `Face/CandidatureController::confirm`.
- **FIX-24.4 — Email « Mission clôturée + crédit wallet Face »** (voir épique `_bmad-output/planning-artifacts/epics-postlaunch-fixes-9.md` §FIX-24.4). Hook dans `MissionService::completeMission`.
- **FIX-24.5 — Email « Demande booking reçue »** vers la Face (voir épique `_bmad-output/planning-artifacts/epics-postlaunch-fixes-9.md` §FIX-24.5). Listener sur l'event `BookingCreated`.
- **FIX-24.6 — Email « Portefeuille Producer crédité »** (voir épique `_bmad-output/planning-artifacts/epics-postlaunch-fixes-9.md` §FIX-24.6). Event `ProducerWalletCredited` sur `WalletService::creditDirect`.

Chaque story FIX-24.2 → FIX-24.6 livre :

- Une sous-classe de `App\Mail\BaseMail` (cf. `backend/app/Mail/BaseMail.php`).
- Une vue Blade qui `@extends('emails.layouts.base')` (cf. `backend/resources/views/emails/layouts/base.blade.php`).
- Le hook applicatif (listener / service) qui dispatche le mail.
- Des tests feature qui vérifient le hook + le contenu du mail.
