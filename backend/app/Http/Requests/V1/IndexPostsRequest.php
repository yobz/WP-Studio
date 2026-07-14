<?php

namespace App\Http\Requests\V1;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                Rule::in([...array_column(PostStatus::cases(), 'value'), 'unpublished']),
            ],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
        ];
    }
}
