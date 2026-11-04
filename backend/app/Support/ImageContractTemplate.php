<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte du contrat d'autorisation d'utilisation de l'image : source unique pour l'API,
 * le PDF et les écrans. Toute modification de texte exige une nouvelle VERSION.
 */
final class ImageContractTemplate
{
    public const VERSION = 'v1';

    /** Durées d'utilisation proposées, en années. */
    public const DUREES = [1, 3, 5, 10];

    /**
     * @return list<array{title: string, body: string, bullets?: list<string>}>
     */
    public static function articles(): array
    {
        return [
            [
                'title' => 'Objet',
                'body' => 'La Face autorise le Producteur à fixer, reproduire, diffuser et exploiter son image, sa voix et sa prestation réalisées pour le Projet, dans les limites des conditions particulières.',
            ],
            [
                'title' => 'Étendue',
                'body' => 'L\'autorisation vaut pour la finalité, les supports, le territoire et la durée indiqués aux conditions particulières. Tout autre usage exige un nouvel accord écrit de la Face.',
            ],
            [
                'title' => 'Prise d\'effet',
                'body' => 'Le contrat lie les parties dès la seconde signature. Le Producteur ne peut exploiter l\'image de la Face qu\'à la prise d\'effet indiquée aux conditions particulières, soit après le paiement de la Face ou, pour un produit seul, sa réception. Le contrat prend fin sans effet si le booking ou la mission est annulé avant cette date.',
            ],
            [
                'title' => 'Usages exclus',
                'body' => 'Sauf accord écrit distinct, le Producteur ne peut pas utiliser l\'image de la Face :',
                'bullets' => [
                    'pour un contenu politique, religieux, pornographique ou diffamatoire ;',
                    'pour un produit ou un projet autre que le Projet ;',
                    'pour générer ou modifier son visage, son corps ou sa voix par intelligence artificielle, ou pour entraîner un modèle d\'intelligence artificielle ;',
                    'd\'une manière qui porte atteinte à sa dignité ou à sa réputation.',
                ],
            ],
            [
                'title' => 'Montage',
                'body' => 'Le Producteur peut monter, recadrer, sous-titrer et adapter les images pour le Projet, sans en dénaturer le sens.',
            ],
            [
                'title' => 'Contrepartie',
                'body' => 'La contrepartie indiquée aux conditions particulières rémunère la prestation et l\'autorisation pour toute la durée prévue. Aucune somme supplémentaire n\'est due à la Face pour les usages autorisés.',
            ],
            [
                'title' => 'Garanties de la Face',
                'body' => 'La Face garantit qu\'elle a la capacité de signer, ou que son parent ou tuteur signe pour elle si elle a moins de 18 ans, et qu\'aucun engagement exclusif ne l\'empêche d\'accepter ce contrat.',
            ],
            [
                'title' => 'Obligations du Producteur',
                'body' => 'Le Producteur respecte les limites du contrat et conserve seul la responsabilité des contenus qu\'il publie. À la fin de la durée, il cesse toute nouvelle diffusion et retire les contenus sous son contrôle dans un délai de 30 jours. Il n\'a pas à retirer une publication déjà partagée par des tiers.',
            ],
            [
                'title' => 'Retrait et fin anticipée',
                'body' => 'En cas d\'usage hors contrat, la Face met le Producteur en demeure par écrit. Sans correction sous 7 jours, elle peut mettre fin à l\'autorisation, sans préjudice de ses dommages et intérêts.',
            ],
            [
                'title' => 'Rôle de WeAct',
                'body' => 'WeAct met à disposition la plateforme, ce modèle et l\'archivage de la signature. WeAct n\'est pas partie au contrat et ne contrôle pas l\'usage de l\'image par le Producteur.',
            ],
            [
                'title' => 'Données personnelles',
                'body' => 'Chaque partie traite les données de l\'autre uniquement pour exécuter ce contrat. WeAct conserve le contrat signé et ses preuves pendant la durée de l\'autorisation, puis 5 ans.',
            ],
            [
                'title' => 'Droit applicable et litiges',
                'body' => 'Le contrat est régi par le droit béninois. Les parties cherchent d\'abord un accord amiable. À défaut, les tribunaux de Cotonou sont compétents.',
            ],
            [
                'title' => 'Signature électronique',
                'body' => 'Les parties conviennent de signer électroniquement sur WeAct et reconnaissent à cette signature la même valeur qu\'une signature manuscrite, sous réserve de la loi. Chaque partie reçoit une copie du contrat signé.',
            ],
        ];
    }
}
