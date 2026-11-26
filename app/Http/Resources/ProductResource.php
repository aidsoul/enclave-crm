<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user() && ($request->user()->isOwner() || $request->user()->isManager());

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'url' => $this->url,
            'price' => (float) $this->price,
            'cost_price' => $canSeeCost ? (float) $this->cost_price : null,
            'margin' => $canSeeCost ? round((float) $this->price - (float) $this->cost_price, 2) : null,
            'is_active' => (bool) $this->is_active,
            'variants' => $this->relationLoaded('variants') ? $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'external_id' => $variant->external_id,
                'sku' => $variant->sku,
                'title' => $variant->title,
                'attributes' => $variant->attributes,
                'price' => $variant->price !== null ? (float) $variant->price : (float) $this->price,
                'purchase_price' => $canSeeCost && $variant->purchase_price !== null ? (float) $variant->purchase_price : ($canSeeCost ? (float) $this->cost_price : null),
                'is_active' => (bool) $variant->is_active,
            ])->all() : [],
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'domain' => $this->site->domain,
                'display_name' => $this->site->display_name,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
