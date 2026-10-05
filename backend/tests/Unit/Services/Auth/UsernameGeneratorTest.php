<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Face;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_concatenates_and_lowercases_the_name(): void
    {
        $this->assertSame('johndoe', $this->generator->generate('John', 'Doe'));
    }

    public function test_a_base_shorter_than_the_minimum_falls_back_to_membre(): void
    {
        $this->assertSame('membre', $this->generator->generate('A', 'B'));
        $this->assertSame('membre', $this->generator->generate('', 'Li'));
        $this->assertSame('abc', $this->generator->generate('A', 'Bc'));
        $this->assertGreaterThanOrEqual(UsernameGenerator::MIN_LENGTH, strlen($this->generator->generateWithRandomSuffix('A', 'B')));
    }

    public function test_it_folds_accents_and_strips_punctuation(): void
    {
        $this->assertSame('leagbedo', $this->generator->generate('Léa', 'Gbèdo'));
        $this->assertSame('marieclairendjamena', $this->generator->generate('Marie-Claire', "N'Djamena"));
    }

    public function test_it_falls_back_when_the_name_yields_no_latin_characters(): void
    {
        $this->assertSame('membre', $this->generator->generate('张', '伟'));
        $this->assertSame('membre', $this->generator->generate('', ''));
    }

    public function test_it_appends_a_counter_on_collision(): void
    {
        Face::create(['nom' => 'Doe', 'prenom' => 'John', 'username' => 'johndoe']);
        $this->assertSame('johndoe2', $this->generator->generate('John', 'Doe'));

        Face::create(['nom' => 'Doe', 'prenom' => 'John', 'username' => 'johndoe2']);
        $this->assertSame('johndoe3', $this->generator->generate('John', 'Doe'));
    }

    public function test_it_never_exceeds_the_column_length(): void
    {
        $long = str_repeat('a', 60);

        $username = $this->generator->generate($long, $long);

        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($username));
        $this->assertSame(str_repeat('a', 46), $username);
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
        $this->assertSame('admin2', $this->generator->generate('ad', 'min'));
    }

    public function test_random_suffix_variant_stays_within_the_column_length(): void
    {
        $username = $this->generator->generateWithRandomSuffix(str_repeat('a', 60), 'Doe');

        $this->assertLessThanOrEqual(UsernameGenerator::MAX_LENGTH, strlen($username));
        $this->assertStringStartsWith(str_repeat('a', 46), $username);
    }

    public function test_random_suffix_variant_produces_distinct_values(): void
    {
        $first = $this->generator->generateWithRandomSuffix('John', 'Doe');
        $second = $this->generator->generateWithRandomSuffix('John', 'Doe');

        $this->assertStringStartsWith('johndoe', $first);
        $this->assertNotSame($first, $second);
    }
}
