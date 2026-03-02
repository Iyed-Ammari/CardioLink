<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Entity\RendezVous;
use App\Service\RendezVousManager;

class RendezVousManagerTest extends TestCase
{
    public function testValidRendezVous()
    {
        $rdv = new RendezVous();
        $rdv->setDateHeure(new \DateTime('+1 day')); // ✅ utiliser setDateHeure
        $rdv->setStatut('Confirmé'); // ✅ respecter la casse exacte
        $rdv->setType('Présentiel'); // ✅ type requis pour le test

        $manager = new RendezVousManager();

        $this->assertTrue($manager->validate($rdv));
    }

    public function testDateInPast()
    {
        $this->expectException(\InvalidArgumentException::class);

        $rdv = new RendezVous();
        $rdv->setDateHeure(new \DateTime('-1 day')); // ✅ setDateHeure
        $rdv->setStatut('En attente'); // ✅ statut valide
        $rdv->setType('Présentiel'); // ✅ type requis

        $manager = new RendezVousManager();
        $manager->validate($rdv);
    }

    public function testInvalidStatut()
    {
        $this->expectException(\InvalidArgumentException::class);

        $rdv = new RendezVous();
        $rdv->setDateHeure(new \DateTime('+1 day')); // ✅ setDateHeure
        $rdv->setStatut('invalide'); // ✅ statut invalide pour déclencher l'exception
        $rdv->setType('Présentiel'); // ✅ type requis

        $manager = new RendezVousManager();
        $manager->validate($rdv);
    }

    public function testTelemedecineWithLieu()
    {
        $this->expectException(\InvalidArgumentException::class);

        $rdv = new RendezVous();
        $rdv->setDateHeure(new \DateTime('+1 day'));
        $rdv->setStatut('En attente');
        $rdv->setType('Télémédecine');

        // Créons un lieu factice pour tester la règle
        $lieu = new \App\Entity\Lieu();
        $rdv->setLieu($lieu);

        $manager = new RendezVousManager();
        $manager->validate($rdv);
    }
}