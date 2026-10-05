<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => $this->value === null ? null : (int) $this->value,
            'float' => $this->value === null ? null : (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            default => $this->value,
        };
    }
}
