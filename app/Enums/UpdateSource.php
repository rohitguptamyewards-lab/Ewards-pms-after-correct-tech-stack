<?php
namespace App\Enums;

enum UpdateSource: string
{
    case Manual = 'manual';
    case Ai = 'ai';

    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manual',
            self::Ai => 'AI',
        };
    }
}
