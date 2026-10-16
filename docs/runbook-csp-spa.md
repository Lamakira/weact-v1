# Runbook : Content-Security-Policy de la SPA

Statut : **préparé, NON appliqué**. Toute modification du nginx de production
exige l'accord du propriétaire. Ce runbook complète
`docs/runbook-nginx-security-headers.md` (en-têtes génériques et CSP `sandbox`
de `/storage/`, qui restent inchangés).

Objectif : limiter l'impact d'une XSS dans la SPA (vol du token Sanctum stocké
dans `localStorage`, exfiltration vers un domaine tiers, injection de script).

## 1. Ce que le code impose (dérivé du build, pas supposé)

Vérifié sur `frontend/` (build Vite, `npm run build-only`) :

| Sujet | Constat | Conséquence CSP |
|---|---|---|
| Scripts | `index.html` ne contient qu'un `<script type="module" src="/assets/...">`. Aucun script inline. | `script-src 'self'` suffit, sans `'unsafe-inline'`. |
| Styles | Feuilles CSS extraites en fichiers (`/assets/*.css`), y compris `vue-sonner/style.css` (importée dans `main.ts`). Le bundle ne contient aucun `createElement("style")`, `insertRule` ni `adoptedStyleSheets`. | `style-src 'self'`. |
| Attributs `style` | Vue (`:style`) et reka-ui (positionnement flottant) écrivent via le CSSOM (`el.style.x = ...`), autorisé par la CSP. Seul un attribut `style="..."` présent dans du HTML injecté en texte (`v-html`) serait bloqué. | Voir point d'attention sur `ArticleDetailView` ci-dessous. |
| `v-html` | `ArticleDetailView.vue` (contenu d'article passé par DOMPurify) et `AdminTwoFactorSetupPage.vue` (SVG du QR code généré côté serveur). | Un `style="..."` conservé par DOMPurify dans un article serait bloqué : à surveiller en Report-Only. |
| Temps réel | `plugins/echo.ts` : Reverb via le protocole Pusher, `wsHost = VITE_REVERB_HOST`, `forceTLS` si `VITE_REVERB_SCHEME=https`, chemin par défaut `/app/{clé}`. Auth des canaux : `POST ${VITE_BACKEND_URL}/broadcasting/auth` (XHR). | `connect-src` doit inclure l'origine WSS de Reverb. |
| API | `VITE_API_URL` (axios, XHR) et `VITE_BACKEND_URL` (cookie CSRF, `/broadcasting/auth`). Valeurs fixées au build : **non vérifiables depuis le dépôt**. | `'self'` si l'API est servie sur le même hôte que la SPA ; sinon ajouter son origine (section 3). |
| Service worker | `public/sw.js` (push uniquement, aucun `fetch`, aucun cache), enregistré depuis la SPA. Manifest : `/manifest.webmanifest`. | `worker-src 'self'`, `manifest-src 'self'`. |
| Polices | `ZalandoSans-Variable.woff2` embarquée, servie depuis `/assets/`. | `font-src 'self'`. |
| Images / vidéos | Médias en `/storage/` (même hôte que l'API aujourd'hui), prévisualisations d'upload en `blob:`, petites images en `data:`. | `img-src 'self' data: blob:`, `media-src 'self' blob:`. |
| Paiement FedaPay | Redirection de navigation (`window.location` vers l'URL de checkout, onglet courant, cf. `redirectToCheckout`). Aucun `<form>` posté vers FedaPay, aucune iframe. | Aucune entrée CSP nécessaire pour FedaPay. `form-action 'self'` suffit. |
| Google Sign-In | Flux OAuth par redirection serveur (`GoogleSignInButton` demande une URL au backend puis navigue). Aucun script Google (`gsi/client`) ni iframe. | Aucune entrée CSP nécessaire pour Google. |
| Web push | Le navigateur contacte lui-même le service de push ; ce n'est pas une requête soumise au `connect-src` de la page. | Rien à ajouter. |

## 2. En-tête prêt à coller (hôte unique `weact.bj`)

Hypothèse : SPA, API (`/api`, `/storage`, `/broadcasting`) et Reverb (`/app`)
sont tous sur `weact.bj`. À placer dans le `location /` qui sert `index.html`
(la SPA), pas dans `location /storage/` (qui garde sa CSP `sandbox`).

Première étape recommandée : **Report-Only**, pendant quelques jours (au
minimum un cycle complet d'usage : connexion, paiement, chat, upload).

```nginx
add_header Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self' wss://weact.bj; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; worker-src 'self'; manifest-src 'self'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'" always;
```

Une fois la période d'observation propre, remplacer `Content-Security-Policy-Report-Only`
par `Content-Security-Policy` (même valeur) :

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self' wss://weact.bj; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; worker-src 'self'; manifest-src 'self'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'" always;
```

Notes :

- **Piège d'héritage `add_header`** (cf. `runbook-nginx-security-headers.md`,
  section 1) : un `location` qui pose son propre `add_header` (par exemple la
  règle `no-cache` d'`index.html`) perd les en-têtes du `server`. Poser la CSP
  dans le `location` qui sert réellement `index.html` et la répéter dans tout
  bloc voisin qui a déjà un `add_header`. Vérifier par `curl -I`.
- `frame-ancestors` n'est **pas** supporté en `Report-Only` : l'anti-clickjacking
  reste porté par `X-Frame-Options: DENY` (runbook des en-têtes) pendant la phase
  d'observation.
- `connect-src 'self'` couvre `ws://`/`wss://` du même hôte selon les navigateurs
  récents, mais Safari ancien l'exige explicitement : garder `wss://weact.bj`.
- `frame-src 'none'` : aucune iframe n'est utilisée (ni Google, ni FedaPay).
  Retirer cette directive si un embed vidéo ou une iframe de paiement est ajouté.
- La CSP ne couvre que la SPA. Les réponses de l'API (JSON) n'en ont pas besoin ;
  les fichiers de `/storage/` ont déjà leur CSP `sandbox`.

## 3. Variantes selon la topologie réelle

Les valeurs `VITE_*` sont fixées au build ; confirmer celles utilisées pour la prod
avant d'appliquer.

- **API sur un autre sous-domaine** (par exemple `api.weact.bj`, comme dans
  `runbook-face-premium-rollout.md`) : ajouter l'origine à `connect-src`
  (`https://api.weact.bj`) ET à `img-src` / `media-src` si les médias `/storage/`
  y sont servis. Reverb sur un autre hôte : `wss://<hôte>` dans `connect-src`.
- **Médias sur R2 / `media.weact.bj`** (cf. `brief-migration-media-r2.md`) :
  ajouter `https://media.weact.bj` à `img-src` et `media-src`.
- **Reverb sur un port dédié** (par exemple 8080 en direct) : `wss://weact.bj:8080`.
  Derrière nginx sur `/app` (cas visé), aucun port à lister.

## 4. Checklist de vérification

Ouvrir la console du navigateur (filtre « Content Security Policy » / « violates
the following directive »), puis parcourir, en Report-Only d'abord, puis après
passage en mode bloquant :

1. Chargement de la page d'accueil, navigation entre pages publiques (aucune
   violation, polices et styles appliqués).
2. Connexion e-mail / mot de passe (Face, Producteur) et connexion admin
   (dont 2FA et affichage du QR code d'enrôlement : SVG injecté via `v-html`).
3. Connexion et inscription Google (redirection vers Google puis retour sur la SPA).
4. Paiement FedaPay : redirection vers le checkout puis retour (même onglet).
5. Notifications push : activation, réception, clic sur la notification
   (service worker, `worker-src`).
6. Temps réel : chat de réservation, compteur de notifications (WebSocket
   Reverb, `/broadcasting/auth`).
7. Uploads : photo de profil, album, photos de réception UGC (aperçu `blob:`).
8. Lecture vidéo : vidéos de profil Face, livrables UGC (`media-src`).
9. Page d'article de blog (contenu `v-html` : vérifier l'absence de violation
   `style-src-attr` ; si un article porte des `style="..."`, soit nettoyer le
   contenu, soit n'accepter que `style-src-attr 'unsafe-inline'`, jamais
   `style-src 'unsafe-inline'`).
10. `curl -sI https://weact.bj/ | grep -i content-security-policy` : en-tête
    présent sur `index.html` ET sur une route SPA profonde (ex. `/admin/login`).

Rollback : retirer le `add_header` puis `sudo nginx -t && sudo systemctl reload nginx`.
