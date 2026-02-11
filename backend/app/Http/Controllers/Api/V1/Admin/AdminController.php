<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

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
            'data' => new AdminResource($admin->fresh()),
            'message' => 'Administrateur créé avec succès',
        ], 201);
    }

    /**
     * Show a single admin.
     */
    public function show(Admin $admin): JsonResponse
    {
        return response()->json([
            'data' => new AdminResource($admin),
            'message' => 'Détails de l\'administrateur récupérés avec succès',
        ]);
    }

    /**
     * Update an admin's fields (name, email, role).
     */
    public function update(UpdateAdminRequest $request, Admin $admin): JsonResponse
    {
        $validated = $request->validated();

        // Self-demotion prevention
        if ($admin->id === $request->user()->id
            && isset($validated['role'])
            && $validated['role'] !== AdminRole::SuperAdmin->value) {
            return response()->json([
                'error' => [
                    'code' => 'self_demotion',
                    'message' => 'Impossible de modifier votre propre rôle de super-administrateur.',
                ],
            ], 422);
        }

        $admin->update($validated);

        return response()->json([
            'data' => new AdminResource($admin->fresh()),
            'message' => 'Administrateur mis à jour avec succès',
        ]);
    }

    /**
     * Delete an admin account.
     */
    public function destroy(Request $request, Admin $admin): JsonResponse
    {
        // Self-deletion prevention
        if ($admin->id === $request->user()->id) {
            return response()->json([
                'error' => [
                    'code' => 'self_deletion',
                    'message' => 'Impossible de supprimer votre propre compte administrateur.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($admin) {
            $admin->tokens()->delete();
            $admin->delete();
        });

        return response()->json([
            'message' => 'Administrateur supprimé avec succès',
        ]);
    }

    /**
     * Send a password reset link to the given admin.
     */
    public function sendPasswordReset(Admin $admin): JsonResponse
    {
        $status = Password::broker('admins')->sendResetLink(
            ['email' => $admin->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'data' => null,
                'message' => "Lien de réinitialisation envoyé à {$admin->email}",
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'error' => [
                    'message' => 'Veuillez patienter avant de réessayer',
                    'code' => 'THROTTLED',
                ],
            ], 422);
        }

        return response()->json([
            'error' => [
                'message' => "Impossible d'envoyer le lien de réinitialisation",
                'code' => 'SEND_FAILED',
            ],
        ], 500);
    }
}
