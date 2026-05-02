<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->has('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->has('action'), function ($query) use ($request) {
                $query->where('action', 'like', "%{$request->action}%");
            })
            ->when($request->has('entity_type'), function ($query) use ($request) {
                $query->where('entity_type', $request->entity_type);
            })
            ->when($request->has('entity_id'), function ($query) use ($request) {
                $query->where('entity_id', $request->entity_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'action' => 'required|string|max:255',
            'entity_type' => 'nullable|string|max:255',
            'entity_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'old_values' => 'nullable|array',
            'new_values' => 'nullable|array',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string',
        ]);

        $log = AuditLog::create($validated);

        return response()->json([
            'message' => 'Audit log created successfully',
            'data' => $log->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AuditLog $auditLog)
    {
        return response()->json([
            'data' => $auditLog->load('user')
        ]);
    }

    /**
     * Get audit logs for a specific entity.
     */
    public function forEntity(Request $request, $entityType, $entityId)
    {
        $logs = AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get audit logs by user.
     */
    public function byUser(Request $request, $userId)
    {
        $logs = AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }
}