<?php

namespace App\Models;

use Database\Factories\SubjectBasketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A basket of optional subjects inside one education level - the O/L has three
 * (Categories I, II and III). A subject belongs to at most one basket, and the
 * basket runs in a range of grades: Category I from Grade 6, so the junior
 * grades pick from it alone, while Grades 10-11 pick from all three.
 */
class SubjectBasket extends Model
{
    /** @use HasFactory<SubjectBasketFactory> */
    use HasFactory;

    protected $fillable = [
        'education_level_id',
        'key',
        'name',
        'description',
        'icon',
        'grade_min',
        'grade_max',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grade_min' => 'integer',
            'grade_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'basket_id');
    }

    /**
     * The grade numbers this basket runs in, in order. Empty means every grade
     * of its level.
     *
     * @return list<int>
     */
    public function gradeNumbers(): array
    {
        if ($this->grade_min === null || $this->grade_max === null) {
            return [];
        }

        return range($this->grade_min, $this->grade_max);
    }

    /**
     * Whether a student in this grade picks one subject from this basket.
     */
    public function appliesToGrade(Grade $grade): bool
    {
        if ($grade->number === null || (int) $grade->education_level_id !== (int) $this->education_level_id) {
            return false;
        }

        $numbers = $this->gradeNumbers();

        return $numbers === [] || in_array((int) $grade->number, $numbers, true);
    }

    /**
     * Human label for the grades the basket runs in, e.g. "Grades 6-11", or
     * null when it runs in every grade of its level.
     */
    public function gradeRangeLabel(): ?string
    {
        $numbers = $this->gradeNumbers();

        if ($numbers === []) {
            return null;
        }

        return 'Grades '.reset($numbers).'-'.end($numbers);
    }

    /**
     * @param  Builder<SubjectBasket>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<SubjectBasket>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
