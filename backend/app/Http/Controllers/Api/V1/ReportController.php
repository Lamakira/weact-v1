<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Store a content report (Art. 498 - Notification de contenus illicites).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string', 'in:face,mission'],
            'reportable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:contenu_inapproprie,usurpation_identite,harcelement,fraude,autre'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'reportable_type.required' => 'Le type de contenu est requis.',
            'reportable_type.in' => 'Type de contenu invalide.',
            'reportable_id.required' => 'L\'identifiant du contenu est requis.',
            'reason.required' => 'Le motif du signalement est requis.',
            'reason.in' => 'Motif de signalement invalide.',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères.',
        ]);

        // Map type to model class and verify entity exists
        $typeMap = [
            'face' => \App\Models\Face::class,
            'mission' => \App\Models\Mission::class,
        ];

        $modelClass = $typeMap[$validated['reportable_type']];
        if (!$modelClass::where('id', $validated['reportable_id'])->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Le contenu signalé n\'existe pas.',
                ],
            ], 404);
        }

        // Prevent duplicate reports from the same user on the same target
        $existingReport = Report::where('reporter_id', $request->user()->id)
            ->where('reportable_type', $modelClass)
            ->where('reportable_id', $validated['reportable_id'])
            ->exists();

        if ($existingReport) {
            return response()->json([
                'message' => 'Vous avez déjà signalé ce contenu. Nous l\'examinerons dans les meilleurs délais.',
            ], 200);
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'data' => ['id' => $report->id],
            'message' => 'Votre signalement a été enregistré. Nous l\'examinerons dans les meilleurs délais.',
        ], 201);
    }
}
