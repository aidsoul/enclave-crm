<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerFilter $customerFilter
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->customerFilter->apply($request);

        $perPage = min(max((int) ($request->query('per_page') ?? $request->query('limit', 20)), 1), 100);
        $customers = $query->paginate($perPage);

        return response()->json([
            'customers' => CustomerResource::collection($customers),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount('orders');
        $customer->loadSum(['orders as ltv' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total_price');
        $customer->load([
            'site',
            'orders' => function ($q) {
                $q->with(['site', 'items'])->orderByDesc('created_at')->limit(5);
            },
        ]);

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json([
            'customer' => new CustomerResource($customer),
            'message' => 'Данные клиента обновлены',
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $phone = $request->query('phone');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;

        $customer = $this->customerFilter->lookup($phone, $siteId);

        return response()->json([
            'customer' => $customer ? new CustomerResource($customer) : null,
        ]);
    }
}
