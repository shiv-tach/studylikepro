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
 * (Categories I, II and III) and a Grade 10-11 student picks one subject from
 * each. A subject belongs to at most one basket.
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
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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
