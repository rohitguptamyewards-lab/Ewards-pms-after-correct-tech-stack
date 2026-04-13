<?php
namespace App\Enums;

enum BlockerStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Resolved => 'Resolved',
        };
    }
}
