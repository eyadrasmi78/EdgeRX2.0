<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a Laravel DatabaseNotification row into the JSON the React bell UI consumes.
 * Pulled out of NotificationsController inline mapping (N8) for reuse + clarity.
 */
class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = is_array($this->data) ? $this->data : (json_decode($this->data ?? '{}', true) ?: []);
        return [
            'id' => $this->id,
            'type' => $data['type'] ?? 'info',
            'kind' => $data['kind'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? '',
            'actionUrl' => $data['actionUrl'] ?? null,
            'meta' => $data['meta'] ?? null,
            'timestamp' => optional($this->created_at)->getTimestamp() * 1000,
            'readAt' => optional($this->read_at)->toIso8601String(),
        ];
    }
}
