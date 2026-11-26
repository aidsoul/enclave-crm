<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Lead $lead) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders-live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LeadCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'lead' => [
                'id' => $this->lead->id,
                'site_id' => $this->lead->site_id,
                'site' => $this->lead->site ? [
                    'id' => $this->lead->site->id,
                    'name' => $this->lead->site->name,
                ] : null,
                'customer_id' => $this->lead->customer_id,
                'type' => $this->lead->type?->value ?? (string) $this->lead->type,
                'type_label' => $this->lead->type_label,
                'status' => $this->lead->status?->value ?? (string) $this->lead->status,
                'status_label' => $this->lead->status_label,
                'status_color' => $this->lead->status_color,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
                'formatted_phone' => $this->lead->formatted_phone,
                'email' => $this->lead->email,
                'message' => $this->lead->message,
                'product_name' => $this->lead->product_name,
                'product_sku' => $this->lead->product_sku,
                'page_url' => $this->lead->page_url,
                'page_title' => $this->lead->page_title,
                'created_at' => $this->lead->created_at?->toIso8601String(),
            ],
        ];
    }
}
