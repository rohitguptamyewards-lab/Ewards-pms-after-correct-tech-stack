<?php
namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
        };
    }
}
