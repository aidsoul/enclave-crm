<?php

namespace App\Jobs;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Events\LeadCreated;
use App\Models\Lead;
use App\Models\Site;
use App\Services\Orders\CustomerResolver;
use App\Services\TelegramService;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessIncomingLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 60;

    public function __construct(
        public int $siteId,
        public array $validatedData
    ) {
        $this->onQueue('high');
    }

    public function handle(CustomerResolver $customerResolver, TelegramService $telegramService): void
    {
        $phone = $this->validatedData['client_phone'] ?? null;
        $normalizedPhone = $phone ? PhoneNumber::normalize($phone) : null;
        $email = isset($this->validatedData['client_email']) ? trim(mb_strtolower($this->validatedData['client_email'])) : null;
        $externalId = $this->validatedData['external_id'] ?? null;
        $type = $this->validatedData['type'] ?? 'callback';

        $lockKey = null;
        if ($externalId) {
            $lockKey = "lock:incoming_lead:{$this->siteId}:ext:{$externalId}";
        } elseif ($normalizedPhone) {
            $lockKey = "lock:incoming_lead:{$this->siteId}:phone:{$normalizedPhone}";
        } elseif ($email) {
            $lockKey = "lock:incoming_lead:{$this->siteId}:email:{$email}";
        }
        $lock = $lockKey ? Cache::lock($lockKey, 15) : null;

        try {
            $site = Site::find($this->siteId);

            if (! $site) {
                Log::channel('webhooks')->warning('ProcessIncomingLeadJob: site not found, skipping', [
                    'site_id' => $this->siteId,
                ]);

                return;
            }

            if ($lock) {
                $lock->block(10);
            }

            if ($externalId) {
                $duplicateByExt = Lead::where('site_id', $this->siteId)
                    ->where('external_id', (string) $externalId)
                    ->exists();

                if ($duplicateByExt) {
                    Log::channel('webhooks')->info('Duplicate lead ignored by external_id', [
                        'site_id' => $this->siteId,
                        'external_id' => $externalId,
                    ]);

                    return;
                }
            }

            $query = Lead::where('site_id', $this->siteId)
                ->where('type', $type)
                ->where('status', LeadStatus::NEW);

            if ($normalizedPhone) {
                $query->where('phone', $normalizedPhone);
            } elseif ($email) {
                $query->where('email', $email);
            } else {
                $query = null;
            }

            if ($query && $query->exists()) {
                Log::channel('webhooks')->info('Duplicate lead ignored: active new lead already exists', [
                    'site_id' => $this->siteId,
                    'phone' => $normalizedPhone,
                    'email' => $email,
                ]);

                return;
            }

            $customer = $customerResolver->resolve($this->siteId, [
                'client_name' => $this->validatedData['client_name'] ?? null,
                'client_phone' => $phone,
                'client_email' => $this->validatedData['client_email'] ?? null,
            ]);

            try {
                $lead = Lead::create([
                    'site_id' => $this->siteId,
                    'customer_id' => $customer?->id,
                    'type' => LeadType::tryFrom($type) ?? LeadType::CALLBACK,
                    'status' => LeadStatus::NEW,
                    'name' => $this->validatedData['client_name'] ?? ($customer?->name),
                    'phone' => $normalizedPhone ?: $phone,
                    'email' => $this->validatedData['client_email'] ?? ($customer?->email),
                    'message' => $this->validatedData['message'] ?? null,
                    'product_name' => $this->validatedData['product_name'] ?? null,
                    'product_sku' => $this->validatedData['product_sku'] ?? null,
                    'page_url' => $this->validatedData['page_url'] ?? null,
                    'page_title' => $this->validatedData['page_title'] ?? null,
                    'external_id' => $externalId !== null ? (string) $externalId : null,
                    'extra_data' => $this->validatedData['extra_data'] ?? null,
                ]);
            } catch (QueryException $e) {
                if ($externalId) {
                    $existing = Lead::where('site_id', $this->siteId)
                        ->where('external_id', (string) $externalId)
                        ->first();
                    if ($existing) {
                        Log::channel('webhooks')->info('Duplicate lead caught by unique index', [
                            'site_id' => $this->siteId,
                            'external_id' => $externalId,
                        ]);

                        return;
                    }
                }

                throw $e;
            }

            $lead->setRelation('site', $site);

            Log::channel('webhooks')->info('Incoming lead created', [
                'lead_id' => $lead->id,
                'site_id' => $site->id,
                'customer_id' => $customer?->id,
                'type' => $type,
            ]);

            event(new LeadCreated($lead));

            $isSuspicious = (bool) ($lead->extra_data['_antifraud']['is_suspicious'] ?? false);
            if (! $isSuspicious) {
                $telegramService->notifyManagersAboutNewLead($lead);
            } else {
                Log::channel('webhooks')->info('Skipping manager notification for suspicious lead', [
                    'lead_id' => $lead->id,
                    'site_id' => $site->id,
                    'antifraud' => $lead->extra_data['_antifraud'] ?? [],
                ]);
            }
        } finally {
            $lock?->release();
        }
    }
}
