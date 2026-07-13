<?php

namespace App\Http\Requests\V1;

use App\Enums\SiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `workspace_id` is deliberately absent — moving a site between
     * workspaces is a bigger operation (ownership transfer) than a
     * plain attribute update, and isn't a supported action yet.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
            'wordpress_version' => ['nullable', 'string', 'max:20'],
            'theme' => ['nullable', 'string', 'max:100'],
            'plugin_updates_available' => ['sometimes', 'integer', 'min:0'],
            'storage_used_mb' => ['sometimes', 'integer', 'min:0'],
            'storage_limit_mb' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
