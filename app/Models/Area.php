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

    protected $appends = [
        'short_name',
    ];

    /**
     * Get the short abbreviated name for this area.
     */
    public function getShortNameAttribute(): string
    {
        if (!empty($this->code)) {
            return strtoupper(trim($this->code));
        }

        return static::abbreviate($this->name);
    }

    /**
     * Static helper to abbreviate area name (taking first letters of each word, or keeping acronyms).
     * Examples:
     * - "Surface Mount Technology" -> "SMT"
     * - "SMT" -> "SMT"
     * - "Final Assembly" -> "FA"
     * - "FA" -> "FA"
     * - "Common (FA & SMT)" -> "Common (FA & SMT)"
     * - "Surface Mount Technology, Final Assembly" -> "SMT, FA"
     */
    public static function abbreviate(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '-';
        }

        $trimmed = trim($name);

        // Common format (e.g. Common (FA & SMT))
        if (preg_match('/^common/i', $trimmed)) {
            return $trimmed;
        }

        // Multiple comma-separated areas
        if (str_contains($trimmed, ',')) {
            return collect(explode(',', $trimmed))
                ->map(fn($part) => static::abbreviate(trim($part)))
                ->filter(fn($part) => $part !== '' && $part !== '-')
                ->join(', ');
        }

        // Split into words
        $words = preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) {
            return '-';
        }

        // If single word: if already an acronym / short word or single word, return uppercase
        if (count($words) === 1) {
            return strtoupper($words[0]);
        }

        // Multiple words -> extract first letter of each word (ignore pure punctuation)
        $initials = '';
        foreach ($words as $word) {
            $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $word);
            if ($cleaned !== '') {
                $initials .= strtoupper(mb_substr($cleaned, 0, 1));
            }
        }

        return $initials ?: strtoupper($trimmed);
    }

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

