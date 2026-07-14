<?php

namespace App\Http\Requests\V1;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
