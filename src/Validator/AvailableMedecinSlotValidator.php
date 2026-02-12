<?php

namespace App\Validator;

use App\Entity\RendezVous;
use App\Validator\Constraint\AvailableMedecinSlot;
use App\Repository\RendezVousRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AvailableMedecinSlotValidator extends ConstraintValidator
{
    public function __construct(private RendezVousRepository $rendezVousRepository)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableMedecinSlot) {
            throw new UnexpectedTypeException($constraint, AvailableMedecinSlot::class);
        }

        // La valeur doit être une instance de RendezVous
        if (!$value instanceof RendezVous) {
            return;
        }

        // Vérifier que les champs requis sont présents
        if (!$value->getMedecin() || !$value->getDateHeure()) {
            return;
        }

        // Vérifier la disponibilité du créneau
        $conflictCount = $this->rendezVousRepository->countCrenau($value->getDateHeure(), $value->getMedecin(), $value->getId());

        if ($conflictCount > 0) {
            $this->context
                ->buildViolation($constraint->message)
                ->atPath('dateHeure')
                ->addViolation();
        }
    }
}
