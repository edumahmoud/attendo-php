<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportResponse;
use App\Models\ReportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['reporter', 'subject']);

        if ($request->user()->role === 'student') {
            $query->where('reporter_id', $request->user()->id);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:academic,technical,behavioral,other',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'related_user_id' => 'nullable|uuid|exists:users,id',
        ]);

        $report = Report::create([
            ...$validated,
            'reporter_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'report' => $report,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $report = Report::with(['reporter', 'responses.responder', 'messages'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }

    public function respond(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'nullable|string',
            'content' => 'nullable|string',
            'forwarded_to' => 'nullable|uuid|exists:users,id',
        ]);

        $response = ReportResponse::create([
            'report_id' => $id,
            'responder_id' => $request->user()->id,
            'action' => $validated['action'] ?? null,
            'content' => $validated['content'] ?? null,
            'forwarded_to' => $validated['forwarded_to'] ?? null,
        ]);

        // Update report status if action is taken
        if (!empty($validated['action'])) {
            Report::findOrFail($id)->update(['status' => 'resolved']);
        }

        return response()->json([
            'success' => true,
            'response' => $response,
        ], 201);
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'message_type' => 'nullable|in:message,file,system',
        ]);

        $message = ReportMessage::create([
            'report_id' => $id,
            'sender_id' => $request->user()->id,
            'content' => $validated['content'],
            'message_type' => $validated['message_type'] ?? 'message',
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }
}