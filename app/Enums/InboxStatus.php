<?php

namespace App\Enums;

enum InboxStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case SkippedDuplicate = 'skipped_duplicate';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::SkippedDuplicate], true);
    }
}
