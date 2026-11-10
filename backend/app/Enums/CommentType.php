<?php

namespace App\Enums;

enum CommentType: string
{
    case Comment = 'comment';
    case WorkUpdate = 'work_update';

    public function label(): string
    {
        return match ($this) {
            self::Comment => 'Comment',
            self::WorkUpdate => 'Work Update',
        };
    }
}
