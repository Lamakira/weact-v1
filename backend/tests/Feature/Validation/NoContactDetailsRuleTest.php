<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Rules\NoContactDetails;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lot B12 — règle réutilisable : aucun numéro de téléphone, e-mail, @pseudo ni lien
 * de réseau social dans un texte libre échangé avant paiement (brief de booking,
 * motivation de candidature). Le chat reste fermé avant paiement.
 */
class NoContactDetailsRuleTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function rejectedTexts(): array
    {
        return [
            'téléphone par paires' => ['Appelez-moi au 97 12 34 56 pour en parler'],
            'téléphone international' => ['Mon numéro : +229 01 97 12 34 56'],
            'téléphone préfixe 00' => ['Joignable au 0022997123456'],
            'téléphone collé' => ['whatsapp 0197123456'],
            'téléphone avec points' => ['tel 97.12.34.56'],
            'téléphone avec tirets' => ['01-97-12-34-56'],
            'chiffres espacés' => ['9 7 1 2 3 4 5 6'],
            'chiffres pleine chasse' => ['９７１２３４５６'],
            'e-mail' => ['Écrivez-moi à awa.diallo@gmail.com'],
            'e-mail avec espaces' => ['awa.diallo @ gmail.com'],
            'e-mail (at)' => ['awa.diallo(at)gmail.com'],
            'e-mail arobase pleine chasse' => ['awa＠gmail.com'],
            'pseudo' => ['Suivez-moi sur Insta @awa.officiel'],
            'pseudo arobase pleine chasse' => ['＠awa_off'],
            'pseudo en début de texte' => ['@awadiallo'],
            'lien https' => ['Mon book : https://monportfolio.bj/awa'],
            'lien www' => ['Voir www.awa-diallo.com'],
            'instagram sans protocole' => ['instagram.com/awa.officiel'],
            'whatsapp' => ['wa.me/22997123456'],
            'telegram' => ['t.me/awadiallo'],
            'tiktok' => ['vm.tiktok.com/ZM123abc'],
            'facebook' => ['facebook.com/awa.diallo'],
            'youtube court' => ['youtu.be/abc123'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedTexts(): array
    {
        return [
            'brief ordinaire' => ['Tournage d\'une publicité de 30 secondes pour une marque de jus, prévoir deux tenues.'],
            'date avec barres' => ['Tournage le 12/10/2026 à Cotonou'],
            'date avec points' => ['Tournage le 12.10.2026'],
            'date avec tirets' => ['Disponible le 12-10-2026'],
            'montant' => ['Budget de 150 000 FCFA'],
            'gros montant groupé' => ['Budget global de 1 500 000 FCFA'],
            'heure' => ['Rendez-vous à 8h30, fin vers 17h'],
            'mention de la messagerie' => ['On en parle sur la messagerie WeAct après le paiement'],
            'mot e-mail' => ['Je vous enverrai les détails par e-mail via WeAct'],
            'arobase isolé' => ['Prix @ négocier'],
            'expérience' => ['J\'ai 10 ans d\'expérience et 25 tournages'],
            'année' => ['Campagne 2026, saison 2'],
            'point final collé' => ['Merci.Bonne journée'],
            'vide' => [''],
        ];
    }

    #[DataProvider('rejectedTexts')]
    public function test_contact_details_are_rejected(string $text): void
    {
        $validator = Validator::make(['brief' => $text], ['brief' => [new NoContactDetails]]);

        $this->assertTrue($validator->fails(), "Texte accepté à tort : {$text}");
        $this->assertSame(NoContactDetails::MESSAGE, $validator->errors()->first('brief'));
    }

    #[DataProvider('acceptedTexts')]
    public function test_ordinary_texts_are_accepted(string $text): void
    {
        $validator = Validator::make(['brief' => $text], ['brief' => ['nullable', 'string', new NoContactDetails]]);

        $this->assertFalse($validator->fails(), "Texte refusé à tort : {$text}");
    }
}
