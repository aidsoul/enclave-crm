<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\LeadService;
use App\Support\StrHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lead::with(['site:id,name,domain', 'customer:id,name,phone,email', 'manager:id,system_name', 'convertedOrder:id,status']);

        if ($request->filled('site_id')) {
            $query->forSite((int) $request->input('site_id'));
        }

        if ($request->filled('customer_id')) {
            $query->forCustomer((int) $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $query->forStatus($request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $escaped = StrHelper::escapeLike($search);
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($escaped, $digits) {
                $q->where('name', 'ilike', "%{$escaped}%")
                    ->orWhere('email', 'ilike', "%{$escaped}%");
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $leads = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => LeadResource::collection($leads->getCollection()),
            'current_page' => $leads->currentPage(),
            'last_page' => $leads->lastPage(),
            'per_page' => $leads->perPage(),
            'total' => $leads->total(),
        ]);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['site:id,name,domain', 'customer', 'manager:id,system_name', 'convertedOrder:id,status,total_price']);

        return response()->json(new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['status'])) {
            $lead->status = LeadStatus::from($validated['status']);
        }
        if (array_key_exists('manager_id', $validated)) {
            $lead->manager_id = $validated['manager_id'];
        }
        if (array_key_exists('message', $validated)) {
            $lead->message = $validated['message'];
        }

        $lead->save();

        return response()->json([
            'success' => true,
            'lead' => new LeadResource($lead->fresh(['site:id,name,domain', 'customer', 'manager:id,system_name'])),
        ]);
    }

    public function convertToOrder(Lead $lead): JsonResponse
    {
        $result = $this->leadService->convertToOrder($lead, auth()->id());

        return response()->json($result);
    }

    public function customerLeads(Customer $customer): JsonResponse
    {
        $leads = Lead::where('customer_id', $customer->id)
            ->with(['site:id,name,domain', 'manager:id,system_name'])
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $totalCount = Lead::where('customer_id', $customer->id)->count();

        return response()->json([
            'leads' => LeadResource::collection($leads),
            'total_count' => $totalCount,
        ]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isManager() && $lead->manager_id !== null && (int) $lead->manager_id !== (int) $user->id) {
            abort(403, 'Нельзя удалить чужую заявку.');
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Заявка удалена',
        ]);
    }
}
