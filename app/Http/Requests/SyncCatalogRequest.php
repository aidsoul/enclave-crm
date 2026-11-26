<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class SyncCatalogRequest extends ApiFormRequest
{
    protected Site $site;

    public function authorize(): bool
    {
        $token = $this->header('X-API-Token');

        if (! $token) {
            Log::channel('webhooks')->warning('Missing API token in catalog sync', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 401));
        }

        $site = Site::findByTokenCached($token);

        if (! $site) {
            Log::channel('webhooks')->warning('Invalid API token in catalog sync', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Forbidden'], 403));
        }

        $this->site = $site;

        return true;
    }

    public function site(): Site
    {
        return $this->site;
    }

    public function rules(): array
    {
        return [
            'async' => 'nullable|boolean',
            'products' => 'required|array|min:1|max:500',
            'products.*.external_id' => 'required|string|max:255',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'nullable|string|max:100',
            'products.*.description' => 'nullable|string|max:5000',
            'products.*.image_url' => 'nullable|url|max:2048',
            'products.*.url' => 'nullable|url|max:2048',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.is_active' => 'nullable|boolean',
            'products.*.variants' => 'nullable|array',
            'products.*.variants.*.external_id' => 'required|string|max:255',
            'products.*.variants.*.sku' => 'nullable|string|max:100',
            'products.*.variants.*.title' => 'required|string|max:255',
            'products.*.variants.*.attributes' => 'nullable|array',
            'products.*.variants.*.price' => 'nullable|numeric|min:0',
            'products.*.variants.*.purchase_price' => 'nullable|numeric|min:0',
            'products.*.variants.*.is_active' => 'nullable|boolean',
        ];
    }
}
