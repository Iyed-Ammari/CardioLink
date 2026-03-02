<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PredictionService
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function getFutureInterventions(): array
    {
        try {
            // On appelle l'URL de Flask
            $response = $this->client->request('GET', 'http://127.0.0.1:5002/predict');
            
            return $response->toArray();
        } catch (\Exception $e) {
            // En cas d'erreur de Flask, on retourne un tableau vide
            return [];
        }
    }
}