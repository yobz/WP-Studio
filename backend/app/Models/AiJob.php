<?php

namespace App\Models;

use App\Enums\AiJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'prompt',
        'status',
        'result',
        'error_message',
        'model',
        'input_tokens',
        'output_tokens',
        'attempted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiJobStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
