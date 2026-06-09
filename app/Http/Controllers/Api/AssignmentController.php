<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Get all assignments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Assignment::with(['subject', 'creator']);

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        $assignments = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Create new assignment
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|uuid|exists:subjects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'max_score' => 'nullable|integer|min:1',
            'allow_late_submission' => 'nullable|boolean',
        ]);

        $assignment = Assignment::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'assignment' => $assignment,
        ], 201);
    }

    /**
     * Get single assignment
     */
    public function show(string $id): JsonResponse
    {
        $assignment = Assignment::with(['subject', 'creator', 'submissions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'assignment' => $assignment,
        ]);
    }

    /**
     * Update assignment
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'max_score' => 'nullable|integer|min:1',
            'allow_late_submission' => 'nullable|boolean',
        ]);

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'assignment' => $assignment,
        ]);
    }

    /**
     * Delete assignment
     */
    public function destroy(string $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully',
        ]);
    }

    /**
     * Submit assignment
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'nullable|string',
            'file_url' => 'nullable|string|url',
        ]);

        $submission = Submission::create([
            'assignment_id' => $id,
            'student_id' => $request->user()->id,
            'content' => $validated['content'] ?? null,
            'file_url' => $validated['file_url'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ], 201);
    }

    /**
     * Grade submission
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => 'required|uuid|exists:submissions,id',
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
            'status' => 'required|in:graded,needs_review',
        ]);

        $submission = Submission::findOrFail($validated['submission_id']);
        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => $validated['status'],
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }
}