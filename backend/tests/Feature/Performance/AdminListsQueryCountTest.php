<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B1: admin Face/Producer listings do not query ratings per row.
 */
class AdminListsQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = Admin::factory()->create()->createToken('admin-token', ['2fa'])->plainTextToken;
    }

    private function queryCount(string $url): int
    {
        // Warm-up: the first request of a token writes last_used_at (unrelated to the lists).
        $this->withToken($this->adminToken)->getJson($url)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($this->adminToken)->getJson($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_admin_faces_index_query_count_is_constant(): void
    {
        Face::factory()->count(2)->withActiveUser()->create();
        $small = $this->queryCount('/api/v1/admin/faces');

        Face::factory()->count(8)->withActiveUser()->create();
        $large = $this->queryCount('/api/v1/admin/faces');

        $this->assertSame($small, $large, "Admin faces queries grew: {$small} -> {$large}");
    }

    public function test_admin_producers_index_query_count_is_constant(): void
    {
        $this->makeProducers(2);
        $small = $this->queryCount('/api/v1/admin/producers');

        $this->makeProducers(8);
        $large = $this->queryCount('/api/v1/admin/producers');

        $this->assertSame($small, $large, "Admin producers queries grew: {$small} -> {$large}");
    }

    private function makeProducers(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $producer = Producer::factory()->create();
            User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);
        }
    }
}
