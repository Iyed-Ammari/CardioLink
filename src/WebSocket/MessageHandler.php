<?php
namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class MessageHandler implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // On stocke la nouvelle connexion
        $this->clients->attach($conn);
        echo "Nouvelle connexion ! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Message reçu : $msg\n";
        
        // Décoder le message JSON pour vérifier son type
        $decodedMsg = json_decode($msg, true);
        
        // Types de messages à ne pas afficher sur l'interface
        $ignoredTypes = ['read', 'typing'];
        
        // Si c'est un message avec un type à ignorer, on le traite différemment
        if (is_array($decodedMsg) && isset($decodedMsg['type']) && in_array($decodedMsg['type'], $ignoredTypes)) {
            // Les messages de type "read" et "typing" ne sont pas envoyés à tous les clients
            echo "Message de type '{$decodedMsg['type']}' reçu - non affiché sur l'interface\n";
            return;
        }
        
        // Quand on reçoit un message normal, on le renvoie à TOUT LE MONDE
        // Le frontend filtrera si c'est la bonne conversation
        foreach ($this->clients as $client) {
            // On l'envoie à tous sauf à l'expéditeur (optionnel, mais souvent mieux de l'afficher via JS direct)
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connexion {$conn->resourceId} fermée\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }
}