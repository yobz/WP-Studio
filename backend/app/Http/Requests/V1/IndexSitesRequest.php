<?php

namespace App\Http\Requests\V1;

use App\Enums\SiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSitesRequest extends FormRequest
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
            'workspace_id' => ['sometimes', 'integer', 'exists:workspaces,id'],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
        ];
    }
}
