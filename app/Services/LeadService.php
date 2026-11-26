<?php

namespace App\Services;

use App\Dto\Lead\CreateLeadDto;
use App\Enums\LeadStatus;
use App\Events\OrderCreated;
use App\Models\Lead;
use App\Models\Site;
use App\Services\Orders\OrderCreator;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadService
{
    public function __construct(
        protected OrderCreator $orderCreator
    ) {}

    public function convertToOrder(Lead $lead, ?int $actorId = null): array
    {
        $createdOrder = null;

        $result = DB::transaction(function () use ($lead, $actorId, &$createdOrder) {
            $lockedLead = Lead::where('id', $lead->id)->lockForUpdate()->firstOrFail();

            if ($lockedLead->converted_order_id) {
                return [
                    'success' => true,
                    'order_id' => $lockedLead->converted_order_id,
                    'message' => 'Заказ уже был создан ранее',
                ];
            }

            $commentParts = [];
            $commentParts[] = "Заявка #{$lockedLead->id} ({$lockedLead->type_label})";
            if ($lockedLead->page_url) {
                $commentParts[] = "Страница: {$lockedLead->page_url}";
            }
            if ($lockedLead->message) {
                $commentParts[] = "Сообщение: {$lockedLead->message}";
            }
            $comment = implode("\n", $commentParts);

            $productName = $lockedLead->product_name ?: ($lockedLead->type_label ?: 'Заявка с сайта');

            $order = $this->orderCreator->create(
                siteId: $lockedLead->site_id,
                data: [
                    'customer_id' => $lockedLead->customer_id,
                    'client_name' => $lockedLead->name,
                    'client_phone' => $lockedLead->phone,
                    'client_email' => $lockedLead->email,
                    'client_comment' => $comment,
                    'order_items' => [
                        [
                            'name' => $productName,
                            'sku' => $lockedLead->product_sku,
                            'qty' => 1,
                            'price' => 0,
                        ],
                    ],
                    'extra_data' => array_merge($lockedLead->extra_data ?? [], [
                        'from_lead_id' => $lockedLead->id,
                        'lead_type' => $lockedLead->type?->value ?? (string) $lockedLead->type,
                    ]),
                ],
                actorId: $actorId
            );

            $lockedLead->update([
                'status' => LeadStatus::CONVERTED,
                'converted_order_id' => $order->id,
                'manager_id' => $lockedLead->manager_id ?: $actorId,
            ]);

            $createdOrder = $order;

            return [
                'success' => true,
                'order_id' => $order->id,
                'lead' => $lockedLead->fresh(),
            ];
        });

        if ($createdOrder) {
            event(new OrderCreated($createdOrder));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|CreateLeadDto  $data
     */
    public function isDuplicateAndAcquireLock(Site $site, array|CreateLeadDto $data): bool
    {
        if ($data instanceof CreateLeadDto) {
            $data = $data->toArray();
        }

        $phone = $data['client_phone'] ?? null;
        $normalizedPhone = $phone ? PhoneNumber::normalize($phone) : null;
        $email = isset($data['client_email']) ? trim(mb_strtolower($data['client_email'])) : null;
        $externalId = $data['external_id'] ?? null;
        $type = $data['type'] ?? 'callback';

        if ($externalId) {
            $duplicate = Lead::where('site_id', $site->id)
                ->where('external_id', (string) $externalId)
                ->exists();

            if (! $duplicate) {
                $extPendingKey = "incoming_lead_ext:{$site->id}:{$externalId}";
                $acquired = Cache::add($extPendingKey, true, now()->addSeconds(60));
                if (! $acquired) {
                    $duplicate = true;
                }
            }

            if ($duplicate) {
                Log::channel('webhooks')->info('Incoming lead deduplicated by external_id', [
                    'site_id' => $site->id,
                    'external_id' => $externalId,
                ]);

                return true;
            }
        }

        $contactIdentifier = $normalizedPhone ? "phone:{$normalizedPhone}" : ($email ? "email:{$email}" : null);

        if ($contactIdentifier) {
            $pendingKey = "incoming_lead_pending:{$site->id}:{$contactIdentifier}:{$type}";
            $query = Lead::where('site_id', $site->id)
                ->where('type', $type)
                ->where('status', LeadStatus::NEW->value);

            if ($normalizedPhone) {
                $query->where('phone', $normalizedPhone);
            } else {
                $query->where('email', $email);
            }

            $duplicate = $query->exists();

            if (! $duplicate) {
                $acquired = Cache::add($pendingKey, true, now()->addSeconds(30));
                if (! $acquired) {
                    $duplicate = true;
                }
            }

            if ($duplicate) {
                Log::channel('webhooks')->info('Incoming lead deduplicated by contact and type', [
                    'site_id' => $site->id,
                    'contact' => $contactIdentifier,
                    'type' => $type,
                ]);

                return true;
            }
        }

        return false;
    }
}
