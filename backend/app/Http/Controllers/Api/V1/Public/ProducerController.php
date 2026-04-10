<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProducerResource;
use App\Models\Producer;
use Illuminate\Http\JsonResponse;

class ProducerController extends Controller
{
    /**
     * Display a public Producer profile.
     *
     * No authentication required - this is a PUBLIC endpoint.
     */
    public function show(Producer $producer): JsonResponse
    {
        abort_unless((bool) data_get($producer->loadMissing('user'), 'user.is_active'), 404);

        return response()->json([
            'data' => new PublicProducerResource($producer),
        ]);
    }
}
