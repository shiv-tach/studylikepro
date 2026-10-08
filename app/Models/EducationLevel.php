<?php

namespace App\Models;

use Database\Factories\EducationLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the four Sri Lankan education levels: Primary, O/L, A/L or Other.
 */
class EducationLevel extends Model
{
    /** @use HasFactory<EducationLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'grade_min',
        'grade_max',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function subjectBaskets(): HasMany
    {
        return $this->hasMany(SubjectBasket::class);
    }

    /**
     * The grade numbers where this level's students pick one subject from each
     * basket (O/L: Grades 10-11). Empty for levels without baskets.
     *
     * @return list<int>
     */
    public function basketGradeNumbers(): array
    {
        $numbers = config("studylikepro.education_levels.{$this->key}.basket_grades", []);

        return array_map('intval', (array) $numbers);
    }

    /**
     * Whether a student in this grade picks one subject per basket.
     */
    public function requiresBasketSelection(Grade $grade): bool
    {
        return $grade->number !== null
            && in_array($grade->number, $this->basketGradeNumbers(), true);
    }

    /**
     * Human label for the grade span, e.g. "Grades 6-11".
     */
    public function gradeRangeLabel(): string
    {
        if ($this->grade_min === null || $this->grade_max === null) {
            return $this->name;
        }

        return "Grades {$this->grade_min}-{$this->grade_max}";
    }

    /**
     * @param  Builder<EducationLevel>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<EducationLevel>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
