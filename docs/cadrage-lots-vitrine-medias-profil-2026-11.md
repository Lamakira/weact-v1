# Cadrage : vitrine, médias et profil Face (2026-11-28)

Décisions prises en séance de questions avec le PO. Vocabulaire : [GLOSSARY.md](../GLOSSARY.md). Décision structurante : [ADR 0001](adr/0001-exclusivite-elite.md).

Chaque lot = une branche et une PR vers `dev`. Maquette validée par le PO avant tout code d'interface.

Ordre : lot 2, puis lot 3, puis lot 4. Le lot 1 avance en parallèle (aucun fichier commun).

## Lot 1 : Profil Face

- **Pays de résidence** : le champ `pays` existe déjà (rempli pour 4 116 Faces sur 4 123, 355 hors Bénin). Renommer le libellé « Pays » en « Pays de résidence ». Afficher le pays sur la vignette du listing quand ce n'est pas le Bénin.
- **Tailles de vêtement** : T-shirt (XS à 3XL), veste (XS à 3XL), pantalon (EU 34 à 56), pointure (EU 35 à 48). Toutes optionnelles. Visibles par les Producteurs connectés sur le profil, rappelées dans le formulaire de booking UGC. Pas de filtre. Attention : dans le code, `taille` désigne déjà la stature en cm.
- **UGC payants uniquement** : interrupteur côté Face, montré seulement aux paliers qui ont accès à l'UGC (Pro, Élite). Par défaut : la Face accepte le produit seul (rien ne change pour l'existant). S'applique aux bookings UGC directs seulement : le Producteur voit la préférence et ne peut plus choisir « Produit seul » pour cette Face. Les missions UGC publiques ne changent pas (la Face choisit de postuler). Les bookings déjà créés ne changent pas.
- **Montant minimum** : quand la Face est en « UGC payants uniquement », le montant d'argent d'un UGC payant doit atteindre un minimum réglé en configuration (5 000 FCFA au départ, à confirmer). Pas de minimum quand la Face accepte le produit seul.

## Lot 2 : Listing

- **Exclusivité Élite** (remplace « En vedette ») : l'admin choisit au plus 4 Faces Élite. Jamais vendue. Places 1 à 4 de la page 1, ordre qui tourne entre elles à chaque passage du carrousel. Jamais sur la page 2 et suivantes. Sort de la file du carrousel. Tombe automatiquement quand la Face perd l'Élite (expiration, annulation, passage en Pro) ; ne revient pas seule au réabonnement ; l'admin reçoit une alerte « place d'exclusivité libre ». Au lancement : les 66 « En vedette » actuelles (7 Élite, 3 Pro, 56 Découverte) sont décochées, l'admin choisit ses 4 (les 7 Élites déjà cochées lui sont proposées en premier).
- **Élites en tête** : sur chaque page, les places Élite viennent en premier (page 1 : 4 exclusivités puis les Élites qui tournent ; pages suivantes : les 9 places Élite aux Élites qui tournent). S'il y a moins d'Élites que de places, les paliers suivants prennent le reste.
- **Ordre après les Élites** : Pro, puis Starter, puis Découverte, en blocs (fin du mélange actuel).
- **Toutes les pages, avec ou sans filtre**. Avec un filtre actif : Élites en tête du résultat, une exclusivité qui correspond au filtre passe devant, toujours pas de rotation (décision du 2026-07-21).
- Coût accepté : avec 20 Élites, une Élite sans exclusivité passe d'environ 45 % à 31 % de présence en page 1 ; la première vignette Pro descend de la 2ᵉ à la 10ᵉ place, Starter de la 4ᵉ à la 14ᵉ.
- Les places par palier ne changent pas (9 / 4 / 2 / 1 sur 16) ; la règle de redistribution vers le haut des places des paliers vides reste en place.

## Lot 3 : Médias

- **Limites de poids** : photos de 8 à 15 Mo (photo de profil, album) ; vidéos de 50 à 100 Mo (présentation, acting, UGC du portfolio). Durée maximale inchangée (2 min). Nombre de photos et vidéos par palier inchangé. Photos produit UGC et livrables UGC inchangés.
- **Version légère des vidéos** : le serveur garde l'original et fabrique en arrière-plan une version 720p, servie par défaut. Bouton « Voir en HD » pour charger l'original à la demande, pour tous les visiteurs. Pendant le traitement, on sert l'original. Les vidéos existantes (5,3 Go) sont traitées une fois au déploiement par une commande.
- Photos : pas de compression avant envoi ; l'original reste intact (décision du 2026-07-06), les versions WebP existantes continuent de servir les pages.
- Au déploiement : relever la limite d'envoi du serveur (nginx `client_max_body_size`, PHP `upload_max_filesize` et `post_max_size`).

## Lot 4 : Message « ajoute des vidéos »

- Deux publics : les abonnés sans vidéo (« Ajoute ta vidéo ») ; les Découverte, qui n'ont droit à aucune vidéo (« les profils avec vidéo sont plus recrutés, passe à Starter »). Version spéciale pour les 331 Découverte qui ont une vidéo enregistrée mais cachée par leur palier (« ta vidéo est prête, elle s'affiche dès Starter »).
- Pas de chiffre dans le texte tant qu'aucune mesure ne le prouve.
- Canaux : bannière dans l'espace Face tant qu'il n'y a pas de vidéo ; un e-mail unique au lancement aux Faces existantes, puis à chaque nouvelle Face 3 jours après l'inscription si elle n'a toujours pas de vidéo. Pas de push.
- Après le lot 3 (nouvelles limites en ligne avant d'inciter).
