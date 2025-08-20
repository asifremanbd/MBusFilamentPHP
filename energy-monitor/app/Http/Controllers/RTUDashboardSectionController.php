<?php

namespace App\Http\Controllers;

use App\Services\RTUDashboardSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RTUDashboardSectionController extends Controller
{
    public function __construct(
        private RTUDashboardSectionService $sectionService
    ) {}

    /**
     * Get user's section configuration
     */
    public function getSections(): JsonResponse
    {
        $user = Auth::user();
        $sections = $this->sectionService->getSectionConfiguration($user);

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Update section collapse state
     */
    public function updateSectionState(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'section_name' => 'required|string|max:50',
                'is_collapsed' => 'required|boolean',
                'display_order' => 'nullable|integer|min:0',
            ]);

            $user = Auth::user();
            $success = $this->sectionService->updateSectionState(
                $user,
                $validated['section_name'],
                $validated['is_collapsed'],
                $validated['display_order'] ?? null
            );

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid section name',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Section state updated successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update section state',
            ], 500);
        }
    }

    /**
     * Reset sections to default state
     */
    public function resetSections(): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->sectionService->resetToDefaults($user);

            return response()->json([
                'success' => true,
                'message' => 'Sections reset to defaults',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset sections',
            ], 500);
        }
    }
}
