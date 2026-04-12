<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
            'done_tasks' => ['required', 'string'],
            'next_tasks' => ['required', 'string'],
            'appendix_link' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
