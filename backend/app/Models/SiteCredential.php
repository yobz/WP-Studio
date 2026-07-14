<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'wp_username',
        'application_password',
    ];

    protected $hidden = [
        'application_password',
    ];

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
