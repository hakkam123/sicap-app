<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemErrorLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'error_type',
        'status_code',
        'severity',
        'feature',
        'message',
        'exception_class',
        'file',
        'line',
        'url',
        'method',
        'request_payload',
        'stack_trace',
        'user_id',
        'user_ip',
        'user_agent',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'line' => 'integer',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User who experienced or triggered the error.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User / Admin who resolved the error.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Human-friendly feature label.
     */
    public function getFeatureLabelAttribute(): string
    {
        return match ($this->feature) {
            'consume' => 'Consume Transaksi',
            'part_number' => 'Part Number',
            'area' => 'Area',
            'machine' => 'Machine',
            'mapping' => 'Mapping Part',
            'user' => 'User Management',
            'report' => 'Laporan',
            'sync_api' => 'Sync API Eksternal',
            'auth' => 'Autentikasi & Keamanan',
            'system' => 'System Core',
            default => ucfirst(str_replace('_', ' ', $this->feature ?: 'Sistem')),
        };
    }

    /**
     * Scopes
     */
    public function scopeUnresolved($query)
    {
        return $query->where('status', 'unresolved');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeFeature($query, ?string $feature)
    {
        if ($feature) {
            return $query->where('feature', $feature);
        }
        return $query;
    }

    public function scopeSeverity($query, ?string $severity)
    {
        if ($severity) {
            return $query->where('severity', $severity);
        }
        return $query;
    }
}
