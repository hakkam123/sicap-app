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
     * Scope query to select catalog fields (tanpa eager load — dilakukan di getCatalogData via chunk).
     */
    public function scopeForCatalog(Builder $query): Builder
    {
        return $query->select(['id', 'pn_baan', 'part_number_code', 'description', 'addressing'])
            ->orderBy('pn_baan', 'asc');
    }

    /**
     * Get transformed catalog data cached for 5 minutes.
     * Return plain array agar aman disimpan di cache (tidak menyimpan Collection/Model).
     */
    public static function getCatalogData(): array
    {
        return Cache::remember(self::CATALOG_CACHE_KEY, self::CATALOG_CACHE_TTL_SECONDS, function () {
            $results = [];

            static::forCatalog()
                ->whereNull('deleted_at')
                ->chunk(200, function ($parts) use (&$results) {
                    // Eager load relasi per chunk — aman dari limit 2100 parameter SQL Server
                    $parts->load([
                        'areas' => fn ($q) => $q->select(['areas.id', 'areas.code', 'areas.name'])->whereNull('areas.deleted_at')->orderBy('areas.code'),
                        'machines' => fn ($q) => $q->select(['machines.id', 'machines.code', 'machines.name', 'machines.area_id'])->whereNull('machines.deleted_at')->orderBy('machines.code'),
                    ]);

                    foreach ($parts as $part) {
                        $machines = $part->machines->map(fn ($m) => $m->code ?: $m->name)->filter()->values()->toArray();
                        $areas = $part->areas->map(fn ($a) => $a->code ?: $a->name)->filter()->values()->toArray();

                        $results[] = [
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
                    }
                });

            return $results;
        });
    }

    /**
     * Invalidate catalog cache.
     */
    public static function clearCatalogCache(): void
    {
        Cache::forget(self::CATALOG_CACHE_KEY);
    }
}

