<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'area_id',
        'code',
        'name',
        'description',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function partNumbers(): BelongsToMany
    {
        return $this->belongsToMany(PartNumber::class, 'machine_part_number');
    }

    public function consumes(): HasMany
    {
        return $this->hasMany(Consume::class);
    }
}

