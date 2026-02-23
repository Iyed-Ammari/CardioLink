<?php
// src/Controller/PredictionController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/ml')]
#[IsGranted('ROLE_ADMIN')]
final class PredictionController extends AbstractController
{
    #[Route('/prediction/{month}', name: 'prediction', methods: ['GET'])]
    public function predict(string $month, HttpClientInterface $client): Response
    {
        try {
            $response = $client->request('GET', 'http://127.0.0.1:8001/predict', [
                'query'   => ['month' => $month],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $data = [];
        }

        return $this->render('admin/ml/prediction.html.twig', [
            'result' => $data,
            'month'  => $month,
        ]);
    }
}