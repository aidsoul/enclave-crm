<?php

namespace App\Http\Resources;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 *
 * @property-read string|null $phone
 * @property-read string|null $formatted_phone
 * @property-read string|null $phone_with_plus
 * @property-read string|null $type_label
 * @property-read string|null $status_label
 * @property-read string|null $status_color
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'customer_id' => $this->customer_id,
            'manager_id' => $this->manager_id,
            'converted_order_id' => $this->converted_order_id,
            'type' => $this->type instanceof LeadType ? $this->type->value : (string) $this->type,
            'type_label' => $this->type_label,
            'status' => $this->status instanceof LeadStatus ? $this->status->value : (string) $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'name' => $this->name,
            'phone' => $this->phone,
            'formatted_phone' => $this->formatted_phone,
            'phone_with_plus' => $this->phone_with_plus,
            'email' => $this->email,
            'message' => $this->message,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'page_url' => $this->page_url,
            'page_title' => $this->page_title,
            'external_id' => $this->external_id,
            'extra_data' => $this->extra_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'site' => $this->whenLoaded('site', function () {
                return $this->site ? [
                    'id' => $this->site->id,
                    'name' => $this->site->name,
                    'domain' => $this->site->domain,
                    'display_name' => $this->site->display_name,
                ] : null;
            }),
            'customer' => $this->whenLoaded('customer', function () {
                return $this->customer ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                ] : null;
            }),
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'system_name' => $this->manager->system_name,
                ] : null;
            }),
            'converted_order' => $this->whenLoaded('convertedOrder', function () {
                return $this->convertedOrder ? [
                    'id' => $this->convertedOrder->id,
                    'status' => $this->convertedOrder->status?->value ?? (string) $this->convertedOrder->status,
                    'total_price' => $this->convertedOrder->total_price,
                ] : null;
            }),
        ];
    }
}
