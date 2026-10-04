<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

enum Urgency: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
}
