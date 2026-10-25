<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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
            // Money settings are stored in minor units, like every other amount.
            'money' => $this->value === null ? null : (int) $this->value,
            'float' => $this->value === null ? null : (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            // Credentials (API keys) are encrypted at rest by PlatformSettings.
            'secret' => $this->secretValue(),
            default => $this->value,
        };
    }

    /**
     * Decrypt a stored secret, or null when it was never saved or the app key
     * changed (the admin then re-enters it).
     */
    private function secretValue(): ?string
    {
        if (blank($this->value)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (DecryptException) {
            return null;
        }
    }
}
