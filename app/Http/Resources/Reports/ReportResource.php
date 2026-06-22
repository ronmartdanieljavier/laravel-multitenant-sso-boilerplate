<?php

namespace App\Http\Resources\Reports;

use App\Models\Central\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'format' => $this->format,
            'delivery' => $this->delivery,
            'status' => $this->status,
            'parameters' => $this->parameters,
            'batch_id' => $this->batch_id,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
