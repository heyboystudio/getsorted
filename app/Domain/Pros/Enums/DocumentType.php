<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

use App\Domain\Catalogue\Enums\RegistrationType;

enum DocumentType: string
{
    case IdDocument = 'id_document';
    case ProofOfAddress = 'proof_of_address';
    case ProfilePhoto = 'profile_photo';
    case Pirb = 'pirb';
    case ElectricalRegisteredPerson = 'electrical_registered_person';

    public function label(): string
    {
        return match ($this) {
            self::IdDocument => __('Identity document'),
            self::ProofOfAddress => __('Proof of address'),
            self::ProfilePhoto => __('Profile photo'),
            self::Pirb => __('PIRB registration'),
            self::ElectricalRegisteredPerson => __('Registered electrician'),
        };
    }

    /**
     * Every applicant provides these.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::IdDocument, self::ProofOfAddress, self::ProfilePhoto];
    }

    public function isRegistration(): bool
    {
        return in_array($this, [self::Pirb, self::ElectricalRegisteredPerson], true);
    }

    /** Registrations expire; admins enter the date when verifying. */
    public function hasExpiry(): bool
    {
        return $this->isRegistration();
    }

    /** Only the profile photo has to be an image. */
    public function acceptsPdf(): bool
    {
        return $this !== self::ProfilePhoto;
    }

    public static function forRegistration(RegistrationType $registration): self
    {
        return match ($registration) {
            RegistrationType::Pirb => self::Pirb,
            RegistrationType::ElectricalRegisteredPerson => self::ElectricalRegisteredPerson,
        };
    }
}
