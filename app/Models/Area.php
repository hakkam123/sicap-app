<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function partNumbers(): BelongsToMany
    {
        return $this->belongsToMany(PartNumber::class, 'area_part_number');
    }

    public function consumes(): HasMany
    {
        return $this->hasMany(Consume::class);
    }
}

