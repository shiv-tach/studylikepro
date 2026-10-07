<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = [
        'education_level_id',
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function teacherProfiles(): BelongsToMany
    {
        return $this->belongsToMany(TeacherProfile::class, 'teacher_subjects')->withTimestamps();
    }

    /**
     * Human label for the grade span this subject covers, e.g. "Grades 6-11".
     */
    public function gradeRangeLabel(): ?string
    {
        return $this->educationLevel?->gradeRangeLabel();
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeForLevel(Builder $query, EducationLevel|string $level): Builder
    {
        if ($level instanceof EducationLevel) {
            return $query->where('education_level_id', $level->id);
        }

        return $query->whereHas('educationLevel', fn (Builder $inner) => $inner->where('key', $level));
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
