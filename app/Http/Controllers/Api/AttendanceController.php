<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Get all attendance sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $query = AttendanceSession::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Create new attendance session
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|uuid|exists:subjects,id',
            'title' => 'required|string|max:255',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'qr_code_data' => 'nullable|string',
        ]);

        $session = AttendanceSession::create([
            'subject_id' => $validated['subject_id'],
            'title' => $validated['title'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'qr_code_data' => $validated['qr_code_data'] ?? bin2hex(random_bytes(16)),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'session' => $session,
        ], 201);
    }

    /**
     * End attendance session
     */
    public function endSession(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::findOrFail($id);
        $session->update(['status' => 'ended']);

        return response()->json([
            'success' => true,
            'session' => $session,
        ]);
    }

    /**
     * Check in to attendance session
     */
    public function checkin(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|uuid|exists:users,id',
        ]);

        $session = AttendanceSession::findOrFail($id);

        if ($session->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => 'Session is not active',
            ], 400);
        }

        $record = AttendanceRecord::create([
            'session_id' => $id,
            'student_id' => $request->student_id,
            'status' => 'present',
            'checked_in_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'record' => $record,
        ], 201);
    }

    /**
     * Get attendance records
     */
    public function records(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with(['session', 'student']);

        if ($request->has('subject_id')) {
            $query->whereHas('session', fn($q) => $q->where('subject_id', $request->subject_id));
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $records = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'records' => $records,
        ]);
    }
}