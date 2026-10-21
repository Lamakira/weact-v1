<?php

namespace Tests\Unit;

use App\Enums\FaceCategory;
use App\Enums\FaceGender;
use App\Models\Face;
use Tests\TestCase;

/**
 * Lot F3 : les libellés de catégorie suivent le sexe de la Face quand il est connu.
 */
class FaceCategoryGenderLabelTest extends TestCase
{
    public function test_label_sans_sexe_garde_la_forme_actuelle(): void
    {
        $this->assertSame('Acteur', FaceCategory::ACTEUR->label());
        $this->assertSame('Créateur de contenu', FaceCategory::CREATEUR->label());
        $this->assertSame('Acteur', FaceCategory::ACTEUR->label(null));
    }

    public function test_label_feminin_pour_une_face_femme(): void
    {
        $this->assertSame('Actrice', FaceCategory::ACTEUR->label(FaceGender::FEMME));
        $this->assertSame('Influenceuse', FaceCategory::INFLUENCEUR->label(FaceGender::FEMME));
        $this->assertSame('Créatrice de contenu', FaceCategory::CREATEUR->label(FaceGender::FEMME));
        $this->assertSame('Figurante', FaceCategory::FIGURANT->label(FaceGender::FEMME));
    }

    public function test_labels_invariables_et_autres_sexes(): void
    {
        $this->assertSame('Mannequin', FaceCategory::MANNEQUIN->label(FaceGender::FEMME));
        $this->assertSame('Égérie', FaceCategory::EGERIE->label(FaceGender::FEMME));
        $this->assertSame('Acteur', FaceCategory::ACTEUR->label(FaceGender::HOMME));
        $this->assertSame('Acteur', FaceCategory::ACTEUR->label(FaceGender::AUTRE));
    }

    public function test_categories_with_labels_accorde_selon_le_sexe_sans_changer_les_valeurs(): void
    {
        $face = (new Face)->forceFill([
            'categories' => ['acteur', 'createur', 'mannequin'],
            'sexe' => FaceGender::FEMME,
        ]);

        $this->assertSame([
            ['value' => 'acteur', 'label' => 'Actrice'],
            ['value' => 'createur', 'label' => 'Créatrice de contenu'],
            ['value' => 'mannequin', 'label' => 'Mannequin'],
        ], $face->categoriesWithLabels());

        $inconnu = (new Face)->forceFill(['categories' => ['acteur'], 'sexe' => null]);
        $this->assertSame([['value' => 'acteur', 'label' => 'Acteur']], $inconnu->categoriesWithLabels());
    }
}
