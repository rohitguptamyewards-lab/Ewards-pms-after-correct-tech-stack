<?php
namespace App\Enums;

enum PlannerStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Blocked => 'Blocked',
        };
    }
}
