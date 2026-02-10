<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * List all admins with pagination.
     */
    public function index(): JsonResponse
    {
        $admins = Admin::orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => AdminResource::collection($admins),
            'meta' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
            ],
        ]);
    }

    /**
     * Create a new admin account.
     */
    public function store(CreateAdminRequest $request): JsonResponse
    {
        $admin = Admin::create($request->validated());

        return response()->json([
            'data' => new AdminResource($admin),
            'message' => 'Administrateur créé avec succès',
        ], 201);
    }
}
