<?php
namespace App\Service;

use App\Entity\DossierMedical;

class DossierMedicalManager
{
    /**
     * Valide les données d'un dossier médical
     */
    public function validate(DossierMedical $dossier): bool
    {
        if ($dossier->getPoids() !== null && $dossier->getPoids() <= 0) {
            throw new \InvalidArgumentException('Le poids doit être positif');
        }

        if ($dossier->getTaille() !== null && $dossier->getTaille() <= 0) {
            throw new \InvalidArgumentException('La taille doit être positive');
        }

        if ($dossier->getTensionSystolique() !== null && $dossier->getTensionSystolique() <= 0) {
            throw new \InvalidArgumentException('La tension systolique doit être positive');
        }

        if ($dossier->getTensionDiastolique() !== null && $dossier->getTensionDiastolique() <= 0) {
            throw new \InvalidArgumentException('La tension diastolique doit être positive');
        }

        if ($dossier->getFrequenceCardiaque() !== null && $dossier->getFrequenceCardiaque() <= 0) {
            throw new \InvalidArgumentException('La fréquence cardiaque doit être positive');
        }

        return true;
    }

    /**
     * Calcule l'IMC
     */
    public function calculateIMC(float $poids, float $taille): float
    {
        if ($taille <= 0) {
            throw new \InvalidArgumentException('La taille doit être supérieure à zéro');
        }
        if ($poids <= 0) {
            throw new \InvalidArgumentException('Le poids doit être supérieur à zéro');
        }
        return round($poids / (($taille / 100) ** 2), 2);
    }

    /**
     * Calcule le risque cardiaque
     */
    public function calculateRisque(
        ?float $imc,
        ?int $tensionSys,
        ?int $tensionDia,
        ?int $frequence
    ): string {
        $score = 0;

        if ($imc !== null && $imc > 30) $score++;
        if ($tensionSys !== null && $tensionSys > 140) $score++;
        if ($tensionDia !== null && $tensionDia > 90) $score++;
        if ($frequence !== null && $frequence > 100) $score++;

        if ($score >= 3) return 'CRITIQUE';
        if ($score >= 2) return 'ÉLEVÉ';
        if ($score >= 1) return 'MODÉRÉ';
        return 'NORMAL';
    }
}