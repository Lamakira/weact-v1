<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrunePublicResponseCacheCommandTest extends TestCase
{
    private string $base;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/weact-prune-'.uniqid();
        $this->dir = $this->base.'/public-responses';
        File::makeDirectory($this->dir.'/ab/cd', 0777, true);
        File::makeDirectory($this->dir.'/ef/01', 0777, true);

        config([
            'cache.public_response_ttl' => 60,
            'cache.public_response_store' => 'public_file',
            'cache.stores.public_file.path' => $this->dir,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_deletes_old_files_keeps_fresh_ones_and_removes_empty_directories(): void
    {
        $old = $this->dir.'/ab/cd/old';
        $fresh = $this->dir.'/ef/01/fresh';
        file_put_contents($old, 'x');
        file_put_contents($fresh, 'x');
        touch($old, time() - 3600);

        $this->artisan('cache:prune-public-responses')
            ->expectsOutputToContain('Pruned 1 ')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertDirectoryDoesNotExist($this->dir.'/ab');
        $this->assertFileExists($fresh);
        $this->assertDirectoryExists($this->dir.'/ef/01');
    }

    public function test_file_younger_than_the_threshold_is_kept(): void
    {
        $file = $this->dir.'/ab/cd/recent';
        file_put_contents($file, 'x');
        touch($file, time() - 300);

        $this->artisan('cache:prune-public-responses')->assertSuccessful();

        $this->assertFileExists($file);
    }

    public function test_is_a_noop_for_a_non_file_store(): void
    {
        config(['cache.public_response_store' => 'array']);
        $old = $this->dir.'/ab/cd/old';
        file_put_contents($old, 'x');
        touch($old, time() - 3600);

        $this->artisan('cache:prune-public-responses')
            ->expectsOutputToContain('not file-backed')
            ->assertSuccessful();

        $this->assertFileExists($old);
    }

    public function test_refuses_a_directory_not_named_public_responses(): void
    {
        $other = $this->base.'/other';
        File::makeDirectory($other, 0777, true);
        $file = $other.'/old';
        file_put_contents($file, 'x');
        touch($file, time() - 3600);
        config(['cache.stores.public_file.path' => $other]);

        $this->artisan('cache:prune-public-responses')->assertFailed();

        $this->assertFileExists($file);
    }

    public function test_is_scheduled_every_ten_minutes(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())->first(
            fn ($e) => str_contains($e->command ?? '', 'cache:prune-public-responses'),
        );

        $this->assertNotNull($event, 'PrunePublicResponseCacheCommand is not scheduled.');
        $this->assertSame('*/10 * * * *', $event->expression);
    }
}
