<?php

namespace App\Http\Requests\V1;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
