<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'feature',
        'filename',
        'status',
        'error_message',
        'error_details',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'started_at',
        'finished_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'success_rows' => 'integer',
            'failed_rows' => 'integer',
            'error_details' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get human readable feature name.
     */
    public function getFeatureLabelAttribute(): string
    {
        return match ($this->feature) {
            'consume' => 'Consume',
            'part_number' => 'Part Number',
            'area' => 'Area',
            'machine' => 'Machine',
            'mapping' => 'Mapping Part',
            'user' => 'User',
            default => ucfirst(str_replace('_', ' ', (string) $this->feature)),
        };
    }

    /**
     * Calculate progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if (!$this->total_rows || $this->total_rows <= 0) {
            return $this->status === 'success' ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}
