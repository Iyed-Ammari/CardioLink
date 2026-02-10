<?php
// Fichier de test pour vérificationrapide des entités

use App\Entity\Suivi;
use App\Entity\Intervention;
use App\Entity\User;

// Test 1: Création d'instanc
$suivi = new Suivi();
echo "✓ Suivi instantié\n";

// Test 2: Setter
$suivi->setTypeDonnee('Fréquence Cardiaque');
$suivi->setValeur(85.5);
echo "✓ Propriétés Suivi définies\n";

// Test 3: Méthodes
echo "Type donnée: " . $suivi->getTypeDonnee() . "\n";
echo "Valeur: " . $suivi->getValeur() . "\n";
echo "Est critique: " . ($suivi->isCritical() ? 'OUI' : 'NON') . "\n";

// Test 4: Intervention
$intervention = new Intervention();
$intervention->setType('Alerte SOS');
$intervention->setDescription('Test description');
echo "✓ Intervention instantiée\n";
echo "Est urgent: " . ($intervention->isUrgent() ? 'OUI' : 'NON') . "\n";

echo "\n✅ Toutes les vérifications réussies!\n";
?>
