<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed], true);
    }

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.import_status.'.$this->value);
    }
}
