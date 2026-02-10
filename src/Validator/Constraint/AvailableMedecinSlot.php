<?php

namespace App\Validator\Constraint;

use App\Validator\AvailableMedecinSlotValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Contrainte pour vérifier qu'un créneau (date/heure + médecin) est disponible
 */
#[\Attribute]
class AvailableMedecinSlot extends Constraint
{
    public string $message = 'Le médecin n\'est pas disponible à ce créneau. Veuillez choisir une autre heure.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return AvailableMedecinSlotValidator::class;
    }
}
