<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartNumber extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pn_baan',
        'description',
        'price_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
        ];
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_part_number');
    }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'machine_part_number');
    }

    public function consumes(): HasMany
    {
        return $this->hasMany(Consume::class);
    }
}

