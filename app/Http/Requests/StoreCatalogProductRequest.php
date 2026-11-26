<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreCatalogProductRequest extends ApiFormRequest
{
    protected Site $site;

    public function authorize(): bool
    {
        $token = $this->header('X-API-Token');

        if (! $token) {
            Log::channel('webhooks')->warning('Missing API token in catalog product save', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 401));
        }

        $site = Site::findByTokenCached($token);

        if (! $site) {
            Log::channel('webhooks')->warning('Invalid API token in catalog product save', ['ip' => $this->ip()]);
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
            'external_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'image_url' => 'nullable|url|max:2048',
            'url' => 'nullable|url|max:2048',
            'price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'variants' => 'nullable|array',
            'variants.*.external_id' => 'required|string|max:255',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.title' => 'required|string|max:255',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.purchase_price' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ];
    }
}
