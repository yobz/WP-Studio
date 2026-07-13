<?php

namespace App\Http\Requests\V1;

use App\Enums\SiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No auth yet (Milestone 8) — every route is currently open.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
            'wordpress_version' => ['nullable', 'string', 'max:20'],
            'theme' => ['nullable', 'string', 'max:100'],
            'plugin_updates_available' => ['sometimes', 'integer', 'min:0'],
            'storage_used_mb' => ['sometimes', 'integer', 'min:0'],
            'storage_limit_mb' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
