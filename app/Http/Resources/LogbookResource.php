<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogbookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'period_id' => $this->period_id,
            'report_date' => optional($this->report_date)->toDateString(),
            'done_tasks' => $this->done_tasks,
            'next_tasks' => $this->next_tasks,
            'status' => $this->status,
            'appendix_link' => $this->appendix_link,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
