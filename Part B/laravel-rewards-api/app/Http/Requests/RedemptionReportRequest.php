<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedemptionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $toRules = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => $toRules,
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
