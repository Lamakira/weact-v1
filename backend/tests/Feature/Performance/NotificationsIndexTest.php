<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PERF-B9: the notifications index sorts by created_at per user.
 */
class NotificationsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_have_a_user_created_at_index(): void
    {
        $columns = collect(Schema::getIndexes('notifications'))
            ->pluck('columns')
            ->map(fn (array $c): string => implode(',', $c))
            ->all();

        $this->assertContains('user_id,created_at', $columns);
        $this->assertContains('user_id,read_at', $columns, 'The existing index stays in place.');
    }
}
