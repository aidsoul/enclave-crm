<?php

namespace App\Http\Controllers;

use App\Dto\Lead\CreateLeadDto;
use App\Http\Requests\StoreIncomingLeadRequest;
use App\Jobs\ProcessIncomingLeadJob;
use App\Services\AntiFraud\WebhookAntiFraudService;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IncomingLeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService,
        protected WebhookAntiFraudService $antiFraudService
    ) {}

    public function store(StoreIncomingLeadRequest $request): JsonResponse
    {
        $site = $request->site();
        $validated = $request->validated();

        $antiFraud = $this->antiFraudService->evaluate($site, $request, $validated);

        if ($antiFraud->isBlocked()) {
            Log::channel('webhooks')->warning('Incoming lead blocked by anti-fraud', [
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
                'message' => 'Lead rejected by anti-fraud protection',
                'reasons' => $antiFraud->reasons,
            ], 202);
        }

        $extraData = $validated['extra_data'] ?? [];
        if (! is_array($extraData)) {
            $extraData = [];
        }
        $extraData['_antifraud'] = $antiFraud->toTelemetryArray();
        $validated['extra_data'] = $extraData;

        $dto = CreateLeadDto::fromArray($site->id, $validated);

        if ($this->leadService->isDuplicateAndAcquireLock($site, $dto)) {
            return response()->json([
                'job_id' => 'dedup_'.uniqid(),
                'duplicate' => true,
                'message' => 'Duplicate lead ignored',
            ], 202);
        }

        $jobId = 'lead_'.uniqid();
        ProcessIncomingLeadJob::dispatch($site->id, $dto->toArray());

        return response()->json([
            'job_id' => $jobId,
            'status' => 'accepted',
        ], 202);
    }
}
