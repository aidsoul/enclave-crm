<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeePurchasePrice = ! $request->user()?->isCourier();

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'site_id' => $this->site_id,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'formatted_phone' => $this->customer->formatted_phone,
                'phone_with_plus' => $this->customer->phone_with_plus,
                'email' => $this->customer->email,
                'address' => $this->customer->address,
                'is_blacklisted' => (bool) $this->customer->is_blacklisted,
                'blacklist_reason' => $this->customer->blacklist_reason,
                'tags' => $this->customer->tags ?? [],
            ] : null,
            'customer_id' => $this->customer_id,
            'extra_data' => $this->extra_data,
            'total_price' => $this->total_price,
            'purchase_price' => $canSeePurchasePrice ? $this->purchase_price : null,
            'status' => $this->status->value,
            'is_edited' => $this->is_edited,
            'is_valuable' => (bool) $this->is_valuable,
            'requires_signature' => (bool) $this->requiresClientSignature(),
            'has_unread_driver_message' => (bool) $this->has_unread_driver_message,
            'has_unread_manager_message' => (bool) $this->has_unread_manager_message,
            'unread_driver_messages_count' => (int) ($this->unread_driver_messages_count ?? 0),
            'unread_manager_messages_count' => (int) ($this->unread_manager_messages_count ?? 0),
            'unread_chat_count' => $canSeePurchasePrice
                ? (int) ($this->unread_driver_messages_count ?? 0)
                : (int) ($this->unread_manager_messages_count ?? 0),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'domain' => $this->site->domain,
                'display_name' => $this->site->display_name,
            ] : null,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'system_name' => $this->manager->system_name,
            ] : null,
            'manager_id' => $this->manager_id,
            'delivery' => $this->delivery->map(fn ($delivery) => [
                'id' => $delivery->id,
                'type' => $delivery->type ? ['value' => $delivery->type->value, 'label' => $delivery->type->label()] : null,
                'courier' => $delivery->courier ? [
                    'id' => $delivery->courier->id,
                    'system_name' => $delivery->courier->system_name,
                ] : null,
                'courier_comment' => $delivery->courier_comment,
                'city' => $delivery->city,
                'pickup_point' => $delivery->pickup_point,
                'delivery_address' => $delivery->delivery_address,
                'delivery_cost' => (float) ($delivery->delivery_cost ?? 0),
                'delivery_price' => (float) ($delivery->delivery_price ?? 0),
                'delivery_date' => $delivery->delivery_date?->format('Y-m-d'),
                'delivery_slot' => $delivery->delivery_slot,
                'photo_proof_path' => $delivery->photo_proof_path,
                'photo_proof_paths' => $delivery->photo_proof_paths,
                'photo_proof_url' => $delivery->photo_proof_url,
                'photo_proof_urls' => $delivery->photo_proof_urls,
                'photo_proof_at' => $delivery->photo_proof_at?->toISOString(),
                'signature_path' => $delivery->signature_path,
                'signature_url' => $delivery->signature_url,
                'signature_at' => $delivery->signature_at?->toISOString(),
                'has_signature' => $delivery->hasSignature(),
            ])->all(),
            'payment' => $this->payment->map(fn ($payment) => [
                'method' => $payment->method ? ['value' => $payment->method->value, 'label' => $payment->method->label()] : null,
                'status' => $payment->status ? ['value' => $payment->status->value, 'label' => $payment->status->label()] : null,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
            ])->all(),
            'items' => $this->relationLoaded('items') ? $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'external_product_id' => $item->external_product_id ?: $item->product?->external_id,
                'product_variant_id' => $item->product_variant_id,
                'external_variant_id' => $item->external_variant_id ?: $item->variant?->external_id,
                'image_url' => $item->product?->image_url,
                'url' => $item->product?->url,
                'name' => $item->name,
                'variant_title' => $item->variant_title,
                'sku' => $item->sku,
                'qty' => $item->qty,
                'price' => $item->price,
                'purchase_price' => $canSeePurchasePrice ? $item->purchase_price : null,
                'subtotal' => $item->subtotal(),
            ])->all() : [],
            'discounts' => $this->relationLoaded('discounts') ? $this->discounts->map(fn ($discount) => [
                'id' => $discount->id,
                'type' => $discount->type?->value,
                'value' => $discount->value,
                'code' => $discount->code,
            ])->all() : [],
        ];
    }
}
