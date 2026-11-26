<?php

namespace App\Http\Controllers;

use App\Dto\Order\CreateOrderDto;
use App\Dto\Order\UpdateOrderDto;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\BulkAssignCourierRequest;
use App\Http\Requests\BulkAssignManagerRequest;
use App\Http\Requests\BulkOrdersRequest;
use App\Http\Requests\StatusOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UploadDeliveryProofRequest;
use App\Http\Requests\UploadDeliverySignatureRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\ActionLogger;
use App\Services\Orders\BulkOrderService;
use App\Services\Orders\OrderAssigner;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderFilter;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Orders\OrderUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        protected OrderCreator $orderCreator,
        protected OrderUpdater $orderUpdater,
        protected OrderAssigner $orderAssigner,
        protected OrderStatusMachine $orderStatusMachine,
        protected OrderFilter $orderFilter,
        protected BulkOrderService $bulkOrderService,
        protected ActionLogger $actionLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->orderFilter->apply($request);

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        if ($request->has('cursor')) {
            $paginator = $query->cursorPaginate($perPage);

            $orders = $paginator->getCollection()->map(fn (Order $order) => new OrderResource($order));

            return response()->json([
                'orders' => $orders,
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ]);
        }

        if ($request->has('page') || $request->has('per_page')) {
            $paginator = $query->paginate($perPage);

            $orders = $paginator->getCollection()->map(fn (Order $order) => new OrderResource($order));

            return response()->json([
                'orders' => $orders,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]);
        }

        $orders = $query->limit(100)->get();

        return response()->json(['orders' => OrderResource::collection($orders)]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        return response()->json(['order' => new OrderResource($order->load(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']))]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dto = CreateOrderDto::fromArray($validated['site_id'], $validated, auth()->user()?->id);

        $order = $this->orderCreator->createFromDto($dto);
        $order->loadMissing(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

        event(new OrderCreated($order));

        return response()->json(['order' => new OrderResource($order)], 201);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $dto = UpdateOrderDto::fromArray($order->id, $request->validated(), (int) auth()->user()?->id);
        $updatedOrder = $this->orderUpdater->updateFromDto($dto);
        $updatedOrder->loadMissing(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

        return response()->json(['order' => new OrderResource($updatedOrder)]);
    }

    public function assign(AssignOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $courier = User::find($validated['delivery_courier_id']);

        if (! $courier || ! $courier->isCourier()) {
            return response()->json(['error' => 'User is not a courier'], 400);
        }

        try {
            $order = $this->orderAssigner->assignCourier($order->id, $validated['delivery_courier_id'], auth()->user()->id);

            return response()->json(['order' => new OrderResource($order)]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function status(StatusOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $updatedOrder = $this->orderStatusMachine->changeStatus(
            $order->id,
            OrderStatus::from($validated['status']),
            auth()->user()->id
        );

        return response()->json(['order' => new OrderResource($updatedOrder)]);
    }

    public function accept(Request $request, Order $order): JsonResponse
    {
        try {
            $order = $this->orderAssigner->acceptOrder($order->id, auth()->user()->id);

            return response()->json(['order' => new OrderResource($order)]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function atWarehouse(Request $request, Order $order): JsonResponse
    {
        $order = $this->orderStatusMachine->markAtWarehouse($order->id, auth()->user()->id);

        return response()->json(['order' => new OrderResource($order)]);
    }

    public function complete(Request $request, Order $order): JsonResponse
    {
        $user = auth()->user();
        if (! $user?->isOwner() && ! in_array(OrderStatus::COMPLETED, $order->status->getAvailableStatuses(), true)) {
            return response()->json([
                'message' => 'Некорректный переход статуса',
            ], 422);
        }
        if ($request->hasFile('signature')) {
            $request->validate([
                'signature' => ['file', 'image', 'mimes:png,webp', 'max:1024'],
            ]);

            $delivery = $order->delivery()->first();
            if (! $delivery) {
                $delivery = $order->delivery()->create([
                    'type' => DeliveryType::COURIER,
                    'user_id' => auth()->id(),
                ]);
            }

            $sigDir = storage_path('app/public/delivery-signatures');
            File::ensureDirectoryExists($sigDir, 0775);

            $sigFile = $request->file('signature');
            $sigExt = strtolower($sigFile->guessExtension() ?: 'png');
            if (! in_array($sigExt, ['png', 'webp'], true)) {
                $sigExt = 'png';
            }

            $sigFilename = 'sig_'.$order->id.'_'.Str::random(16).'.'.$sigExt;
            $sigPath = $sigFile->storeAs('delivery-signatures', $sigFilename, 'public');

            if (! $sigPath) {
                return response()->json([
                    'message' => 'Не удалось сохранить файл подписи на сервере',
                ], 500);
            }

            $oldSig = $delivery->signature_path;

            try {
                DB::transaction(function () use ($delivery, $sigPath) {
                    $delivery->update([
                        'signature_path' => $sigPath,
                        'signature_at' => now(),
                    ]);
                });
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($sigPath);
                throw $e;
            }

            if ($oldSig && $oldSig !== $sigPath) {
                Storage::disk('public')->delete($oldSig);
            }

            $this->actionLogger->logUpdate($order, 'delivery.signature', null, $delivery->signature_url, auth()->user());
        }

        if ($order->requiresClientSignature()) {
            $existingDelivery = $order->delivery()->first();
            if (! $existingDelivery || empty($existingDelivery->signature_path)) {
                return response()->json([
                    'message' => 'Для ценного отправления требуется подпись клиента',
                    'errors' => ['signature' => ['Для ценного отправления требуется подпись клиента']],
                ], 422);
            }
        }

        $oldPhotoPathsToDelete = [];
        $newlySavedPaths = [];

        if ($request->hasFile('photo') || $request->hasFile('photos')) {
            $request->validate([
                'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,heic,heif', 'max:10240'],
                'photos' => ['nullable', 'array', 'max:3'],
                'photos.*' => ['image', 'mimes:jpeg,png,jpg,webp,heic,heif', 'max:10240'],
            ]);

            $delivery = $order->delivery()->first();
            if (! $delivery) {
                $delivery = $order->delivery()->create([
                    'type' => DeliveryType::COURIER,
                    'user_id' => auth()->id(),
                ]);
            }

            $oldPhotoPathsToDelete = array_values(array_filter(array_unique(array_merge(
                is_array($delivery->photo_proof_paths) ? $delivery->photo_proof_paths : [],
                $delivery->photo_proof_path ? [$delivery->photo_proof_path] : []
            ))));

            $proofDir = storage_path('app/public/delivery-proofs');
            File::ensureDirectoryExists($proofDir, 0775);

            $uploadedFiles = [];
            if ($request->hasFile('photos')) {
                $uploadedFiles = (array) $request->file('photos');
            } elseif ($request->hasFile('photo')) {
                $uploadedFiles = [$request->file('photo')];
            }

            $savedPaths = [];
            foreach (array_slice($uploadedFiles, 0, 3) as $file) {
                $extension = strtolower($file->guessExtension() ?: 'jpg');
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
                    $extension = 'jpg';
                }
                $filename = 'proof_'.$order->id.'_'.Str::random(16).'.'.$extension;
                $path = $file->storeAs('delivery-proofs', $filename, 'public');
                if ($path) {
                    $savedPaths[] = $path;
                }
            }

            if (! empty($savedPaths)) {
                $newlySavedPaths = $savedPaths;
                try {
                    $delivery->update([
                        'photo_proof_paths' => $savedPaths,
                        'photo_proof_path' => $savedPaths[0],
                        'photo_proof_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    foreach ($savedPaths as $savedPath) {
                        Storage::disk('public')->delete($savedPath);
                    }
                    throw $e;
                }

                $this->actionLogger->logUpdate($order, 'delivery.photo_proof', null, $delivery->photo_proof_url, auth()->user());
            }
        }

        try {
            $order = $this->orderStatusMachine->markComplete($order->id, (int) auth()->user()->id);
        } catch (\InvalidArgumentException $e) {
            foreach ($newlySavedPaths as $savedPath) {
                Storage::disk('public')->delete($savedPath);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        foreach ($oldPhotoPathsToDelete as $oldPath) {
            if (! in_array($oldPath, $newlySavedPaths, true)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return response()->json(['order' => new OrderResource($order)]);
    }

    public function uploadProof(UploadDeliveryProofRequest $request, Order $order): JsonResponse
    {
        $savedPaths = [];

        try {
            return DB::transaction(function () use ($request, $order, &$savedPaths) {
                $delivery = $order->delivery()->lockForUpdate()->first();
                if (! $delivery) {
                    $delivery = $order->delivery()->create([
                        'type' => DeliveryType::COURIER,
                        'user_id' => auth()->id(),
                    ]);
                }

                $proofDir = storage_path('app/public/delivery-proofs');
                File::ensureDirectoryExists($proofDir, 0775);

                $existingPaths = array_values(array_filter(
                    is_array($delivery->photo_proof_paths) ? $delivery->photo_proof_paths : [],
                    fn ($p) => ! empty($p) && $p !== '0'
                ));
                if ($delivery->photo_proof_path && $delivery->photo_proof_path !== '0' && ! in_array($delivery->photo_proof_path, $existingPaths, true)) {
                    $existingPaths[] = $delivery->photo_proof_path;
                }

                if (count($existingPaths) >= 3) {
                    return response()->json([
                        'message' => 'Достигнут лимит фотофиксаций (максимум 3 фото)',
                        'errors' => [
                            'photo' => ['Уже загружено максимальное количество фотофиксаций (3 фото).'],
                        ],
                    ], 422);
                }

                $existingHashes = [];
                foreach ($existingPaths as $existingPath) {
                    $parsed = parse_url($existingPath, PHP_URL_PATH) ?? $existingPath;
                    $rel = str_starts_with($parsed, '/storage/') ? substr($parsed, 9) : ltrim($parsed, '/');
                    $fullPath = Storage::disk('public')->path($rel);
                    if (is_file($fullPath)) {
                        $hash = @sha1_file($fullPath);
                        if ($hash) {
                            $existingHashes[] = $hash;
                        }
                    }
                }

                $uploadedFiles = [];
                if ($request->hasFile('photos')) {
                    $uploadedFiles = (array) $request->file('photos');
                } elseif ($request->hasFile('photo')) {
                    $uploadedFiles = [$request->file('photo')];
                }

                $seenHashes = [];
                $duplicateCount = 0;
                foreach ($uploadedFiles as $file) {
                    if (count($existingPaths) + count($savedPaths) >= 3) {
                        break;
                    }

                    $realPath = $file->getRealPath();
                    $hash = $realPath && is_file($realPath) ? @sha1_file($realPath) : null;
                    if ($hash && (in_array($hash, $existingHashes, true) || in_array($hash, $seenHashes, true))) {
                        $duplicateCount++;

                        continue;
                    }
                    if ($hash) {
                        $seenHashes[] = $hash;
                    }

                    $extension = strtolower($file->guessExtension() ?: 'jpg');
                    if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
                        $extension = 'jpg';
                    }
                    $filename = 'proof_'.$order->id.'_'.Str::random(16).'.'.$extension;
                    $path = $file->storeAs('delivery-proofs', $filename, 'public');
                    if ($path) {
                        $savedPaths[] = $path;
                    }
                }

                if (empty($savedPaths)) {
                    if ($duplicateCount > 0) {
                        return response()->json([
                            'message' => 'Выбранное фото уже прикреплено к этому заказу (дубликат)',
                            'errors' => [
                                'photo' => ['Фотофиксация содержит дубликат уже существующего снимка.'],
                            ],
                        ], 422);
                    }

                    if (count($existingPaths) >= 3) {
                        return response()->json([
                            'message' => 'Достигнут лимит фотофиксаций (максимум 3 фото)',
                            'errors' => [
                                'photo' => ['Уже загружено максимальное количество фотофиксаций (3 фото).'],
                            ],
                        ], 422);
                    }

                    return response()->json([
                        'message' => 'Не удалось сохранить файл фотофиксации на сервере',
                    ], 500);
                }

                $mergedPaths = array_values(array_slice(array_merge($existingPaths, $savedPaths), 0, 3));

                $delivery->update([
                    'photo_proof_paths' => $mergedPaths,
                    'photo_proof_path' => $mergedPaths[0] ?? null,
                    'photo_proof_at' => now(),
                ]);

                $this->actionLogger->logUpdate($order, 'delivery.photo_proof', null, $delivery->photo_proof_url, auth()->user());

                $order->load(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

                return response()->json([
                    'message' => $duplicateCount > 0 ? 'Фотофиксация сохранена (дубликаты пропущены)' : 'Фотофиксация вручения сохранена',
                    'duplicates_skipped' => $duplicateCount,
                    'photo_proof_url' => $delivery->photo_proof_url,
                    'photo_proof_urls' => $delivery->photo_proof_urls,
                    'photo_proof_at' => $delivery->photo_proof_at?->toIso8601String(),
                    'order' => new OrderResource($order),
                ]);
            });
        } catch (\Throwable $e) {
            foreach ($savedPaths as $savedPath) {
                Storage::disk('public')->delete($savedPath);
            }
            throw $e;
        }
    }

    public function deleteProof(Request $request, Order $order): JsonResponse
    {
        $delivery = $order->delivery()->first();
        if ($delivery) {
            $specificPath = $request->input('path') ?? $request->query('path');
            if (is_string($specificPath)) {
                $specificPath = trim($specificPath);
                if ($specificPath === '' || $specificPath === '[object Object]') {
                    $specificPath = null;
                }
            } else {
                $specificPath = null;
            }
            $delivery->deletePhotoProofFile($specificPath);

            $this->actionLogger->logUpdate($order, 'delivery.photo_proof', 'deleted', null, auth()->user());
        }

        $order->load(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

        return response()->json([
            'message' => 'Фотофиксация вручения удалена',
            'photo_proof_urls' => $delivery?->photo_proof_urls ?? [],
            'order' => new OrderResource($order),
        ]);
    }

    public function uploadSignature(UploadDeliverySignatureRequest $request, Order $order): JsonResponse
    {
        $delivery = $order->delivery()->first();
        if (! $delivery) {
            $delivery = $order->delivery()->create([
                'type' => DeliveryType::COURIER,
                'user_id' => auth()->id(),
            ]);
        }

        $sigDir = storage_path('app/public/delivery-signatures');
        File::ensureDirectoryExists($sigDir, 0775);

        $file = $request->file('signature');
        $extension = strtolower($file->guessExtension() ?: 'png');
        if (! in_array($extension, ['png', 'webp'], true)) {
            $extension = 'png';
        }

        $filename = 'sig_'.$order->id.'_'.Str::random(16).'.'.$extension;
        $path = $file->storeAs('delivery-signatures', $filename, 'public');

        if (! $path) {
            return response()->json([
                'message' => 'Не удалось сохранить файл подписи на сервере',
            ], 500);
        }

        $oldPath = $delivery->signature_path;

        try {
            DB::transaction(function () use ($delivery, $path) {
                $delivery->update([
                    'signature_path' => $path,
                    'signature_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->actionLogger->logUpdate($order, 'delivery.signature', null, $delivery->signature_url, auth()->user());

        $order->load(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

        return response()->json([
            'message' => 'Подпись клиента сохранена',
            'signature_url' => $delivery->signature_url,
            'signature_at' => $delivery->signature_at?->toIso8601String(),
            'order' => new OrderResource($order),
        ]);
    }

    public function deleteSignature(Request $request, Order $order): JsonResponse
    {
        $delivery = $order->delivery()->first();
        if ($delivery) {
            $oldPath = $delivery->signature_path;

            DB::transaction(function () use ($delivery) {
                $delivery->update([
                    'signature_path' => null,
                    'signature_at' => null,
                ]);
            });

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $this->actionLogger->logUpdate($order, 'delivery.signature', 'deleted', null, auth()->user());
        }

        $order->load(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts']);

        return response()->json([
            'message' => 'Подпись клиента удалена',
            'order' => new OrderResource($order),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $order = $this->orderStatusMachine->markCancelled($order->id, auth()->user()->id);

        return response()->json(['order' => new OrderResource($order)]);
    }

    public function bulkCancel(BulkOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->bulkOrderService->cancel($validated['order_ids'], auth()->user());

        return response()->json($result);
    }

    public function bulkAssign(BulkAssignCourierRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->bulkOrderService->assignCourier($validated['order_ids'], (int) $validated['delivery_courier_id'], auth()->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return response()->json($result);
    }

    public function bulkAccept(BulkOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->bulkOrderService->accept($validated['order_ids'], auth()->user());

        return response()->json($result);
    }

    public function bulkAssignManager(BulkAssignManagerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->bulkOrderService->assignManager($validated['order_ids'], (int) $validated['manager_id'], auth()->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return response()->json($result);
    }

    public function bulkAtWarehouse(BulkOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->bulkOrderService->atWarehouse($validated['order_ids'], auth()->user());

        return response()->json($result);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json([
            'message' => 'Заказ успешно удален',
        ]);
    }
}
