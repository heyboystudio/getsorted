<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

enum ActorType: string
{
    case Customer = 'customer';
    case Pro = 'pro';
    case Admin = 'admin';
    case System = 'system';
}
