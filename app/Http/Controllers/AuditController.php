<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\ActionLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->auditService->paginateLogs($request);

        $logs = $paginator->getCollection()->map(fn (ActionLog $log) => new AuditLogResource($log));

        return response()->json([
            'logs' => $logs,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function orderLogs(Request $request, int $orderId): JsonResponse
    {
        $logs = $this->auditService->getOrderLogs($orderId);

        return response()->json(['logs' => AuditLogResource::collection($logs)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $log = $this->auditService->getLog($id);

        return response()->json(['log' => new AuditLogResource($log)]);
    }
}
