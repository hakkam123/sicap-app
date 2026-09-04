<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consume extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'part_number_id',
        'area_id',
        'machine_id',
        'quantity',
        'amount',
        'consumed_at',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'amount' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function partNumber(): BelongsTo
    {
        return $this->belongsTo(PartNumber::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class)->withTrashed();
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}

