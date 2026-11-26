<?php

namespace App\Http\Controllers;

use App\Dto\Order\CreateOrderDto;
use App\Http\Requests\StoreIncomingOrderRequest;
use App\Jobs\ProcessIncomingOrderJob;
use App\Services\AntiFraud\WebhookAntiFraudService;
use App\Services\Orders\OrderCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IncomingOrderController extends Controller
{
    public function store(
        StoreIncomingOrderRequest $request,
        OrderCreator $orderCreator,
        WebhookAntiFraudService $antiFraudService
    ): JsonResponse {
        $site = $request->site();
        $validated = $request->validated();

        $antiFraud = $antiFraudService->evaluate($site, $request, $validated);

        if ($antiFraud->isBlocked()) {
            Log::channel('webhooks')->warning('Incoming order blocked by anti-fraud', [
                'site_id' => $site->id,
                'ip' => $request->ip(),
                'client_ip' => $antiFraud->clientIp,
                'fingerprint' => $antiFraud->fingerprint,
                'score' => $antiFraud->score,
                'flags' => $antiFraud->flags,
                'reasons' => $antiFraud->reasons,
            ]);

            return response()->json([
                'job_id' => 'spam_'.uniqid(),
                'spam' => true,
                'message' => 'Order rejected by anti-fraud protection',
                'reasons' => $antiFraud->reasons,
            ], 202);
        }

        $extraData = $validated['extra_data'] ?? [];
        if (! is_array($extraData)) {
            $extraData = [];
        }
        $extraData['_antifraud'] = $antiFraud->toTelemetryArray();
        $validated['extra_data'] = $extraData;

        $dto = CreateOrderDto::fromArray($site->id, $validated);

        if ($orderCreator->isDuplicateAndAcquireLock($site, $dto)) {
            return response()->json([
                'job_id' => 'dedup_'.uniqid(),
                'duplicate' => true,
                'message' => 'Duplicate order ignored',
            ], 202);
        }

        $jobId = uniqid('order_');

        ProcessIncomingOrderJob::dispatch($site->id, $dto->toArray());

        Log::channel('webhooks')->info('Incoming order queued', [
            'job_id' => $jobId,
            'site_id' => $site->id,
            'site_name' => $site->name,
        ]);

        return response()->json(['job_id' => $jobId], 202);
    }
}
