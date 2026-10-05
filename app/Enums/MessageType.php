<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Message',
            self::Image => 'Photo',
            self::System => 'Update',
        };
    }

    public function isSystem(): bool
    {
        return $this === self::System;
    }
}
