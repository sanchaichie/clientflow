<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** GET /api/reports?from=YYYY-MM-DD&to=YYYY-MM-DD (both optional). */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewReports');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function from(): ?Carbon
    {
        return $this->validated('from') ? Carbon::parse($this->validated('from')) : null;
    }

    public function to(): ?Carbon
    {
        return $this->validated('to') ? Carbon::parse($this->validated('to')) : null;
    }
}
