<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    protected $appends = [
        'display_area',
        'display_machine',
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

    /**
     * Scope query to apply consistent filtering across dashboard and reports.
     * Supports direct area assignment as well as area mapping via area_part_number and 'common' special filter.
     */
    public function scopeFilteredByRequest(
        Builder $query,
        ?string $areaId = null,
        ?string $machineId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Builder {
        return $query
            ->when($areaId, function ($q, $v) {
                if ($v === 'common') {
                    $q->whereHas('partNumber', function ($pnQuery) {
                        $pnQuery->whereHas('areas', fn($a) => $a->where('code', 'FA'))
                                ->whereHas('areas', fn($a) => $a->where('code', 'SMT'));
                    });
                } else {
                    $q->where(function ($sub) use ($v) {
                        $sub->where('consumes.area_id', $v)
                            ->orWhereExists(function ($ex) use ($v) {
                                $ex->select(DB::raw(1))
                                   ->from('area_part_number')
                                   ->whereColumn('area_part_number.part_number_id', 'consumes.part_number_id')
                                   ->where('area_part_number.area_id', $v);
                            });
                    });
                }
            })
            ->when($machineId, fn($q, $v) => $q->where('consumes.machine_id', $v))
            ->when($dateFrom, fn($q, $v) => $q->whereDate('consumes.consumed_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->whereDate('consumes.consumed_at', '<=', $v));
    }

    /**
     * Get the dynamic display area name (Common if FA & SMT mapped, or specific area acronyms).
     */
    public function getDisplayAreaAttribute(): string
    {
        $partNumber = $this->partNumber;
        if ($partNumber && $partNumber->areas && $partNumber->areas->isNotEmpty()) {
            $codes = $partNumber->areas->map(fn($a) => !empty($a->code) ? strtoupper(trim($a->code)) : Area::abbreviate($a->name))->all();
            $hasFa = in_array('FA', $codes);
            $hasSmt = in_array('SMT', $codes);

            if ($hasFa && $hasSmt) {
                return 'Common (FA & SMT)';
            }

            if (count($partNumber->areas) === 1) {
                $first = $partNumber->areas->first();
                return !empty($first->code) ? strtoupper(trim($first->code)) : Area::abbreviate($first->name);
            }

            return $partNumber->areas->map(fn($a) => !empty($a->code) ? strtoupper(trim($a->code)) : Area::abbreviate($a->name))->unique()->join(', ');
        }

        if ($this->area) {
            return !empty($this->area->code) ? strtoupper(trim($this->area->code)) : Area::abbreviate($this->area->name);
        }

        return '-';
    }

    /**
     * Get the dynamic display machines list (e.g., "Machine 1 (FA), Machine 2 (SMT)").
     */
    public function getDisplayMachineAttribute(): string
    {
        $partNumber = $this->partNumber;
        if ($partNumber && $partNumber->machines && $partNumber->machines->isNotEmpty()) {
            $machines = $partNumber->machines->map(function ($m) {
                $areaCode = $m->area?->code;
                if (!$areaCode && $m->area_id) {
                    $areaCode = Area::find($m->area_id)?->code;
                }
                if (!$areaCode && $m->area?->name) {
                    $areaCode = Area::abbreviate($m->area->name);
                }
                return $areaCode ? "{$m->name} ({$areaCode})" : $m->name;
            })->unique()->values();

            return $machines->isNotEmpty() ? $machines->join(', ') : '-';
        }

        if ($this->machine) {
            $areaCode = $this->machine->area?->code;
            if (!$areaCode && $this->machine->area_id) {
                $areaCode = Area::find($this->machine->area_id)?->code;
            }
            if (!$areaCode && $this->machine->area?->name) {
                $areaCode = Area::abbreviate($this->machine->area->name);
            }
            return $areaCode ? "{$this->machine->name} ({$areaCode})" : $this->machine->name;
        }

        return '-';
    }
}
