<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['sometimes', Rule::in(['upload', 'wordpress'])],
            'mime_type' => ['sometimes', 'string'],
        ];
    }
}
