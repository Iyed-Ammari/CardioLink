<?php
namespace App\Tests\Service;

use App\Entity\DossierMedical;
use App\Service\DossierMedicalManager;
use PHPUnit\Framework\TestCase;

class DossierMedicalManagerTest extends TestCase
{
    private DossierMedicalManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DossierMedicalManager();
    }

    // ===== TESTS VALIDATION =====

    /**
     * Test 1 : Dossier valide → retourne true
     */
    public function testDossierValide(): void
    {
        $dossier = new DossierMedical();
        $dossier->setPoids(70);
        $dossier->setTaille(175);
        $dossier->setTensionSystolique(120);
        $dossier->setTensionDiastolique(80);
        $dossier->setFrequenceCardiaque(70);

        $this->assertTrue($this->manager->validate($dossier));
    }

    /**
     * Test 2 : Poids négatif → exception
     */
    public function testPoidsNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le poids doit être positif');

        $dossier = new DossierMedical();
        $dossier->setPoids(-10);

        $this->manager->validate($dossier);
    }

    /**
     * Test 3 : Taille négative → exception
     */
    public function testTailleNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La taille doit être positive');

        $dossier = new DossierMedical();
        $dossier->setTaille(-5);

        $this->manager->validate($dossier);
    }

    /**
     * Test 4 : Fréquence cardiaque négative → exception
     */
    public function testFrequenceNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La fréquence cardiaque doit être positive');

        $dossier = new DossierMedical();
        $dossier->setFrequenceCardiaque(-5);

        $this->manager->validate($dossier);
    }

    /**
     * Test 5 : Tension négative → exception
     */
    public function testTensionNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La tension systolique doit être positive');

        $dossier = new DossierMedical();
        $dossier->setTensionSystolique(-10);

        $this->manager->validate($dossier);
    }

    // ===== TESTS CALCUL IMC =====

    /**
     * Test 6 : IMC normal correct
     */
    public function testCalculIMCNormal(): void
    {
        $imc = $this->manager->calculateIMC(70, 175);
        $this->assertEquals(22.86, $imc);
    }

    /**
     * Test 7 : IMC obésité correct
     */
    public function testCalculIMCObesite(): void
    {
        $imc = $this->manager->calculateIMC(100, 170);
        $this->assertEquals(34.60, $imc);
        $this->assertGreaterThan(30, $imc);
    }

    /**
     * Test 8 : Taille zéro → exception
     */
    public function testCalculIMCTailleZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La taille doit être supérieure à zéro');

        $this->manager->calculateIMC(70, 0);
    }

    /**
     * Test 9 : Poids zéro → exception
     */
    public function testCalculIMCPoidsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le poids doit être supérieur à zéro');

        $this->manager->calculateIMC(0, 175);
    }

    // ===== TESTS RISQUE CARDIAQUE =====

    /**
     * Test 10 : Risque NORMAL → score 0
     */
    public function testRisqueNormal(): void
    {
        $risque = $this->manager->calculateRisque(22.0, 120, 80, 70);
        $this->assertEquals('NORMAL', $risque);
    }

    /**
     * Test 11 : Risque MODÉRÉ → score 1
     */
    public function testRisqueModere(): void
    {
        $risque = $this->manager->calculateRisque(32.0, 120, 80, 70);
        $this->assertEquals('MODÉRÉ', $risque);
    }

    /**
     * Test 12 : Risque ÉLEVÉ → score 2
     */
    public function testRisqueEleve(): void
    {
        $risque = $this->manager->calculateRisque(32.0, 145, 80, 70);
        $this->assertEquals('ÉLEVÉ', $risque);
    }

    /**
     * Test 13 : Risque CRITIQUE → score 3+
     */
    public function testRisqueCritique(): void
    {
        $risque = $this->manager->calculateRisque(32.0, 145, 95, 105);
        $this->assertEquals('CRITIQUE', $risque);
    }

    /**
     * Test 14 : Risque CRITIQUE score maximum → score 4
     */
    public function testRisqueCritiqueMax(): void
    {
        $risque = $this->manager->calculateRisque(35.0, 165, 100, 110);
        $this->assertEquals('CRITIQUE', $risque);
    }

    /**
     * Test 15 : Données nulles → risque NORMAL
     */
    public function testRisqueAvecDonneesNulles(): void
    {
        $risque = $this->manager->calculateRisque(null, null, null, null);
        $this->assertEquals('NORMAL', $risque);
    }
}