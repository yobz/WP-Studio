<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'storage_used_mb' => ['sometimes', 'integer', 'min:0'],
            'storage_limit_mb' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
