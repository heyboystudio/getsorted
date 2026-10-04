<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Enums;

/** Registrations a pro must hold to offer a service (checked during vetting, spec 008). */
enum RegistrationType: string
{
    case Pirb = 'pirb';
    case ElectricalRegisteredPerson = 'electrical_registered_person';

    public function label(): string
    {
        return match ($this) {
            self::Pirb => 'PIRB plumber',
            self::ElectricalRegisteredPerson => 'Registered electrician',
        };
    }
}
