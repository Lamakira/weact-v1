<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Face;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsernameGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private UsernameGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new UsernameGenerator;
    }

    public function test_it_uses_the_first_name_and_the_initial_of_the_last_name(): void
    {
        $this->assertSame('johnd', $this->generator->generate('John', 'Doe'));
        $this->assertSame('aichak', $this->generator->generate('Aïcha', 'Kouassi'));
    }

    public function test_a_generated_handle_never_contains_the_last_name(): void
    {
        $names = [
            ['Aïcha', 'Kouassi'],
            ['Marie-Claire', "N'Djamena"],
            ['Jean', 'De la Croix'],
            ['Éloïse', 'Ouédraogo-Sawadogo'],
            ['Koffi', 'Koffi-Mensah'],
        ];

        foreach ($names as [$prenom, $nom]) {
            $handle = $this->generator->generate($prenom, $nom);
            $slug = Str::of($nom)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

            $this->assertStringNotContainsString($slug, $handle, "{$prenom} {$nom} => {$handle}");
            $this->assertStringNotContainsString($slug, $this->generator->generateWithRandomSuffix($prenom, $nom));
        }
    }

    public function test_compound_and_accented_last_names_use_a_single_ascii_initial(): void
    {
        $this->assertSame('marieclaire'.'n', $this->generator->generate('Marie-Claire', "N'Djamena"));
        $this->assertSame('jeand', $this->generator->generate('Jean', 'De la Croix'));
        $this->assertSame('eloiseo', $this->generator->generate('Éloïse', 'Ouédraogo-Sawadogo'));
        $this->assertSame('eloisee', $this->generator->generate('Éloïse', 'Éla'));
    }

    public function test_a_base_shorter_than_the_minimum_falls_back_to_membre(): void
    {
        $this->assertSame('membre', $this->generator->generate('A', 'B'));
        $this->assertSame('membre', $this->generator->generate('', 'Li'));
        $this->assertSame('abc', $this->generator->generate('Ab', 'Cd'));
        $this->assertGreaterThanOrEqual(UsernameGenerator::MIN_LENGTH, strlen($this->generator->generateWithRandomSuffix('A', 'B')));
    }

    public function test_it_folds_accents_and_strips_punctuation(): void
    {
        $this->assertSame('leag', $this->generator->generate('Léa', 'Gbèdo'));
        $this->assertSame('marieclairen', $this->generator->generate('Marie-Claire', "N'Djamena"));
    }

    public function test_it_falls_back_when_the_name_yields_no_latin_characters(): void
    {
        $this->assertSame('membre', $this->generator->generate('张', '伟'));
        $this->assertSame('membre', $this->generator->generate('', ''));
    }

    public function test_it_appends_a_counter_on_collision(): void
    {
        Face::create(['nom' => 'Doe', 'prenom' => 'John', 'username' => 'johnd']);
        $this->assertSame('johnd2', $this->generator->generate('John', 'Doe'));

        // Another last name with the same initial collides too.
        $this->assertSame('johnd2', $this->generator->generate('John', 'Dupont'));

        Face::create(['nom' => 'Doe', 'prenom' => 'John', 'username' => 'johnd2']);
        $this->assertSame('johnd3', $this->generator->generate('John', 'Doe'));
    }

    public function test_it_never_exceeds_the_column_length(): void
    {
        $long = str_repeat('a', 60);

        $username = $this->generator->generate($long, $long);

        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($username));
        $this->assertSame(str_repeat('a', 46), $username);

        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($this->generator->generateWithRandomSuffix($long, $long)));
    }

    public function test_the_counter_still_fits_within_the_column_length(): void
    {
        $base = str_repeat('a', 46);
        Face::create(['nom' => 'A', 'prenom' => 'A', 'username' => $base]);

        $username = $this->generator->generate(str_repeat('a', 60), '');

        $this->assertSame($base.'2', $username);
        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($username));
    }

    public function test_it_skips_reserved_route_segments(): void
    {
        // "admin" is a reserved word: the deterministic ladder must move past it.
        $this->assertSame('admin2', $this->generator->generate('admi', 'n'));
    }

    public function test_random_suffix_variant_stays_within_the_column_length(): void
    {
        $username = $this->generator->generateWithRandomSuffix(str_repeat('a', 60), 'Doe');

        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($username));
        $this->assertStringStartsWith(str_repeat('a', 45).'d', $username);
    }

    public function test_random_suffix_variant_produces_distinct_values(): void
    {
        $first = $this->generator->generateWithRandomSuffix('John', 'Doe');
        $second = $this->generator->generateWithRandomSuffix('John', 'Doe');

        $this->assertStringStartsWith('johnd', $first);
        $this->assertNotSame($first, $second);
    }
}
