<?php

namespace App\Service;

use App\Entity\RendezVous;

class RendezVousManager
{
    public function validate(RendezVous $rdv): bool
    {
        // ✅ Règle 1 : Date obligatoire et future
        $date = $rdv->getDateHeure();

        if (!$date) {
            throw new \InvalidArgumentException("La date est obligatoire");
        }

        if ($date <= new \DateTime()) {
            throw new \InvalidArgumentException("Le rendez-vous doit être dans le futur");
        }

        // ✅ Règle 2 : Statut valide
        $statutsAutorises = ['En attente', 'Confirmé', 'Annulé', 'Complété'];

        if (!in_array($rdv->getStatut(), $statutsAutorises)) {
            throw new \InvalidArgumentException("Statut invalide");
        }

        // ✅ Règle 3 : Si Téléconsultation → pas de lieu
        if ($rdv->getType() === 'Téléconsultation' && $rdv->getLieu() !== null) {
            throw new \InvalidArgumentException("Un RDV en visio ne doit pas avoir de lieu");
        }

        return true;
    }
}