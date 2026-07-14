<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('media.max_upload_kb'),
                'mimes:'.implode(',', config('media.allowed_mimes')),
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
