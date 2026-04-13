<?php
namespace App\Enums;

enum WorkLogStatus: string
{
    case Done = 'done';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::Done => 'Done',
            self::InProgress => 'In Progress',
            self::Blocked => 'Blocked',
        };
    }
}
