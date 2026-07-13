<?php

namespace App\Http\Requests\V1;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the (currently unused — see PostController::index())
 * optional filters a real Posts index will need. Added now,
 * ahead of the controller actually filtering by them, specifically to
 * demonstrate this project's Form Request pattern — see
 * docs/adr/0004-backend-foundation.md. `authorize(): true` because
 * there's no auth yet (Milestone 8); every route is currently open.
 */
class IndexPostsRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
        ];
    }
}
