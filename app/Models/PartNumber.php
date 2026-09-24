<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PartNumber extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    public const CATALOG_CACHE_KEY = 'sparepart_catalog_data';
    public const CATALOG_CACHE_TTL_SECONDS = 300; // 5 minutes

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pn_baan',
        'part_number_code',
        'description',
        'addressing',
    ];

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

    /**
     * Scope query to select catalog fields and eager load mapping.
     */
    public function scopeForCatalog(Builder $query): Builder
    {
        return $query->select(['id', 'pn_baan', 'part_number_code', 'description', 'addressing'])
            ->with([
                'machines' => fn ($q) => $q->select(['machines.id', 'machines.code', 'machines.name', 'machines.area_id'])->whereNull('machines.deleted_at')->orderBy('machines.code'),
                'areas' => fn ($q) => $q->select(['areas.id', 'areas.code', 'areas.name'])->whereNull('areas.deleted_at')->orderBy('areas.code'),
            ])
            ->orderBy('pn_baan');
    }

    /**
     * Get transformed catalog data cached for 5 minutes as a pure array.
     * Storing plain array prevents __PHP_Incomplete_Class deserialization issues across cache drivers.
     *
     * @return array
     */
    public static function getCatalogData(): array
    {
        $cached = Cache::remember(self::CATALOG_CACHE_KEY, self::CATALOG_CACHE_TTL_SECONDS, function () {
            return static::forCatalog()
                ->get()
                ->map(function (PartNumber $part) {
                    $machines = $part->machines->map(fn ($m) => $m->code ?: $m->name)->filter()->values()->all();
                    $areas = $part->areas->map(fn ($a) => $a->code ?: $a->name)->filter()->values()->all();

                    return [
                        'id'               => $part->id,
                        'pn_baan'          => $part->pn_baan,
                        'part_number_code' => $part->part_number_code,
                        'description'      => $part->description,
                        'addressing'       => $part->addressing,
                        'machines_text'    => !empty($machines) ? implode(', ', $machines) : '-',
                        'areas_text'       => !empty($areas) ? implode(', ', $areas) : '-',
                        'machines'         => $machines,
                        'areas'            => $areas,
                    ];
                })
                ->values()
                ->all();
        });

        // Ensure we always return an array even if cache previously contained an incomplete class
        if (!is_array($cached)) {
            static::clearCatalogCache();
            return static::getCatalogData();
        }

        return $cached;
    }

    /**
     * Invalidate catalog cache.
     */
    public static function clearCatalogCache(): void
    {
        Cache::forget(self::CATALOG_CACHE_KEY);
    }
}
