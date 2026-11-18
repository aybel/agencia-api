<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MenuItem */
class MenuItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->semantic_key,
            'name' => $this->display_name,
            'type' => $this->semantic_type,
            'route' => $this->route_name,
            'icon' => $this->semantic_icon,
            'order' => $this->order,
            'metadata' => $this->metadata,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
